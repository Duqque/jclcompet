<?php
declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Services\AttendanceService;
use App\Services\BoutService;
use App\Services\CompetitionService;
use App\Services\PoolService;
use App\Services\RegistrationService;
use App\Services\SalesService;
use App\Services\SettingsService;
use App\Services\TvTokenService;

/**
 * DONNÉES DE DÉMONSTRATION FICTIVES (is_demo = 1) : judokas, résultats et ventes inventés, rattachés aux vrais clubs de l'édition.
 * Refusé en production. Comptes de démonstration documentés dans docs/INSTALLATION.md, à supprimer avant usage réel.
 */
final class DemoSeeder
{
    public const ACCOUNTS = [
        ['admin@demo-judo.test', 'AdminDemo2026!', 'Admin Démo', 'admin'],
        ['commissaire1@demo-judo.test', 'CommissaireDemo2026!', 'Commissaire Tapis 1 (démo)', 'commissioner'],
        ['commissaire2@demo-judo.test', 'CommissaireDemo2026!', 'Commissaire Tapis 2 (démo)', 'commissioner'],
        ['coach@demo-judo.test', 'CoachDemo2026!', 'Entraîneur JC Lensois (démo)', 'coach'],
        ['coach2@demo-judo.test', 'CoachDemo2026!', 'Entraîneure Hénin Judo (démo)', 'coach'],
        ['accueil@demo-judo.test', 'AccueilDemo2026!', 'Accueil (démo)', 'reception'],
        ['buvette@demo-judo.test', 'VendeurDemo2026!', 'Buvette (démo)', 'seller'],
    ];

    private const FIRST_M = ['Lucas', 'Nathan', 'Hugo', 'Léo', 'Gabin', 'Noah', 'Arthur', 'Jules', 'Maël', 'Sacha', 'Timéo', 'Rayan', 'Eliott', 'Nolan', 'Malo', 'Axel', 'Kylian', 'Lenny', 'Ethan', 'Tom'];
    private const FIRST_F = ['Emma', 'Léa', 'Chloé', 'Inès', 'Jade', 'Lina', 'Manon', 'Zoé', 'Lou', 'Mila', 'Rose', 'Anna', 'Nina', 'Lola', 'Romane', 'Eva', 'Louise', 'Clara', 'Ambre', 'Lise'];
    private const LAST = ['Martinot', 'Bernardin', 'Duvalier', 'Lefebvrier', 'Moreaux', 'Fourmont', 'Girardet', 'Bonnaud', 'Mercieux', 'Blanchet', 'Garnieri', 'Fauvel', 'Rousselin', 'Chevalet', 'Lambertin', 'Colinet', 'Perrinet', 'Marchais', 'Delmotte', 'Vasseurin', 'Caronnet', 'Leclercq', 'Dupuich', 'Hennebelle'];

    public function run(): int
    {
        if (Config::get('app.env') === 'production') {
            throw new \RuntimeException('Les données de démonstration sont interdites en production (APP_ENV=production).');
        }
        if (Database::value("SELECT 1 FROM users WHERE email = 'admin@demo-judo.test'")) {
            throw new \RuntimeException('Les données de démonstration sont déjà présentes (utilisez bin/reset-dev.php pour repartir de zéro).');
        }
        mt_srand(2026);
        $users = $this->accounts();
        Auth::actAs($users['admin@demo-judo.test']);
        $clubs = $this->clubs($users);
        $cid = $this->competition();
        $this->registrations($cid, $clubs);
        $this->assignCommissioners($cid, $users);
        $this->pools($cid);
        $this->bouts($cid);
        $this->sales($cid);
        $this->gallery($cid);
        return $cid;
    }

    private function accounts(): array
    {
        $roles = array_column(Database::all('SELECT id, code FROM roles'), 'id', 'code');
        $ids = [];
        foreach (self::ACCOUNTS as [$email, $password, $name, $role]) {
            $ids[$email] = Database::insert('users', [
                'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'display_name' => $name,
                'role_id' => $roles[$role], 'is_demo' => 1,
            ]);
        }
        return $ids;
    }

    /**
     * Clubs de la démo : les vrais clubs de l'édition de l'année (avec leurs logos), les judokas restent fictifs.
     * Les deux comptes entraîneurs de démonstration sont rattachés aux deux premiers.
     */
    private const DEMO_CLUBS = ['Judo Club Leforest', 'Judo Club Lensois', 'Hénin Judo Ju-Jitsu', 'Judo Club Harnésien', 'USA Liévin Judo Ju-Jitsu', 'Noyelles-sous-Lens Judo Ju-Jitsu', 'Méricourt Judo', 'Judo Club Sallauminois'];

