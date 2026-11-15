import SwiftUI

struct PoolsTab: View {
    let slug: String
    @State private var pools: [Pool] = []
    @State private var query = ""
    @State private var error: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            SearchField(text: $query, prompt: "Nom, club, catégorie, n° de poule")
            if let error, pools.isEmpty { ErrorCard(message: error) }
            ForEach(filtered) { p in
                NavigationLink(value: Route.pool(slug, p.id)) { PoolCard(pool: p) }.buttonStyle(.plain)
            }
            if !pools.isEmpty && filtered.isEmpty { EmptyState(icon: "magnifyingglass", title: "Aucune poule ne correspond") }
        }
        .padding(.horizontal, 16)
        .polling(every: 10) {
            do { pools = try await API.pools(slug); error = nil } catch { let m = readable(error); if !m.isEmpty { self.error = m } }
        }
    }

    private var filtered: [Pool] {
        let q = query.folded
        guard !q.isEmpty else { return pools }
        return pools.filter { p in
            "\(p.number)" == q || p.title.folded.contains(q)
                || p.athletes.contains { "\($0.firstName) \($0.lastName) \($0.club ?? "") \($0.clubShort ?? "")".folded.contains(q) }
        }
    }
}

struct PoolCard: View {
    let pool: Pool
    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 10) {
                Text("\(pool.number)").font(.score(20, weight: 900)).foregroundStyle(.white)
                    .frame(width: 40, height: 40).background(Theme.red, in: RoundedRectangle(cornerRadius: 12, style: .continuous))
                VStack(alignment: .leading, spacing: 1) {
                    Text(pool.title.isEmpty ? "Poule \(pool.number)" : pool.title).font(.system(size: 15, weight: .bold)).foregroundStyle(Theme.ink)
                    Text([pool.mat.map { "Tapis \($0)" }, "\(pool.athletes.count) judokas", "\(pool.boutsDone)/\(pool.boutsTotal) combats"].compactMap { $0 }.joined(separator: " · "))
                        .font(.caption).foregroundStyle(Theme.muted)
                }
                Spacer()
                if pool.complete { Chip(text: "Terminée", color: Theme.green, fill: Theme.greenSoft) }
                else if pool.boutsDone > 0 { Chip(text: "En cours", color: Theme.red, fill: Theme.redSoft) }
            }
            HStack(spacing: 6) {
                ForEach(pool.athletes) { a in ClubLogo(path: a.clubLogo, name: a.club, size: 28) }
            }
        }
        .card(padding: 14)
    }
}

struct BoutsTab: View {
    enum Filter: Hashable { case upcoming, done, favorites }
    let slug: String
    let mats: Int
    @State private var bouts: [Bout] = []
    @State private var mat = 0
    @State private var filter: Filter = .upcoming
    @State private var query = ""
    @State private var error: String?
    @Environment(Favorites.self) private var favorites

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            PillTabs(items: [(.upcoming, "À venir"), (.done, "Terminés"), (.favorites, "Favoris")], selection: $filter)
                .padding(.horizontal, -16)
            if mats > 1 {
                PillTabs(items: [(0, "Tous")] + (1...mats).map { ($0, "Tapis \($0)") }, selection: $mat).padding(.horizontal, -16)
            }
            SearchField(text: $query, prompt: "Rechercher un judoka ou un club")
            if let error, bouts.isEmpty { ErrorCard(message: error) }
            ForEach(shown) { b in
                NavigationLink(value: Route.bout(b.id)) { BoutRow(bout: b) }.buttonStyle(.plain)
            }
            if shown.isEmpty && error == nil {
                EmptyState(icon: filter == .favorites ? "star" : "list.bullet",
                           title: filter == .favorites ? "Aucun combat de vos favoris" : "Aucun combat",
                           message: filter == .favorites ? "Suivez des judokas avec l'étoile pour retrouver leurs combats ici." : nil)
            }
        }
        .padding(.horizontal, 16)
        .task(id: "\(mat)-\(filter)") { await load() }
        .polling(every: 8) { await load() }
    }

    private var shown: [Bout] {
        var l = bouts
        if filter == .favorites { l = l.filter { $0.involves(favorites.ids) } }
        let q = query.folded
        if !q.isEmpty {
            l = l.filter { b in [b.a, b.b].contains { f in f.map { "\($0.firstName) \($0.lastName) \($0.club ?? "")".folded.contains(q) } ?? false } }
        }
        return l
    }

    private func load() async {
        do {
            let s: String? = filter == .upcoming ? "a_venir" : filter == .done ? "termines" : nil
            var l = try await API.bouts(slug, mat: mat == 0 ? nil : mat, status: s)
            if filter == .done { l.reverse() }
            bouts = l; error = nil
        } catch { let m = readable(error); if !m.isEmpty { self.error = m } }
    }
}

