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
        'flyer' => 'Affiche', 'other' => 'Autre',
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
        $id = Database::insert('documents', [
            'competition_id' => $competitionId, 'type' => array_key_exists($type, self::TYPES) ? $type : 'other',
            'title' => mb_substr($title !== '' ? $title : $originalName, 0, 190), 'original_name' => mb_substr(basename($originalName), 0, 255),
            'stored_name' => $stored, 'mime_type' => $mime, 'size_bytes' => $size, 'is_public' => $public ? 1 : 0, 'uploaded_by' => Auth::id(),
        ]);
        AuditLogger::log('document.upload', 'document', $id, null, ['title' => $title, 'type' => $type], null, $competitionId);
        return $id;
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
