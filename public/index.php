<?php
declare(strict_types=1);

// Contrôleur frontal unique : toutes les requêtes non statiques passent ici (voir .htaccess).
require dirname(__DIR__) . '/app/bootstrap.php';

$router = new App\Core\Router();
require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/api.php';

(new App\Core\App($router))->handle(App\Core\Request::capture())->send();
