<?php
declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Database;
use App\Services\CategoryService;
use App\Services\CompetitionService;
use App\Services\ContentService;
use App\Services\DocumentService;
use App\Services\SettingsService;

/**
 * Contenu RÉEL issu du « Programme de la Compétition Amicale du Judo Club Leforest » (samedi 26 septembre 2026) :
 * club, Taïso, édition 2026 (horaires, déroulement, catégories, temps de combat, buvette, clubs présents,
 * partenaires, loterie, bénévoles, historique). Tout est ensuite modifiable dans Admin > Site vitrine.
 * Idempotent : ne fait rien si le contenu a déjà été importé (clé site_contents « programme_seed »).
 */
final class ProgrammeSeeder
{
    private string $assets;
    private array $docs = [];

    public function __construct()
    {
        $this->assets = BASE_PATH . '/database/seeds/programme';
    }

    public function run(bool $force = false): ?int
    {
        if (!$force && Database::value("SELECT 1 FROM site_contents WHERE content_key = 'programme_seed'")) {
            return null;
        }
        return Database::transaction(function () {
            $this->siteSettings();
            $this->clubPage();
            $this->taisoPage();
            $this->history();
            $this->partners();
            $competitionId = $this->competition();
            Database::run("INSERT INTO site_contents (content_key, content) VALUES ('programme_seed', ?) ON DUPLICATE KEY UPDATE content = VALUES(content)", [date('c')]);
            return $competitionId;
        });
    }

    private function doc(string $relative, string $type, string $title, ?int $competitionId = null): int
    {
        $key = $relative . '|' . $competitionId;
        if (!isset($this->docs[$key])) {
            $this->docs[$key] = DocumentService::storePath($this->assets . '/' . $relative, basename($relative), $type, $title, $competitionId);
        }
        return $this->docs[$key];
    }

    private function site(string $key, string $value): void
    {
        Database::run('INSERT INTO site_contents (content_key, content) VALUES (?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content)', [$key, $value]);
    }

    private function section(?int $cid, string $page, string $key, ?string $title, string $body, int $order, ?int $image = null): void
    {
        Database::insert('content_sections', [
            'competition_id' => $cid, 'page_key' => $page, 'section_key' => $key, 'title' => $title, 'body' => $body,
            'sort_order' => $order, 'image_document_id' => $image,
        ]);
    }

    private function item(?int $cid, string $list, string $title, array $extra = []): void
    {
        static $orders = [];
        $k = $cid . $list;
        $orders[$k] = ($orders[$k] ?? 0) + 10;
        Database::insert('content_items', array_merge([
            'competition_id' => $cid, 'list_key' => $list, 'title' => $title, 'sort_order' => $orders[$k],
        ], $extra));
    }

    // ---------------------------------------------------------------------
    private function siteSettings(): void
    {
        foreach ([
            'club_name' => 'JC Leforest',
            'club_full_name' => 'Judo Club Leforest',
            'club_tagline' => 'Le judo à Leforest depuis 2000',
            'club_founded' => '2000',
            'club_address' => 'Salle Jean Rostand, rue des Bergeronnettes, 62790 Leforest',
            'club_phone' => '06 09 79 75 83',
            'club_phone_2' => '07 86 47 17 89',
            'club_email' => 'judoclubleforest@yahoo.fr',
            'club_facebook' => 'https://www.facebook.com/jcleforest',
            'club_instagram' => 'https://www.instagram.com/judo_club_leforest',
            'club_licensees' => '144',
            'club_black_belts' => '17',
            'club_stats_season' => '2025-2026',
            'club_presentation' => "Créé en 2000, le Judo Club Leforest accueille judokas et taïsokas de tous âges, de l'École de Judo aux Séniors, à la salle Jean Rostand et ses 198 m² de tatamis. Chaque saison, le club organise sa **Compétition Amicale**, ouverte aux clubs de la région : plus de 450 judokas et une trentaine de clubs y participent.",
            'home_cta_title' => 'Responsables de clubs, inscrivez vos judokas en ligne',
            'home_cta_text' => "Chaque club dispose d'un espace privé pour inscrire ses judokas par catégorie d'âge, de sexe et de poids, retrouver les horaires, suivre les poules et les résultats en direct.",
            'legal_notice' => "**Éditeur** : Judo Club Leforest, association loi 1901 — Salle Jean Rostand, rue des Bergeronnettes, 62790 Leforest — judoclubleforest@yahoo.fr — 06 09 79 75 83.\n\n**Directrice de la publication** : la présidente du club.\n\n**Hébergement** : à compléter (ex. Hostinger International Ltd.).\n\nLes logos des clubs et des partenaires restent la propriété de leurs titulaires.",
            'privacy_notice' => "Les données des judokas (nom, prénom, sexe, date de naissance, club, catégories ; grade et licence facultatifs) servent uniquement à l'organisation des compétitions et à la publication des résultats (nom, prénom, club, catégorie, place). Elles sont saisies par les responsables de clubs et conservées le temps de l'archivage sportif. Pour toute demande (accès, rectification, suppression), contactez le club : judoclubleforest@yahoo.fr.",
        ] as $k => $v) {
            $this->site($k, $v);
        }
    }

