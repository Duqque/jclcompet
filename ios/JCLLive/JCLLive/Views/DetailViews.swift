import SwiftUI

/// Page combat : tableau d'affichage en direct, déroulé horodaté, autres combats des deux judokas.
struct BoutView: View {
    let id: Int
    @State private var d: BoutDetail?
    @State private var error: String?
    @AppStorage(SideColor.key) private var firstColor = SideColor.defaultValue

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                if let d {
                    HStack(spacing: 8) {
                        StatusChip(bout: d.bout)
                        if let m = d.bout.mat { Chip(text: "Tapis \(m)", color: Theme.red, fill: Theme.redSoft) }
                        if let p = d.bout.poolNumber { Chip(text: "Poule \(p)" + (d.bout.poolPosition.map { " · combat \($0)/\(d.bout.poolBouts ?? 0)" } ?? ""), fill: Theme.surface) }
                    }
                    Scoreboard(bout: d.bout)
                    fighters(d)
                    timeline(d)
                    others(d.bout.a, d.otherA, d)
                    others(d.bout.b, d.otherB, d)
                } else if let error { ErrorCard(message: error) { Task { await load() } } }
                else { ProgressView().frame(maxWidth: .infinity).padding(.top, 80) }
            }
            .padding(16)
        }
        .screenBackground(glow: 0.5)
        .navigationTitle("Combat")
        .navigationBarTitleDisplayMode(.inline)
        .polling(every: 2) { await load() }
    }

    private func fighters(_ d: BoutDetail) -> some View {
        HStack(spacing: 10) {
            ForEach([("a", d.bout.a), ("b", d.bout.b)], id: \.0) { side, f in
                if let f {
                    NavigationLink(value: Route.athlete(f.registrationId)) {
                        HStack(spacing: 8) {
                            BeltMark(side: side)
                            Text(f.lastName).font(.system(size: 13, weight: .bold)).foregroundStyle(Theme.ink)
                                .lineLimit(1).minimumScaleFactor(0.5).fixedSize(horizontal: false, vertical: true)
                            Spacer(minLength: 0)
                            FavoriteButton(athlete: FollowedAthlete(f, competition: d.competition.slug, category: d.bout.categoryLine))
                        }
                        .padding(.leading, 12).padding(.trailing, 2).padding(.vertical, 4)
                        .background(Theme.surface, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
                    }
                    .buttonStyle(.plain)
                }
            }
        }
    }

    private func timeline(_ d: BoutDetail) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Déroulé du combat").font(.headline).foregroundStyle(Theme.ink).padding(.bottom, 10)
            if d.timeline.isEmpty {
                Text(d.bout.isDone ? "Aucune action enregistrée." : "Le déroulé s'affichera au hajime.").font(.subheadline).foregroundStyle(Theme.muted)
            }
            ForEach(Array(d.timeline.enumerated()), id: \.offset) { i, t in
                HStack(alignment: .top, spacing: 12) {
                    Text(t.clockMs.map { clockText($0).padding(toLength: 5, withPad: " ", startingAt: 0) } ?? "")
                        .font(.system(size: 13, weight: .semibold, design: .monospaced)).foregroundStyle(Theme.muted).frame(width: 46, alignment: .leading)
                    VStack(spacing: 0) {
                        Circle().fill(dot(t.side)).frame(width: 12, height: 12)
                            .overlay(Circle().stroke(t.side != nil && !SideColor.isRed(t.side, firstColor: firstColor) ? Color(hex: 0xD4D4D8) : .clear))
                        if i < d.timeline.count - 1 { Rectangle().fill(Theme.line).frame(width: 2).frame(maxHeight: .infinity) }
                    }
                    .frame(width: 12)
                    VStack(alignment: .leading, spacing: 1) {
                        Text(t.label).font(.system(size: 15, weight: isScore(t.type) ? .bold : .regular)).foregroundStyle(Theme.ink)
                        if let s = t.side, let f = s == "a" ? d.bout.a : d.bout.b { Text(f.fullName).font(.caption).foregroundStyle(Theme.muted) }
                    }
                    .padding(.bottom, 14)
                    Spacer(minLength: 0)
                }
            }
        }
        .card()
    }

    private func isScore(_ type: String) -> Bool { ["ippon", "wazaari", "yuko", "shido", "hansoku", "hansoku_make", "validate"].contains(type) }
    private func dot(_ side: String?) -> Color { side == nil ? Theme.ink : SideColor.isRed(side, firstColor: firstColor) ? Theme.red : .white }

    @ViewBuilder private func others(_ f: Fighter?, _ list: [Bout], _ d: BoutDetail) -> some View {
        let rest = list.filter { $0.id != d.bout.id }
        if let f, !rest.isEmpty {
            VStack(alignment: .leading, spacing: 8) {
                Text("Autres combats de \(f.firstName) \(f.lastName)").font(.headline).foregroundStyle(Theme.ink)
                ForEach(rest) { b in NavigationLink(value: Route.bout(b.id)) { BoutRow(bout: b) }.buttonStyle(.plain) }
            }
        }
    }

    private func load() async {
        do { let r = try await API.bout(id); ServerClock.shared.sync(r.serverNowMs); d = r; error = nil }
        catch { let m = readable(error); if !m.isEmpty { self.error = m } }
    }
}

