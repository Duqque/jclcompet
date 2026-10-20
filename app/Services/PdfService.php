<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Response;
use App\Core\View;

/**
 * Génération des PDF à partir des données en base (jamais de données saisies dans le navigateur).
 * Utilise Dompdf (installé par Composer, compatible Hostinger) ; à défaut, renvoie une version HTML imprimable.
 */
final class PdfService
{
    public static function available(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    public static function render(string $template, array $data, string $filename, string $orientation = 'portrait', bool $forceHtml = false): Response
    {
        $html = View::render('pdf/' . $template, $data + ['orientation' => $orientation], 'pdf');
        if ($forceHtml || !self::available()) {
            return Response::html($html);
        }
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', BASE_PATH . '/public');
        $options->set('tempDir', BASE_PATH . '/storage/cache');
        $options->set('fontCache', BASE_PATH . '/storage/cache');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation === 'landscape' ? 'landscape' : 'portrait');
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($orientation === 'landscape' ? 760 : 520, $orientation === 'landscape' ? 570 : 815, 'Page {PAGE_NUM} / {PAGE_COUNT}', null, 7, [0.4, 0.4, 0.4]);
        $resp = new Response((string) $dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) . '"',
            'Cache-Control' => 'private, no-store',
        ]);
        return $resp;
    }
}