    private function clubPage(): void
    {
        $logo = DocumentService::storePath(BASE_PATH . '/public/assets/img/logo-jcl.png', 'logo-jcl.png', 'visual', 'Logo du Judo Club Leforest');
        $this->section(null, 'club', 'identity', 'Notre club', "**Année de création :** 2000\n\n**Le logo, dévoilé en 2017**, réunit le kanji du judo (柔道), les initiales du club et le portrait de Jigoro Kano, fondateur du judo.", 10, $logo);
        $this->section(null, 'club', 'staff_intro', 'Le staff', '', 20);
        $this->section(null, 'club', 'stats', 'Le club en chiffres', 'En fin de saison 2025-2026, le Club comptait **144 licenciés**, dont **17 ceintures noires**.', 30);
        $this->section(null, 'club', 'contact', 'Nous contacter et nous suivre', "Pas encore abonné(e) à nos réseaux sociaux ? Retrouvez-nous sur [Facebook](https://www.facebook.com/jcleforest) et [Instagram](https://www.instagram.com/judo_club_leforest) !", 40);
        $this->section(null, 'club', 'dojo', 'Notre dojo', "**Salle Jean Rostand**\nRue des Bergeronnettes\n62790 LEFOREST\n\n**198 m² de tatamis !**", 50, $this->doc('site/dojo-plan-acces.jpg', 'photo', 'Plan d\'accès au dojo'));
        $this->section(null, 'club', 'results', 'Les résultats de la saison 2025-2026', '**6 judokas au niveau national :**', 60);
        $this->section(null, 'club', 'alumni', 'Formés à Leforest, au très haut niveau', '3 judokas formés au Judo Club Leforest poursuivent aujourd\'hui leur parcours au très haut niveau dans leurs clubs respectifs, chacun à sa manière :', 70);
        $this->section(null, 'club', 'courses', 'Horaires des cours de Judo de la saison 2026-2027', "Il est possible au cas par cas que des enfants puissent faire un autre cours que celui qui les concerne. L'objectif est que chacun d'entre eux s'épanouisse en fonction de sa taille ou de son niveau :\n\n- Les 2016 peuvent rester au cours précédent tout le temps ou une fois sur deux pour s'habituer aux plus grands.\n- Les 2013 « compétiteurs » peuvent s'entraîner avec les adultes.\n- Les 2012 peuvent rester avec les jeunes ados plutôt que de se retrouver avec les adultes.\n\nNous restons à votre écoute si vous rencontrez des soucis liés aux horaires afin de trouver une solution (taille et/ou poids inadapté(s), fratrie, indisponibilité pour les conduites, etc.).", 80);

        foreach ([
            ['Marie-Paule KOWALSKI', 'Présidente', 'Comité directeur'],
            ['Pauline BOURBOTTE', 'Secrétaire', 'Comité directeur'],
            ['Aurélie KOWALSKI', 'Trésorière', 'Comité directeur'],
            ['David KOWALSKI', 'Judo, Diplômé d\'État', 'Professeurs'],
            ['Louis VANDAPEL', 'Taïso', 'Professeurs'],
        ] as [$name, $role, $group]) {
            $this->item(null, 'club_staff', $name, ['subtitle' => $role, 'group_label' => $group]);
        }
        foreach ([
            ['Tom JEUMETZ', 'Coupe de France Individuelle Minimes et Coupe de France par Équipes de Départements Minimes'],
            ['Hugo URBAIN', 'Championnat de France Individuel Espoirs (1^ère^ année Cadets) (8^e^ de finale)'],
            ['Alix CANIPEL', 'Championnat de France Individuel Espoirs (1^ère^ année Cadettes)'],
            ['Telio ENNAS', 'Championnat de France Cadets 2^e^ Division (8^e^ de finale)'],
            ['Amandine PEROUCHET', 'Championnat de France Séniors 3^e^ Division et Championnat de France par Équipe Juniors avec le Hénin Judo-Jujitsu'],
            ['Louis VANDAPEL', '5^e^ du Championnat de France Juniors en Ne-Waza (Judo au sol)'],
        ] as [$name, $body]) {
            $this->item(null, 'club_results', $name, ['body' => $body, 'group_label' => '2025-2026']);
        }
        $this->item(null, 'club_alumni', 'Matthias DERNONCOURT', [
            'body' => 'Vice-Champion de France par Équipes Juniors avec le Judo Club de Chilly-Mazarin Morangis (Essonne).',
            'link_url' => 'https://www.facebook.com/jccmm.judo/', 'image_document_id' => $this->doc('partners/jccmm.jpg', 'club_logo', 'JC Chilly-Mazarin Morangis'),
        ]);
        $this->item(null, 'club_alumni', 'Lioney BEBEY', [
            'body' => "Demi-finaliste de la Judo Pro League 2025-2026 et 3^e^ au Championnat de France 2^e^ Division Séniors avec le Red Star Club Montreuil (Seine-Saint-Denis). Qualifié pour le Championnat de France Séniors 1^ère^ Division fin 2026. Pour rappel, lorsqu'il était encore Leforestois, il avait été Champion de France Espoirs. Et entre-temps, il a également été Vice-Champion de France Juniors avec le Dojo Béglais (Gironde).",
            'link_url' => 'https://www.rscm-judo.fr/', 'image_document_id' => $this->doc('partners/rscm.jpg', 'club_logo', 'Red Star Club Montreuil Judo'),
        ]);
        $this->item(null, 'club_alumni', 'Quentin DUQUENNE', [
            'body' => "En tant qu'entraîneur de l'Équipe Cadet(te)s du Judo Club de Chilly-Mazarin Morangis lors de la dernière saison, il a placé 4 de ses combattant(e)s sur divers podiums nationaux en individuel et son Équipe Cadette a terminé Vice-Championne de France par Équipes, ainsi qu'un 5^e^ aux Championnats d'Europe et un 5^e^ au Festival Olympique de la Jeunesse Européenne il y a 2 ans. Pour la saison 2026-2027, Quentin devient entraîneur des Équipes Cadet(te)s, Juniors et Séniors de la Section Judo du Benfica Lisbonne, l'un des plus gros clubs du Portugal.",
            'link_url' => 'https://www.slbenfica.pt/pt-pt/modalidades/judo', 'image_document_id' => $this->doc('partners/benfica.png', 'club_logo', 'SL Benfica Judo'),
        ]);
        foreach ([
            ['École de Judo F & G 1 et 2 (2023-2022)', 'Lundi : 17h15 – 18h15'],
            ['École de Judo F & G 3 (2021)', 'Lundi : 18h15 – 19h15'],
            ['Mini-poussin(e)s 1 (2020)', "Lundi : 18h15 – 19h15\nMercredi : 16h30 – 17h40"],
            ['Mini-poussin(e)s 2 (2019)', "Mercredi : 16h30 – 17h40\nVendredi : 17h15 – 18h30"],
            ['Poussin(e)s 1 et 2 (2018-2017)', "Mercredi : 16h30 – 17h40\nVendredi : 17h15 – 18h30"],
            ['Benjamin(e)s 1 et 2 (2016-2015)', "Mercredi : 17h40 – 19h00\nVendredi : 18h30 – 19h45"],
            ['Minimes F & G 1 et 2 (2014-2013)', "Mercredi : 17h40 – 19h00\nVendredi : 18h30 – 19h45"],
            ['Cadet(te)s 1, 2 et 3 (2012-2011-2010)', "Mercredi : 19h00 – 20h30\nVendredi : 19h45 – 21h15"],
            ['Juniors F & G 1, 2 et 3 (2009-2008-2007)', "Mercredi : 19h00 – 20h30\nVendredi : 19h45 – 21h15"],
            ['Séniors F & G (2006 et avant)', "Mercredi : 19h00 – 20h30\nVendredi : 19h45 – 21h15"],
        ] as [$cat, $slots]) {
            $this->item(null, 'course_schedule', $cat, ['body' => $slots, 'group_label' => '2026-2027']);
        }
        $this->item(null, 'dojo_photos', 'La salle Jean Rostand', ['image_document_id' => $this->doc('site/dojo-exterieur.jpg', 'photo', 'Salle Jean Rostand')]);
        $this->item(null, 'dojo_photos', '198 m² de tatamis', ['image_document_id' => $this->doc('site/dojo-tatamis.jpg', 'photo', 'Les tatamis du dojo')]);
    }

