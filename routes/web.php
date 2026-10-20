<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\Admin;
use App\Controllers\AuthController;
use App\Controllers\Attendance\AttendanceController;
use App\Controllers\Club\ClubSpaceController;
use App\Controllers\Commissioner\CommissionerController;
use App\Controllers\Public\SiteController;
use App\Controllers\Sales\PosController;
use App\Controllers\Tv\TvController;

// --- Site vitrine public -----------------------------------------------------
$router->get('/', [SiteController::class, 'home']);
$router->get('/competitions', [SiteController::class, 'competitions']);
$router->get('/competitions/{slug}', [SiteController::class, 'competition']);
$router->get('/competitions/{slug}/resultats', [SiteController::class, 'results']);
$router->get('/competitions/{slug}/direct', [SiteController::class, 'live']);
$router->get('/classement/{slug}', [SiteController::class, 'rankingBoard']);
$router->get('/club', [SiteController::class, 'club']);
$router->get('/taiso', [SiteController::class, 'taiso']);
$router->get('/partenaires', [SiteController::class, 'partners']);
$router->get('/mentions-legales', [SiteController::class, 'legal']);
$router->get('/confidentialite', [SiteController::class, 'privacy']);
$router->get('/media/{id:\d+}', [SiteController::class, 'media']);

// --- Authentification -----------------------------------------------------------
$router->get('/connexion', [AuthController::class, 'showLogin']);
$router->post('/connexion', [AuthController::class, 'login']);
$router->post('/deconnexion', [AuthController::class, 'logout']);
$router->get('/compte/mot-de-passe', [AuthController::class, 'showPassword'], ['auth']);
$router->post('/compte/mot-de-passe', [AuthController::class, 'updatePassword'], ['auth']);

// --- Écrans TV (jeton d'affichage, lecture seule, aucune session) -----------------
$router->get('/tv/tapis/{number:\d+}', [TvController::class, 'show']);

// --- Administration -----------------------------------------------------------
$router->group('/admin', ['auth', 'role:admin'], function ($r) {
    $r->get('', [Admin\DashboardController::class, 'index']);
    $r->get('/competitions', [Admin\CompetitionController::class, 'index']);
    $r->get('/competitions/nouvelle', [Admin\CompetitionController::class, 'create'], ['can:competitions.manage']);
    $r->post('/competitions', [Admin\CompetitionController::class, 'store'], ['can:competitions.manage']);
    $r->group('/competitions/{id:\d+}', [], function ($r) {
        $r->get('', [Admin\CompetitionController::class, 'show']);
        $r->get('/modifier', [Admin\CompetitionController::class, 'edit'], ['can:competitions.manage']);
        $r->post('/modifier', [Admin\CompetitionController::class, 'update'], ['can:competitions.manage']);
        $r->post('/statut', [Admin\CompetitionController::class, 'status'], ['can:competitions.manage']);
        $r->post('/archiver', [Admin\CompetitionController::class, 'archive'], ['can:archive.manage']);
        $r->post('/rouvrir', [Admin\CompetitionController::class, 'reopen'], ['can:archive.manage']);
        $r->post('/dupliquer', [Admin\CompetitionController::class, 'duplicate'], ['can:competitions.manage']);
        $r->get('/parametres', [Admin\SettingsController::class, 'edit'], ['can:competitions.manage']);
        $r->post('/parametres', [Admin\SettingsController::class, 'update'], ['can:competitions.manage']);
        $r->get('/categories', [Admin\CategoryController::class, 'index'], ['can:competitions.manage']);
        $r->post('/categories', [Admin\CategoryController::class, 'save'], ['can:competitions.manage']);
        $r->get('/durees', [Admin\DurationController::class, 'index'], ['can:competitions.manage']);
        $r->post('/durees', [Admin\DurationController::class, 'save'], ['can:competitions.manage']);
        $r->get('/bareme', [Admin\RankingController::class, 'rules'], ['can:rankings.manage']);
        $r->post('/bareme', [Admin\RankingController::class, 'saveRules'], ['can:rankings.manage']);
        $r->get('/tapis', [Admin\TatamiController::class, 'index'], ['can:tv.manage']);
        $r->post('/tapis', [Admin\TatamiController::class, 'action'], ['can:tv.manage']);
        $r->get('/inscriptions', [Admin\RegistrationController::class, 'index'], ['can:registrations.manage']);
        $r->get('/inscriptions/nouvelle', [Admin\RegistrationController::class, 'create'], ['can:registrations.manage']);
        $r->post('/inscriptions', [Admin\RegistrationController::class, 'store'], ['can:registrations.manage']);
        $r->get('/inscriptions/{rid:\d+}', [Admin\RegistrationController::class, 'edit'], ['can:registrations.manage']);
        $r->post('/inscriptions/{rid:\d+}', [Admin\RegistrationController::class, 'update'], ['can:registrations.manage']);
        $r->post('/inscriptions/{rid:\d+}/action', [Admin\RegistrationController::class, 'action'], ['can:registrations.manage']);
        $r->get('/inscriptions/export.csv', [Admin\RegistrationController::class, 'export'], ['can:registrations.manage']);
        $r->post('/inscriptions/import', [Admin\RegistrationController::class, 'import'], ['can:registrations.manage']);
        $r->get('/clubs-presents', [Admin\CompetitionController::class, 'clubs'], ['can:content.manage']);
        $r->post('/clubs-presents', [Admin\CompetitionController::class, 'saveClubs'], ['can:content.manage']);
        $r->get('/poules', [Admin\PoolController::class, 'index'], ['can:pools.manage']);
        $r->post('/poules', [Admin\PoolController::class, 'action'], ['can:pools.manage']);
        $r->get('/combats', [Admin\BoutController::class, 'index']);
        $r->get('/combats/{bid:\d+}', [Admin\BoutController::class, 'show']);
        $r->post('/combats/{bid:\d+}', [Admin\BoutController::class, 'action'], ['can:results.correct']);
        $r->get('/classements', [Admin\RankingController::class, 'index']);
        $r->post('/classements', [Admin\RankingController::class, 'action'], ['can:rankings.manage']);
        $r->get('/ventes', [Admin\SalesController::class, 'index'], ['can:sales.manage']);
        $r->post('/ventes', [Admin\SalesController::class, 'action'], ['can:sales.manage']);
        $r->get('/ventes/export.csv', [Admin\SalesController::class, 'export'], ['can:sales.manage']);
        $r->get('/documents-pdf', [Admin\PdfController::class, 'index'], ['can:pdf.export']);
        $r->get('/pdf/{type}', [Admin\PdfController::class, 'render'], ['can:pdf.export']);
        $r->get('/contenu', [Admin\ContentController::class, 'competitionContent'], ['can:content.manage']);
        $r->get('/journal', [Admin\AuditController::class, 'index'], ['can:audit.view']);
    });
    $r->get('/clubs', [Admin\ClubController::class, 'index'], ['can:clubs.manage']);
    $r->get('/clubs/nouveau', [Admin\ClubController::class, 'create'], ['can:clubs.manage']);
    $r->post('/clubs', [Admin\ClubController::class, 'store'], ['can:clubs.manage']);
    $r->get('/clubs/{cid:\d+}', [Admin\ClubController::class, 'edit'], ['can:clubs.manage']);
    $r->post('/clubs/{cid:\d+}', [Admin\ClubController::class, 'update'], ['can:clubs.manage']);
    $r->post('/clubs/{cid:\d+}/action', [Admin\ClubController::class, 'action'], ['can:clubs.manage']);
    $r->get('/utilisateurs', [Admin\UserController::class, 'index'], ['can:users.manage']);
    $r->post('/utilisateurs', [Admin\UserController::class, 'action'], ['can:users.manage']);
    $r->get('/site', [Admin\ContentController::class, 'index'], ['can:content.manage']);
    $r->post('/site', [Admin\ContentController::class, 'action'], ['can:content.manage']);
    $r->get('/journal', [Admin\AuditController::class, 'index'], ['can:audit.view']);
    $r->post('/notifications/lues', [Admin\DashboardController::class, 'readNotifications']);
});

