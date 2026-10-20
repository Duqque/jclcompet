<?php
declare(strict_types=1);

// Création (ou mise à jour) d'un compte, quel que soit son rôle.
// Usage :
//   php bin/create-user.php --role=admin --email=prenom.nom@exemple.fr --name="Prénom NOM" [--changer]
//   php bin/create-user.php --role=commissioner --email=table1@exemple.fr --name="Table 1" --tatami=1
//   php bin/create-user.php --role=seller --email=buvette@exemple.fr --name="Buvette"
//   php bin/create-user.php --role=coach --email=club@exemple.fr --name="Club - JC Leforest" --club="Judo Club Leforest"
//   php bin/create-user.php --fichier=config/comptes.json      (création en lot, sans mot de passe dans le fichier)
// Rôles : admin, commissioner (commissaire), reception (accueil), seller (buvette), coach (club).
// Le mot de passe est généré (ou lu dans la variable d'environnement USER_PASSWORD) et affiché UNE seule fois ;
// seule son empreinte est enregistrée. --changer impose un nouveau mot de passe à la première connexion.
// --tatami=N affecte le commissaire au tapis N de chaque compétition non archivée (à refaire pour une nouvelle édition,
// ou depuis Administration › Tapis & TV).
require dirname(__DIR__) . '/app/bootstrap.php';
$_SESSION = [];

use App\Core\Database;
use App\Services\AuditLogger;

$cli = getopt('', ['role:', 'email:', 'name:', 'club:', 'tatami:', 'changer', 'fichier:']);
$jobs = [];
if (isset($cli['fichier'])) {
    $data = json_decode((string) @file_get_contents((string) $cli['fichier']), true);
    if (!is_array($data['comptes'] ?? null)) {
        fwrite(STDERR, "Fichier JSON illisible ou sans liste « comptes ».\n");
        exit(1);
    }
    foreach ($data['comptes'] as $c) {
        $jobs[] = ['role' => $c['role'] ?? '', 'email' => $c['email'] ?? '', 'name' => $c['nom'] ?? '', 'club' => $c['club'] ?? null,
            'tatami' => $c['tapis'] ?? null, 'changer' => !empty($c['changer_mot_de_passe']), 'password' => null];
    }
} else {
    $jobs[] = ['role' => $cli['role'] ?? '', 'email' => $cli['email'] ?? '', 'name' => $cli['name'] ?? '', 'club' => $cli['club'] ?? null,
        'tatami' => $cli['tatami'] ?? null, 'changer' => isset($cli['changer']), 'password' => getenv('USER_PASSWORD') ?: null];
}

/** Mot de passe lisible et robuste : 3 mots, chiffres, au moins 16 caractères. */
function generatePassword(): string
{
    $words = ['Tatami', 'Ippon', 'Judogi', 'Kimono', 'Hajime', 'Matte', 'Seoi', 'Uchimata', 'Osoto', 'Ukemi', 'Dojo', 'Kata', 'Randori', 'Sensei', 'Koka', 'Yuko', 'Waza', 'Obi', 'Rei', 'Shiai'];
    $pick = fn () => $words[random_int(0, count($words) - 1)];
    return $pick() . '-' . $pick() . '-' . $pick() . '-' . random_int(10, 99);
}

/** Crée ou met à jour un compte ; affiche le mot de passe une seule fois. */
function createAccount(array $opt): bool
{
    $role = (string) $opt['role'];
    $email = mb_strtolower(trim((string) $opt['email']));
    $name = trim((string) $opt['name']);
    $forcedPassword = $opt['password'];
    $roleId = (int) Database::value('SELECT id FROM roles WHERE code = ?', [$role]);
    if (!$roleId || !filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
        fwrite(STDERR, "Compte ignoré ($email) : rôle, e-mail ou nom invalide.\n");
        return false;
    }
    $password = $forcedPassword ?: generatePassword();
    if (mb_strlen($password) < 12 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        fwrite(STDERR, "Mot de passe trop faible (12 caractères minimum, avec des lettres et des chiffres).\n");
        return false;
    }

    $clubId = null;
    if ($role === 'coach') {
        $clubName = trim((string) ($opt['club'] ?? ''));
        $clubId = $clubName !== '' ? (int) Database::value('SELECT id FROM clubs WHERE name = ? OR short_name = ?', [$clubName, $clubName]) : 0;
        if (!$clubId) {
            fwrite(STDERR, "Club introuvable : indiquez --club=\"nom exact du club\" (voir Administration › Profils › Clubs).\n");
            return false;
        }
    }

    $row = ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'display_name' => $name, 'role_id' => $roleId, 'is_active' => 1, 'is_demo' => 0,
        'must_change_password' => $opt['changer'] ? 1 : 0];
    $existing = (int) Database::value('SELECT id FROM users WHERE email = ?', [$email]);
    if ($existing) {
        Database::update('users', $row, 'id = :id', ['id' => $existing]);
        $id = $existing;
        AuditLogger::log('user.update_cli', 'user', $id);
    } else {
        $id = Database::insert('users', $row + ['email' => $email]);
        AuditLogger::log('user.create_cli', 'user', $id);
    }
    if ($clubId) {
        Database::run('DELETE FROM club_users WHERE user_id = ?', [$id]);
        Database::insert('club_users', ['club_id' => $clubId, 'user_id' => $id]);
    }
    $assigned = [];
    if ($opt['tatami'] !== null && $role === 'commissioner') {
        foreach (Database::all("SELECT t.id, t.number, c.name FROM tatamis t JOIN competitions c ON c.id = t.competition_id WHERE t.number = ? AND c.status <> 'archived' AND c.is_demo = 0", [(int) $opt['tatami']]) as $t) {
            Database::run('INSERT IGNORE INTO commissioner_tatamis (user_id, tatami_id, is_active) VALUES (?, ?, 1)', [$id, $t['id']]);
            $assigned[] = $t['name'] . ' (tapis ' . $t['number'] . ')';
        }
    }

    echo ($existing ? 'Compte mis à jour' : 'Compte créé') . " : $name <$email> — rôle $role\n";
    echo "Mot de passe : $password\n";
    if ($assigned) {
        echo 'Affecté à : ' . implode(', ', $assigned) . "\n";
    }
    if ($opt['changer']) {
        echo "Un nouveau mot de passe sera demandé à la première connexion.\n";
    }
    echo "\n";
    return true;
}

$ok = true;
foreach ($jobs as $job) {
    $ok = createAccount($job) && $ok;
}
exit($ok ? 0 : 1);
