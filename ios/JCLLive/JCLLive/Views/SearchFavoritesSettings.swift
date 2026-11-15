import SwiftUI
import UserNotifications

/// Recherche globale : judokas et clubs de toutes les compétitions en cours et à venir.
struct SearchView: View {
    @State private var query = ""
    @State private var athletes: [Athlete] = []
    @State private var comps: [String: Competition] = [:]
    @State private var loading = true

    var body: some View {
        NavigationStack {
            ScrollView {
                LazyVStack(alignment: .leading, spacing: 8) {
                    SearchField(text: $query, prompt: "Judoka, club, catégorie").padding(.bottom, 6)
                    if loading { ProgressView().frame(maxWidth: .infinity).padding(.top, 40) }
                    else if query.folded.count < 2 {
                        EmptyState(icon: "magnifyingglass", title: "Trouvez un judoka", message: "Tapez au moins deux lettres : nom, prénom ou club. \(athletes.count) judokas inscrits.")
                    } else if results.isEmpty {
                        EmptyState(icon: "person.fill.questionmark", title: "Aucun résultat pour « \(query) »")
                    }
                    ForEach(groups, id: \.0) { slug, list in
                        Text(comps[slug]?.name ?? slug).font(.caption.weight(.heavy)).foregroundStyle(Theme.muted).padding(.top, 8)
                        ForEach(list) { a in
                            NavigationLink(value: Route.athlete(a.registrationId)) { AthleteRow(athlete: a) }.buttonStyle(.plain)
                        }
                    }
                }
                .padding(16)
            }
            .scrollDismissesKeyboard(.immediately)
            .screenBackground(glow: 0.4)
            .navigationTitle("Recherche")
            .navigationDestination(for: Route.self) { $0.view }
            .task { await load() }
            .refreshable { await load() }
        }
    }

    private var results: [Athlete] {
        let q = query.folded
        guard q.count >= 2 else { return [] }
        return athletes.filter { "\($0.firstName) \($0.lastName) \($0.lastName) \($0.firstName) \($0.club ?? "") \($0.clubShort ?? "") \($0.categoryLine)".folded.contains(q) }
    }

    private var groups: [(String, [Athlete])] {
        Dictionary(grouping: results.prefix(150), by: \.competition).sorted { (comps[$0.key]?.live ?? false) && !(comps[$1.key]?.live ?? false) }
    }

    private func load() async {
        guard let list = try? await API.competitions() else { loading = false; return }
        comps = Dictionary(uniqueKeysWithValues: list.map { ($0.slug, $0) })
        var all: [Athlete] = []
        for c in list where !c.isFinished && c.athletesCount > 0 {
            if let a = try? await API.athletes(c.slug) { all += a }
        }
        athletes = all
        loading = false
    }
}

/// Judokas suivis : prochain combat, résultat, alertes.
struct FavoritesView: View {
    @Environment(Favorites.self) private var favorites
    @State private var notif: UNAuthorizationStatus = .notDetermined

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 12) {
                    if favorites.items.isEmpty {
                        EmptyState(icon: "star", title: "Suivez vos judokas", message: "Touchez l'étoile d'un judoka (fiche, combat, liste) : vous verrez ici son prochain combat et serez prévenu quand il approche.")
                    } else {
                        if notif == .denied {
                            Label("Alertes désactivées dans les réglages iOS : autorisez les notifications de JCL Live pour être prévenu.", systemImage: "bell.slash")
                                .font(.subheadline).foregroundStyle(Theme.ink).card()
                        }
                        ForEach(favorites.items) { a in FavoriteCard(athlete: a, follow: favorites.follow[a.registrationId]) }
                    }
                }
                .padding(16)
            }
            .screenBackground(glow: 0.5)
            .navigationTitle("Favoris")
            .navigationDestination(for: Route.self) { $0.view }
            .refreshable { await favorites.refresh() }
            .task {
                await favorites.refresh()
                notif = await UNUserNotificationCenter.current().notificationSettings().authorizationStatus
            }
        }
    }
}

struct FavoriteCard: View {
    let athlete: FollowedAthlete
    let follow: FollowItem?
    @Environment(Favorites.self) private var favorites

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            NavigationLink(value: Route.athlete(athlete.registrationId)) {
                HStack(spacing: 12) {
                    ClubLogo(path: athlete.clubLogo, name: athlete.clubShort, size: 44)
                    VStack(alignment: .leading, spacing: 2) {
                        Text("\(athlete.firstName) \(Text(athlete.lastName).fontWeight(.bold))").font(.system(size: 16)).foregroundStyle(Theme.ink)
                        Text([athlete.clubShort, athlete.category].compactMap { $0 }.filter { !$0.isEmpty }.joined(separator: " · "))
                            .font(.caption).foregroundStyle(Theme.muted).lineLimit(1)
                    }
                    Spacer()
                    FavoriteButton(athlete: athlete)
                }
            }
            .buttonStyle(.plain)