/// Fiche judoka : club, catégorie, poule, classement, tous ses combats.
struct AthleteView: View {
    let id: Int
    @State private var d: AthleteDetail?
    @State private var error: String?

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                if let d {
                    let a = d.athlete
                    VStack(spacing: 12) {
                        ClubLogo(path: a.clubLogo, name: a.club, size: 84)
                        VStack(spacing: 2) {
                            Text(a.firstName).font(.title3).foregroundStyle(Theme.ink)
                            Text(a.lastName).font(.score(28, weight: 900)).foregroundStyle(Theme.ink)
                            Text(a.club ?? "").font(.subheadline).foregroundStyle(Theme.muted).multilineTextAlignment(.center)
                        }
                        HStack(spacing: 6) {
                            if !a.categoryLine.isEmpty { Chip(text: a.categoryLine, fill: Theme.surface2) }
                            if let g = a.grade { Chip(text: "Ceinture \(g.lowercased())", fill: Theme.surface2) }
                        }
                        FavoriteButton(athlete: FollowedAthlete(a))
                            .background(Theme.surface2, in: Circle())
                    }
                    .frame(maxWidth: .infinity)
                    .card(padding: 20)

                    HStack(spacing: 10) {
                        if (a.poolKind ?? "pool") == "pool" { StatTile(value: a.poolNumber.map(String.init) ?? "—", label: "poule") }
                        StatTile(value: a.mat.map(String.init) ?? "—", label: "tapis")
                        StatTile(value: a.place.map { "\($0)e" } ?? "—", label: a.medal.map { _ in Theme.medalLabel(a.place) } ?? "classement")
                    }
                    // Catégorie en tableau (ou 2 poules + tableau final) : le tableau complet de la catégorie
                    if let cat = a.categoryPoolId, a.poolKind != "pool" || cat != a.poolId {
                        NavigationLink(value: Route.bracket(d.competition.slug, cat)) {
                            Label("Voir le tableau de la catégorie", systemImage: "trophy").font(.subheadline.weight(.semibold))
                                .frame(maxWidth: .infinity).padding(.vertical, 12)
                                .background(Theme.redSoft, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
                                .foregroundStyle(Theme.red)
                        }
                    }
                    if (a.poolKind ?? "pool") == "pool", let p = a.poolNumber, let pool = a.poolId ?? d.bouts.first?.poolId {
                        NavigationLink(value: Route.pool(d.competition.slug, pool)) {
                            Label("Voir la feuille de la poule \(p)", systemImage: "tablecells").font(.subheadline.weight(.semibold))
                                .frame(maxWidth: .infinity).padding(.vertical, 12)
                                .background(Theme.redSoft, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
                                .foregroundStyle(Theme.red)
                        }
                    }
                    SectionTitle(title: "Combats", trailing: d.competition.name)
                    if d.bouts.isEmpty { EmptyState(icon: "figure.martial.arts", title: "Pas encore de combat", message: "Les combats apparaissent dès la publication des poules.") }
                    ForEach(d.bouts) { b in NavigationLink(value: Route.bout(b.id)) { BoutRow(bout: b) }.buttonStyle(.plain) }
                } else if let error { ErrorCard(message: error) { Task { await load() } } }
                else { ProgressView().frame(maxWidth: .infinity).padding(.top, 80) }
            }
            .padding(16)
        }
        .screenBackground(glow: 0.6)
        .navigationTitle(d?.athlete.fullName ?? "")
        .navigationBarTitleDisplayMode(.inline)
        .polling(every: 10) { await load() }
    }

    private func load() async {
        do { d = try await API.athlete(id); error = nil } catch { let m = readable(error); if !m.isEmpty { self.error = m } }
    }
}

