import SwiftUI

struct CompetitionsView: View {
    enum Filter: Hashable { case live, upcoming, done }
    @State private var list: [Competition] = []
    @State private var filter: Filter = .live
    @State private var error: String?
    @State private var loaded = false

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 12) {
                    PillTabs(items: [(.live, "En cours"), (.upcoming, "À venir"), (.done, "Terminées")], selection: $filter)
                        .padding(.horizontal, -16)
                    if let error, list.isEmpty { ErrorCard(message: error) { Task { await load() } } }
                    let shown = list.filter { c in
                        switch filter { case .live: c.live; case .upcoming: c.isUpcoming; case .done: c.isFinished }
                    }
                    if loaded && shown.isEmpty {
                        EmptyState(icon: "calendar", title: filter == .live ? "Aucune compétition en cours" : filter == .upcoming ? "Aucune compétition à venir" : "Aucune compétition terminée")
                    }
                    ForEach(shown) { c in
                        NavigationLink(value: Route.competition(c.slug)) { CompetitionCard(c: c) }.buttonStyle(.plain)
                    }
                }
                .padding(16)
            }
            .screenBackground(glow: 0.5)
            .navigationTitle("Compétitions")
            .refreshable { await load() }
            .task { await load(); if !list.contains(where: \.live) { filter = .upcoming } }
            .navigationDestination(for: Route.self) { $0.view }
        }
    }

    private func load() async {
        do { list = try await API.competitions(); error = nil } catch { let m = readable(error); if !m.isEmpty { self.error = m } }
        loaded = true
    }
}

/// Onglet « Direct » : la compétition en cours, tapis par tapis.
struct DirectTabView: View {
    @State private var slug: String?
    @State private var name: String?
    @State private var checked = false

    var body: some View {
        NavigationStack {
            Group {
                if let slug {
                    ScrollView { LiveMatsView(slug: slug).padding(.vertical, 12) }
                } else if checked {
                    EmptyState(icon: "dot.radiowaves.left.and.right", title: "Rien en direct pour le moment", message: "Revenez le jour de la compétition : chaque tapis s'affichera ici en temps réel.")
                } else { ProgressView() }
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity)
            .screenBackground(glow: 0.5)
            .navigationTitle("Direct")
            .navigationSubtitleCompat(name)
            .navigationDestination(for: Route.self) { $0.view }
            .polling(every: 30) {
                if let h = try? await API.home() { slug = h.live.first?.slug; name = h.live.first?.name }
                checked = true
            }
        }
    }
}

extension View {
    @ViewBuilder func navigationSubtitleCompat(_ s: String?) -> some View {
        if #available(iOS 26.0, *), let s { self.navigationSubtitle(s) } else { self }
    }
}

struct CompetitionView: View {
    enum Tab: Hashable { case overview, live, pools, bouts, athletes, results }
    let slug: String
    @State private var detail: CompetitionDetail?
    @State private var tab: Tab = .overview
    @State private var error: String?

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 14) {
                if let d = detail { header(d) }
                else if let error { ErrorCard(message: error) { Task { await load() } }.padding(.horizontal, 16) }
                else { ProgressView().frame(maxWidth: .infinity).padding(.top, 60) }

                if let d = detail {
                    PillTabs(items: tabs(d), selection: $tab)
                    Group {
                        switch tab {
                        case .overview: OverviewTab(d: d)
                        case .live: LiveMatsView(slug: slug)
                        case .pools: PoolsTab(slug: slug)
                        case .bouts: BoutsTab(slug: slug, mats: d.matsCount)
                        case .athletes: AthletesTab(slug: slug)
                        case .results: ResultsTab(slug: slug)
                        }
                    }
                    .padding(.bottom, 24)
                }
            }
            .padding(.top, 8)
        }
        .screenBackground(glow: 0.7)
        .navigationTitle(detail?.name ?? "")
        .navigationBarTitleDisplayMode(.inline)
        .refreshable { await load() }
        .task { await load() }
    }

    private func tabs(_ d: CompetitionDetail) -> [(Tab, String)] {
        var t: [(Tab, String)] = [(.overview, "Vue d'ensemble")]
        if d.live && d.livePublic { t.append((.live, "Direct")) }
        if d.poolsPublished { t += [(.pools, "Poules"), (.bouts, "Combats")] }
        t.append((.athletes, "Judokas"))
        if d.poolsPublished { t.append((.results, "Résultats")) }
        return t
    }

    private func header(_ d: CompetitionDetail) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 8) {
                if d.live { LiveBadge() } else { Chip(text: d.statusLabel, color: d.registrationsOpen ? Theme.green : Theme.muted, fill: d.registrationsOpen ? Theme.greenSoft : Theme.surface) }
                if let e = d.edition { Chip(text: e, fill: Theme.surface) }
            }
            Text(d.name).font(.score(26, weight: 900)).foregroundStyle(Theme.ink).fixedSize(horizontal: false, vertical: true)
            HStack(spacing: 6) {
                Image(systemName: "calendar")
                Text(DateFormatter.apiDay.date(from: d.date).map { DateFormatter.longDay.string(from: $0).capitalizedFirst } ?? d.date)
                if let t = d.startTime { Text("· \(t)") }
            }
            .font(.subheadline).foregroundStyle(Theme.muted)
            if !d.summary.place.isEmpty {
                Label(d.summary.place, systemImage: "mappin.and.ellipse").font(.subheadline).foregroundStyle(Theme.muted)
            }
        }
        .padding(.horizontal, 16)
    }

    private func load() async {
        do {
            let d = try await API.competition(slug)
            detail = d; error = nil
            if d.live && d.livePublic && tab == .overview && detail != nil { tab = .live }
        } catch { let m = readable(error); if !m.isEmpty { self.error = m } }
    }
}