            if let n = follow?.next {
                NavigationLink(value: Route.bout(n.id)) {
                    HStack(spacing: 10) {
                        Image(systemName: n.isLive ? "dot.radiowaves.left.and.right" : "clock.fill")
                            .foregroundStyle(n.isLive ? .white : Theme.red)
                            .frame(width: 34, height: 34).background(n.isLive ? Theme.red : Theme.redSoft, in: Circle())
                        VStack(alignment: .leading, spacing: 1) {
                            Text(n.isLive ? "Combat en cours" : nextLabel(n)).font(.subheadline.weight(.bold)).foregroundStyle(Theme.ink)
                            Text(["Tapis \(n.mat ?? 0)", opponent(n).map { "contre \($0)" }].compactMap { $0 }.joined(separator: " · "))
                                .font(.caption).foregroundStyle(Theme.muted).lineLimit(1)
                        }
                        Spacer()
                        Image(systemName: "chevron.right").font(.caption.weight(.bold)).foregroundStyle(Theme.faint)
                    }
                    .padding(10).background(Theme.surface2, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
                }
                .buttonStyle(.plain)
            } else if let l = follow?.last {
                NavigationLink(value: Route.bout(l.id)) {
                    HStack {
                        Image(systemName: won(l) ? "trophy.fill" : "flag.checkered").foregroundStyle(won(l) ? Theme.gold : Theme.muted)
                        Text("Dernier combat : \(won(l) ? "victoire" : "défaite")\(l.methodLabel.map { " · \($0)" } ?? "")").font(.subheadline).foregroundStyle(Theme.ink)
                        Spacer()
                    }
                    .padding(10).background(Theme.surface2, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
                }
                .buttonStyle(.plain)
            } else if follow != nil {
                Text("Aucun combat programmé pour l'instant.").font(.caption).foregroundStyle(Theme.muted)
            }
        }
        .card(padding: 14)
        .contextMenu { Button("Ne plus suivre", systemImage: "star.slash", role: .destructive) { favorites.remove(athlete.registrationId) } }
    }

    private func nextLabel(_ n: Bout) -> String {
        guard let b = n.boutsBefore else { return "Prochain combat" }
        return b == 0 ? "C'est le prochain combat" : "Dans \(b) combat\(b > 1 ? "s" : "")"
    }
    private func opponent(_ n: Bout) -> String? { (n.a?.registrationId == athlete.registrationId ? n.b : n.a)?.fullName }
    private func won(_ l: Bout) -> Bool { (l.winner == "a" ? l.a : l.winner == "b" ? l.b : nil)?.registrationId == athlete.registrationId }
}

struct SettingsView: View {
    @Environment(\.dismiss) private var dismiss
    @AppStorage(API.serverKey) private var server = API.defaultServer
    @AppStorage("appearance") private var appearance = 0
    @State private var notifications = Notifier.shared.enabled
    @State private var test: String?

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    TextField("https://compet.jcleforest.fr", text: $server)
                        .keyboardType(.URL).textInputAutocapitalization(.never).autocorrectionDisabled()
                    Button("Tester la connexion") {
                        Task {
                            do { let c = try await API.competitions(); test = "Connecté : \(c.count) compétition\(c.count > 1 ? "s" : "")." }
                            catch { test = readable(error) }
                        }
                    }
                    if let test { Text(test).font(.footnote).foregroundStyle(Theme.muted) }
                } header: { Text("Site de la compétition") } footer: {
                    Text("Adresse du site JCL Compét. L'application ne lit que les informations publiques : aucun compte, aucune date de naissance.")
                }
                Section("Alertes") {
                    Toggle("Prévenir quand un favori va combattre", isOn: $notifications)
                        .onChange(of: notifications) { _, v in Notifier.shared.enabled = v; if v { Notifier.shared.requestIfNeeded() } }
                }
                Section("Apparence") {
                    Picker("Thème", selection: $appearance) {
                        Text("Système").tag(0); Text("Clair").tag(1); Text("Sombre").tag(2)
                    }
                    .pickerStyle(.segmented)
                }
                Section {
                    LabeledContent("Version", value: Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? "1.0")
                    LabeledContent("Club", value: "Judo Club Leforest")
                }
            }
            .navigationTitle("Réglages")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar { ToolbarItem(placement: .confirmationAction) { Button("OK") { dismiss() } } }
        }
    }
}