struct AthletesTab: View {
    let slug: String
    @State private var athletes: [Athlete] = []
    @State private var query = ""
    @State private var club = ""
    @State private var error: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            SearchField(text: $query, prompt: "Nom, prénom, club, catégorie")
            let clubs = Array(Set(athletes.compactMap { $0.clubShort ?? $0.club })).sorted()
            if clubs.count > 1 {
                PillTabs(items: [("", "Tous les clubs")] + clubs.map { ($0, $0) }, selection: $club).padding(.horizontal, -16)
            }
            if let error, athletes.isEmpty { ErrorCard(message: error) }
            Text("\(shown.count) judoka\(shown.count > 1 ? "s" : "")").font(.caption).foregroundStyle(Theme.muted)
            LazyVStack(spacing: 8) {
                ForEach(shown) { a in
                    NavigationLink(value: Route.athlete(a.registrationId)) { AthleteRow(athlete: a) }.buttonStyle(.plain)
                }
            }
        }
        .padding(.horizontal, 16)
        .task {
            do { athletes = try await API.athletes(slug); error = nil } catch { let m = readable(error); if !m.isEmpty { self.error = m } }
        }
    }

    private var shown: [Athlete] {
        let q = query.folded
        return athletes.filter { a in
            (club.isEmpty || (a.clubShort ?? a.club) == club)
                && (q.isEmpty || "\(a.firstName) \(a.lastName) \(a.club ?? "") \(a.clubShort ?? "") \(a.categoryLine)".folded.contains(q))
        }
    }
}

struct ResultsTab: View {
    enum Mode: Hashable { case podiums, points, medals }
    let slug: String
    @State private var results: Results?
    @State private var mode: Mode = .podiums
    @State private var error: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            if let r = results {
                HStack(spacing: 10) {
                    medalTile(r.totals.gold, "Or", Theme.gold)
                    medalTile(r.totals.silver, "Argent", Theme.silver)
                    medalTile(r.totals.bronze, "Bronze", Theme.bronze)
                }
                PillTabs(items: [(.podiums, "Podiums"), (.points, "Clubs · points"), (.medals, "Clubs · médailles")], selection: $mode)
                    .padding(.horizontal, -16)
                switch mode {
                case .podiums:
                    if r.podiums.isEmpty { EmptyState(icon: "trophy", title: "Pas encore de podium", message: "Ils s'affichent dès qu'une poule est validée.") }
                    ForEach(r.podiums) { PodiumCard(podium: $0) }
                case .points: clubTable(r.clubsPoints, points: true)
                case .medals: clubTable(r.clubsMedals, points: false)
                }
            } else if let error { ErrorCard(message: error) } else { ProgressView().frame(maxWidth: .infinity).padding(.top, 30) }
        }
        .padding(.horizontal, 16)
        .polling(every: 10) {
            do { results = try await API.results(slug); error = nil } catch { let m = readable(error); if !m.isEmpty { self.error = m } }
        }
    }

    private func medalTile(_ n: Int, _ label: String, _ c: Color) -> some View {
        VStack(spacing: 4) {
            Circle().fill(c).frame(width: 14, height: 14)
            Text("\(n)").font(.score(24, weight: 900)).foregroundStyle(Theme.ink)
            Text(label).font(.caption).foregroundStyle(Theme.muted)
        }
        .frame(maxWidth: .infinity).padding(.vertical, 12)
        .background(Theme.surface, in: RoundedRectangle(cornerRadius: 16, style: .continuous))
    }

    private func clubTable(_ rows: [ClubRank], points: Bool) -> some View {
        VStack(spacing: 0) {
            ForEach(Array(rows.enumerated()), id: \.element.id) { i, c in
                HStack(spacing: 10) {
                    Text("\((points ? c.rankPoints : c.rankMedals) ?? i + 1)").font(.score(16, weight: 900))
                        .foregroundStyle(i < 3 ? Theme.red : Theme.muted).frame(width: 28)
                    ClubLogo(path: c.clubLogo, name: c.club, size: 32)
                    VStack(alignment: .leading, spacing: 0) {
                        Text(c.clubShort ?? c.club).font(.system(size: 14, weight: .semibold)).foregroundStyle(Theme.ink).lineLimit(1)
                        Text("\(c.participants) judoka\(c.participants > 1 ? "s" : "")").font(.caption2).foregroundStyle(Theme.muted)
                    }
                    Spacer()
                    if points {
                        Text("\(c.points) pts").font(.score(15, weight: 800)).foregroundStyle(Theme.ink)
                    } else {
                        HStack(spacing: 8) {
                            medalCount(c.gold, Theme.gold); medalCount(c.silver, Theme.silver); medalCount(c.bronze, Theme.bronze)
                        }
                    }
                }
                .padding(.vertical, 10).padding(.horizontal, 12)
                if i < rows.count - 1 { Divider().padding(.leading, 50) }
            }
        }
        .background(Theme.surface, in: RoundedRectangle(cornerRadius: Theme.radius, style: .continuous))
    }

    private func medalCount(_ n: Int, _ c: Color) -> some View {
        HStack(spacing: 3) { Circle().fill(c).frame(width: 9, height: 9); Text("\(n)").font(.system(size: 13, weight: .bold)).monospacedDigit() }
    }
}

struct SearchField: View {
    @Binding var text: String
    let prompt: String
    var body: some View {
        HStack(spacing: 8) {
            Image(systemName: "magnifyingglass").foregroundStyle(Theme.faint)
            TextField(prompt, text: $text).textInputAutocapitalization(.never).autocorrectionDisabled()
            if !text.isEmpty { Button { text = "" } label: { Image(systemName: "xmark.circle.fill").foregroundStyle(Theme.faint) }.accessibilityLabel("Effacer") }
        }
        .padding(.horizontal, 12).padding(.vertical, 10)
        .background(Theme.surface, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
    }
}

extension String {
    /// Comparaison sans accents ni majuscules.
    var folded: String { folding(options: [.diacriticInsensitive, .caseInsensitive], locale: Locale(identifier: "fr_FR")).trimmingCharacters(in: .whitespaces) }
}