    private function clubs(array $users): array
    {
        $ids = [];
        foreach (self::DEMO_CLUBS as $name) {
            $id = Database::value('SELECT id FROM clubs WHERE name = ?', [$name]);
            if (!$id) {
                throw new \RuntimeException("Club « $name » introuvable : chargez d'abord le contenu du programme (php bin/seed.php --content).");
            }
            $ids[] = (int) $id;
        }
        Database::insert('club_users', ['club_id' => $ids[1], 'user_id' => $users['coach@demo-judo.test']]);
        Database::insert('club_users', ['club_id' => $ids[2], 'user_id' => $users['coach2@demo-judo.test']]);
        return $ids;
    }

    private function competition(): int
    {
        $year = (int) date('Y');
        $cid = CompetitionService::create([
            'name' => 'Tournoi de démonstration JCL (données fictives)',
            'tagline' => 'Compétition fictive pour découvrir l\'application : inscriptions, poules, combats en direct, classements',
            'description' => "**Données de démonstration** : clubs, athlètes, résultats et ventes sont entièrement fictifs.\n\nCette compétition permet de tester tous les espaces : administration, clubs, accueil, commissaires sportifs, écrans TV et classement public.",
            'event_date' => date('Y-m-d'),
            'start_time' => '09:00:00',
            'venue' => 'Salle de démonstration',
            'address' => '1 rue de l\'Exemple',
            'city' => 'Démoville',
            'registration_opens_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            'registration_closes_at' => date('Y-m-d 23:59:00'),
            'age_reference_date' => $year . '-12-31',
            'mat_count' => 4,
            'expected_participants' => 48,
            'pool_sizes' => '3,4,5',
            'practical_info' => 'Données fictives. Buvette ouverte toute la journée.',
            'coach_info' => 'Données fictives. Merci de vérifier la présence de vos judokas à l\'accueil.',
            'is_demo' => 1,
        ], ['U11', 'U13', 'U15'], true);
        SettingsService::set($cid, 'weight_input', 'weight');
        SettingsService::set($cid, 'pool_grouping', 'morphological');
        SettingsService::set($cid, 'pools_published', true);
        Database::run("UPDATE competitions SET status = 'registrations_open' WHERE id = ?", [$cid]);
        return $cid;
    }

    private function registrations(int $cid, array $clubs): void
    {
        $year = (int) date('Y');
        // [catégorie (âge min, âge max), sexe, effectif, poids moyen]
        $plan = [
            [[9, 10], 'M', 11, 30.0], [[9, 10], 'F', 7, 29.0],
            [[11, 12], 'M', 12, 40.0], [[11, 12], 'F', 8, 38.0],
            [[13, 14], 'M', 10, 50.0],
        ];
        $n = 0;
        foreach ($plan as [[$minAge, $maxAge], $sex, $count, $avg]) {
            for ($i = 0; $i < $count; $i++) {
                $n++;
                $age = mt_rand($minAge, $maxAge);
                $birth = sprintf('%d-%02d-%02d', $year - $age, mt_rand(1, 12), mt_rand(1, 28));
                $first = $sex === 'M' ? self::FIRST_M[$n % count(self::FIRST_M)] : self::FIRST_F[$n % count(self::FIRST_F)];
                $last = self::LAST[($n * 7) % count(self::LAST)];
                $weight = round($avg + mt_rand(-60, 60) / 10, 1);
                $club = $clubs[$n % count($clubs)];
                RegistrationService::register($cid, [
                    'last_name' => $last, 'first_name' => $first, 'sex' => $sex, 'birth_date' => $birth,
                    'declared_weight_kg' => $weight, 'grade' => ['Blanche', 'Jaune', 'Orange', 'Verte'][mt_rand(0, 3)],
                ], ['actor' => 'club', 'club_id' => $club]);
            }
        }
        Database::run('UPDATE athletes SET is_demo = 1 WHERE id IN (SELECT athlete_id FROM registrations WHERE competition_id = ?)', [$cid]);
        Database::run("UPDATE competitions SET status = 'attendance' WHERE id = ?", [$cid]);
        $regs = Database::all('SELECT id, declared_weight_kg FROM registrations WHERE competition_id = ? ORDER BY id', [$cid]);
        foreach ($regs as $k => $r) {
            if ($k === 5 || $k === 23) {
                AttendanceService::mark((int) $r['id'], 'absent', null, 'Forfait (démo)');
            } elseif ($k === 40) {
                AttendanceService::mark((int) $r['id'], 'to_check', null, 'Licence à vérifier (démo)');
            } else {
                $measured = round((float) $r['declared_weight_kg'] + mt_rand(-5, 8) / 10, 1);
                AttendanceService::mark((int) $r['id'], 'present', $measured);
            }
        }
    }