/// Feuille de poule visuelle (grille des scores IWY-cartons), classement et ordre des combats.
struct PoolView: View {
    let slug: String
    let poolId: Int
    @State private var pool: Pool?
    @State private var error: String?

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                if let p = pool {
                    VStack(alignment: .leading, spacing: 4) {
                        Text("Poule \(p.number)").font(.score(28, weight: 900)).foregroundStyle(Theme.ink)
                        Text([p.title, p.mat.map { "Tapis \($0)" }].compactMap { $0 }.filter { !$0.isEmpty }.joined(separator: " · "))
                            .font(.subheadline).foregroundStyle(Theme.muted)
                    }
                    grid(p)
                    Text("Score : ippon, waza-ari, yuko puis cartons (ex. 100-1). Cases vertes : victoire.").font(.caption).foregroundStyle(Theme.muted)
                    SectionTitle(title: "Ordre des combats", trailing: "\(p.boutsDone)/\(p.boutsTotal)")
                    ForEach(p.order, id: \.boutId) { o in
                        NavigationLink(value: Route.bout(o.boutId)) { orderRow(p, o) }.buttonStyle(.plain)
                    }
                } else if let error { ErrorCard(message: error) { Task { await load() } } }
                else { ProgressView().frame(maxWidth: .infinity).padding(.top, 80) }
            }
            .padding(16)
        }
        .screenBackground(glow: 0.5)
        .navigationTitle(pool.map { "Poule \($0.number)" } ?? "Poule")
        .navigationBarTitleDisplayMode(.inline)
        .polling(every: 6) { await load() }
    }

    private func grid(_ p: Pool) -> some View {
        let n = p.athletes.count
        // Colonne des noms assez large pour le nom de famille le plus long (jamais tronqué).
        let longest = p.athletes.map(\.lastName.count).max() ?? 0
        let nameW = max(104, CGFloat(longest) * 8.5 + 48)
        return ScrollView(.horizontal, showsIndicators: false) {
            VStack(spacing: 0) {
                HStack(spacing: 0) {
                    Text("Judoka").frame(width: nameW, alignment: .leading)
                    ForEach(1...max(n, 1), id: \.self) { c in Text("\(c)").frame(width: 40) }
                    Text("V").frame(width: 26); Text("Pts").frame(width: 32); Text("Cl.").frame(width: 32)
                }
                .font(.caption.weight(.heavy)).foregroundStyle(Theme.muted)
                .padding(.vertical, 8).padding(.horizontal, 8)
                .background(Theme.surface2)
                ForEach(p.athletes) { a in
                    HStack(spacing: 0) {
                        HStack(spacing: 6) {
                            Text("\(a.seed)").font(.score(13, weight: 900)).foregroundStyle(Theme.red).frame(width: 12)
                            ClubLogo(path: a.clubLogo, name: a.club, size: 24)
                            VStack(alignment: .leading, spacing: 0) {
                                Text(a.lastName).font(.system(size: 12, weight: .bold)).lineLimit(1).minimumScaleFactor(0.6)
                                    .fixedSize(horizontal: false, vertical: true)
                                Text(a.firstName).font(.caption2).foregroundStyle(Theme.muted).lineLimit(1)
                            }
                        }
                        .frame(width: nameW, alignment: .leading)
                        ForEach(1...max(n, 1), id: \.self) { c in cell(p, row: a.seed, col: c) }
                        Text("\(a.wins)").font(.score(14, weight: 800)).frame(width: 26)
                        Text("\(a.points)").font(.score(14, weight: 800)).frame(width: 32)
                        Group {
                            if let r = a.rank { Text("\(r)").font(.score(14, weight: 900)).foregroundStyle(r <= 3 ? .white : Theme.ink)
                                .frame(width: 24, height: 24).background(r <= 3 ? Theme.medal(r == 1 ? "gold" : r == 2 ? "silver" : "bronze") : Theme.surface2, in: Circle())
                            } else { Text("–").foregroundStyle(Theme.faint) }
                        }
                        .frame(width: 32)
                    }
                    .foregroundStyle(Theme.ink)
                    .padding(.vertical, 8).padding(.horizontal, 8)
                    Divider()
                }
            }
            .background(Theme.surface)
            .clipShape(RoundedRectangle(cornerRadius: Theme.radius, style: .continuous))
        }
    }

    private func cell(_ p: Pool, row: Int, col: Int) -> some View {
        let c = p.cell(row, col)
        return ZStack {
            if row == col { Rectangle().fill(Theme.ink.opacity(0.08)) }
            else if let c {
                RoundedRectangle(cornerRadius: 7).fill(c.won == true ? Theme.greenSoft : Theme.surface2).padding(2)
                Text(c.score ?? (c.status == "in_progress" ? "•" : "")).font(.system(size: 11, weight: c.won == true ? .bold : .medium, design: .rounded)).minimumScaleFactor(0.7)
                    .foregroundStyle(c.won == true ? Theme.green : c.status == "in_progress" ? Theme.red : Theme.muted)
            }
        }
        .frame(width: 40, height: 34)
    }

    private func orderRow(_ p: Pool, _ o: PoolOrder) -> some View {
        let a = p.athletes.first { $0.seed == o.a }, b = p.athletes.first { $0.seed == o.b }
        return HStack(spacing: 10) {
            Text("\(o.order)").font(.score(15, weight: 900)).foregroundStyle(Theme.muted).frame(width: 22)
            VStack(alignment: .leading, spacing: 6) {
                name(a, side: "a", won: o.winner == "a")
                name(b, side: "b", won: o.winner == "b")
            }
            Spacer()
            if o.status == "in_progress" { LiveBadge(label: "EN COURS") }
            else if o.winner != nil { Image(systemName: "checkmark.circle.fill").foregroundStyle(Theme.green) }
            Image(systemName: "chevron.right").font(.caption.weight(.bold)).foregroundStyle(Theme.faint)
        }
        .card(padding: 12)
    }

    private func name(_ a: PoolAthlete?, side: String, won: Bool) -> some View {
        HStack(spacing: 8) {
            BeltMark(side: side, width: 16)
            Text(a.map { "\($0.firstName) \($0.lastName)" } ?? "—").font(.system(size: 14, weight: won ? .bold : .regular)).foregroundStyle(Theme.ink)
                .fixedSize(horizontal: false, vertical: true)
        }
    }

    private func load() async {
        do { pool = try await API.pools(slug).first { $0.id == poolId }; error = pool == nil ? "Poule introuvable." : nil }
        catch { let m = readable(error); if !m.isEmpty { self.error = m } }
    }
}