    private function taisoPage(): void
    {
        $this->section(null, 'taiso', 'hero', 'Nouveauté 2026 : Taïso !', "**Remettez-vous en forme !** Renforcement · Cardio · Mobilité · Bien-être.\n\nTous les lundis de **19h45 à 21h** au dojo du Judo Club Leforest.", 10, $this->doc('site/taiso-affiche.jpg', 'flyer', 'Affiche Taïso'));
        $this->section(null, 'taiso', 'what', 'Le Taïso pour tous !', "**TAÏSO** (体操) en japonais signifie : préparation du corps. Il était utilisé comme discipline de préparation et de récupération au combat. Le Taïso n'est pas réservé aux seuls pratiquants d'arts martiaux. Dans sa version actuelle, il s'adresse à un très large public, hommes et femmes sans limite d'âge, qui n'a pas forcément pratiqué de sport auparavant et qui recherche un loisir axé sur l'activité physique.\n\nLe Taïso se pratique de façon décontractée, dans une tenue permettant des mouvements aisés. Les séances se déroulent sur le tatami, avec ou sans petit matériel (bâtons, ballons, haltères, cerceaux, etc.). Des exercices variés, adaptés à l'âge et aux aptitudes de chacun, permettent d'entretenir et d'améliorer ses propres capacités physiques, en évacuant le stress et en retrouvant une harmonie avec son corps.\n\nCette pratique propose une approche ludique de remise en forme où il n'y a ni chute, ni coup porté, avec des exercices souvent en binômes permettant de travailler graduellement :", 20);
        $this->section(null, 'taiso', 'outro', null, 'La plupart des exercices sont fonctionnels et posturaux. Ils sollicitent les mêmes muscles que ceux que l\'on devrait activer dans la vie courante, pour mieux vivre les moments présents et à venir.', 30);
        foreach ([
            ['Quand', 'Tous les lundis, 19h45 – 21h'], ['Public', 'À partir de 14 ans — tout niveau bienvenu'],
            ['Tarif', '100 € (licence FFJudo 46 € incluse)'], ['Tenue', 'Tenue de sport obligatoire'],
            ['Lieu', 'Dojo, 19 rue des Bergeronnettes, Leforest'], ['À partir du', 'Lundi 14 septembre'],
            ['Plus d\'infos', '06 09 79 75 83 · 07 86 47 17 89'], ['Séance d\'essai', 'Possible !'],
        ] as [$k, $v]) {
            $this->item(null, 'taiso_infos', $k, ['subtitle' => $v]);
        }
        foreach (['L\'équilibre', 'Les capacités psychomotrices', 'La coordination générale des membres', 'Les assouplissements', 'Les étirements', 'L\'entretien cardio-respiratoire', 'L\'amélioration de l\'endurance', 'Le renforcement musculaire'] as $b) {
            $this->item(null, 'taiso_benefits', $b);
        }
    }

    private function history(): void
    {
        $labels = ['École de Judo F & G', 'Mini-poussin(e)s', 'Poussin(e)s', 'Benjamin(e)s', 'Minimes F & G', 'Cadet(te)s'];
        $rows = [
            ['2018-2019', 24, [83, 139, 116, 63, 28, null], 429, null],
            ['2021-2022', 16, [97, 127, 86, 53, 23, null], 386, 504],
            ['2022-2023', 14, [61, 101, 70, 39, 23, null], 294, 408],
            ['2024-2025', 21, [31, 73, 116, 79, 54, 33], 386, 536],
            ['2025-2026', 24, [42, 118, 115, 104, 50, 39], 468, 605],
        ];
        foreach ($rows as $i => [$season, $clubs, $counts, $total, $bouts]) {
            Database::insert('competition_history', [
                'season_label' => $season, 'clubs_count' => $clubs,
                'category_counts' => json_encode(array_map(fn ($l, $c) => ['label' => $l, 'count' => $c], $labels, $counts), JSON_UNESCAPED_UNICODE),
                'total_participants' => $total, 'bouts_count' => $bouts, 'sort_order' => ($i + 1) * 10,
            ]);
        }
    }

    private function partner(string $tier, string $name, ?string $url, ?string $logo, ?string $ad = null, ?string $description = null, int $order = 0): void
    {
        Database::insert('partners', [
            'tier' => $tier, 'name' => $name, 'url' => $url, 'description' => $description, 'sort_order' => $order,
            'logo_document_id' => $logo ? $this->doc($logo, 'partner_logo', $name) : null,
            'ad_document_id' => $ad ? $this->doc($ad, 'ad', $name) : null,
        ]);
    }

