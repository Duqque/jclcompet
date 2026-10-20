<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLogger;
use App\Services\CompetitionService;
use App\Services\ContentService;
use App\Services\DocumentService;
use App\Services\DomainError;

/** Gestion de tous les contenus éditables : site vitrine (club, Taïso, accueil) et programme de chaque compétition. */
final class ContentController extends AdminController
{
    public const TABS = ['reglages' => 'Coordonnées et textes', 'pages' => 'Pages (club, Taïso, accueil)', 'listes' => 'Listes', 'partenaires' => 'Partenaires', 'historique' => 'Historique', 'documents' => 'Documents et images'];

    public function index(Request $r): Response
    {
        $tab = array_key_exists($r->query['onglet'] ?? '', self::TABS) ? $r->query['onglet'] : 'reglages';
        $data = ['title' => 'Site vitrine', 'tab' => $tab, 'cid' => null];
        if ($tab === 'pages') {
            $page = array_key_exists($r->query['page'] ?? '', ContentService::PAGES) && ($r->query['page'] ?? '') !== 'competition' ? $r->query['page'] : 'club';
            $data += ['page' => $page, 'sections' => ContentService::sections(null, $page, false)];
        }
        if ($tab === 'listes') {
            $lists = array_filter(ContentService::LISTS, fn ($l) => $l['scope'] === 'site');
            $list = array_key_exists($r->query['liste'] ?? '', $lists) ? $r->query['liste'] : array_key_first($lists);
            $data += ['lists' => $lists, 'list' => $list, 'items' => ContentService::items(null, $list, false)];
        }
        if ($tab === 'partenaires') {
            $data['partners'] = Database::all('SELECT p.*, c.name AS competition_name FROM partners p LEFT JOIN competitions c ON c.id = p.competition_id ORDER BY FIELD(p.tier, "institutional", "federal", "event", "util_member", "patron", "supplier", "partner"), p.sort_order, p.name');
            $data['competitions'] = Database::all('SELECT id, name, edition_label FROM competitions ORDER BY event_date DESC');
        }
        if ($tab === 'historique') {
            $data['history'] = ContentService::history();
            $data['competitions'] = Database::all("SELECT id, name, edition_label FROM competitions WHERE status IN ('finished','archived','in_progress') ORDER BY event_date DESC");
        }
        if ($tab === 'documents') {
            $data['documents'] = Database::all('SELECT d.*, c.name AS competition_name FROM documents d LEFT JOIN competitions c ON c.id = d.competition_id ORDER BY d.id DESC LIMIT 300');
        }
        return $this->admin('content/site', $data);
    }

    public function competitionContent(Request $r): Response
    {
        $c = $this->competition($r);
        $cid = (int) $c['id'];
        $lists = array_filter(ContentService::LISTS, fn ($l) => $l['scope'] === 'competition');
        $items = [];
        foreach (array_keys($lists) as $k) {
            $items[$k] = ContentService::items($cid, $k, false);
        }
        return $this->admin('content/competition', [
            'title' => 'Programme (contenus)', 'competition' => $c, 'c' => $c, 'cid' => $cid,
            'sections' => ContentService::sections($cid, 'competition', false), 'lists' => $lists, 'listItems' => $items,
        ]);
    }

    private function upload(Request $r, string $field, string $type, string $title, ?int $cid): ?int
    {
        $f = $r->file($field);
        return $f ? DocumentService::storeUpload($f, $type, $title, $cid) : null;
    }

