import SwiftUI

// Onglet « Tableau » : tableaux à double repêchage, 2 poules + tableau final, poules — en direct.
// Lecture pensée pour le téléphone : une page par tour (glisser d'un tour à l'autre ou onglets), des cartes de combat
// larges avec le nom de famille entier, le vainqueur en gras, le combat en cours signalé, puis le classement complet
// de la catégorie (1, 2, 3, 3, 5, 5, 7, 7, puis NC ; poules : 1 à 5).

// MARK: - Modèles (/api/v1/competitions/{slug}/tableaux)

struct BracketCategoriesResponse: Codable { let competition: Competition; let categories: [BracketCategory] }

struct BracketCategory: Codable, Identifiable, Hashable {
    let id: Int
    let label: String
    let kind: String
    let count: Int
    let mat: Int?
    let finished: Bool
    let sex: String?
    let age: String?

    var kindLabel: String {
        switch kind {
        case "bracket": return count >= 9 ? "Tableau · double repêchage" : "Tableau"
        case "pools_final": return "2 poules + tableau final"
        case "pools": return "Poules"
        default: return count <= 1 ? "Seul(e)" : "Poule"
        }
    }
}

struct BracketAthlete: Codable, Hashable {
    let registrationId: Int
    let clubId: Int?
    let firstName: String
    let lastName: String
    let club: String?
    let clubShort: String?
    let clubLogo: String?
    let clubColor: String?
}

struct BracketSide: Codable, Hashable {
    let type: String
    let text: String?
    let seed: Int?
    let athlete: BracketAthlete?
    let score: String?
}

struct BracketBout: Codable, Hashable {
    let id: Int
    let status: String
    let mat: Int?
    let method: String?
    let boutsBefore: Int?
}

struct BracketMatch: Codable, Identifiable, Hashable {
    let key: String
    let label: String
    let stage: String
    let a: BracketSide
    let b: BracketSide
    let winner: String?
    let bout: BracketBout?
    var id: String { key }
    var isLive: Bool { bout?.status == "in_progress" || bout?.status == "finished" }
    var isDone: Bool { bout?.status == "validated" || (winner != nil && bout == nil) }
}

struct BracketSection: Codable, Identifiable, Hashable {
    let key: String
    let title: String
    let note: String?
    let matches: [BracketMatch]
    var id: String { key }
    var hasOpenMatch: Bool { matches.contains { !$0.isDone && ($0.a.type == "athlete" || $0.b.type == "athlete") } }
}

struct RankingRow: Codable, Hashable {
    let place: Int?
    let athlete: BracketAthlete?
}

struct RankingGroup: Codable, Identifiable, Hashable {
    let title: String
    let complete: Bool
    let rows: [RankingRow]
    var id: String { title }
}

struct BracketDetail: Codable {
    let id: Int
    let label: String
    let kind: String
    let mat: Int?
    let complete: Bool
    let size: Int?
    let sections: [BracketSection]
    let rankings: [RankingGroup]
    let trees: [TreeDrawing]?
    let pdf: String?
}

extension API {
    static func brackets(_ slug: String) async throws -> BracketCategoriesResponse { try await get("/competitions/\(slug)/tableaux") }
    static func bracket(_ slug: String, _ id: Int) async throws -> BracketDetail { try await get("/competitions/\(slug)/tableaux/\(id)") }
}

// MARK: - Onglet

struct BracketTabView: View {
    @State private var competitions: [Competition] = []
    @AppStorage("bracket_competition") private var savedSlug = ""
    @State private var slug: String?
    @State private var data: BracketCategoriesResponse?
    @State private var error: String?
    @State private var query = ""
    @State private var sex = "all"
    @State private var age = "all"

    private var ages: [String] {
        var seen: [String] = []
        for c in data?.categories ?? [] { if let a = c.age, !seen.contains(a) { seen.append(a) } }
        return seen
    }