    private function partners(): void
    {
        $this->partner('institutional', 'Ville de Leforest', 'https://www.villedeleforest.fr/', 'partners/ville-leforest.jpg', null, null, 10);
        $this->partner('institutional', 'Communauté d\'Agglomération Hénin-Carvin', 'https://www.agglo-henincarvin.fr/', 'partners/agglo-henin-carvin.png', null, null, 20);
        $this->partner('institutional', 'Région Hauts-de-France', 'https://www.hautsdefrance.fr/', 'partners/region-hdf.png', null, null, 30);
        $this->partner('federal', 'France Judo', 'https://www.ffjudo.com/', 'partners/france-judo.png', null, null, 10);
        $this->partner('federal', 'Ligue Hauts-de-France de Judo', 'https://hautsdefrancejudo.ffjudo.com/', 'partners/ligue-hdf-judo.png', null, null, 20);
        $this->partner('federal', 'Comité du Pas-de-Calais de Judo', 'https://pas-de-calais-judo.ffjudo.com/', 'partners/comite-62-judo.jpg', null, null, 30);
        $this->partner('event', 'L\'U.T.I.L. — Union des Travailleurs Indépendants Leforestois', 'https://www.facebook.com/p/UTIL-Leforest-100090693041327/', 'partners/util.jpg', null,
            "Merci à l'Union des Travailleurs Indépendants de Leforest (UTIL) de nous accompagner dans la mise en place de la loterie ! Grâce à leurs adhérents, de superbes lots seront mis en jeu. « J'achète dans ma ville ».", 10);
        $members = [
            ['Optique Delattre', 'https://www.facebook.com/profile.php?id=100089641298575', 'optique-delattre', 'Opticien — 7 place Roger Salengro, 62790 Leforest — 03 21 77 85 63'],
            ['Intemporel Coiffure by Laetitia', 'https://www.facebook.com/p/Intemporel-coiffure-by-Laetitia-100063522903900/', 'intemporel-coiffure', 'Coiffure mixte, bien-être, head spa — 9 rue Jean Jaurès, 62790 Leforest — 03 21 77 68 50'],
            ['AS Bois Construction', 'https://asboisconstruction.com/', 'as-bois-construction', 'Extension ossature bois, charpente, menuiserie, bardage, terrasse — Leforest — 06 51 89 31 81'],
            ['LC Orthopédie', 'https://www.facebook.com/p/LC-Orthop%C3%A9die-61577799221106/', 'lc-orthopedie', 'Orthopédie sur mesure, semelles orthopédiques — 210 résidence des Houblons d\'Or, 62790 Leforest'],
            ['G-Nexia', 'https://www.facebook.com/GNexiaInformatique', 'g-nexia', 'Dépannage et assistance informatique à domicile autour de Leforest — 06 25 62 97 45'],
            ['Pharmacie de l\'Eden', 'https://www.facebook.com/share/19ovMGJVAm/', 'pharmacie-eden', 'Pharmacie — Leforest'],
            ['Ferme de Ch\'Debout — Producteurs de goût', 'https://www.facebook.com/profile.php?id=100062953734086', 'ferme-ch-debout', 'Légumes de saison, produits laitiers, confitures, miel — 55 rue Jean Jaurès, 62790 Leforest'],
            ['L\'Appart', 'https://www.facebook.com/Lappart62/', 'l-appart', 'Centre de soins esthétiques femmes et hommes — 11 rue Pressensé, 62790 Leforest — 03 21 72 01 38'],
            ['Autocontrol Leforest', 'https://www.facebook.com/profile.php?id=61583063250007', 'autocontrol', 'Contrôle technique — 3 rue Voltaire, 62790 Leforest — 03 21 77 78 78'],
            ['Charcuterie Traiteur Thibault Kino', 'https://www.facebook.com/charcuterie.traiteur.kino/', 'thibault-kino', 'Charcuterie, traiteur — 27 rue Léon Gambetta, 62790 Leforest — 03 21 69 42 31'],
        ];
        foreach ($members as $i => [$name, $url, $file, $desc]) {
            $this->partner('util_member', $name, $url, null, 'ads/' . $file . '.jpg', $desc, ($i + 1) * 10);
        }
        $this->partner('patron', 'AD Beauté by Anaïs Demessine', 'https://www.facebook.com/profile.php?id=100063464681524', null, 'ads/ad-beaute.jpg', 'Artisan coiffeur créateur, institut de beauté — 58 bis rue Marcel Sembat, 59283 Raimbeaucourt — 03 27 92 17 08', 10);
        $this->partner('patron', 'SARL Engels — Centre de lavage Auby', null, null, 'ads/engels-lavage.jpg', '2 rue Condorcet, 59950 Auby — 06 60 19 03 80', 20);
        $this->partner('supplier', 'Boucherie-Charcuterie P. Colin-Hadoux', 'https://www.facebook.com/profile.php?id=100063702514765', null, 'ads/colin-hadoux.jpg', 'Notre fournisseur « Pâté » depuis plusieurs saisons ! — 482 route de Dourges, 62110 Hénin-Beaumont', 10);
        $this->partner('supplier', 'Brasserie Au Delà', 'https://www.facebook.com/brasserieaudela', 'ads/brasserie-au-dela.png', null, 'Notre fournisseur « Bière ».', 20);
    }

    // ---------------------------------------------------------------------
    private function competition(): int
    {
        $id = Database::insert('competitions', [
            'uuid' => CompetitionService::uuid(),
            'slug' => CompetitionService::uniqueSlug('competition-amicale-judo-club-leforest-2026'),
            'name' => 'Compétition Amicale du Judo Club Leforest',
            'edition_label' => 'Édition 2026',
            'tagline' => 'Combats debout avec classement, de Mini-poussin(e)s à Cadet(te)s — animation pour l\'École de Judo',
            'description' => "Bienvenue à la Compétition Amicale du Judo Club Leforest ! Une journée de judo ouverte aux clubs de la région : combats debout avec classement pour les Mini-poussin(e)s, Poussin(e)s, Benjamin(e)s, Minimes et Cadet(te)s, et une animation collective pour l'École de Judo. Médaille pour tous les judokas.",
            'event_date' => '2026-09-26',
            'start_time' => '08:30:00',
            'venue' => null,
            'address' => 'Leforest (62790) — adresse de la salle à compléter',
            'city' => 'Leforest',
            'age_reference_date' => '2027-12-31',
            'mat_count' => 6,
            'expected_participants' => 470,
            'pool_sizes' => '3,4,5',
            'schedule_text' => null,
            'practical_info' => "Il n'y a pas de pesée : les judokas sont inscrits sur confiance selon leur sexe, leur catégorie d'âge et leur catégorie de poids.\n\nMerci d'arriver environ 15 minutes avant le début de l'échauffement. Buvette sur place (CB autorisée), loterie 100 % gagnante.",
            'coach_info' => "À votre arrivée, pointez vos judokas puis validez leur inscription à la **Table Centrale**. Merci de vérifier la présence de vos judokas avant de rendre les feuilles d'inscription.\n\nAfin de faciliter le tirage au sort et de limiter les retards, les feuilles d'inscription doivent être **lisibles, en simple recto**, sur **une seule page par catégorie d'âge et sexe**, avec le **nom du club** sur chaque feuille, et remises au plus tard au début des échauffements.",
            'status' => 'finished',
            'is_featured' => 1,
        ]);
        SettingsService::initialize($id);
        foreach ([
            'weight_input' => 'category', 'pool_grouping' => 'weight_category', 'use_measured_weight' => false,
            'late_registration_allowed' => true, 'late_registration_requires_validation' => false,
            'fighter_color' => 'red', 'card_payment_accepted' => true, 'preferred_pool_size' => 4,
        ] as $k => $v) {
            Database::run('UPDATE competition_settings SET setting_value = ? WHERE competition_id = ? AND setting_key = ?', [json_encode($v), $id, $k]);
        }
        $competition = CompetitionService::find($id);
        CompetitionService::syncTatamis($competition);
        $this->categories($id);
        $this->durations($id);
        \App\Services\RankingService::createDefaultRules($id);

        $program = $this->doc('site/programme-2026.pdf', 'program', 'Programme 2026 (PDF)', $id);
        $cover = $this->doc('site/programme-couverture.jpg', 'visual', 'Couverture du programme 2026', $id);
        Database::run('UPDATE competitions SET regulation_document_id = ?, cover_document_id = ? WHERE id = ?', [$program, $cover, $id]);

        $this->competitionContent($id);
        $this->products($id);
        $this->clubs($id);
        return $id;
    }

