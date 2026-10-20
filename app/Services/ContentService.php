<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Contenus éditables du site vitrine et du programme : réglages du site (clé/valeur), sections de texte,
 * listes ordonnées, partenaires, historique des éditions. Tout est modifiable depuis Admin > Site vitrine.
 */
final class ContentService
{
    /** Réglages du site (coordonnées du club, liens, mentions). */
    public const SITE_KEYS = [
        'club_name' => 'Nom du club', 'club_full_name' => 'Nom complet', 'club_tagline' => 'Accroche',
        'club_founded' => 'Année de création', 'club_address' => 'Adresse du dojo', 'club_phone' => 'Téléphone',
        'club_phone_2' => 'Téléphone (2)', 'club_email' => 'E-mail', 'club_facebook' => 'Facebook (URL)', 'club_instagram' => 'Instagram (URL)',
        'club_licensees' => 'Licenciés (fin de saison)', 'club_black_belts' => 'Ceintures noires', 'club_stats_season' => 'Saison des chiffres',
        'club_presentation' => 'Présentation (accueil)', 'home_cta_title' => 'Titre encart clubs', 'home_cta_text' => 'Texte encart clubs',
        'legal_notice' => 'Mentions légales', 'privacy_notice' => 'Confidentialité',
    ];

    /** Pages et sections de texte attendues (créées par le contenu initial, ajout libre possible). */
    public const PAGES = [
        'competition' => 'Programme de la compétition',
        'club' => 'Notre club',
        'taiso' => 'Taïso',
        'home' => 'Accueil',
    ];

    /** Listes éditables : libellés des champs pour l'interface d'administration. */
    public const LISTS = [
        'schedule' => ['label' => 'Horaires prévisionnels', 'scope' => 'competition', 'title' => 'Heure', 'subtitle' => 'Catégories concernées', 'body' => 'Déroulé', 'group' => 'Bloc (ex. Minimes & Cadets)', 'highlight' => 'Début des combats'],
        'access_rules' => ['label' => 'Plateau réservé à…', 'scope' => 'competition', 'title' => 'Personnes autorisées'],
        'lottery_prizes' => ['label' => 'Lots de la loterie', 'scope' => 'competition', 'title' => 'Lot'],
        'day_steps' => ['label' => 'Déroulement de la journée (étapes)', 'scope' => 'competition', 'title' => 'Titre', 'body' => 'Texte', 'image' => 'Illustration'],
        'club_staff' => ['label' => 'Staff du club', 'scope' => 'site', 'title' => 'Nom', 'subtitle' => 'Fonction', 'group' => 'Groupe (Comité directeur, Professeurs)'],
        'club_results' => ['label' => 'Résultats de la saison', 'scope' => 'site', 'title' => 'Judoka', 'body' => 'Résultats', 'group' => 'Saison'],
        'club_alumni' => ['label' => 'Formés au club, au haut niveau', 'scope' => 'site', 'title' => 'Nom', 'body' => 'Parcours', 'link' => 'Lien (club actuel)', 'image' => 'Logo du club'],
        'course_schedule' => ['label' => 'Horaires des cours', 'scope' => 'site', 'title' => 'Catégorie / années', 'body' => 'Créneaux', 'group' => 'Saison'],
        'taiso_infos' => ['label' => 'Taïso — infos pratiques', 'scope' => 'site', 'title' => 'Libellé', 'subtitle' => 'Valeur'],
        'taiso_benefits' => ['label' => 'Taïso — ce que l\'on travaille', 'scope' => 'site', 'title' => 'Bienfait'],
        'dojo_photos' => ['label' => 'Photos du dojo', 'scope' => 'site', 'title' => 'Légende', 'image' => 'Photo'],
    ];

    public const PARTNER_TIERS = [
        'institutional' => 'Partenaires institutionnels',
        'federal' => 'Partenaires fédéraux',
        'event' => 'Partenaire évènement et loterie',
        'util_member' => 'Membres de l\'UTIL (lots de la loterie)',
        'patron' => 'Autres mécènes',
        'supplier' => 'Fournisseurs',
        'partner' => 'Partenaires',
    ];

    private static array $site = [];

    public static function site(string $key, string $default = ''): string
    {
        if (!self::$site) {
            self::$site = array_column(Database::all('SELECT content_key, content FROM site_contents'), 'content', 'content_key');
        }
        return (string) (self::$site[$key] ?? $default);
    }

    public static function setSite(string $key, string $value): void
    {
        $old = self::site($key);
        Database::run(
            'INSERT INTO site_contents (content_key, content, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content), updated_by = VALUES(updated_by)',
            [$key, $value, Auth::id()]
        );
        if ($old !== $value) {
            AuditLogger::log('content.site', 'site_content', $key, ['value' => mb_substr($old, 0, 200)], ['value' => mb_substr($value, 0, 200)]);
        }
        self::$site = [];
    }