    public function action(Request $r): Response
    {
        $cid = $r->int('competition_id');
        if ($cid) {
            CompetitionService::assertWritable($cid);
        }
        $op = $r->str('op');
        switch ($op) {
            case 'site_save':
                foreach (array_keys(ContentService::SITE_KEYS) as $k) {
                    if (array_key_exists($k, $r->post)) {
                        ContentService::setSite($k, trim((string) $r->post[$k]));
                    }
                }
                return $this->back($r, 'Coordonnées et textes enregistrés.');
            case 'section_save':
                $data = $r->post;
                $data['competition_id'] = $cid;
                if ($img = $this->upload($r, 'image', 'visual', (string) ($r->post['title'] ?? 'Image'), $cid)) {
                    $data['image_document_id'] = $img;
                } elseif (!empty($r->post['remove_image'])) {
                    $data['image_document_id'] = null;
                }
                if (empty($data['page_key']) && !$r->int('section_id')) {
                    throw new DomainError('Page inconnue.');
                }
                ContentService::saveSection($r->int('section_id'), $data);
                return $this->back($r, 'Section enregistrée.');
            case 'section_delete':
                ContentService::deleteSection((int) $r->int('section_id'));
                return $this->back($r, 'Section supprimée.');
            case 'item_save':
                $data = $r->post;
                $data['competition_id'] = $cid;
                if ($img = $this->upload($r, 'image', 'photo', (string) ($r->post['title'] ?? 'Image'), $cid)) {
                    $data['image_document_id'] = $img;
                } elseif (!empty($r->post['remove_image'])) {
                    $data['image_document_id'] = null;
                }
                ContentService::saveItem($r->int('item_id'), $data);
                return $this->back($r, 'Élément enregistré.');
            case 'item_delete':
                ContentService::deleteItem((int) $r->int('item_id'));
                return $this->back($r, 'Élément supprimé.');
            case 'item_up':
            case 'item_down':
                ContentService::moveItem((int) $r->int('item_id'), $op === 'item_up' ? -1 : 1);
                return $this->back($r, 'Ordre modifié.');
            case 'partner_save':
                $name = $r->str('name');
                if ($name === '') {
                    throw new DomainError('Nom du partenaire obligatoire.');
                }
                $url = $r->str('url');
                if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                    throw new DomainError('Le lien doit commencer par https://');
                }
                $row = [
                    'name' => mb_substr($name, 0, 120), 'url' => $url ?: null, 'description' => $r->str('description') ?: null,
                    'tier' => array_key_exists($r->str('tier'), ContentService::PARTNER_TIERS) ? $r->str('tier') : 'partner',
                    'competition_id' => $r->int('partner_competition_id'), 'sort_order' => (int) $r->int('sort_order', 0), 'is_active' => $r->bool('is_active') ? 1 : 0,
                ];
                if ($logo = $this->upload($r, 'logo', 'partner_logo', $name, null)) {
                    $row['logo_document_id'] = $logo;
                }
                if ($ad = $this->upload($r, 'ad', 'ad', $name, null)) {
                    $row['ad_document_id'] = $ad;
                }
                if ($r->bool('remove_logo')) {
                    $row['logo_document_id'] = null;
                }
                if ($r->bool('remove_ad')) {
                    $row['ad_document_id'] = null;
                }
                if ($id = $r->int('partner_id')) {
                    Database::update('partners', $row, 'id = :id', ['id' => $id]);
                } else {
                    $id = Database::insert('partners', $row);
                }
                AuditLogger::log('content.partner.save', 'partner', $id, null, ['name' => $row['name'], 'tier' => $row['tier']]);
                return $this->back($r, 'Partenaire enregistré.');
            case 'partner_delete':
                $id = (int) $r->int('partner_id');
                Database::run('DELETE FROM partners WHERE id = ?', [$id]);
                AuditLogger::log('content.partner.delete', 'partner', $id);
                return $this->back($r, 'Partenaire supprimé.');
            case 'history_save':
                $cats = [];
                foreach (preg_split('/\R/', $r->str('category_counts')) as $line) {
                    if (preg_match('/^(.+?)\s*[:=]\s*(-|\d+)\s*$/u', trim($line), $m)) {
                        $cats[] = ['label' => trim($m[1]), 'count' => $m[2] === '-' ? null : (int) $m[2]];
                    }
                }
                $season = $r->str('season_label');
                if (!preg_match('/^\d{4}\s*[-–]\s*\d{4}$/', $season)) {
                    throw new DomainError('Saison au format 2026-2027.');
                }
                $row = [
                    'season_label' => preg_replace('/\s*[-–]\s*/', '-', $season), 'clubs_count' => $r->int('clubs_count'),
                    'category_counts' => json_encode($cats, JSON_UNESCAPED_UNICODE),
                    'total_participants' => $r->int('total_participants') ?? (array_sum(array_map(fn ($c) => (int) $c['count'], $cats)) ?: null),
                    'bouts_count' => $r->int('bouts_count'), 'sort_order' => (int) $r->int('sort_order', 0), 'notes' => $r->str('notes') ?: null,
                ];
                if ($id = $r->int('history_id')) {
                    Database::update('competition_history', $row, 'id = :id', ['id' => $id]);
                } else {
                    $id = Database::insert('competition_history', $row);
                }
                AuditLogger::log('content.history.save', 'competition_history', $id, null, $row);
                return $this->back($r, 'Saison enregistrée dans l\'historique.');
            case 'history_delete':
                Database::run('DELETE FROM competition_history WHERE id = ?', [(int) $r->int('history_id')]);
                AuditLogger::log('content.history.delete', 'competition_history', (int) $r->int('history_id'));
                return $this->back($r, 'Saison supprimée.');
            case 'history_from_competition':
                $comp = CompetitionService::find((int) $r->int('source_competition_id'));
                $stats = ContentService::statsFromCompetition((int) $comp['id']);
                $year = (int) substr($comp['event_date'], 0, 4);
                $month = (int) substr($comp['event_date'], 5, 2);
                $season = $month >= 8 ? $year . '-' . ($year + 1) : ($year - 1) . '-' . $year;
                Database::run(
                    'INSERT INTO competition_history (competition_id, season_label, clubs_count, category_counts, total_participants, bouts_count, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE competition_id = VALUES(competition_id), clubs_count = VALUES(clubs_count),
                     category_counts = VALUES(category_counts), total_participants = VALUES(total_participants), bouts_count = VALUES(bouts_count)',
                    [$comp['id'], $season, $stats['clubs'], json_encode($stats['categories'], JSON_UNESCAPED_UNICODE), $stats['total'], $stats['bouts'], 1000 + $year]
                );
                AuditLogger::log('content.history.from_competition', 'competition', (int) $comp['id'], null, $stats);
                return $this->back($r, "Saison $season ajoutée à l'historique depuis les données de la compétition.");
            case 'document_upload':
                $f = $r->file('file') ?? throw new DomainError('Choisissez un fichier.');
                DocumentService::storeUpload($f, $r->str('type') ?: 'other', $r->str('title') ?: (string) $f['name'], $r->int('doc_competition_id'), !$r->bool('private'));
                return $this->back($r, 'Document ajouté.');
            case 'document_delete':
                $id = (int) $r->int('document_id');
                $used = Database::value(
                    'SELECT 1 FROM competitions WHERE cover_document_id = ? OR regulation_document_id = ? UNION SELECT 1 FROM partners WHERE logo_document_id = ? OR ad_document_id = ?
                     UNION SELECT 1 FROM content_sections WHERE image_document_id = ? UNION SELECT 1 FROM content_items WHERE image_document_id = ? UNION SELECT 1 FROM clubs WHERE logo_path = ? LIMIT 1',
                    [$id, $id, $id, $id, $id, $id, 'media/' . $id]
                );
                if ($used) {
                    throw new DomainError('Ce document est utilisé (compétition, partenaire, section, liste ou logo de club) : retirez-le d\'abord.');
                }
                DocumentService::delete($id);
                return $this->back($r, 'Document supprimé.');
        }
        throw new DomainError('Action inconnue.');
    }
}
