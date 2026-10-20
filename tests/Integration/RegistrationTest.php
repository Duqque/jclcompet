<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Services\AttendanceService;
use App\Services\CompetitionService;
use App\Services\RegistrationService;
use App\Services\SettingsService;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    public function testCompetitionCreationBuildsTatamisCategoriesAndRules(): void
    {
        $this->admin();
        $cid = $this->competition(['mat_count' => 4], ['U11', 'U13', 'U15'], true);
        $this->assertCount(4, CompetitionService::tatamis($cid));
        $this->assertSame(3, (int) Database::value('SELECT COUNT(*) FROM age_categories WHERE competition_id = ?', [$cid]));
        $this->assertSame(6, (int) Database::value('SELECT COUNT(*) FROM competition_categories WHERE competition_id = ?', [$cid]));
        $this->assertGreaterThan(0, (int) Database::value('SELECT COUNT(*) FROM weight_categories WHERE competition_id = ?', [$cid]));
        $this->assertGreaterThan(0, (int) Database::value('SELECT COUNT(*) FROM fight_duration_rules WHERE competition_id = ? AND is_example = 1', [$cid]));
        CompetitionService::update($cid, ['mat_count' => 2]);
        $this->assertCount(2, CompetitionService::tatamis($cid));
    }

    public function testAgeCategoryIsComputedFromBirthYear(): void
    {
        $this->admin();
        $cid = $this->competition();
        [$rid] = $this->athletes($cid, 1, 'F', 12, 38, null, false);
        $this->assertSame('U13', RegistrationService::find($rid)['age_code']);
    }

    public function testIncompleteDuplicateAndIncompatibleRegistrationsAreRefused(): void
    {
        $this->admin();
        $cid = $this->competition();
        $club = $this->club();
        $this->expectValidation(fn () => RegistrationService::register($cid, ['last_name' => 'X'], ['actor' => 'club', 'club_id' => $club]));
        $data = ['last_name' => 'durand', 'first_name' => 'léa', 'sex' => 'F', 'birth_date' => $this->birthFor(9), 'declared_weight_kg' => 28];
        $id = RegistrationService::register($cid, $data, ['actor' => 'club', 'club_id' => $club]);
        $this->assertSame('DURAND', RegistrationService::find($id)['last_name']);
        $this->assertSame('Léa', RegistrationService::find($id)['first_name']);
        $this->assertDomainError(fn () => RegistrationService::register($cid, $data, ['actor' => 'club', 'club_id' => $this->club()]), 'Doublon');
        $this->assertDomainError(fn () => RegistrationService::register($cid, ['birth_date' => $this->birthFor(30)] + $data + ['first_name' => 'Autre'], ['actor' => 'club', 'club_id' => $club]), 'aucune catégorie');
        Database::run('UPDATE competition_categories SET is_open = 0 WHERE competition_id = ? AND sex = "M"', [$cid]);
        $this->assertDomainError(fn () => RegistrationService::register($cid, ['sex' => 'M', 'first_name' => 'Tom'] + $data, ['actor' => 'club', 'club_id' => $club]), "n'est pas ouverte");
    }

    private function expectValidation(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Validation attendue');
        } catch (\App\Core\ValidationException $e) {
            $this->assertArrayHasKey('first_name', $e->errors);
        }
    }

    public function testClubRegistrationAfterDeadlineIsRefusedButAdminCanRegisterLate(): void
    {
        $this->admin();
        $cid = $this->competition(['registration_opens_at' => date('Y-m-d H:i:s', strtotime('-10 days')), 'registration_closes_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
        $club = $this->club();
        $data = ['last_name' => 'TARD', 'first_name' => 'Ivy', 'sex' => 'F', 'birth_date' => $this->birthFor(10), 'declared_weight_kg' => 30];
        $this->assertDomainError(fn () => RegistrationService::register($cid, $data, ['actor' => 'club', 'club_id' => $club]), 'pas ouvertes');
        $id = RegistrationService::register($cid, $data, ['actor' => 'admin', 'club_id' => $club]);
        $r = RegistrationService::find($id);
        $this->assertSame(1, (int) $r['is_late']);
        $this->assertSame('confirmed', $r['status']);
        $id2 = RegistrationService::register($cid, ['first_name' => 'Sur place'] + $data, ['actor' => 'onsite', 'club_id' => $club, 'present' => true]);
        $r2 = RegistrationService::find($id2);
        $this->assertSame('present', $r2['attendance_status']);
        $this->assertSame(1, (int) $r2['needs_validation'], 'Inscription tardive sur place à valider (réglage par défaut)');
        $this->assertSame(1, (int) $r2['is_onsite']);
    }

    public function testWeightCategoryComesFromWeightOrFromClubChoice(): void
    {
        $this->admin();
        $cid = $this->competition([], ['U13'], true);
        $club = $this->club();
        $base = ['last_name' => 'POIDS', 'sex' => 'M', 'birth_date' => $this->birthFor(11)];
        $id = RegistrationService::register($cid, $base + ['first_name' => 'A', 'declared_weight_kg' => 33.4], ['actor' => 'admin', 'club_id' => $club]);
        $this->assertSame('-34', RegistrationService::find($id)['weight_label']);
        $this->assertDomainError(fn () => RegistrationService::register($cid, $base + ['first_name' => 'B'], ['actor' => 'admin', 'club_id' => $club]), 'poids');
        SettingsService::set($cid, 'weight_input', 'category');
        $w = (int) Database::value('SELECT id FROM weight_categories WHERE competition_id = ? AND sex = "M" AND label = "-42"', [$cid]);
        $id2 = RegistrationService::register($cid, $base + ['first_name' => 'C', 'weight_category_id' => $w], ['actor' => 'admin', 'club_id' => $club]);
        $r = RegistrationService::find($id2);
        $this->assertSame('-42', $r['weight_label']);
        $this->assertSame(1, (int) $r['weight_declared_by_category']);
        $wf = (int) Database::value('SELECT id FROM weight_categories WHERE competition_id = ? AND sex = "F" LIMIT 1', [$cid]);
        $this->assertDomainError(fn () => RegistrationService::register($cid, $base + ['first_name' => 'D', 'weight_category_id' => $wf], ['actor' => 'admin', 'club_id' => $club]), 'ne correspond pas');
    }

    public function testQuotasAreEnforced(): void
    {
        $this->admin();
        $cid = $this->competition(['max_athletes' => 2]);
        $this->athletes($cid, 2, 'M', 10, 30, null, false);
        $this->assertDomainError(fn () => $this->athletes($cid, 1, 'M', 10, 40, null, false), 'maximal');
    }

    public function testCsvImportReportsInvalidLines(): void
    {
        $this->admin();
        $cid = $this->competition();
        $club = $this->club();
        $csv = "nom;prenom;sexe;date_naissance;poids_declare\nMARTIN;Paul;M;15/06/" . ((int) date('Y') - 10) . ";30,5\n;SansNom;F;01/01/2015;30\nDUPUIS;Ana;F;01/01/1950;40\n";
        $res = RegistrationService::importCsv($cid, $csv, ['actor' => 'club', 'club_id' => $club]);
        $this->assertSame(1, $res['created']);
        $this->assertCount(2, $res['errors']);
        $export = RegistrationService::exportCsv($cid, $club);
        $this->assertStringContainsString('MARTIN', $export);
    }

    public function testAttendanceIsRecordedWithHistoryAndAudit(): void
    {
        $admin = $this->admin();
        $cid = $this->competition();
        [$rid] = $this->athletes($cid, 1, 'M', 10, 30, null, false);
        AttendanceService::mark($rid, 'to_check', null, 'Licence');
        $r = AttendanceService::mark($rid, 'present', 31.2);
        $this->assertSame('present', $r['attendance_status']);
        $this->assertSame($admin, (int) $r['attendance_by']);
        $this->assertNotNull($r['attendance_at']);
        $this->assertCount(2, AttendanceService::history($rid));
        $this->assertSame(2, (int) Database::value("SELECT COUNT(*) FROM audit_logs WHERE action = 'attendance.mark' AND entity_id = ?", [(string) $rid]));
        $this->assertDomainError(fn () => AttendanceService::mark($rid, 'nimporte'), 'invalide');
    }
}