    private var shown: [BracketCategory] {
        let q = query.folded
        return (data?.categories ?? []).filter {
            (q.isEmpty || $0.label.folded.contains(q)) && (sex == "all" || $0.sex == sex) && (age == "all" || $0.age == age)
        }
    }

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 14) {
                    HStack(alignment: .firstTextBaseline) {
                        Text("Tableaux").font(.score(32, weight: 900)).foregroundStyle(Theme.ink)
                        Spacer()
                    }
                    // Compétition : menu compact (les noms sont longs)
                    Menu {
                        ForEach(competitions) { c in
                            Button { slug = c.slug } label: { Label(c.name, systemImage: c.live ? "dot.radiowaves.left.and.right" : "checkmark.seal") }
                        }
                    } label: {
                        HStack(spacing: 10) {
                            if data?.competition.live == true { LiveBadge() }
                            Text(data?.competition.name ?? competitions.first(where: { $0.slug == slug })?.name ?? "Compétition")
                                .font(.subheadline.weight(.semibold)).foregroundStyle(Theme.ink).multilineTextAlignment(.leading)
                            Spacer(minLength: 4)
                            if competitions.count > 1 { Image(systemName: "chevron.up.chevron.down").font(.footnote.weight(.bold)).foregroundStyle(Theme.muted) }
                        }
                        .padding(12)
                        .background(Theme.surface, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
                    }
                    .disabled(competitions.count < 2)
                    if let error, data == nil { ErrorCard(message: error) { Task { await load() } } }
                    if let d = data {
                        if d.categories.count > 6 { SearchField(text: $query, prompt: "Catégorie, poids") }
                        // Filtres : âge et sexe
                        if ages.count > 1 {
                            PillTabs(items: [("all", "Tous les âges")] + ages.map { ($0, $0) }, selection: $age).padding(.horizontal, -16)
                        }
                        PillTabs(items: [("all", "Filles et garçons"), ("M", "Garçons"), ("F", "Filles")], selection: $sex).padding(.horizontal, -16)
                        if !d.categories.isEmpty && shown.isEmpty {
                            EmptyState(icon: "line.3.horizontal.decrease.circle", title: "Aucune catégorie pour ces filtres")
                        }
                        if d.categories.isEmpty {
                            EmptyState(icon: "trophy", title: "Pas encore de tableaux", message: "Les tableaux et poules apparaissent dès que l'organisation les publie.")
                        }
                        ForEach(shown) { cat in
                            NavigationLink(value: Route.bracket(d.competition.slug, cat.id)) { BracketCategoryCard(cat: cat) }.buttonStyle(.plain)
                        }
                    } else if error == nil && slug != nil {
                        ProgressView().frame(maxWidth: .infinity).padding(.top, 60)
                    } else if competitions.isEmpty && error == nil {
                        EmptyState(icon: "trophy", title: "Aucune compétition en cours")
                    }
                }
                .padding(16)
            }
            .screenBackground(glow: 0.7)
            .toolbar(.hidden, for: .navigationBar)
            .navigationDestination(for: Route.self) { $0.view }
            .refreshable { await load() }
            .task { await loadCompetitions() }
            .task(id: slug) { data = nil; if let slug { savedSlug = slug }; await load() }
            .polling(every: 15) { await load() }
        }
    }

    private func loadCompetitions() async {
        guard let list = try? await API.competitions() else { return }
        // En cours d'abord, puis terminées récentes (les poules et tableaux ne sont publiés qu'à partir du jour J)
        competitions = list.filter { $0.live } + list.filter { $0.isFinished }.prefix(3)
        // Dernière compétition choisie si elle est toujours listée, sinon la première en cours
        if slug == nil { slug = competitions.first(where: { $0.slug == savedSlug })?.slug ?? competitions.first?.slug }
    }

    private func load() async {
        guard let slug else { return }
        do { data = try await API.brackets(slug); error = nil } catch { if data == nil { self.error = readable(error) } }
    }
}

