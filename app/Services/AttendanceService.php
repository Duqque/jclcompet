<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/** Contrôle des présences le jour J : chaque changement est horodaté, attribué et journalisé. */
final class AttendanceService
{
    public static function mark(int $registrationId, string $status, ?float $measuredWeight = null, ?string $note = null): array
    {
        if (!isset(RegistrationService::ATTENDANCE[$status])) {
            throw new DomainError('Statut de présence invalide.');
        }
        $reg = RegistrationService::find($registrationId);
        CompetitionService::assertWritable((int) $reg['competition_id']);
        if ($reg['status'] === 'withdrawn' || $reg['status'] === 'rejected') {
            throw new DomainError('Inscription retirée ou refusée : rétablissez-la avant de pointer la présence.');
        }
        if ($measuredWeight !== null && ($measuredWeight < 10 || $measuredWeight > 250)) {
            throw new DomainError('Poids mesuré invalide.');
        }
        if ($status !== 'present' && RegistrationService::inPool($registrationId)) {
            $started = Database::value(
                "SELECT 1 FROM bouts WHERE (a_registration_id = ? OR b_registration_id = ?) AND status <> 'scheduled' AND status <> 'cancelled' LIMIT 1",
                [$registrationId, $registrationId]
            );
            if ($started) {
                throw new DomainError('Cet athlète a déjà combattu : déclarez un forfait (fusen-gachi) sur ses combats restants plutôt que de modifier sa présence.');
            }
        }
        return Database::transaction(function () use ($reg, $registrationId, $status, $measuredWeight, $note) {
            $row = ['attendance_status' => $status, 'attendance_at' => date('Y-m-d H:i:s'), 'attendance_by' => Auth::id()];
            if ($measuredWeight !== null) {
                $row['measured_weight_kg'] = $measuredWeight;
                if (SettingsService::get((int) $reg['competition_id'], 'use_measured_weight') && SettingsService::get((int) $reg['competition_id'], 'weight_input') === 'weight') {
                    $age = Database::one('SELECT * FROM age_categories WHERE id = ?', [$reg['age_category_id']]);
                    if (($age['format'] ?? 'pools') === 'pools') {
                        $cat = CategoryService::weightCategoryFor((int) $reg['age_category_id'], $reg['sex'], $measuredWeight);
                        $row['weight_category_id'] = $cat['id'] ?? null;
                    }
                }
            }
            Database::update('registrations', $row, 'id = :id', ['id' => $registrationId]);
            Database::insert('attendance_records', [
                'registration_id' => $registrationId, 'status' => $status, 'measured_weight_kg' => $measuredWeight,
                'note' => $note ? mb_substr($note, 0, 255) : null, 'recorded_by' => Auth::id(),
            ]);
            AuditLogger::log('attendance.mark', 'registration', $registrationId,
                ['attendance' => $reg['attendance_status'], 'weight_category_id' => $reg['weight_category_id']],
                ['attendance' => $status, 'measured_weight' => $measuredWeight, 'weight_category_id' => $row['weight_category_id'] ?? $reg['weight_category_id']],
                $note, (int) $reg['competition_id']);
            return RegistrationService::find($registrationId);
        });
    }

    public static function history(int $registrationId): array
    {
        return Database::all(
            'SELECT ar.*, u.display_name FROM attendance_records ar LEFT JOIN users u ON u.id = ar.recorded_by WHERE registration_id = ? ORDER BY ar.id DESC',
            [$registrationId]
        );
    }
}
