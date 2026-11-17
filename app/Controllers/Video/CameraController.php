<?php
declare(strict_types=1);

namespace App\Controllers\Video;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\VideoService;

/**
 * Téléphone ou iPad utilisé comme caméra d'un tapis : aucune connexion, un code à 6 caractères donné par la régie
 * (valable 24 h, limité à un emplacement de caméra). La vidéo part directement vers la régie (WebRTC, réseau local).
 */
final class CameraController extends Controller
{
    /** Saisie du code. */
    public function form(Request $r): Response
    {
        return $this->view('video/camera_code', ['title' => 'Caméra'], 'bare');
    }

    public function enter(Request $r): Response
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($r->post['code'] ?? '')));
        return $this->redirect('/camera/' . $code);
    }

    public function show(Request $r): Response
    {
        $cam = $this->camera($r);
        return $this->view('video/camera', [
            'title' => 'Caméra ' . $cam['slot'] . ' · Tapis ' . $cam['tatami_number'], 'cam' => $cam,
            'config' => [
                'offerUrl' => url('/api/camera/' . $r->param('code') . '/offre'), 'answerUrl' => url('/api/camera/' . $r->param('code') . '/reponse'),
                'slotLabel' => VideoService::SLOTS[(int) $cam['slot']] ?? 'Caméra', 'tatami' => (int) $cam['tatami_number'],
                'ice' => \App\Core\Config::get('app.video_ice_servers', [['urls' => 'stun:stun.l.google.com:19302']]),
            ],
        ], 'bare');
    }

    public function offer(Request $r): Response
    {
        $cam = $this->camera($r);
        $in = $r->json();
        VideoService::cameraOffer($cam, (string) ($in['session'] ?? ''), (string) ($in['sdp'] ?? ''), (string) ($in['device'] ?? ''));
        return $this->json(['ok' => true]);
    }

    /** Réponse de la régie (attendue par le téléphone) ; sert aussi de signe de vie. */
    public function answer(Request $r): Response
    {
        $cam = $this->camera($r);
        VideoService::cameraPing($cam);
        $session = (string) ($r->query['session'] ?? '');
        return $this->json([
            'answer' => $cam['session'] === $session ? $cam['answer_sdp'] : null,
            'replaced' => $cam['session'] !== null && $cam['session'] !== $session,
        ])->header('Cache-Control', 'no-store');
    }

    /** Code vérifié, avec limitation des essais par adresse IP (codes courts). */
    private function camera(Request $r): array
    {
        $ip = $r->ip();
        if (RateLimiter::tooManyAttempts('camera:' . $ip, $ip)) {
            throw new HttpException(429, 'Trop d\'essais : patientez quelques minutes.');
        }
        try {
            return VideoService::cameraByCode((string) $r->param('code'));
        } catch (HttpException $e) {
            RateLimiter::hit('camera:' . $ip, $ip, false);
            throw $e;
        }
    }
}
