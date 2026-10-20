<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\Attendance\AttendanceController;
use App\Controllers\Commissioner\CommissionerController;
use App\Controllers\Public\SiteController;
use App\Controllers\Sales\PosController;
use App\Controllers\Tv\TvController;
use App\Controllers\Admin\PoolController;

// API interne (JSON). Les écritures exigent une session et le jeton CSRF (en-tête X-CSRF-Token).

// Public, lecture seule
$router->get('/api/public/competitions/{slug}/classement', [SiteController::class, 'rankingApi']);
$router->get('/api/public/competitions/{slug}/classement/etat', [SiteController::class, 'rankingStampApi']);
$router->get('/api/public/competitions/{slug}/direct', [SiteController::class, 'liveApi']);

// Écran TV : jeton d'affichage dans la requête, aucune écriture possible
$router->get('/api/tv/etat', [TvController::class, 'state']);

// Commissaire sportif (et administrateur)
$router->get('/api/commissaire/tapis/{tid:\d+}', [CommissionerController::class, 'tatamiState'], ['auth']);
$router->post('/api/commissaire/tapis/{tid:\d+}/selection', [CommissionerController::class, 'select'], ['auth']);
$router->post('/api/combats/{bid:\d+}/action', [CommissionerController::class, 'act'], ['auth']);
$router->post('/api/combats/{bid:\d+}/correction', [CommissionerController::class, 'correctApi'], ['auth']);

// Accueil
$router->get('/api/accueil/{id:\d+}/athletes', [AttendanceController::class, 'list'], ['auth', 'can:attendance.manage']);
$router->post('/api/accueil/inscriptions/{rid:\d+}/presence', [AttendanceController::class, 'mark'], ['auth', 'can:attendance.manage']);

// Poules (glisser-déposer)
$router->post('/api/admin/competitions/{id:\d+}/poules', [PoolController::class, 'api'], ['auth', 'role:admin', 'can:pools.manage']);

// Caisse
$router->post('/api/caisse/{id:\d+}/ventes', [PosController::class, 'store'], ['auth', 'can:sales.record']);