struct BracketCategoryCard: View {
    let cat: BracketCategory
    var body: some View {
        HStack(spacing: 14) {
            Image(systemName: cat.kind == "bracket" ? "trophy.fill" : "square.grid.3x3.fill")
                .font(.system(size: 18, weight: .semibold)).foregroundStyle(cat.finished ? Theme.green : Theme.red)
                .frame(width: 46, height: 46)
                .background(cat.finished ? Theme.greenSoft : Theme.redSoft, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
            VStack(alignment: .leading, spacing: 3) {
                Text(cat.label).font(.system(size: 17, weight: .bold)).foregroundStyle(Theme.ink).fixedSize(horizontal: false, vertical: true)
                Text([cat.kindLabel, "\(cat.count) judoka\(cat.count > 1 ? "s" : "")", cat.mat.map { "Tapis \($0)" }].compactMap { $0 }.joined(separator: " · "))
                    .font(.caption).foregroundStyle(Theme.muted)
            }
            Spacer(minLength: 4)
            if cat.finished { Chip(text: "Terminé", color: Theme.green, fill: Theme.greenSoft) }
            Image(systemName: "chevron.right").font(.footnote.weight(.bold)).foregroundStyle(Theme.faint)
        }
        .card(padding: 14)
    }
}

// MARK: - Catégorie

struct BracketDetailView: View {
    let slug: String
    let id: Int
    @State private var d: BracketDetail?
    @State private var error: String?
    @State private var page = ""
    @AppStorage("bracket_mode") private var mode = "tree"
    @State private var treePage = 0
    @State private var download = false
    @Environment(Favorites.self) private var favorites

    private var hasTree: Bool { !(d?.trees ?? []).isEmpty }

    private var pages: [(String, String)] {
        guard let d else { return [] }
        return d.sections.map { ($0.key, $0.title) } + [("rank", "Classement")]
    }

    var body: some View {
        VStack(spacing: 0) {
            if let d {
                header(d)
                if hasTree { modeBar(d) }
                if hasTree && mode == "tree", let trees = d.trees {
                    PillTabs(items: trees.indices.map { ($0, $0 == 0 ? "Tableau principal" : (trees[$0].title.contains("(1)") ? "Repêchage 1" : "Repêchage 2")) } + [(-1, "Classement")],
                             selection: $treePage).padding(.vertical, 8)
                    if treePage >= 0 && treePage < trees.count {
                        BracketTreeView(tree: trees[treePage]).id(trees[treePage].title).padding(.horizontal, 12).padding(.bottom, 8)
                    } else {
                        ScrollView {
                            VStack(alignment: .leading, spacing: 14) { ForEach(d.rankings) { g in RankingCard(group: g, slug: slug) } }
                                .padding(.horizontal, 16).padding(.bottom, 24).fixedScreenWidth()
                        }
                    }
                } else {
                PillTabs(items: pages, selection: $page).padding(.vertical, 8)
                TabView(selection: $page) {
                    ForEach(d.sections) { s in
                        ScrollView {
                            LazyVStack(alignment: .leading, spacing: 10) {
                                if let n = s.note { Text(n).font(.footnote).foregroundStyle(Theme.muted) }
                                if s.matches.isEmpty { EmptyState(icon: "hourglass", title: "Après les quarts de finale") }
                                ForEach(s.matches) { m in
                                    if let b = m.bout {
                                        NavigationLink(value: Route.bout(b.id)) { BracketMatchCard(match: m) }.buttonStyle(.plain)
                                    } else {
                                        BracketMatchCard(match: m)
                                    }
                                }
                            }
                            .padding(.horizontal, 16).padding(.bottom, 24)
                            .fixedScreenWidth()
                        }
                        .tag(s.key)
                    }
                    ScrollView {
                        VStack(alignment: .leading, spacing: 14) {
                            ForEach(d.rankings) { g in RankingCard(group: g, slug: slug) }
                        }
                        .padding(.horizontal, 16).padding(.bottom, 24)
                        .fixedScreenWidth()
                    }
                    .tag("rank")
                }
                .tabViewStyle(.page(indexDisplayMode: .never))
                }
            } else if let error {
                ErrorCard(message: error) { Task { await load() } }.padding(16)
                Spacer()
            } else {
                ProgressView().padding(.top, 80)
                Spacer()
            }
        }
        .screenBackground(glow: 0.4)
        .navigationTitle("")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar(.visible, for: .navigationBar)
        .polling(every: 4) { await load() }
        .sheet(isPresented: $download) {
            if let path = d?.pdf { BracketPDFSheet(path: path, title: d?.label ?? "Tableau") }
        }
    }