    private function assignCommissioners(int $cid, array $users): void
    {
        $tatamis = CompetitionService::tatamis($cid);
        Database::insert('commissioner_tatamis', ['user_id' => $users['commissaire1@demo-judo.test'], 'tatami_id' => $tatamis[0]['id'], 'is_active' => 1]);
        Database::insert('commissioner_tatamis', ['user_id' => $users['commissaire2@demo-judo.test'], 'tatami_id' => $tatamis[1]['id'], 'is_active' => 1]);
        Database::insert('commissioner_tatamis', ['user_id' => $users['commissaire2@demo-judo.test'], 'tatami_id' => $tatamis[2]['id'], 'is_active' => 0]);
    }

    private function pools(int $cid): void
    {
        $gen = PoolService::generate($cid);
        PoolService::validate($gen, true);
        Database::run("UPDATE competitions SET status = 'in_progress' WHERE id = ?", [$cid]);
    }

    private function play(int $boutId, array $script, bool $validate = true): void
    {
        $act = function (array $in) use ($boutId) {
            $s = BoutService::state($boutId);
            return BoutService::act($boutId, $in + ['version' => $s['version'], 'client_event_id' => bin2hex(random_bytes(8))]);
        };
        $act(['action' => 'start']);
        foreach ($script as $step) {
            $act($step);
        }
        $s = BoutService::state($boutId);
        if ($s['status'] === 'in_progress') {
            $act(['action' => 'pause']);
            try {
                $act(['action' => 'finish']);
            } catch (\App\Services\DomainError $e) {
                $act(['action' => 'finish', 'winner_side' => mt_rand(0, 1) ? 'a' : 'b']); // égalité : décision
            }
        }
        if ($validate) {
            $act(['action' => 'validate']);
        }
    }

    private function bouts(int $cid): void
    {
        $scripts = [
            [['action' => 'score', 'side' => 'a', 'kind' => 'ippon', 'delta' => 1]],
            [['action' => 'score', 'side' => 'b', 'kind' => 'wazaari', 'delta' => 1], ['action' => 'score', 'side' => 'b', 'kind' => 'wazaari', 'delta' => 1]],
            [['action' => 'score', 'side' => 'a', 'kind' => 'yuko', 'delta' => 1], ['action' => 'score', 'side' => 'b', 'kind' => 'shido', 'delta' => 1]],
            [['action' => 'score', 'side' => 'b', 'kind' => 'wazaari', 'delta' => 1], ['action' => 'score', 'side' => 'a', 'kind' => 'shido', 'delta' => 1]],
            [['action' => 'score', 'side' => 'a', 'kind' => 'wazaari', 'delta' => 1], ['action' => 'score', 'side' => 'b', 'kind' => 'yuko', 'delta' => 1]],
            [['action' => 'score', 'side' => 'b', 'kind' => 'ippon', 'delta' => 1]],
        ];
        foreach (CompetitionService::tatamis($cid) as $idx => $t) {
            $bouts = array_map('intval', \App\Core\Database::column("SELECT id FROM bouts WHERE tatami_id = ? AND status = 'scheduled' ORDER BY order_on_tatami", [$t['id']]));
            // Tapis 1 et 3 : bien avancés ; tapis 2 : un combat terminé en attente de validation ; tapis 4 : un combat en cours.
            $done = [0 => 9, 1 => 6, 2 => 11, 3 => 4][$idx] ?? 3;
            for ($k = 0; $k < min($done, count($bouts)); $k++) {
                BoutService::selectCurrent((int) $t['id'], $bouts[$k]);
                $this->play($bouts[$k], $scripts[($k + $idx) % count($scripts)]);
            }
            $nextIdx = min($done, count($bouts) - 1);
            if (!isset($bouts[$nextIdx])) {
                continue;
            }
            BoutService::selectCurrent((int) $t['id'], $bouts[$nextIdx]);
            if ($idx === 1) {
                $this->play($bouts[$nextIdx], [['action' => 'score', 'side' => 'a', 'kind' => 'wazaari', 'delta' => 1]], false);
            } elseif ($idx === 3) {
                $s = BoutService::state($bouts[$nextIdx]);
                BoutService::act($bouts[$nextIdx], ['action' => 'start', 'version' => $s['version']]);
                $s = BoutService::state($bouts[$nextIdx]);
                BoutService::act($bouts[$nextIdx], ['action' => 'score', 'side' => 'b', 'kind' => 'yuko', 'delta' => 1, 'version' => $s['version']]);
                $s = BoutService::state($bouts[$nextIdx]);
                BoutService::act($bouts[$nextIdx], ['action' => 'pause', 'version' => $s['version']]);
            }
        }
    }

