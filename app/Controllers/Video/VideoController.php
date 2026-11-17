<?php
declare(strict_types=1);

namespace App\Controllers\Video;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\VideoService;

/**
 * Régie vidéo d'un tapis (compte « Vidéo-arbitrage » ou administrateur) : 2 caméras, incrustation du combat,
 * vidéo-arbitrage (retour, ralenti, zoom) et sortie programme capturée par OBS pour le direct YouTube.
 */
final class VideoController extends Controller
{
    private function guard(): void
    {
        if (!Auth::can('video.operate')) {
            throw new HttpException(403, 'Accès réservé à la vidéo-arbitrage.');
        }
    }

    /** Choix de la compétition (mémorisé), puis du tapis. */
    public function home(Request $r): Response
    {
        $this->guard();
        $competitions = VideoService::competitions();
        $ids = array_map(fn ($c) => (int) $c['id'], $competitions);
        if (array_key_exists('competition', $r->query)) {
            Session::put('video_competition', (int) $r->query['competition']);
        }
        $chosen = (int) Session::get('video_competition', 0);
        if (!in_array($chosen, $ids, true)) {
            $chosen = count($ids) === 1 && !array_key_exists('competition', $r->query) ? $ids[0] : 0;
        }
        $competition = null;
        foreach ($competitions as $c) {
            if ((int) $c['id'] === $chosen) {
                $competition = $c;
            }
        }
        return $this->view('video/home', [
            'title' => 'Vidéo-arbitrage', 'competitions' => $competitions, 'competition' => $competition,
            'tatamis' => $competition ? VideoService::tatamis((int) $competition['id']) : [],
            'brand' => 'Vidéo-arbitrage', 'brandHref' => '/video',
        ], 'commissioner');
    }

    public function regie(Request $r): Response
    {
        $t = VideoService::tatami((int) $r->param('tid'));
        Session::put('video_competition', (int) $t['competition_id']);
        $base = '/api/video/tapis/' . $t['id'];
        return $this->view('video/regie', [
            'title' => 'Régie vidéo · Tapis ' . $t['number'], 't' => $t, 'fullApp' => true, 'scripts' => ['video.js'],
            'config' => [
                'tatamiId' => (int) $t['id'], 'tatamiNumber' => (int) $t['number'], 'competition' => $t['competition_name'],
                'stateUrl' => url($base), 'varUrl' => url($base . '/var'), 'streamUrl' => url($base . '/direct'),
                'streamStopUrl' => url($base . '/direct/arret'), 'offsetUrl' => url($base . '/decalage'),
                'cameraUrl' => url($base . '/cameras/__SLOT__'), 'cameraRemoveUrl' => url($base . '/cameras/__SLOT__/retirer'),
                'answerUrl' => url($base . '/cameras/__SLOT__/reponse'), 'outputUrl' => url('/video/tapis/' . $t['id'] . '/sortie'),
                'phoneUrl' => url('/camera'), 'tickerUrl' => asset('js/video-ticker.js'), 'homeUrl' => url('/video'), 'slots' => VideoService::SLOTS,
                'ice' => \App\Core\Config::get('app.video_ice_servers', [['urls' => 'stun:stun.l.google.com:19302']]),
            ],
        ], 'commissioner');
    }

    /** Sortie programme (fenêtre capturée par OBS) : l'image vient de la régie ouverte dans la fenêtre principale. */
    public function output(Request $r): Response
    {
        $t = VideoService::tatami((int) $r->param('tid'));
        return $this->view('video/output', ['title' => 'Sortie programme · Tapis ' . $t['number'], 't' => $t], 'bare');
    }

    // ------------------------------------------------------------------ API régie

    public function state(Request $r): Response
    {
        return $this->json(VideoService::regieState(VideoService::tatami((int) $r->param('tid'))));
    }

    public function setVar(Request $r): Response
    {
        $t = VideoService::tatami((int) $r->param('tid'));
        $in = $r->json();
        return $this->json(['ok' => true, 'video' => VideoService::setVar($t, (bool) ($in['active'] ?? false), (bool) ($in['on_air'] ?? true))]);
    }

    public function startStream(Request $r): Response
    {
        $t = VideoService::tatami((int) $r->param('tid'));
        return $this->json(['ok' => true, 'stream' => VideoService::startStream($t, (string) ($r->json()['link'] ?? ''))]);
    }

    public function stopStream(Request $r): Response
    {
        VideoService::stopStream(VideoService::tatami((int) $r->param('tid')));
        return $this->json(['ok' => true]);
    }

    public function offset(Request $r): Response
    {
        $t = VideoService::tatami((int) $r->param('tid'));
        VideoService::setOffset($t, (int) ($r->json()['seconds'] ?? 0));
        return $this->json(['ok' => true, 'stream' => VideoService::currentStream((int) $t['id'])]);
    }

    public function createCamera(Request $r): Response
    {
        $t = VideoService::tatami((int) $r->param('tid'));
        $code = VideoService::createCamera($t, (int) $r->param('slot'));
        return $this->json(['ok' => true, 'code' => $code, 'url' => url('/camera/' . $code)]);
    }

    public function removeCamera(Request $r): Response
    {
        VideoService::removeCamera(VideoService::tatami((int) $r->param('tid')), (int) $r->param('slot'));
        return $this->json(['ok' => true]);
    }

    public function answer(Request $r): Response
    {
        $t = VideoService::tatami((int) $r->param('tid'));
        $in = $r->json();
        VideoService::cameraAnswer($t, (int) $r->param('slot'), (string) ($in['session'] ?? ''), (string) ($in['sdp'] ?? ''));
        return $this->json(['ok' => true]);
    }
}
