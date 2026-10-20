<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuditLogger;
use App\Services\CategoryService;
use App\Services\CompetitionService;
use App\Services\DomainError;

final class CategoryController extends AdminController
{
    public function index(Request $r): Response
    {
        $c = $this->competition($r);
        $cid = (int) $c['id'];
        $weights = [];
        foreach (CategoryService::weightCategories($cid) as $w) {
            $weights[$w['age_category_id']][$w['sex']][] = $w;
        }
        $open = [];
        foreach (Database::all('SELECT * FROM competition_categories WHERE competition_id = ?', [$cid]) as $cc) {
            $open[$cc['age_category_id']][$cc['sex']] = $cc;
        }
        $counts = [];
        foreach (Database::all("SELECT r.age_category_id, a.sex, COUNT(*) n FROM registrations r JOIN athletes a ON a.id = r.athlete_id WHERE r.competition_id = ? AND r.status IN ('pending','confirmed') GROUP BY r.age_category_id, a.sex", [$cid]) as $x) {
            $counts[$x['age_category_id']][$x['sex']] = (int) $x['n'];
        }
        return $this->admin('categories', [
            'title' => 'Catégories', 'competition' => $c, 'c' => $c, 'ages' => CategoryService::ageCategories($cid),
            'weights' => $weights, 'open' => $open, 'counts' => $counts, 'refYear' => CategoryService::referenceYear($c),
        ]);
    }

    public function save(Request $r): Response
    {
        $c = $this->competition($r);
        CompetitionService::assertWritable($c);
        $cid = (int) $c['id'];
        $op = $r->str('op');
        $msg = 'Catégories mises à jour.';
        Database::transaction(function () use ($r, $cid, $op, &$msg) {
            switch ($op) {
                case 'age_save':
                    $d = Validator::validate($r->post, [
                        'code' => 'required|maxlen:20', 'name' => 'required|maxlen:80', 'name_m' => 'nullable|maxlen:80', 'name_f' => 'nullable|maxlen:80',
                        'birth_year_min' => 'required|int|min:1900|max:2100', 'birth_year_max' => 'required|int|min:1900|max:2100',
                        'format' => 'required|in:pools,animation', 'upgrade_to_id' => 'nullable|int', 'max_participants' => 'nullable|int|min:1', 'sort_order' => 'nullable|int',
                    ], ['code' => 'Code', 'name' => 'Nom', 'birth_year_min' => 'Année min.', 'birth_year_max' => 'Année max.']);
                    if ($d['birth_year_min'] > $d['birth_year_max']) {
                        throw new DomainError("L'année minimale doit être inférieure ou égale à l'année maximale.");
                    }
                    $id = $r->int('age_id');
                    $overlap = Database::value(
                        'SELECT name FROM age_categories WHERE competition_id = ? AND id <> ? AND birth_year_min <= ? AND birth_year_max >= ? LIMIT 1',
                        [$cid, $id ?? 0, $d['birth_year_max'], $d['birth_year_min']]
                    );
                    if ($overlap) {
                        throw new DomainError("Ces années de naissance chevauchent la catégorie « $overlap ».");
                    }
                    if ($d['upgrade_to_id'] && !Database::value('SELECT 1 FROM age_categories WHERE id = ? AND competition_id = ?', [$d['upgrade_to_id'], $cid])) {
                        $d['upgrade_to_id'] = null;
                    }
                    $d['sort_order'] ??= 0;
                    if ($id) {
                        Database::update('age_categories', $d, 'id = :id AND competition_id = :cid', ['id' => $id, 'cid' => $cid]);
                        AuditLogger::log('category.age.update', 'age_category', $id, null, $d, null, $cid);
                    } else {
                        $id = Database::insert('age_categories', $d + ['competition_id' => $cid]);
                        foreach (['M', 'F'] as $sex) {
                            Database::insert('competition_categories', ['competition_id' => $cid, 'age_category_id' => $id, 'sex' => $sex]);
                        }
                        AuditLogger::log('category.age.create', 'age_category', $id, null, $d, null, $cid);
                    }
                    break;
                case 'age_delete':
                    $id = (int) $r->int('age_id');
                    if (Database::value('SELECT 1 FROM registrations WHERE age_category_id = ? LIMIT 1', [$id])) {
                        throw new DomainError('Des athlètes sont inscrits dans cette catégorie : retirez-les ou modifiez leur inscription avant de la supprimer.');
                    }
                    Database::run('DELETE FROM age_categories WHERE id = ? AND competition_id = ?', [$id, $cid]);
                    AuditLogger::log('category.age.delete', 'age_category', $id, null, null, null, $cid);
                    break;
                case 'table_save':
                    foreach ((array) ($r->post['cc'] ?? []) as $ccId => $vals) {
                        Database::run(
                            'UPDATE competition_categories SET is_open = ?, merge_group = ?, max_participants = ? WHERE id = ? AND competition_id = ?',
                            [!empty($vals['is_open']) ? 1 : 0, trim((string) ($vals['merge_group'] ?? '')) ?: null, ($vals['max_participants'] ?? '') !== '' ? (int) $vals['max_participants'] : null, (int) $ccId, $cid]
                        );
                    }
                    AuditLogger::log('category.tables.update', 'competition', $cid, null, null, null, $cid);
                    break;
                case 'weights_save':
                    $ageId = (int) $r->int('age_id');
                    $sex = $r->str('sex') === 'F' ? 'F' : 'M';
                    $raw = preg_split('/[\s,;]+/', $r->str('limits'), -1, PREG_SPLIT_NO_EMPTY);
                    $limits = [];
                    $open = false;
                    foreach ($raw as $x) {
                        $x = str_replace(['kg', 'Kg', 'KG', '−'], ['', '', '', '-'], $x);
                        if (str_starts_with($x, '+')) {
                            $open = true;
                            continue;
                        }
                        $v = (float) str_replace(',', '.', ltrim($x, '-'));
                        if ($v > 0) {
                            $limits[] = $v;
                        }
                    }
                    sort($limits);
                    $limits = array_values(array_unique($limits));
                    $used = Database::value("SELECT 1 FROM registrations r JOIN athletes a ON a.id = r.athlete_id WHERE r.age_category_id = ? AND a.sex = ? AND r.weight_category_id IS NOT NULL AND r.status IN ('pending','confirmed') LIMIT 1", [$ageId, $sex]);
                    if ($used) {
                        throw new DomainError('Des athlètes sont déjà inscrits dans ces catégories de poids : modifiez-les une par une (ajout possible en fin de liste uniquement après retrait des inscriptions).');
                    }
                    Database::run('DELETE FROM weight_categories WHERE age_category_id = ? AND sex = ?', [$ageId, $sex]);
                    if ($limits) {
                        if ($open) {
                            $limits[] = null;
                        }
                        CategoryService::createWeights($cid, $ageId, $sex, $limits);
                    }
                    AuditLogger::log('category.weights.update', 'age_category', $ageId, null, ['sex' => $sex, 'limits' => $limits], null, $cid);
                    $msg = 'Catégories de poids enregistrées.';
                    break;
                case 'weight_quota':
                    foreach ((array) ($r->post['wq'] ?? []) as $wid => $q) {
                        Database::run('UPDATE weight_categories SET max_participants = ? WHERE id = ? AND competition_id = ?', [$q !== '' ? (int) $q : null, (int) $wid, $cid]);
                    }
                    break;
                default:
                    throw new DomainError('Action inconnue.');
            }
        });
        return $this->back($r, $msg);
    }
}
