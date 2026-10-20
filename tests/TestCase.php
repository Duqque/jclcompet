<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Auth;
use App\Core\Database;
use App\Services\CompetitionService;
use App\Services\SettingsService;
use PHPUnit\Framework\TestCase as Base;

abstract class TestCase extends Base
{
    private static int $seq = 0;

    protected function setUp(): void
    {
        $_SESSION = [];
        SettingsService::flush();
        Database::pdo()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (Database::pdo()->inTransaction()) {
            Database::pdo()->rollBack();
        }
        Auth::actAs(null);
        SettingsService::flush();
    }

    protected function user(string $role, ?int $clubId = null, string $password = 'MotDePasse2026!'): int
    {
        self::$seq++;
        $id = Database::insert('users', [
            'email' => "$role" . self::$seq . '@test.local', 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => ucfirst($role) . ' ' . self::$seq, 'role_id' => (int) Database::value('SELECT id FROM roles WHERE code = ?', [$role]),
        ]);
        if ($clubId) {
            Database::insert('club_users', ['club_id' => $clubId, 'user_id' => $id]);
        }
        return $id;
    }

    protected function admin(): int
    {
        $id = $this->user('admin');
        Auth::actAs($id);
        return $id;
    }

    protected function club(string $name = 'Club'): int
    {
        self::$seq++;
        return Database::insert('clubs', ['name' => $name . ' ' . self::$seq, 'city' => 'Ville', 'contact_name' => 'Contact', 'email' => 'c' . self::$seq . '@test.local']);
    }

    /** Compétition aux inscriptions ouvertes, catégories d'exemple (Poussins, Benjamins), poules morphologiques. */
    protected function competition(array $over = [], array $codes = ['U11', 'U13'], bool $weights = false): int
    {
        $year = (int) date('Y');
        $id = CompetitionService::create(array_merge([
            'name' => 'Test ' . (++self::$seq), 'event_date' => date('Y-m-d', strtotime('+10 days')), 'start_time' => '09:00:00',
            'address' => '1 rue du Test', 'city' => 'Testville', 'mat_count' => 2, 'pool_sizes' => '3,4,5',
            'registration_opens_at' => date('Y-m-d H:i:s', strtotime('-5 days')), 'registration_closes_at' => date('Y-m-d H:i:s', strtotime('+5 days')),
            'age_reference_date' => $year . '-12-31',
        ], $over), $codes, $weights);
        Database::run("UPDATE competitions SET status = 'registrations_open' WHERE id = ?", [$id]);
        SettingsService::set($id, 'pool_grouping', 'morphological');
        SettingsService::set($id, 'weight_input', 'weight');
        return $id;
    }

    protected function birthFor(int $age): string
    {
        return ((int) date('Y') - $age) . '-06-15';
    }

    /** Inscrit et pointe présents $n athlètes (âge, sexe, poids croissants). @return int[] ids d'inscription */
    protected function athletes(int $cid, int $n, string $sex = 'M', int $age = 10, float $from = 25.0, ?array $clubs = null, bool $present = true): array
    {
        $clubs ??= [$this->club()];
        $ids = [];
        for ($i = 0; $i < $n; $i++) {
            self::$seq++;
            $ids[] = \App\Services\RegistrationService::register($cid, [
                'last_name' => 'Nom' . self::$seq, 'first_name' => 'Prénom', 'sex' => $sex, 'birth_date' => $this->birthFor($age),
                'declared_weight_kg' => $from + $i * 0.8,
            ], ['actor' => 'admin', 'club_id' => $clubs[$i % count($clubs)]]);
        }
        if ($present) {
            foreach ($ids as $rid) {
                \App\Services\AttendanceService::mark($rid, 'present');
            }
        }
        return $ids;
    }

    protected function assertDomainError(callable $fn, ?string $contains = null, int $status = 422): void
    {
        try {
            $fn();
        } catch (\App\Core\HttpException $e) {
            $this->assertSame($status, $e->status, 'Statut inattendu : ' . $e->getMessage());
            if ($contains !== null) {
                $this->assertStringContainsStringIgnoringCase($contains, $e->getMessage());
            }
            return;
        }
        $this->fail('Une erreur métier était attendue.');
    }
}