// --- Espace club -----------------------------------------------------------------
$router->group('/espace-club', ['auth', 'role:coach'], function ($r) {
    $r->get('', [ClubSpaceController::class, 'dashboard']);
    $r->get('/profil', [ClubSpaceController::class, 'profile']);
    $r->post('/profil', [ClubSpaceController::class, 'updateProfile']);
    $r->get('/competitions/{id:\d+}', [ClubSpaceController::class, 'competition']);
    $r->post('/competitions/{id:\d+}/inscriptions', [ClubSpaceController::class, 'register']);
    $r->get('/competitions/{id:\d+}/inscriptions/{rid:\d+}', [ClubSpaceController::class, 'editRegistration']);
    $r->post('/competitions/{id:\d+}/inscriptions/{rid:\d+}', [ClubSpaceController::class, 'updateRegistration']);
    $r->post('/competitions/{id:\d+}/inscriptions/{rid:\d+}/retirer', [ClubSpaceController::class, 'withdraw']);
    $r->get('/competitions/{id:\d+}/export.csv', [ClubSpaceController::class, 'export']);
    $r->post('/competitions/{id:\d+}/import', [ClubSpaceController::class, 'import']);
    $r->get('/competitions/{id:\d+}/poules', [ClubSpaceController::class, 'pools']);
    $r->get('/competitions/{id:\d+}/resultats', [ClubSpaceController::class, 'results']);
});

// --- Accueil / contrôle des présences (mobile) -----------------------------------
$router->group('/accueil', ['auth', 'can:attendance.manage'], function ($r) {
    $r->get('', [AttendanceController::class, 'index']);
    $r->get('/{id:\d+}', [AttendanceController::class, 'show']);
    $r->get('/{id:\d+}/sur-place', [AttendanceController::class, 'onsiteForm']);
    $r->post('/{id:\d+}/sur-place', [AttendanceController::class, 'onsiteStore']);
});

// --- Commissaire sportif -------------------------------------------------------
$router->group('/commissaire', ['auth'], function ($r) {
    $r->get('', [CommissionerController::class, 'home']);
    $r->post('/tapis/{tid:\d+}/activer', [CommissionerController::class, 'activate']);
    $r->get('/tapis/{tid:\d+}', [CommissionerController::class, 'scoring']);
    $r->get('/tapis/{tid:\d+}/historique', [CommissionerController::class, 'history']);
    $r->get('/combats/{bid:\d+}/corriger', [CommissionerController::class, 'correctForm']);
    $r->post('/combats/{bid:\d+}/corriger', [CommissionerController::class, 'correct']);
});

// --- Caisse buvette / boutique ------------------------------------------------------
$router->group('/caisse', ['auth', 'can:sales.record'], function ($r) {
    $r->get('', [PosController::class, 'index']);
    $r->get('/{id:\d+}', [PosController::class, 'show']);
});