    private function sales(int $cid): void
    {
        $products = SalesService::products($cid);
        if (!$products) {
            foreach ([['restauration', 'Sandwich (démo)', 300], ['restauration', 'Crêpe (démo)', 150], ['boissons', 'Soda 33 cl (démo)', 200], ['boissons', 'Eau 50 cl (démo)', 100], ['boissons', 'Café (démo)', 150], ['boutique', 'T-shirt du club (démo)', 1500]] as $i => [$cat, $name, $price]) {
                Database::insert('products', ['competition_id' => $cid, 'category' => $cat, 'name' => $name, 'price_cents' => $price, 'sort_order' => $i, 'is_demo' => 1]);
            }
            $products = SalesService::products($cid);
        }
        $first = null;
        for ($i = 0; $i < 25; $i++) {
            $items = [];
            foreach ((array) array_rand($products, mt_rand(1, 3)) as $k) {
                $items[] = ['product_id' => (int) $products[$k]['id'], 'quantity' => mt_rand(1, 3)];
            }
            $id = SalesService::record($cid, $items, mt_rand(0, 2) ? 'cash' : 'card', 'demo-' . $i);
            Database::run('UPDATE sales SET is_demo = 1, sold_at = ? WHERE id = ?', [date('Y-m-d H:i:s', strtotime('today 09:00') + $i * 900), $id]);
            $first ??= $id;
        }
        SalesService::refund($first, 'Erreur de saisie (démo)');
    }

    public const GALLERY_PASSWORD = 'photos2026';

    /** Album de démonstration : images abstraites générées (aucune vraie photo), mot de passe « photos2026 ». */
    private function gallery(int $cid): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }
        $palettes = [[255, 214, 120], [255, 170, 120], [245, 120, 130], [190, 200, 255], [170, 230, 190], [255, 235, 170], [230, 190, 255], [255, 200, 160], [200, 235, 245]];
        $tmp = sys_get_temp_dir() . '/jcl-demo-' . bin2hex(random_bytes(4));
        mkdir($tmp);
        foreach ($palettes as $i => [$r, $g, $b]) {
            $w = 1200;
            $h = $i % 3 === 1 ? 1500 : 800;
            $im = imagecreatetruecolor($w, $h);
            for ($y = 0; $y < $h; $y++) {
                $t = $y / $h;
                imageline($im, 0, $y, $w, $y, imagecolorallocate($im, (int) ($r + (250 - $r) * $t), (int) ($g + (246 - $g) * $t), (int) ($b + (240 - $b) * $t)));
            }
            imagefilledellipse($im, (int) ($w * .7), (int) ($h * .35), (int) ($w * .6), (int) ($w * .6), imagecolorallocatealpha($im, 255, 255, 255, 80));
            imagestring($im, 5, 40, $h - 60, 'Photo de demonstration ' . ($i + 1) . ' - donnees fictives', imagecolorallocate($im, 60, 60, 60));
            $file = "$tmp/demo-$i.jpg";
            imagejpeg($im, $file, 85);
            $id = \App\Services\DocumentService::storePath($file, "demo-$i.jpg", 'gallery', 'Photo de démonstration ' . ($i + 1), $cid, false);
            Database::run('UPDATE documents SET sort_order = ? WHERE id = ?', [$i + 1, $id]);
            unlink($file);
        }
        rmdir($tmp);
        \App\Services\GalleryService::setPassword($cid, self::GALLERY_PASSWORD);
    }

    /** Liens d'affichage TV (un par tapis) — à afficher à la fin du seed. */
    public static function tvLinks(int $cid): array
    {
        $out = [];
        foreach (CompetitionService::tatamis($cid) as $t) {
            $out[(int) $t['number']] = TvTokenService::create((int) $t['id'], 'Écran tapis ' . $t['number'] . ' (démo)', 30)['url'];
        }
        return $out;
    }
}