    /// Deux affichages : l'arbre (forme du tableau, zoomable) ou les combats par tour ; téléchargement du PDF à jour.
    private func modeBar(_ d: BracketDetail) -> some View {
        HStack(spacing: 8) {
            Picker("Affichage", selection: $mode) {
                Text("Tableau").tag("tree")
                Text("Par tours").tag("rounds")
            }
            .pickerStyle(.segmented)
            if d.pdf != nil {
                Button { download = true } label: {
                    Label("Télécharger", systemImage: "arrow.down.doc.fill").font(.subheadline.weight(.semibold))
                        .padding(.horizontal, 12).frame(height: 34)
                        .foregroundStyle(.white).background(Theme.red, in: Capsule())
                }
                .buttonStyle(.plain)
                .accessibilityLabel("Télécharger le tableau à jour (PDF)")
            }
        }
        .padding(.horizontal, 16).padding(.top, 6)
    }

    private func header(_ d: BracketDetail) -> some View {
        HStack(spacing: 10) {
            VStack(alignment: .leading, spacing: 2) {
                Text(d.label).font(.score(22, weight: 900)).foregroundStyle(Theme.ink).fixedSize(horizontal: false, vertical: true)
                Text(subtitle(d)).font(.caption).foregroundStyle(Theme.muted)
            }
            Spacer()
            if d.complete { Chip(text: "Terminé", color: Theme.green, fill: Theme.greenSoft) }
            else if d.sections.contains(where: { $0.matches.contains(where: \.isLive) }) { LiveBadge() }
        }
        .padding(.horizontal, 16).padding(.top, 8)
    }

    private func subtitle(_ d: BracketDetail) -> String {
        let kind: String
        switch d.kind {
        case "bracket": kind = d.size.map { "Tableau à double repêchage · \($0) cases" } ?? "Tableau"
        case "pools_final": kind = "2 poules puis tableau final croisé"
        case "pools": kind = "Poules"
        default: kind = "Poule"
        }
        return [kind, d.mat.map { "Tapis \($0)" }].compactMap { $0 }.joined(separator: " · ")
    }

    private func load() async {
        do {
            let fresh = try await API.bracket(slug, id)
            let first = d == nil
            withAnimation(.snappy) { d = fresh }
            error = nil
            if first || page.isEmpty {
                // Ouvre le tour en cours (premier tour avec un combat à disputer), sinon le classement
                page = fresh.sections.first(where: \.hasOpenMatch)?.key ?? "rank"
            }
        } catch { if d == nil { self.error = readable(error) } }
    }
}

// MARK: - Carte de combat

struct BracketMatchCard: View {
    let match: BracketMatch
    @Environment(Favorites.self) private var favorites

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 8) {
                Text(match.label).font(.caption.weight(.heavy)).foregroundStyle(match.isLive ? Theme.red : Theme.muted)
                Spacer(minLength: 4)
                status
            }
            row("a")
            Divider().overlay(Theme.line)
            row("b")
        }
        .card(padding: 14)
        .overlay(RoundedRectangle(cornerRadius: Theme.radius, style: .continuous).stroke(match.isLive ? Theme.red : .clear, lineWidth: 2))
    }

    @ViewBuilder private var status: some View {
        if match.isLive {
            LiveBadge(label: "EN COURS")
        } else if match.bout?.status == "validated", let m = match.bout?.method {
            Chip(text: m, color: Theme.green, fill: Theme.greenSoft)
        } else if let b = match.bout, let before = b.boutsBefore {
            Chip(text: (b.mat.map { "Tapis \($0) · " } ?? "") + (before == 0 ? "Sur le tapis" : "dans \(before) combat\(before > 1 ? "s" : "")"), color: Theme.red, fill: Theme.redSoft)
        } else if match.bout == nil && (match.a.type == "pending" || match.b.type == "pending") {
            Chip(text: "À venir")
        }
    }

    @ViewBuilder private func row(_ side: String) -> some View {
        let s = side == "a" ? match.a : match.b
        let win = match.winner == side
        let lost = match.winner != nil && !win && match.bout != nil
        HStack(spacing: 8) {
            BeltMark(side: side, width: 20)
            if let a = s.athlete {
                ClubLogo(path: a.clubLogo, name: a.club, size: 38)
                VStack(alignment: .leading, spacing: 1) {
                    HStack(spacing: 6) {
                        // NOM Prénom, même taille sur toutes les lignes (retour à la ligne seulement si le nom est très long)
                        Text("\(Text(a.lastName).fontWeight(.heavy)) \(a.firstName)")
                            .font(.system(size: 16, weight: win ? .semibold : .regular)).foregroundStyle(Theme.ink)
                            .lineLimit(2).fixedSize(horizontal: false, vertical: true)
                        if favorites.contains(a.registrationId) { Image(systemName: "star.fill").font(.caption).foregroundStyle(Theme.yellow) }
                    }
                    HStack(spacing: 6) {
                        if let seed = s.seed { Text("TS \(seed)").font(.system(size: 10, weight: .heavy)).foregroundStyle(.white).padding(.horizontal, 5).padding(.vertical, 2).background(Theme.red, in: Capsule()) }
                        Text(a.clubShort ?? a.club ?? "").font(.caption).foregroundStyle(Theme.muted).lineLimit(1)
                    }
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .layoutPriority(1)
                // Scores alignés d'une ligne à l'autre : colonne de largeur fixe + place réservée pour la coche
                if match.a.score != nil || match.b.score != nil || match.winner != nil {
                    Text(s.score ?? "").font(.score(20, weight: 800)).monospacedDigit()
                        .foregroundStyle(win ? Theme.ink : Theme.muted)
                        .lineLimit(1).minimumScaleFactor(0.7)
                        .frame(width: 58, alignment: .trailing)
                    Image(systemName: "checkmark.circle.fill").foregroundStyle(Theme.green)
                        .frame(width: 22).opacity(win ? 1 : 0).accessibilityHidden(!win)
                }
            } else {
                Text(s.type == "bye" ? "Exempt" : (s.text ?? "À venir")).font(.subheadline.italic()).foregroundStyle(Theme.faint)
                    .fixedSize(horizontal: false, vertical: true)
                Spacer()
            }
        }
        .opacity(lost ? 0.45 : 1)
        .accessibilityElement(children: .combine)
    }
}

