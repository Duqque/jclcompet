<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Services\BoutService;
use App\Services\PoolService;
use App\Services\TvTokenService;
use App\Services\VideoService;
use Tests\TestCase;

/** Vidéo : régie des tapis, vidéo-arbitrage partagée (TV, commissaire, application), directs YouTube, caméras téléphone. */
final class VideoTest extends TestCase
{
    /** Compétition du jour J avec un combat programmé sur un tapis. @return array{0:int,1:array} */
    private function dayWithBout(): array
    {
        $this->admin();
        $cid = $this->competition();
        $this->athletes($cid, 3);
        $g = PoolService::generate($cid);
        PoolService::validate($g);
        Database::run("UPDATE competitions SET status = 'in_progress' WHERE id = ?", [$cid]);
        return [$cid, Database::one('SELECT * FROM bouts WHERE competition_id = ? ORDER BY id LIMIT 1', [$cid])];
    }

    public function testOnlyVideoAccountsDriveTheRegie(): void
    {
        [, $bout] = $this->dayWithBout();
        $tid = (int) $bout['tatami_id'];
        Auth::actAs($this->user('commissioner'));
        $this->assertDomainError(fn () => VideoService::tatami($tid), 'vidéo', 403);
        Auth::actAs($this->user('video'));
        $t = VideoService::tatami($tid);
        $this->assertSame($tid, (int) $t['id']);
        // Compétition hors jour J : refusé
        Database::run("UPDATE competitions SET status = 'registrations_open' WHERE id = ?", [$t['competition_id']]);
        $this->assertDomainError(fn () => VideoService::tatami($tid), 'ouvert', 403);
    }

    public function testVarStateReachesTvCommissionerAndApp(): void
    {
        [, $bout] = $this->dayWithBout();
        Auth::actAs($this->user('video'));
        $t = VideoService::tatami((int) $bout['tatami_id']);
        $v = VideoService::setVar($t, true, true);
        $this->assertTrue($v['var']);
        $this->assertTrue($v['var_on_air']);
        $tatami = Database::one('SELECT t.*, c.name AS competition_name FROM tatamis t JOIN competitions c ON c.id = t.competition_id WHERE t.id = ?', [$t['id']]);
        $this->assertTrue(TvTokenService::tvState($tatami)['video']['var'], 'bandeau sur l\'écran TV');
        $this->assertSame(1, (int) Database::value("SELECT COUNT(*) FROM audit_logs WHERE action = 'video.var_start' AND entity_id = ?", [(string) $t['id']]));
        VideoService::setVar(VideoService::tatami((int) $t['id']), false, true);
        $this->assertFalse(TvTokenService::tvState(Database::one('SELECT * FROM tatamis WHERE id = ?', [$t['id']]))['video']['var']);
    }

    public function testYoutubeLinksAndReplayPosition(): void
    {
        foreach (['https://youtube.com/live/abcdefghijk?feature=share', 'https://youtu.be/abcdefghijk', 'https://www.youtube.com/watch?v=abcdefghijk&t=3', 'abcdefghijk', 'https://studio.youtube.com/video/abcdefghijk/livestreaming'] as $link) {
            $this->assertSame('abcdefghijk', VideoService::youtubeId($link), $link);
        }
        $this->assertNull(VideoService::youtubeId('https://example.org/live'));

        [, $bout] = $this->dayWithBout();
        Auth::actAs($this->user('video'));
        $t = VideoService::tatami((int) $bout['tatami_id']);
        $this->assertNull(VideoService::replay($bout), 'pas de direct : pas de replay');
        $stream = VideoService::startStream($t, 'https://youtu.be/abcdefghijk');
        $this->assertSame('abcdefghijk', $stream['youtube_id']);
        // Direct lancé il y a 10 minutes
        $stream['started_at_ms'] -= 600000;
        Database::run('UPDATE video_streams SET started_at_ms = ? WHERE tatami_id = ?', [$stream['started_at_ms'], $t['id']]);
        // Hajime 2 min après le début du direct : replay à 1:55 (5 s avant)
        Database::run('UPDATE bouts SET started_at_ms = ? WHERE id = ?', [$stream['started_at_ms'] + 120000, $bout['id']]);
        $r = VideoService::replay(Database::one('SELECT * FROM bouts WHERE id = ?', [$bout['id']]));
        $this->assertSame(['youtube_id' => 'abcdefghijk', 'start_s' => 115, 'live' => true], $r);
        // Recalage et direct relancé : l'ancien combat garde sa vidéo
        VideoService::setOffset($t, 3);
        VideoService::startStream(VideoService::tatami((int) $t['id']), 'https://youtu.be/zzzzzzzzzzz');
        (new \ReflectionClass(VideoService::class))->getProperty('streams')->setValue(null, []); // nouvelle requête
        $r = VideoService::replay(Database::one('SELECT * FROM bouts WHERE id = ?', [$bout['id']]));
        $this->assertSame('abcdefghijk', $r['youtube_id']);
        $this->assertSame(118, $r['start_s']);
        $this->assertFalse($r['live'], 'premier direct terminé');
    }

    public function testBoutStartIsStampedForVideoMarkers(): void
    {
        [, $bout] = $this->dayWithBout();
        $u = $this->user('commissioner');
        Database::insert('commissioner_tatamis', ['user_id' => $u, 'tatami_id' => $bout['tatami_id'], 'is_active' => 1]);
        Auth::actAs($u);
        BoutService::act((int) $bout['id'], ['action' => 'start', 'version' => $bout['version']]);
        $b = Database::one('SELECT * FROM bouts WHERE id = ?', [$bout['id']]);
        $this->assertNotNull($b['started_at_ms']);
        BoutService::act((int) $bout['id'], ['action' => 'score', 'side' => 'a', 'kind' => 'wazaari', 'version' => $b['version']]);
        $markers = VideoService::markers((int) $bout['tatami_id'], 0);
        $this->assertSame(['start', 'score:wazaari'], array_column($markers, 'kind'));
        $this->assertSame('a', $markers[1]['side']);
    }

    public function testPhoneCameraPairing(): void
    {
        [, $bout] = $this->dayWithBout();
        Auth::actAs($this->user('video'));
        $t = VideoService::tatami((int) $bout['tatami_id']);
        $code = VideoService::createCamera($t, 2);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $code);
        $cam = VideoService::cameraByCode(strtolower($code));
        $this->assertSame(2, (int) $cam['slot']);
        VideoService::cameraOffer($cam, 'abcd1234ef', "v=0\r\no=- 1 2 IN IP4 127.0.0.1\r\n", 'iPhone');
        $list = VideoService::cameras((int) $t['id']);
        $this->assertSame('abcd1234ef', $list[0]['session']);
        $this->assertNotNull($list[0]['offer'], 'offre en attente de la régie');
        VideoService::cameraAnswer($t, 2, 'abcd1234ef', "v=0\r\nanswer\r\n");
        $this->assertNull(VideoService::cameras((int) $t['id'])[0]['offer']);
        $this->assertDomainError(fn () => VideoService::cameraAnswer($t, 2, 'autre000', "v=0\r\n"), 'session', 409);
        // Nouveau code : l'ancien ne fonctionne plus
        VideoService::createCamera($t, 2);
        $this->expectException(HttpException::class);
        VideoService::cameraByCode($code);
    }
}