    // --- Sections -------------------------------------------------------------

    public static function sections(?int $competitionId, string $page, bool $publishedOnly = true): array
    {
        return Database::all(
            'SELECT * FROM content_sections WHERE competition_id <=> ? AND page_key = ?' . ($publishedOnly ? ' AND is_published = 1' : '') . ' ORDER BY sort_order, id',
            [$competitionId, $page]
        );
    }

    /** @return array<string, array> sections indexées par clé */
    public static function sectionMap(?int $competitionId, string $page): array
    {
        return array_column(self::sections($competitionId, $page), null, 'section_key');
    }

    public static function section(int $id): array
    {
        $s = Database::one('SELECT * FROM content_sections WHERE id = ?', [$id]);
        if (!$s) {
            throw new HttpException(404, 'Section introuvable.');
        }
        return $s;
    }

    public static function saveSection(?int $id, array $data): int
    {
        $row = [
            'title' => mb_substr(trim((string) ($data['title'] ?? '')), 0, 190) ?: null,
            'body' => (string) ($data['body'] ?? ''),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_published' => !empty($data['is_published']) ? 1 : 0,
            'updated_by' => Auth::id(),
        ];
        if (array_key_exists('image_document_id', $data)) {
            $row['image_document_id'] = $data['image_document_id'] ?: null;
        }
        if ($id) {
            $old = self::section($id);
            if ($old['competition_id']) {
                CompetitionService::assertWritable((int) $old['competition_id']);
            }
            Database::update('content_sections', $row, 'id = :id', ['id' => $id]);
            AuditLogger::log('content.section.update', 'content_section', $id, ['title' => $old['title']], ['title' => $row['title']], null, $old['competition_id'] === null ? null : (int) $old['competition_id']);
            return $id;
        }
        $key = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) ($data['section_key'] ?? ''))) ?: 'section_' . bin2hex(random_bytes(3));
        $row += [
            'competition_id' => $data['competition_id'] ?? null,
            'page_key' => (string) $data['page_key'],
            'section_key' => $key,
        ];
        $id = Database::insert('content_sections', $row);
        AuditLogger::log('content.section.create', 'content_section', $id, null, ['page' => $row['page_key'], 'key' => $key], null, $row['competition_id']);
        return $id;
    }

    public static function deleteSection(int $id): void
    {
        $s = self::section($id);
        Database::run('DELETE FROM content_sections WHERE id = ?', [$id]);
        AuditLogger::log('content.section.delete', 'content_section', $id, ['title' => $s['title'], 'key' => $s['section_key']], null, null, $s['competition_id'] === null ? null : (int) $s['competition_id']);
    }

    // --- Listes ---------------------------------------------------------------

    public static function items(?int $competitionId, string $list, bool $publishedOnly = true): array
    {
        return Database::all(
            'SELECT * FROM content_items WHERE competition_id <=> ? AND list_key = ?' . ($publishedOnly ? ' AND is_published = 1' : '') . ' ORDER BY sort_order, id',
            [$competitionId, $list]
        );
    }

    public static function item(int $id): array
    {
        $i = Database::one('SELECT * FROM content_items WHERE id = ?', [$id]);
        if (!$i) {
            throw new HttpException(404, 'Élément introuvable.');
        }
        return $i;
    }

    public static function saveItem(?int $id, array $data): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new DomainError('Le premier champ est obligatoire.');
        }
        $link = trim((string) ($data['link_url'] ?? ''));
        if ($link !== '' && !preg_match('#^(https?://|mailto:|tel:|/)#i', $link)) {
            throw new DomainError('Le lien doit commencer par https://, mailto:, tel: ou /.');
        }
        $row = [
            'title' => mb_substr($title, 0, 255),
            'subtitle' => mb_substr(trim((string) ($data['subtitle'] ?? '')), 0, 255) ?: null,
            'body' => trim((string) ($data['body'] ?? '')) ?: null,
            'group_label' => mb_substr(trim((string) ($data['group_label'] ?? '')), 0, 120) ?: null,
            'link_url' => $link ?: null,
            'is_highlight' => !empty($data['is_highlight']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_published' => !array_key_exists('is_published', $data) || !empty($data['is_published']) ? 1 : 0,
        ];
        if (array_key_exists('image_document_id', $data)) {
            $row['image_document_id'] = $data['image_document_id'] ?: null;
        }
        if ($id) {
            $old = self::item($id);
            if ($old['competition_id']) {
                CompetitionService::assertWritable((int) $old['competition_id']);
            }
            Database::update('content_items', $row, 'id = :id', ['id' => $id]);
            AuditLogger::log('content.item.update', 'content_item', $id, ['title' => $old['title']], ['title' => $row['title']], null, $old['competition_id'] === null ? null : (int) $old['competition_id']);
            return $id;
        }
        if (!isset(self::LISTS[$data['list_key'] ?? ''])) {
            throw new DomainError('Liste inconnue.');
        }
        $row['competition_id'] = $data['competition_id'] ?? null;
        $row['list_key'] = $data['list_key'];
        if (!isset($data['sort_order'])) {
            $row['sort_order'] = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM content_items WHERE competition_id <=> ? AND list_key = ?', [$row['competition_id'], $row['list_key']]);
        }
        $id = Database::insert('content_items', $row);
        AuditLogger::log('content.item.create', 'content_item', $id, null, ['list' => $row['list_key'], 'title' => $row['title']], null, $row['competition_id']);
        return $id;
    }

    public static function deleteItem(int $id): void
    {
        $i = self::item($id);
        Database::run('DELETE FROM content_items WHERE id = ?', [$id]);
        AuditLogger::log('content.item.delete', 'content_item', $id, ['title' => $i['title'], 'list' => $i['list_key']], null, null, $i['competition_id'] === null ? null : (int) $i['competition_id']);
    }

    public static function moveItem(int $id, int $direction): void
    {
        $i = self::item($id);
        $items = self::items($i['competition_id'] === null ? null : (int) $i['competition_id'], $i['list_key'], false);
        $ids = array_column($items, 'id');
        $pos = array_search($id, $ids);
        $swap = $pos + ($direction < 0 ? -1 : 1);
        if ($pos === false || !isset($ids[$swap])) {
            return;
        }
        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
        foreach ($ids as $k => $itemId) {
            Database::run('UPDATE content_items SET sort_order = ? WHERE id = ?', [($k + 1) * 10, $itemId]);
        }
    }

    // --- Partenaires ------------------------------------------------------------

    public static function partners(?int $competitionId = null, ?string $tier = null, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM partners WHERE (competition_id IS NULL' . ($competitionId ? ' OR competition_id = ' . (int) $competitionId : '') . ')';
        $p = [];
        if ($tier !== null) {
            $sql .= ' AND tier = ?';
            $p[] = $tier;
        }
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        return Database::all($sql . ' ORDER BY FIELD(tier, "institutional", "federal", "event", "util_member", "patron", "supplier", "partner"), sort_order, name', $p);
    }

    public static function partnersByTier(?int $competitionId = null): array
    {
        $out = [];
        foreach (self::partners($competitionId) as $p) {
            $out[$p['tier']][] = $p;
        }
        return $out;
    }

    // --- Historique ------------------------------------------------------------

    public static function history(): array
    {
        $rows = Database::all('SELECT * FROM competition_history ORDER BY sort_order, season_label');
        foreach ($rows as &$r) {
            $r['categories'] = json_decode((string) $r['category_counts'], true) ?: [];
        }
        return $rows;
    }

    /** Libellés de catégories présents dans l'historique (lignes du tableau), dans l'ordre d'apparition. */
    public static function historyCategories(array $rows): array
    {
        $labels = [];
        foreach ($rows as $r) {
            foreach ($r['categories'] as $c) {
                if (!in_array($c['label'], $labels, true)) {
                    $labels[] = $c['label'];
                }
            }
        }
        return $labels;
    }

    /** Statistiques d'une compétition gérée dans l'application, pour alimenter l'historique. */
    public static function statsFromCompetition(int $competitionId): array
    {
        $cats = Database::all(
            "SELECT ac.name, COUNT(r.id) AS n FROM age_categories ac
             LEFT JOIN registrations r ON r.age_category_id = ac.id AND r.status = 'confirmed' AND r.attendance_status = 'present'
             WHERE ac.competition_id = ? GROUP BY ac.id ORDER BY ac.sort_order",
            [$competitionId]
        );
        return [
            'clubs' => (int) Database::value("SELECT COUNT(DISTINCT club_id) FROM registrations WHERE competition_id = ? AND status = 'confirmed' AND attendance_status = 'present'", [$competitionId]),
            'categories' => array_map(fn ($c) => ['label' => $c['name'], 'count' => (int) $c['n']], $cats),
            'total' => array_sum(array_column($cats, 'n')),
            'bouts' => (int) Database::value("SELECT COUNT(*) FROM bouts WHERE competition_id = ? AND status = 'validated'", [$competitionId]),
        ];
    }
}
