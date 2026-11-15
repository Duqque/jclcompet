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

// Application mobile « JCL Live » : lecture seule, aucune donnée privée
use App\Controllers\Api\MobileController;
$router->get('/api/v1/accueil', [MobileController::class, 'home']);
$router->get('/api/v1/competitions', [MobileController::class, 'competitionsList']);
$router->get('/api/v1/competitions/{slug}', [MobileController::class, 'competition']);
$router->get('/api/v1/competitions/{slug}/direct', [MobileController::class, 'live']);
$router->get('/api/v1/competitions/{slug}/etat', [MobileController::class, 'stamp']);
$router->get('/api/v1/competitions/{slug}/combats', [MobileController::class, 'bouts']);
$router->get('/api/v1/competitions/{slug}/poules', [MobileController::class, 'pools']);
$router->get('/api/v1/competitions/{slug}/judokas', [MobileController::class, 'athletes']);
$router->get('/api/v1/competitions/{slug}/resultats', [MobileController::class, 'results']);
$router->get('/api/v1/combats/{id:\d+}', [MobileController::class, 'boutDetail']);
$router->get('/api/v1/judokas/{id:\d+}', [MobileController::class, 'athlete']);
$router->get('/api/v1/suivi', [MobileController::class, 'follow']);
