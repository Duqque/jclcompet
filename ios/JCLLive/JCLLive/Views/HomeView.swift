import SwiftUI

struct HomeView: View {
    var openTab: (Int) -> Void
    @State private var home: Home?
    @State private var podiums: [Podium] = []
    @State private var error: String?
    @State private var settings = false
    @State private var search = false
    @State private var showFavorites = false
    @Environment(Favorites.self) private var favorites

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 18) {
                    header
                    if let error, home == nil { ErrorCard(message: error) { Task { await load() } } }
                    if let home {
                        ForEach(home.live) { block in liveHero(block) }
                        if home.live.isEmpty { noLive }
                        if !podiums.isEmpty { spotlight }
                        let upcoming = home.competitions.filter(\.isUpcoming)
                        if !upcoming.isEmpty {
                            SectionTitle(title: "À venir")
                            ForEach(upcoming) { c in NavigationLink(value: Route.competition(c.slug)) { CompetitionCard(c: c) }.buttonStyle(.plain) }
                        }
                        let done = home.competitions.filter(\.isFinished).prefix(3)
                        if !done.isEmpty {
                            SectionTitle(title: "Dernières compétitions")
                            ForEach(Array(done)) { c in NavigationLink(value: Route.competition(c.slug)) { CompetitionCard(c: c) }.buttonStyle(.plain) }
                        }
                    } else if error == nil {
                        ProgressView().frame(maxWidth: .infinity).padding(.top, 80)
                    }
                }
                .padding(16)
            }
            .screenBackground(glow: 0.9)
            .refreshable { await load() }
            .polling(every: 5) { await load() }
            .navigationDestination(for: Route.self) { $0.view }
            .toolbar(.hidden, for: .navigationBar)
            .sheet(isPresented: $settings) { SettingsView() }
            .sheet(isPresented: $search) { SearchView().environment(favorites) }
            .sheet(isPresented: $showFavorites) { FavoritesView().environment(favorites) }
        }
    }

    private var header: some View {
        HStack(spacing: 12) {
            Image("Logo").resizable().scaledToFit().padding(6)
                .frame(width: 50, height: 50)
                .background(Color.white, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
                .shadow(color: .black.opacity(0.06), radius: 8, y: 3)
            VStack(alignment: .leading, spacing: 0) {
                Text("JCL Live").font(.score(26, weight: 900)).foregroundStyle(Theme.ink)
                Text("Judo Club Leforest").font(.caption).foregroundStyle(Theme.muted)
            }
            Spacer(minLength: 4)
            HStack(spacing: 8) {
                Button { search = true } label: {
                    Image(systemName: "magnifyingglass").font(.system(size: 16, weight: .semibold)).foregroundStyle(Theme.ink)
                        .frame(width: 40, height: 40).background(Theme.surface, in: Circle())
                }
                .accessibilityLabel("Rechercher un judoka")
                Button { showFavorites = true } label: {
                    Image(systemName: "star.fill").font(.system(size: 16, weight: .semibold))
                        .foregroundStyle(favorites.items.isEmpty ? Theme.ink : Theme.yellow)
                        .frame(width: 40, height: 40).background(Theme.surface, in: Circle())
                }
                .accessibilityLabel(favorites.items.isEmpty ? "Favoris" : "Favoris, \(favorites.items.count) judoka\(favorites.items.count > 1 ? "s" : "") suivi\(favorites.items.count > 1 ? "s" : "")")
                Button { settings = true } label: {
                    Image(systemName: "gearshape.fill").font(.system(size: 16)).foregroundStyle(Theme.ink)
                        .frame(width: 40, height: 40).background(Theme.surface, in: Circle())
                }
                .accessibilityLabel("Réglages")
            }
        }
        .padding(.top, 4)
    }

    private func liveHero(_ b: Home.LiveBlock) -> some View {
        VStack(alignment: .leading, spacing: 14) {
            NavigationLink(value: Route.competition(b.slug)) {
                VStack(alignment: .leading, spacing: 10) {
                    HStack { LiveBadge(inverted: true); Spacer(); Image(systemName: "chevron.right").font(.subheadline.weight(.bold)).opacity(0.8) }
                    Text(b.name).font(.score(24, weight: 900)).multilineTextAlignment(.leading).fixedSize(horizontal: false, vertical: true)
                    Text([b.venue, b.city].compactMap { $0 }.joined(separator: " · ")).font(.subheadline).opacity(0.85)
                    HStack(spacing: 14) {
                        Label("\(b.matsCount) tapis", systemImage: "square.grid.2x2")
                        Label("\(b.athletesCount) judokas", systemImage: "person.2")
                        Label("\(b.clubsCount) clubs", systemImage: "shield")
                    }
                    .font(.caption.weight(.semibold)).opacity(0.9)
                }
                .foregroundStyle(.white)
                .padding(18)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background {
                    ZStack {
                        LinearGradient(colors: [Color(hex: 0xE2364A), Theme.red, Color(hex: 0x7D0F1D)], startPoint: .topLeading, endPoint: .bottomTrailing)
                        RadialGradient(colors: [Color.white.opacity(0.35), .clear], center: UnitPoint(x: 0.95, y: 0), startRadius: 0, endRadius: 260)
                    }
                    .clipShape(RoundedRectangle(cornerRadius: 24, style: .continuous))
                }
                .shadow(color: Theme.red.opacity(0.3), radius: 18, y: 10)
            }
            .buttonStyle(.plain)

            let mats = b.mats.filter { $0.current != nil }
            if !mats.isEmpty {
                HStack {
                    Text("Sur les tapis").font(.headline).foregroundStyle(Theme.ink)
                    Spacer()
                    Button("Tout le direct") { openTab(2) }.font(.subheadline.weight(.semibold))
                }
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 12) {
                        ForEach(mats) { m in
                            NavigationLink(value: Route.bout(m.current!.id)) {
                                VStack(alignment: .leading, spacing: 8) {
                                    HStack {
                                        Text("Tapis \(m.number)").font(.caption.weight(.heavy)).foregroundStyle(Theme.red)
                                        Spacer()
                                        StatusChip(bout: m.current!)
                                    }
                                    FighterLine(fighter: m.current!.a, side: "a", winner: m.current!.winner, showScore: m.current!.isDone || m.current!.isLive)
                                    FighterLine(fighter: m.current!.b, side: "b", winner: m.current!.winner, showScore: m.current!.isDone || m.current!.isLive)
                                }
                                .frame(maxHeight: .infinity, alignment: .top)
                                .card(padding: 14)
                                .frame(width: 300)
                            }
                            .buttonStyle(.plain)
                        }
                    }
                    .fixedSize(horizontal: false, vertical: true) // toutes les cartes à la hauteur de la plus grande
                    .padding(.horizontal, 16).padding(.vertical, 4)
                }
                .padding(.horizontal, -16)
            }
        }
    }

    private var noLive: some View {
        VStack(alignment: .leading, spacing: 6) {
            Text("Pas de compétition en direct").font(.headline).foregroundStyle(Theme.ink)
            Text("Les combats s'afficheront ici dès le premier hajime.").font(.subheadline).foregroundStyle(Theme.muted)
        }
        .card()
    }

    private var spotlight: some View {
        VStack(alignment: .leading, spacing: 10) {
            SectionTitle(title: "Derniers podiums")
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 12) {
                    ForEach(podiums.prefix(8)) { p in PodiumCard(podium: p).frame(width: 290) }
                }
                .fixedSize(horizontal: false, vertical: true)
                .padding(.horizontal, 16).padding(.vertical, 4)
            }
            .padding(.horizontal, -16)
        }
    }

    private func load() async {
        do {
            let h = try await API.home()
            ServerClock.shared.sync(h.serverNowMs)
            home = h; error = nil
            if let slug = h.live.first?.slug, let r = try? await API.results(slug) {
                podiums = Array(r.podiums.sorted { ($0.at ?? "") > ($1.at ?? "") })
            } else { podiums = [] }
        } catch {
            let msg = readable(error); if !msg.isEmpty { self.error = msg }
        }
    }
}