    private function categories(int $cid): void
    {
        $cats = [
            ['EJ', 'École de Judo', 'École de Judo Garçons', 'École de Judo Filles', 2021, 2022, 'animation'],
            ['MP', 'Mini-poussin(e)s', 'Mini-poussins', 'Mini-poussines', 2019, 2020, 'pools'],
            ['PO', 'Poussin(e)s', 'Poussins', 'Poussines', 2017, 2018, 'pools'],
            ['BE', 'Benjamin(e)s', 'Benjamins', 'Benjamines', 2015, 2016, 'pools'],
            ['MI', 'Minimes', 'Minimes Garçons', 'Minimes Filles', 2013, 2014, 'pools'],
            ['CA', 'Cadet(te)s', 'Cadets', 'Cadettes', 2010, 2012, 'pools'],
        ];
        $weights = [
            'MP' => ['F' => [19, 22, 25, 28, 32, 36, 40, 44, null], 'M' => [21, 24, 27, 30, 34, 38, 42, 46, null]],
            'PO' => ['F' => [22, 25, 28, 32, 36, 40, 44, 48, null], 'M' => [24, 27, 30, 34, 38, 42, 46, 50, null]],
            'BE' => ['F' => [28, 32, 36, 40, 44, 48, 52, 57, 63, null], 'M' => [27, 30, 34, 38, 42, 46, 50, 55, 60, null]],
            'MI' => ['F' => [36, 40, 44, 48, 52, 57, 63, 70, null], 'M' => [34, 38, 42, 46, 50, 55, 60, 66, 73, null]],
            'CA' => ['F' => [40, 44, 48, 52, 57, 63, 70, null], 'M' => [46, 50, 55, 60, 66, 73, 81, null]],
        ];
        foreach ($cats as $i => [$code, $name, $m, $f, $y1, $y2, $format]) {
            $ageId = Database::insert('age_categories', [
                'competition_id' => $cid, 'code' => $code, 'name' => $name, 'name_m' => $m, 'name_f' => $f, 'format' => $format,
                'birth_year_min' => $y1, 'birth_year_max' => $y2, 'sort_order' => ($i + 1) * 10,
            ]);
            foreach (['F', 'M'] as $sex) {
                Database::insert('competition_categories', ['competition_id' => $cid, 'age_category_id' => $ageId, 'sex' => $sex]);
                if (isset($weights[$code][$sex])) {
                    CategoryService::createWeights($cid, $ageId, $sex, $weights[$code][$sex]);
                }
            }
        }
    }

    private function durations(int $cid): void
    {
        $ages = array_column(Database::all('SELECT id, code FROM age_categories WHERE competition_id = ?', [$cid]), 'id', 'code');
        foreach ([
            [null, 120, 'Règle par défaut', null],
            ['MP', 60, 'Mini-poussin(e)s', 'Selon affluence : 1 min ou 1 min 30 s'],
            ['PO', 90, 'Poussin(e)s', 'Selon affluence : 1 min 30 s ou 2 min'],
            ['BE', 120, 'Benjamin(e)s', null],
            ['MI', 180, 'Minimes F. & G.', null],
            ['CA', 180, 'Cadet(te)s', 'Selon affluence : 3 min ou 4 min'],
        ] as [$code, $seconds, $label, $note]) {
            Database::insert('fight_duration_rules', [
                'competition_id' => $cid, 'age_category_id' => $code ? $ages[$code] : null, 'phase' => 'all', 'label' => $label,
                'duration_seconds' => $seconds, 'golden_score_mode' => 'unlimited', 'golden_score_seconds' => null,
                'overtime_rule' => $note, 'priority' => $code ? 10 : 0, 'is_example' => 0,
            ]);
        }
    }

