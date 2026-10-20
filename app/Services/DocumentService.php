<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Documents et visuels téléversés : stockés hors de la racine publique (storage/documents), servis par /media/{id}.
 * Types acceptés : PDF, PNG, JPEG, WebP, GIF (le SVG est refusé : risque de script).
 */
final class DocumentService
{
    public const MIME = [
        'application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif',
    ];
    public const TYPES = [
        'regulation' => 'Règlement', 'program' => 'Programme', 'visual' => 'Visuel', 'photo' => 'Photo', 'plan' => 'Plan de salle',
        'partner_logo' => 'Logo partenaire', 'ad' => 'Encart publicitaire', 'club_logo' => 'Logo de club', 'coach_info' => 'Document entraîneurs',
        'flyer' => 'Affiche', 'gallery' => 'Photo de compétition', 'athlete_photo' => 'Photo de judoka', 'other' => 'Autre',
    ];

    public static function dir(): string
    {
        $d = BASE_PATH . '/storage/documents';
        if (!is_dir($d)) {
            mkdir($d, 0775, true);
        }
        return $d;
    }

    public static function storeUpload(array $file, string $type, string $title, ?int $competitionId = null, bool $public = true): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new DomainError('Le fichier n\'a pas pu être téléversé.');
        }
        return self::storePath($file['tmp_name'], (string) $file['name'], $type, $title, $competitionId, $public, true);
    }

    public static function storePath(string $path, string $originalName, string $type, string $title, ?int $competitionId = null, bool $public = true, bool $move = false): int
    {
        $size = filesize($path);
        if ($size === false || $size === 0 || $size > (int) Config::get('app.upload_max_bytes', 8388608) * 2) {
            throw new DomainError('Fichier vide ou trop volumineux.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!isset(self::MIME[$mime])) {
            throw new DomainError('Format non accepté (PDF, PNG, JPEG, WebP ou GIF uniquement).');
        }
        $stored = bin2hex(random_bytes(16)) . '.' . self::MIME[$mime];
        $dest = self::dir() . '/' . $stored;
        $ok = $move ? move_uploaded_file($path, $dest) : copy($path, $dest);
        if (!$ok) {
            throw new DomainError('Impossible d\'enregistrer le fichier.');
        }
        $thumb = null;
        $dims = [null, null];
        if (str_starts_with($mime, 'image/') && $mime !== 'image/gif' && function_exists('imagecreatetruecolor')) {
            // Photos : orientation corrigée, original limité à 2400 px, vignette 640 px (pages légères).
            [$thumb, $dims] = self::prepareImage($dest, $mime, in_array($type, ['gallery', 'athlete_photo', 'photo'], true));
            clearstatcache(true, $dest);
            $size = (int) filesize($dest);
        }
        $id = Database::insert('documents', [
            'competition_id' => $competitionId, 'type' => array_key_exists($type, self::TYPES) ? $type : 'other',
            'title' => mb_substr($title !== '' ? $title : $originalName, 0, 190), 'original_name' => mb_substr(basename($originalName), 0, 255),
            'stored_name' => $stored, 'thumb_name' => $thumb, 'mime_type' => $mime, 'size_bytes' => $size,
            'width' => $dims[0], 'height' => $dims[1], 'is_public' => $public ? 1 : 0, 'uploaded_by' => Auth::id(),
        ]);
        AuditLogger::log('document.upload', 'document', $id, null, ['title' => $title, 'type' => $type], null, $competitionId);
        return $id;
    }

    /** @return array{0: ?string, 1: array{0: ?int, 1: ?int}} [nom de la vignette, dimensions] */
    private static function prepareImage(string $file, string $mime, bool $resize): array
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file),
            'image/png' => @imagecreatefrompng($file),
            'image/webp' => @imagecreatefromwebp($file),
            default => false,
        };
        if (!$img) {
            return [null, [null, null]];
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file);
            $rot = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
            if ($rot) {
                $img = imagerotate($img, $rot, 0);
            }
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $write = function (\GdImage $im, string $target, int $max) use ($mime): array {
            $w = imagesx($im);
            $h = imagesy($im);
            $ratio = min(1, $max / max($w, $h));
            $nw = max(1, (int) round($w * $ratio));
            $nh = max(1, (int) round($h * $ratio));
            $out = imagecreatetruecolor($nw, $nh);
            if ($mime !== 'image/jpeg') {
                imagealphablending($out, false);
                imagesavealpha($out, true);
            }
            imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
            match ($mime) {
                'image/jpeg' => imagejpeg($out, $target, 84),
                'image/png' => imagepng($out, $target, 7),
                'image/webp' => imagewebp($out, $target, 82),
            };
            return [$nw, $nh];
        };
        if ($resize && max($w, $h) > 2400) {
            [$w, $h] = $write($img, $file, 2400);
        }
        $thumb = pathinfo($file, PATHINFO_FILENAME) . '-mini.' . pathinfo($file, PATHINFO_EXTENSION);
        $write($img, self::dir() . '/' . $thumb, 640);
        return [$thumb, [$w, $h]];
    }

    /** Photos de compétition (galerie publique), par compétition ou toutes. */
    public static function gallery(?int $competitionId = null, int $limit = 500): array
    {
        $sql = "SELECT d.*, c.name AS competition_name, c.slug AS competition_slug, c.edition_label FROM documents d
                LEFT JOIN competitions c ON c.id = d.competition_id WHERE d.type = 'gallery' AND d.is_public = 1";
        $p = [];
        if ($competitionId !== null) {
            $sql .= ' AND d.competition_id = ?';
            $p[] = $competitionId;
        }
        return Database::all($sql . ' ORDER BY c.event_date DESC, d.sort_order, d.id LIMIT ' . (int) $limit, $p);
    }

    /** Albums : une entrée par compétition ayant des photos publiques. */
    public static function albums(): array
    {
        return Database::all(
            "SELECT c.id, c.name, c.slug, c.edition_label, c.event_date, COUNT(d.id) AS photos, MIN(d.id) AS cover_id
             FROM documents d JOIN competitions c ON c.id = d.competition_id
             WHERE d.type = 'gallery' AND d.is_public = 1 AND c.status <> 'draft' GROUP BY c.id ORDER BY c.event_date DESC"
        );
    }

    public static function find(int $id): array
    {
        $d = Database::one('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$d) {
            throw new HttpException(404, 'Document introuvable.');
        }
        return $d;
    }

    public static function url(?int $id): ?string
    {
        return $id ? url('/media/' . $id) : null;
    }

    public static function delete(int $id): void
    {
        $d = self::find($id);
        Database::run('DELETE FROM documents WHERE id = ?', [$id]);
        @unlink(self::dir() . '/' . $d['stored_name']);
        if ($d['thumb_name']) {
            @unlink(self::dir() . '/' . $d['thumb_name']);
        }
        AuditLogger::log('document.delete', 'document', $id, ['title' => $d['title']], null, null, $d['competition_id'] === null ? null : (int) $d['competition_id']);
    }

    public static function list(?int $competitionId = null, ?string $type = null): array
    {
        $sql = 'SELECT * FROM documents WHERE 1=1';
        $p = [];
        if ($competitionId !== null) {
            $sql .= ' AND competition_id = ?';
            $p[] = $competitionId;
        }
        if ($type !== null) {
            $sql .= ' AND type = ?';
            $p[] = $type;
        }
        return Database::all($sql . ' ORDER BY id DESC', $p);
    }
}
