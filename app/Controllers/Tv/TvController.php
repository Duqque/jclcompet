<?php
declare(strict_types=1);

namespace App\Controllers\Tv;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ContentService;
use App\Services\TvTokenService;

/** Écran TV d'un tapis : lecture seule, accès par jeton d'affichage (aucun compte sur le téléviseur). */
final class TvController extends Controller
{
    public function show(Request $r): Response
    {
        $number = (int) $r->param('number');
        $token = (string) ($r->query['jeton'] ?? '');
        try {
            $t = TvTokenService::verify($token, $number);
        } catch (\App\Core\HttpException $e) {
            return Response::html(View::render('tv/denied', ['message' => $e->getMessage(), 'number' => $number]), 403);
        }
        $state = TvTokenService::tvState($t);
        return Response::html(View::render('tv/screen', [
            'state' => $state, 'number' => $number,
            'apiUrl' => url('/api/tv/etat', ['tapis' => $number, 'jeton' => $token]),
            'organizerLogo' => asset('img/logo-jcl.png'),
            'clubName' => ContentService::site('club_name', 'JC Leforest'),
        ]))->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function state(Request $r): Response
    {
        $t = TvTokenService::verify((string) ($r->query['jeton'] ?? ''), (int) ($r->query['tapis'] ?? 0));
        return $this->json(TvTokenService::tvState($t));
    }
}