    private function competitionContent(int $cid): void
    {
        $this->section($cid, 'competition', 'welcome', 'Le déroulement général de la journée', "**Bienvenue à la Compétition Amicale du Judo Club Leforest !**\n\nVoici quelques explications sur le déroulement de la journée. C'est peut-être votre 1^ère^ venue à Leforest ou votre 1^ère^ compétition amicale tout court. On espère que cela vous permettra de vous rassurer un peu !", 10);
        $this->section($cid, 'competition', 'schedule_intro', 'Horaires prévisionnels', '**Merci aux responsables de Clubs de vérifier la présence de leurs Judokas** avant de rendre les feuilles d\'inscriptions à la Table Centrale.', 20);
        $this->section($cid, 'competition', 'schedule_note', null, '*Nous vous tiendrons informés dans la mesure du possible des éventuels retards qu\'il pourrait y avoir.*', 21);
        $this->section($cid, 'competition', 'plan', 'Plan de la salle', 'Le plateau de compétition (zone en vert sur le plan) est réservé uniquement :', 30, $this->doc('site/plan-salle.png', 'plan', 'Plan de la salle', $cid));
        $this->section($cid, 'competition', 'respect', 'Pour le confort de tous', "Nous vous remercions de garder les différents espaces propres (gradins, toilettes, vestiaires, espace extérieur, parking, etc.). Des poubelles sont à votre disposition.\n\nEnfin, veuillez s'il vous plaît ne pas stationner debout devant les barrières. Merci de vous asseoir dans les gradins pour préserver la vue de tous les spectateurs. Veillez également à vos affaires personnelles.\n\n**Nous vous souhaitons une agréable journée !**", 40);
        $this->section($cid, 'competition', 'categories', 'Les catégories de poids par âge et sexe', '**Il n\'y a pas de pesée.** Les judokas sont inscrits sur confiance selon leurs catégories de poids indiquées par les clubs.', 50);
        $this->section($cid, 'competition', 'rules_format', 'Formule de compétition', "Entraînement collectif (ateliers et randoris) d'une heure pour les **Écoles de Judo (2021/2022) Filles et Garçons**. Médaille pour tous les judokas.\n\nCombats debout avec un classement pour les **Mini-poussin(e)s** (2019/2020), **Poussin(e)s** (2017/2018), **Benjamin(e)s** (2015/2016), **Minimes Filles et Garçons** (2013/2014) et **Cadet(te)s** (2010/2011/2012).", 60);
        $this->section($cid, 'competition', 'rules_refereeing', 'Arbitrage', 'Les règles d\'arbitrage sont celles de la saison 2026-2027 appliquées par la F.F.J.D.A., adaptées à des fins pédagogiques.', 61);
        $this->section($cid, 'competition', 'rules_durations', 'Temps de combats (selon affluence)', "- Mini-poussin(e)s (2019/2020) : 1 min ou 1 min et 30 s\n- Poussin(e)s (2017/2018) : 1 min et 30 s ou 2 min\n- Benjamin(e)s (2015/2016) : 2 min\n- Minimes F. & G. (2013/2014) : 3 min\n- Cadet(te)s (2010/2011/2012) : 3 min ou 4 min", 62);
        $this->section($cid, 'competition', 'buvette', 'Buvette', "**Veuillez acheter vos tickets avant votre passage en buvette !** Merci !\n\nTicket boissons · Ticket sandwichs — **CB autorisée** (CB, Visa, Mastercard, Maestro).", 70);
        $this->section($cid, 'competition', 'alcohol', null, '* L\'abus d\'alcool est dangereux pour la santé. À consommer avec modération.', 71);
        $this->section($cid, 'competition', 'clubs', 'Merci aux 33 clubs présents', 'Cliquez sur les logos ou noms des clubs pour accéder à leurs pages Facebook !', 80);
        $this->section($cid, 'competition', 'partners', 'Merci à nos partenaires et mécènes', 'Cliquez sur les logos pour accéder aux sites Internet de nos partenaires !', 90);
        $this->section($cid, 'competition', 'adage', 'Jita-kyoei', "**自他共栄** — En japonais, se lit « Jita-kyoei », ce qui signifie littéralement « entraide et prospérité mutuelle ». C'est l'un des principes essentiels sur lesquels s'appuie le judo !", 91);
        $this->section($cid, 'competition', 'lottery', 'Loterie — enveloppe à 2 €', "**100 % gagnant !** Nombreux lots à gagner. Rapprochez-vous de la buvette !\n\nMerci à l'Union des Travailleurs Indépendants de Leforest (UTIL) de nous accompagner dans la mise en place de la loterie ! Grâce à leurs adhérents, de superbes lots seront mis en jeu. Découvrez les membres de l'UTIL qui vous offrent ces lots !", 100);
        $this->section($cid, 'competition', 'lottery_outro', null, '**BONNE CHANCE !**', 101);
        $this->section($cid, 'competition', 'volunteers', 'Merci aux bénévoles !', "Cette journée ne pourrait pas avoir lieu sans toutes les personnes qui donnent de leur temps et de leur énergie.\n\nMerci à celles et ceux qui ont préparé et installé cette salle, et à tous les bénévoles mobilisés aujourd'hui.\n\nUn merci tout particulier aux parents et proches de nos judokas qui ont préparé avec générosité crêpes et pâtisseries pour la buvette !\n\n**Sans vous, rien de tout cela ne serait possible.** ❤️", 110);
        $this->section($cid, 'competition', 'history', 'L\'historique de notre compétition', 'Et pour cette édition, combien serons-nous ? 😊', 120);
        $this->section($cid, 'competition', 'thanks', 'Merci de votre présence', 'Au plaisir de vous retrouver !', 130, $this->doc('site/merci.jpg', 'visual', 'Merci de votre présence', $cid));

        foreach ([
            ['À votre arrivée', "Prévenez le responsable de votre club ! Il pointera votre enfant et validera ensuite son inscription à la Table Centrale. **Il n'y a pas de pesée** : les enfants sont inscrits sur confiance selon leur sexe, leur catégorie d'âge et leur catégorie de poids. Merci d'arriver environ 15 minutes avant le début de l'échauffement."],
            ['Feuilles d\'inscription', "Afin de faciliter le tirage au sort et de limiter les risques de retard, merci aux responsables de clubs de rendre les feuilles d'inscription lisibles, en simple recto (pas en recto-verso) et sur une seule page par catégorie d'âge et sexe, au plus tard au début des échauffements. Notez bien le nom de votre club sur chaque feuille."],
            ['Échauffement', "Pour chaque catégorie d'âge, il y aura un échauffement collectif. À l'issue de celui-ci, les premiers enfants seront appelés sur leurs tapis respectifs ; les autres seront invités à rejoindre leurs parents et seront appelés au fil de l'eau. Afin de limiter le risque de blessure, il est primordial d'arriver à l'heure et d'assister à l'échauffement."],
            ['Combats et podium', "Les enfants seront amenés à faire 2 à 5 combats selon les catégories d'âge et le nombre de judokas dans leurs poules. Il peut y avoir plusieurs poules pour une même catégorie d'âge et de poids. À l'issue de leurs combats, tous les judokas seront dirigés vers le podium pour recevoir leurs médailles, puis pourront rejoindre leurs parents."],
            ['École de Judo', "Les enfants de la catégorie École de Judo (2021-2022) auront un entraînement collectif et recevront également une médaille à la fin de leur animation."],
        ] as [$t, $b]) {
            $this->item($cid, 'day_steps', $t, ['body' => $b]);
        }
        foreach (['Aux judokas appelés', 'Aux entraîneurs et responsables de clubs', 'Aux secouristes', 'Aux arbitres et commissaires sportifs', 'À l\'équipe d\'organisation', 'Aux représentants de la Municipalité et partenaires invités', 'Aux personnels du service technique de la Ville', 'Aux personnes appelées à la Table Centrale'] as $r) {
            $this->item($cid, 'access_rules', $r);
        }
        $schedule = [
            ['Minimes & Cadet(te)s', [
                ['8h30', 'Accueil des **Minimes Garçons et Filles** (né(e)s en 2013/2014) et **Cadet(te)s** (né(e)s en 2010/2011/2012)', 0],
                ['8h40 maximum', 'Remise des feuilles d\'inscription des **Minimes Garçons et Filles** et **Cadet(te)s** à la Table Centrale', 0],
                ['8h40 – 9h00', 'Échauffement dirigé des **Minimes Garçons et Filles** et **Cadet(te)s**', 0],
                ['9h00', 'Début des combats pour les **Minimes Garçons et Filles** et **Cadet(te)s** (3 minutes)', 1],
            ]],
            ['Benjamin(e)s', [
                ['10h15', 'Accueil des **Benjamin(e)s** (né(e)s en 2015/2016)', 0],
                ['10h30 maximum', 'Remise des feuilles d\'inscription des **Benjamin(e)s** à la Table Centrale', 0],
                ['10h30 – 10h45', 'Échauffement dirigé des **Benjamin(e)s**', 0],
                ['10h45', 'Début des combats pour les **Benjamin(e)s** (2 minutes)', 1],
            ]],
            ['Poussin(e)s', [
                ['11h30', 'Accueil des **Poussin(e)s** (né(e)s en 2017/2018)', 0],
                ['11h45 maximum', 'Remise des feuilles d\'inscription des **Poussin(e)s** à la Table Centrale', 0],
                ['11h45 – 12h00', 'Échauffement dirigé des **Poussin(e)s**', 0],
                ['12h00', 'Début des combats pour les **Poussin(e)s** (1 minute et 30 secondes)', 1],
            ]],
            ['Mini-poussin(e)s', [
                ['13h15', 'Accueil des **Mini-poussin(e)s** (né(e)s en 2019/2020)', 0],
                ['13h30 maximum', 'Remise des feuilles d\'inscription des **Mini-poussin(e)s** à la Table Centrale', 0],
                ['13h30 – 13h45', 'Échauffement dirigé des **Mini-poussin(e)s**', 0],
                ['13h45', 'Début des combats pour les **Mini-poussin(e)s** (1 minute)', 1],
            ]],
            ['École de Judo', [
                ['15h15', 'Accueil des **Écoles de Judo Garçons et Filles** (né(e)s en 2021/2022)', 0],
                ['15h30 – 16h45', 'Animation collective (ateliers pédagogiques + randoris) pour les **Écoles de Judo Garçons et Filles** et remise de médailles', 1],
            ]],
            ['Fin de journée', [
                ['16h45', 'Fin prévisionnelle', 0],
            ]],
        ];
        foreach ($schedule as [$group, $rows]) {
            foreach ($rows as [$time, $text, $hl]) {
                $this->item($cid, 'schedule', $time, ['body' => $text, 'group_label' => $group, 'is_highlight' => $hl]);
            }
        }
        foreach (['Des paires de lunettes de soleil', 'Un panier garni de produits soin des cheveux', 'Des chèques cadeaux dans des enseignes de l\'UTIL et d\'ailleurs', 'Des porte-monnaie de jetons pour un centre de lavage de véhicule', 'Quatre bons d\'achat de 25 € chez Décathlon', 'Des bilans podologiques', 'Un contrôle technique de votre véhicule', 'Un bilan de remise en forme d\'ordinateur', 'Un soin du visage', 'Un massage', 'Des paniers garnis de produits fermiers', 'Des paires de chaussettes', 'Des produits de pharmacie', 'Etc.'] as $lot) {
            $this->item($cid, 'lottery_prizes', $lot);
        }
    }