extension String {
    var capitalizedFirst: String { prefix(1).uppercased() + dropFirst() }
}

struct OverviewTab: View {
    let d: CompetitionDetail
    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 10) {
                StatTile(value: "\(d.athletesCount)", label: "judokas")
                StatTile(value: "\(d.clubsCount)", label: "clubs")
                StatTile(value: "\(d.matsCount)", label: "tapis")
                StatTile(value: d.boutsTotal > 0 ? "\(d.boutsDone)/\(d.boutsTotal)" : "—", label: "combats joués")
            }
            if d.boutsTotal > 0 {
                VStack(alignment: .leading, spacing: 8) {
                    HStack {
                        Text("Avancement").font(.subheadline.weight(.semibold))
                        Spacer()
                        Text("\(Int(Double(d.boutsDone) / Double(d.boutsTotal) * 100)) %").font(.score(16, weight: 800))
                    }
                    ProgressView(value: Double(d.boutsDone), total: Double(d.boutsTotal)).tint(Theme.red)
                }
                .card()
            }
            if let a = d.address ?? d.venue {
                VStack(alignment: .leading, spacing: 6) {
                    Text("Lieu").font(.headline)
                    Text([d.venue, a == d.venue ? nil : a, d.city].compactMap { $0 }.joined(separator: "\n")).font(.subheadline).foregroundStyle(Theme.muted)
                }
                .card()
            }
            if let p = d.practicalInfo, !p.isEmpty {
                VStack(alignment: .leading, spacing: 6) {
                    Text("Infos pratiques").font(.headline)
                    Text(markdown(p)).font(.subheadline).foregroundStyle(Theme.muted)
                }
                .card()
            }
            if let t = d.description, !t.isEmpty {
                VStack(alignment: .leading, spacing: 6) {
                    Text("Présentation").font(.headline)
                    Text(markdown(t)).font(.subheadline).foregroundStyle(Theme.ink)
                }
                .card()
            }
            if !d.poolsPublished && !d.isFinishedLike {
                Label("Les poules et l'ordre des combats seront publiés avant le début de la compétition.", systemImage: "clock")
                    .font(.subheadline).foregroundStyle(Theme.muted).card()
            }
        }
        .padding(.horizontal, 16)
    }

    private func markdown(_ s: String) -> AttributedString {
        (try? AttributedString(markdown: s, options: .init(interpretedSyntax: .inlineOnlyPreservingWhitespace))) ?? AttributedString(s)
    }
}

extension CompetitionDetail { var isFinishedLike: Bool { ["finished", "archived"].contains(status) } }

/// Tous les tapis d'une compétition : combat en cours en grand, puis les suivants.
struct LiveMatsView: View {
    let slug: String
    @State private var mats: [Mat] = []
    @State private var selected = 0
    @State private var error: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            if mats.count > 1 {
                PillTabs(items: [(0, "Tous les tapis")] + mats.map { ($0.number, "Tapis \($0.number)") }, selection: $selected)
            }
            if let error, mats.isEmpty { ErrorCard(message: error).padding(.horizontal, 16) }
            ForEach(mats.filter { selected == 0 || $0.number == selected }) { m in
                VStack(alignment: .leading, spacing: 10) {
                    HStack {
                        Text("Tapis \(m.number)").font(.score(20, weight: 900)).foregroundStyle(Theme.ink)
                        Spacer()
                        Text(modeLabel(m)).font(.caption.weight(.semibold)).foregroundStyle(m.mode == "running" ? Theme.red : Theme.muted)
                    }
                    // Vidéo du tapis : lecteur en vue « un tapis », raccourci en vue « tous les tapis »
                    if let v = m.video {
                        if selected != 0 && v.youtubeId != nil {
                            MatVideoCard(mat: m.number, video: v)
                        } else {
                            if v.review { VarBanner() }
                            if v.youtubeId != nil {
                                Button { selected = m.number } label: {
                                    Label("Regarder le tapis \(m.number) en vidéo", systemImage: "play.rectangle.fill")
                                        .font(.subheadline.weight(.bold)).foregroundStyle(.white)
                                        .frame(maxWidth: .infinity).padding(.vertical, 11)
                                        .background(Theme.red, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
                                }
                                .buttonStyle(.plain)
                            }
                        }
                    }
                    if let c = m.current {
                        NavigationLink(value: Route.bout(c.id)) { Scoreboard(bout: c, large: selected != 0) }.buttonStyle(.plain)
                    } else {
                        Text("Aucun combat programmé.").font(.subheadline).foregroundStyle(Theme.muted).card()
                    }
                    if !m.next.isEmpty {
                        Text("À suivre").font(.caption.weight(.heavy)).foregroundStyle(Theme.muted).tracking(0.6).padding(.top, 2)
                        ForEach(m.next.prefix(selected == 0 ? 1 : 5)) { n in
                            NavigationLink(value: Route.bout(n.id)) { BoutRow(bout: n) }.buttonStyle(.plain)
                        }
                    }
                }
                .padding(.horizontal, 16)
            }
        }
        .polling(every: 2) { await load() }
    }

    private func modeLabel(_ m: Mat) -> String {
        switch m.mode {
        case "running": return "Hajime · combat en cours"
        case "paused": return "Matte"
        case "result": return "Résultat"
        case "next": return "Prochain combat"
        case "waiting": return "En attente"
        default: return ""
        }
    }

    private func load() async {
        do { let l = try await API.live(slug); ServerClock.shared.sync(l.serverNowMs); mats = l.mats; error = nil }
        catch { let m = readable(error); if !m.isEmpty { self.error = m } }
    }
}