struct CompetitionCard: View {
    let c: Competition
    var body: some View {
        HStack(spacing: 14) {
            VStack(spacing: 0) {
                Text(c.day.map { DateFormatter.dayNumber.string(from: $0) } ?? "–").font(.score(24, weight: 900)).foregroundStyle(c.live ? .white : Theme.ink)
                Text(c.day.map { DateFormatter.monthShort.string(from: $0).uppercased() } ?? "").font(.system(size: 11, weight: .heavy)).foregroundStyle(c.live ? .white.opacity(0.85) : Theme.red)
            }
            .frame(width: 58, height: 62)
            .background(c.live ? Theme.red : Theme.redSoft, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
            VStack(alignment: .leading, spacing: 4) {
                Text(c.name).font(.system(size: 16, weight: .bold)).foregroundStyle(Theme.ink).lineLimit(2).multilineTextAlignment(.leading)
                if !c.place.isEmpty { Text(c.place).font(.caption).foregroundStyle(Theme.muted).lineLimit(1) }
                HStack(spacing: 6) {
                    if c.live { LiveBadge() } else { Chip(text: c.statusLabel, color: c.registrationsOpen ? Theme.green : Theme.muted, fill: c.registrationsOpen ? Theme.greenSoft : Theme.surface2) }
                    if c.athletesCount > 0 { Chip(text: "\(c.athletesCount) judokas") }
                }
            }
            Spacer(minLength: 0)
            Image(systemName: "chevron.right").font(.footnote.weight(.bold)).foregroundStyle(Theme.faint)
        }
        .card(padding: 14)
    }
}

struct PodiumCard: View {
    let podium: Podium
    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text(podium.title.isEmpty ? "Poule \(podium.poolNumber)" : podium.title).font(.subheadline.weight(.bold)).foregroundStyle(Theme.ink).lineLimit(1)
                Spacer()
                Text("Poule \(podium.poolNumber)").font(.caption).foregroundStyle(Theme.muted)
            }
            ForEach(podium.athletes.filter { $0.place <= 3 }, id: \.self) { a in
                HStack(spacing: 10) {
                    Text("\(a.place)").font(.score(18, weight: 900)).foregroundStyle(.white)
                        .frame(width: 30, height: 30).background(Theme.medal(a.medal), in: Circle())
                    ClubLogo(path: a.logo, name: a.club, size: 30)
                    VStack(alignment: .leading, spacing: 0) {
                        Text("\(a.firstName) \(Text(a.lastName).fontWeight(.bold))").font(.system(size: 14)).foregroundStyle(Theme.ink)
                            .fixedSize(horizontal: false, vertical: true)
                        Text(a.clubShort ?? a.club ?? "").font(.caption2).foregroundStyle(Theme.muted).lineLimit(1)
                    }
                    Spacer(minLength: 0)
                }
            }
        }
        .frame(maxHeight: .infinity, alignment: .top)
        .card(padding: 14)
    }
}

extension DateFormatter {
    static let dayNumber: DateFormatter = { let f = DateFormatter(); f.locale = Locale(identifier: "fr_FR"); f.dateFormat = "d"; return f }()
    static let monthShort: DateFormatter = { let f = DateFormatter(); f.locale = Locale(identifier: "fr_FR"); f.dateFormat = "MMM"; return f }()
}

/// Destinations de navigation partagées par tous les onglets.
enum Route: Hashable {
    case competition(String)
    case bout(Int)
    case athlete(Int)
    case pool(String, Int)
    case bracket(String, Int)

    @ViewBuilder var view: some View {
        switch self {
        case let .bracket(slug, id): BracketDetailView(slug: slug, id: id)
        case let .competition(slug): CompetitionView(slug: slug)
        case let .bout(id): BoutView(id: id)
        case let .athlete(id): AthleteView(id: id)
        case let .pool(slug, id): PoolView(slug: slug, poolId: id)
        }
    }
}
