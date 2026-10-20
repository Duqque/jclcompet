<?php
declare(strict_types=1);

// Création d'un compte administrateur (ou réinitialisation de son mot de passe).
// Usage : php bin/create-admin.php email@domaine.fr "Nom affiché"
// Le mot de passe est demandé de façon masquée (12 caractères minimum) ; il n'est jamais stocké en clair.
require dirname(__DIR__) . '/app/bootstrap.php';
$_SESSION = [];

use App\Core\Database;
use App\Services\AuditLogger;

[$_, $email, $name] = array_pad($argv, 3, null);
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$name) {
    fwrite(STDERR, "Usage : php bin/create-admin.php email@domaine.fr \"Nom affiché\"\n");
    exit(1);
}
$password = getenv('ADMIN_PASSWORD') ?: null;
if ($password === null) {
    echo 'Mot de passe (12 caractères minimum) : ';
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
    }
    $password = trim((string) fgets(STDIN));
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty echo');
    }
    echo "\n";
}
if (mb_strlen($password) < 12) {
    fwrite(STDERR, "Mot de passe trop court (12 caractères minimum).\n");
    exit(1);
}
$roleId = (int) Database::value("SELECT id FROM roles WHERE code = 'admin'");
$email = mb_strtolower($email);
$existing = Database::value('SELECT id FROM users WHERE email = ?', [$email]);
if ($existing) {
    Database::run('UPDATE users SET password_hash = ?, role_id = ?, is_active = 1, display_name = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $roleId, $name, $existing]);
    AuditLogger::log('user.admin_reset_cli', 'user', (int) $existing);
    echo "Compte administrateur mis à jour : $email\n";
} else {
    $id = Database::insert('users', ['email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'display_name' => $name, 'role_id' => $roleId]);
    AuditLogger::log('user.admin_create_cli', 'user', $id);
    echo "Compte administrateur créé : $email\n";
}