    private function products(int $cid): void
    {
        $rows = [
            ['restauration', 'Sandwich au jambon', null, 250], ['restauration', 'Sandwich au pâté', null, 250], ['restauration', 'Sandwich au fromage', null, 250],
            ['restauration', 'Sandwich au thon, tomate, salade', null, 300], ['restauration', 'Sandwich au surimi, tomate, salade', null, 300],
            ['restauration', 'Sandwich au poulet halal, tomate, salade', null, 300], ['restauration', 'Croque-monsieur', null, 200],
            ['restauration', 'Chips', null, 100], ['restauration', 'Sachet de bonbons', null, 100], ['restauration', 'Barre chocolatée Kinder Bueno', null, 150],
            ['restauration', 'Crêpe au sucre', null, 150], ['restauration', 'Crêpe au Nutella', null, 200], ['restauration', 'Pâtisserie', null, 100],
            ['boissons', 'Coca-Cola', '33 cl', 200], ['boissons', 'Orangina', '33 cl', 200], ['boissons', 'Ice Tea', '33 cl', 200],
            ['boissons', 'Fanta', '33 cl', 200], ['boissons', 'Perrier', '33 cl', 200], ['boissons', 'Capri-Sun', '20 cl', 150],
            ['boissons', 'Jus de pomme', '20 cl', 150], ['boissons', 'Petite bouteille d\'eau', '50 cl', 100],
            ['boissons', 'Bière pression', '25 cl *', 300], ['boissons', 'Café', null, 150],
            ['loterie', 'Enveloppe de loterie', '100 % gagnant', 200],
        ];
        foreach ($rows as $i => [$cat, $name, $desc, $price]) {
            Database::insert('products', [
                'competition_id' => $cid, 'category' => $cat, 'name' => $name, 'description' => $desc, 'price_cents' => $price, 'sort_order' => ($i + 1) * 10,
            ]);
        }
    }

    /** Noms courts des clubs (pastilles, cartes des judokas). */
    private const CLUB_SHORT = [
        'Judo Club Lensois' => 'JC Lensois',
        'Judo Club Sallauminois' => 'JC Sallauminois',
        'Judo Club de Loison-sous-Lens' => 'JC Loison',
        'Judo Club d\'Évin-Malmaison' => 'JC Évin-Malmaison',
        'Judo Club Fouquièrois' => 'JC Fouquièrois',
        'Judo Club d\'Hulluch' => 'JC Hulluch',
        'Lille Université Club Judo' => 'LUC Judo',
        'Judo Club de Roncq' => 'JC Roncq',
        'Judo Club de Bois-Bernard' => 'JC Bois-Bernard',
        'USA Liévin Judo Ju-Jitsu' => 'USA Liévin',
        'Judo Club d\'Aix-Noulette' => 'JC Aix-Noulette',
        'Judo Club Léo Lagrange Hellemmes' => 'Léo Lagrange Hellemmes',
        'Ippon Club Rouvroysien' => 'Ippon Club Rouvroy',
        'Noyelles-sous-Lens Judo Ju-Jitsu' => 'Noyelles-sous-Lens',
        'Hénin Judo Ju-Jitsu' => 'Hénin Judo',
        'Éleu Judo Club' => 'Éleu JC',
        'Judo Ju-Jitsu Taïso UDAS Beaurains-Achicourt' => 'UDAS Beaurains',
        'Marck Judo' => 'Marck Judo',
        'Judo Club de Drocourt' => 'JC Drocourt',
        'Judo Club Lewardois' => 'JC Lewardois',
        'Judo Club Vermellois' => 'JC Vermellois',
        'Judo Club Harnésien' => 'JC Harnésien',
        'Judo Club de Givenchy-en-Gohelle' => 'JC Givenchy',
        'Dourges Association Judo' => 'Dourges Judo',
        'Vallée de l\'Oise et des Impressionnistes Judo' => 'VOI Judo',
        'Judo Club Vitry-en-Artois' => 'JC Vitry-en-Artois',
        'Club des Arts Martiaux d\'Oignies' => 'CAM Oignies',
        'Judo Club Roost-Warendin' => 'JC Roost-Warendin',
        'Flixecourt Judo' => 'Flixecourt Judo',
        'ACAMA' => 'ACAMA',
        'Méricourt Judo' => 'Méricourt Judo',
        'Union Sportive des Cheminots d\'Avion — Judo' => 'USC Avion',
        'Judo Club Leforest' => 'JC Leforest',
    ];

