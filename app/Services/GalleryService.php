<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

/**
 * Galeries photos des compétitions, accessibles uniquement avec le mot de passe de l'album.
 * Les photos sont des documents privés : /media ne les sert qu'aux visiteurs ayant déverrouillé l'album
 * (ou aux utilisateurs connectés). Changer le mot de passe révoque les accès déjà ouverts.
 */
final class GalleryService
{
    public static function passwordHash(int $competitionId): ?string
    {
        $h = SettingsService::get($competitionId, 'gallery_password_hash');
        return is_string($h) && $h !== '' ? $h : null;
    }

    public static function setPassword(int $competitionId, string $password): void
    {
        if (mb_strlen($password) < 6) {
            throw new DomainError('Le mot de passe de l\'album doit contenir au moins 6 caractères.');
        }
        SettingsService::set($competitionId, 'gallery_password_hash', password_hash($password, PASSWORD_DEFAULT));
        AuditLogger::log('gallery.password', 'competition', $competitionId, null, null, null, $competitionId);
    }

    public static function disable(int $competitionId): void
    {
        SettingsService::set($competitionId, 'gallery_password_hash', '');
        AuditLogger::log('gallery.disable', 'competition', $competitionId, null, null, null, $competitionId);
    }

    private static function fingerprint(string $hash): string
    {
        return substr(hash('sha256', $hash), 0, 16);
    }

    public static function hasAccess(int $competitionId): bool
    {
        $hash = self::passwordHash($competitionId);
        $granted = (array) Session::get('gallery_access', []);
        return $hash !== null && isset($granted[$competitionId]) && hash_equals(self::fingerprint($hash), (string) $granted[$competitionId]);
    }

    /** @return true|string true si l'album est déverrouillé, sinon le message d'erreur */
    public static function unlock(int $competitionId, string $password, Request $request): bool|string
    {
        $hash = self::passwordHash($competitionId);
        if ($hash === null) {
            return 'Cet album n\'est pas encore ouvert.';
        }
        $key = 'gallery:' . $competitionId;
        if (\App\Core\RateLimiter::tooManyAttempts($key, $request->ip())) {
            return 'Trop de tentatives. Réessayez dans quelques minutes.';
        }
        if (!password_verify($password, $hash)) {
            \App\Core\RateLimiter::hit($key, $request->ip(), false);
            return 'Mot de passe incorrect.';
        }
        \App\Core\RateLimiter::hit($key, $request->ip(), true);
        Session::regenerate();
        $granted = (array) Session::get('gallery_access', []);
        $granted[$competitionId] = self::fingerprint($hash);
        Session::put('gallery_access', $granted);
        return true;
    }

    public static function lock(int $competitionId): void
    {
        $granted = (array) Session::get('gallery_access', []);
        unset($granted[$competitionId]);
        Session::put('gallery_access', $granted);
    }

    /** @return int[] identifiants des photos ajoutées */
    public static function upload(int $competitionId, array $files, string $caption = ''): array
    {
        CompetitionService::assertWritable($competitionId);
        $ids = [];
        $n = is_array($files['name'] ?? null) ? count($files['name']) : 0;
        $order = (int) Database::value("SELECT COALESCE(MAX(sort_order), 0) FROM documents WHERE type = 'gallery' AND competition_id = ?", [$competitionId]);
        for ($i = 0; $i < $n; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $one = ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => UPLOAD_ERR_OK, 'size' => $files['size'][$i]];
            $id = DocumentService::storeUpload($one, 'gallery', $caption !== '' ? $caption : pathinfo((string) $one['name'], PATHINFO_FILENAME), $competitionId, false);
            Database::run('UPDATE documents SET sort_order = ? WHERE id = ?', [++$order, $id]);
            $ids[] = $id;
        }
        if (!$ids) {
            throw new DomainError('Aucune photo valide (formats JPEG, PNG ou WebP).');
        }
        return $ids;
    }

    public static function photos(int $competitionId): array
    {
        return Database::all("SELECT * FROM documents WHERE type = 'gallery' AND competition_id = ? ORDER BY sort_order, id", [$competitionId]);
    }

    /** Albums publiés : compétitions ayant des photos et un mot de passe défini. */
    public static function albums(): array
    {
        $rows = Database::all(
            "SELECT c.id, c.name, c.slug, c.edition_label, c.event_date, COUNT(d.id) AS photos
             FROM documents d JOIN competitions c ON c.id = d.competition_id
             WHERE d.type = 'gallery' AND c.status <> 'draft' GROUP BY c.id ORDER BY c.event_date DESC"
        );
        return array_values(array_filter($rows, fn ($a) => self::passwordHash((int) $a['id']) !== null));
    }
}