// MARK: - Classement

struct RankingCard: View {
    let group: RankingGroup
    let slug: String

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text(group.title).font(.system(size: 18, weight: .bold)).foregroundStyle(Theme.ink)
                Spacer()
                if !group.complete { Chip(text: "Provisoire") }
            }
            ForEach(Array(group.rows.enumerated()), id: \.offset) { _, r in
                if let a = r.athlete {
                    HStack(spacing: 12) {
                        place(r.place)
                        ClubLogo(path: a.clubLogo, name: a.club, size: 34)
                        VStack(alignment: .leading, spacing: 1) {
                            Text("\(a.firstName) \(Text(a.lastName).fontWeight(.bold))").font(.system(size: 16)).foregroundStyle(Theme.ink)
                                .fixedSize(horizontal: false, vertical: true)
                            Text(a.clubShort ?? a.club ?? "").font(.caption).foregroundStyle(Theme.muted).lineLimit(1)
                        }
                        Spacer(minLength: 4)
                        FavoriteButton(athlete: FollowedAthlete(registrationId: a.registrationId, competition: slug, firstName: a.firstName, lastName: a.lastName,
                                                                clubShort: a.clubShort, clubLogo: a.clubLogo, category: nil))
                    }
                }
            }
            if !group.complete {
                Text("Places connues au fil des combats ; « – » : place pas encore attribuée. NC : non classé (à la fin).").font(.caption).foregroundStyle(Theme.muted)
            }
        }
        .card(padding: 14)
    }

    private func place(_ p: Int?) -> some View {
        let text = p.map { $0 == 1 ? "1er" : "\($0)e" } ?? (group.complete ? "NC" : "–")
        let color: Color = p == 1 ? Theme.gold : p == 2 ? Theme.silver : p == 3 ? Theme.bronze : Theme.surface2
        return Text(text).font(.score(15, weight: 800))
            .foregroundStyle((p ?? 9) <= 3 ? .white : Theme.ink)
            .frame(width: 46, height: 30)
            .background(color, in: Capsule())
    }
}