    private function clubs(int $cid): void
    {
        $clubs = [
            ['Judo Club Lensois', 'Lens', '62', 'lensois.png', 'https://www.facebook.com/JudoClubLensoisOfficiel'],
            ['Judo Club Sallauminois', 'Sallaumines', '62', 'sallauminois.jpg', 'https://www.facebook.com/JudoClubSallauminoisOfficiel'],
            ['Judo Club de Loison-sous-Lens', 'Loison-sous-Lens', '62', 'loison.jpg', 'https://www.facebook.com/profile.php?id=100083339132606'],
            ['Judo Club d\'Évin-Malmaison', 'Évin-Malmaison', '62', 'evin-malmaison.jpg', 'https://www.facebook.com/judoevin'],
            ['Judo Club Fouquièrois', 'Fouquières-lès-Lens', '62', 'fouquierois.jpg', 'https://www.facebook.com/p/Judo-Club-Fouqui%C3%A8rois-100063678814189/'],
            ['Judo Club d\'Hulluch', 'Hulluch', '62', 'hulluch.jpg', 'https://www.facebook.com/judo.hulluch'],
            ['Lille Université Club Judo', 'Lille', '59', 'luc-lille.jpg', 'https://www.facebook.com/profile.php?id=61583786950868'],
            ['Judo Club de Roncq', 'Roncq', '59', 'roncq.jpg', 'https://www.facebook.com/Judoclubroncq'],
            ['Judo Club de Bois-Bernard', 'Bois-Bernard', '62', 'bois-bernard.jpg', 'https://www.facebook.com/profile.php?id=100075936831310'],
            ['USA Liévin Judo Ju-Jitsu', 'Liévin', '62', 'usa-lievin.jpg', 'https://www.facebook.com/www.judolievin.fr'],
            ['Judo Club d\'Aix-Noulette', 'Aix-Noulette', '62', 'aix-noulette.png', 'https://www.facebook.com/judo.aixnoulette'],
            ['Judo Club Léo Lagrange Hellemmes', 'Hellemmes', '59', 'leo-lagrange-hellemmes.jpg', 'https://www.facebook.com/profile.php?id=61585061854062'],
            ['Ippon Club Rouvroysien', 'Rouvroy', '62', 'ippon-rouvroy.jpg', null, 'https://ipponclubrouvroy62.fr/'],
            ['Noyelles-sous-Lens Judo Ju-Jitsu', 'Noyelles-sous-Lens', '62', 'noyelles-sous-lens.jpg', 'https://www.facebook.com/NSLJudo/'],
            ['Hénin Judo Ju-Jitsu', 'Hénin-Beaumont', '62', 'henin.png', 'https://www.facebook.com/heninjudojujitsu.taiso'],
            ['Éleu Judo Club', 'Éleu-dit-Leauwette', '62', 'eleu.png', 'https://www.facebook.com/groups/313751908817660'],
            ['Judo Ju-Jitsu Taïso UDAS Beaurains-Achicourt', 'Beaurains', '62', 'udas-beaurains.jpg', 'https://www.facebook.com/JudoClubdeBeaurain'],
            ['Marck Judo', 'Marck', '62', 'marck.jpg', 'https://www.facebook.com/marck.judo'],
            ['Judo Club de Drocourt', 'Drocourt', '62', 'drocourt.jpg', 'https://www.facebook.com/profile.php?id=100075925837867'],
            ['Judo Club Lewardois', 'Lewarde', '59', 'lewarde.jpg', 'https://www.facebook.com/p/Judo-club-Lewarde-100057548596224/'],
            ['Judo Club Vermellois', 'Vermelles', '62', 'vermelles.jpg', 'https://www.facebook.com/Judoclubvermelles'],
            ['Judo Club Harnésien', 'Harnes', '62', 'harnes.jpg', 'https://www.facebook.com/judoharne/'],
            ['Judo Club de Givenchy-en-Gohelle', 'Givenchy-en-Gohelle', '62', 'givenchy.jpg', 'https://www.facebook.com/profile.php?id=100010590194859'],
            ['Dourges Association Judo', 'Dourges', '62', 'dourges.jpg', 'https://www.facebook.com/profile.php?id=100063557371841'],
            ['Vallée de l\'Oise et des Impressionnistes Judo', 'Vallée de l\'Oise', '95', 'voi-judo.jpg', 'https://www.facebook.com/judo.voi.169'],
            ['Judo Club Vitry-en-Artois', 'Vitry-en-Artois', '62', 'vitry-en-artois.jpg', 'https://www.facebook.com/profile.php?id=61555666537439'],
            ['Club des Arts Martiaux d\'Oignies', 'Oignies', '62', 'oignies.jpg', 'https://www.facebook.com/profile.php?id=100063499065517'],
            ['Judo Club Roost-Warendin', 'Roost-Warendin', '59', 'roost-warendin.jpg', 'https://www.facebook.com/judo.roostwarendin'],
            ['Flixecourt Judo', 'Flixecourt', '80', 'flixecourt.jpg', 'https://www.facebook.com/p/Flixecourt-Judo-100063172003304/'],
            ['ACAMA', '', '62', 'acama.png', 'https://www.facebook.com/judoacama/'],
            ['Méricourt Judo', 'Méricourt', '62', 'mericourt.jpg', 'https://www.facebook.com/profile.php?id=100007101407658'],
            ['Union Sportive des Cheminots d\'Avion — Judo', 'Avion', '62', null, 'https://www.facebook.com/uscajudo'],
            ['Judo Club Leforest', 'Leforest', '62', '__host__', 'https://www.facebook.com/jcleforest'],
        ];
        foreach ($clubs as $i => $c) {
            [$name, $city, $dept, $logo, $fb] = $c;
            $website = $c[5] ?? null;
            $logoPath = null;
            if ($logo === '__host__') {
                $logoPath = 'media/' . DocumentService::storePath(BASE_PATH . '/public/assets/img/logo-jcl.png', 'logo-jcl.png', 'club_logo', $name);
            } elseif ($logo) {
                $logoPath = 'media/' . $this->doc('clubs/' . $logo, 'club_logo', $name);
            }
            $existing = Database::value('SELECT id FROM clubs WHERE name = ?', [$name]);
            if ($existing) {
                Database::run('UPDATE clubs SET short_name = ? WHERE id = ? AND short_name IS NULL', [self::CLUB_SHORT[$name] ?? null, $existing]);
            }
            $clubId = $existing ? (int) $existing : Database::insert('clubs', [
                'name' => $name, 'short_name' => self::CLUB_SHORT[$name] ?? null, 'city' => $city, 'department' => $dept, 'country' => 'FR', 'contact_name' => '', 'email' => '',
                'logo_path' => $logoPath, 'facebook_url' => $fb, 'website_url' => $website,
            ]);
            Database::insert('competition_clubs', ['competition_id' => $cid, 'club_id' => $clubId, 'is_host' => $logo === '__host__' ? 1 : 0, 'sort_order' => ($i + 1) * 10]);
        }
    }
}
