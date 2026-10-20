<?php
declare(strict_types=1);

// Remplissage de la base.
//   php bin/seed.php --content   contenu réel du programme JC Leforest (club, Taïso, édition 2026…) — idempotent
//   php bin/seed.php --demo      données de démonstration FICTIVES (refusé si APP_ENV=production)
require dirname(__DIR__) . '/app/bootstrap.php';
$_SESSION = [];

use Database\Seeds\DemoSeeder;
use Database\Seeds\ProgrammeSeeder;

$args = array_slice($argv, 1);
if (!$args) {
    fwrite(STDERR, "Usage : php bin/seed.php [--content] [--demo]\n");
    exit(1);
}
try {
    if (in_array('--content', $args, true)) {
        $id = (new ProgrammeSeeder())->run();
        echo $id ? "Contenu du programme importé (compétition n° $id).\n" : "Contenu du programme déjà présent : rien à faire.\n";
    }
    if (in_array('--demo', $args, true)) {
        $cid = (new DemoSeeder())->run();
        echo "Données de démonstration FICTIVES créées (compétition n° $cid).\n\nComptes de démonstration (à supprimer avant toute utilisation réelle) :\n";
        foreach (DemoSeeder::ACCOUNTS as [$email, $password, $name, $role]) {
            printf("  %-32s %-22s %s\n", $email, $password, $role);
        }
        echo "\nÉcrans TV (liens valables 30 jours) :\n";
        foreach (DemoSeeder::tvLinks($cid) as $n => $url) {
            echo "  Tapis $n : $url\n";
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n" . (App\Core\Config::get('app.debug') ? $e->getTraceAsString() . "\n" : ''));
    exit(1);
}
