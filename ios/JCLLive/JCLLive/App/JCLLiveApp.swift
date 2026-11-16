import SwiftUI
import UserNotifications
import BackgroundTasks

@main
struct JCLLiveApp: App {
    @UIApplicationDelegateAdaptor(AppDelegate.self) private var delegate
    @Environment(\.scenePhase) private var phase
    @AppStorage("appearance") private var appearance = 0

    var body: some Scene {
        WindowGroup {
            RootView()
                .environment(Favorites.shared)
                .tint(Theme.red)
                .preferredColorScheme(appearance == 1 ? .light : appearance == 2 ? .dark : nil)
                .task(id: phase) {
                    // Au premier plan : suivi des judokas favoris toutes les 20 s.
                    guard phase == .active else { Notifier.shared.scheduleBackgroundRefresh(); return }
                    while !Task.isCancelled {
                        await Favorites.shared.refresh()
                        try? await Task.sleep(for: .seconds(20))
                    }
                }
        }
        .backgroundTask(.appRefresh(Notifier.refreshTask)) {
            Notifier.shared.scheduleBackgroundRefresh()
            await Favorites.shared.refresh()
        }
    }
}

final class AppDelegate: NSObject, UIApplicationDelegate, UNUserNotificationCenterDelegate {
    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]? = nil) -> Bool {
        UNUserNotificationCenter.current().delegate = self
        return true
    }

    // Afficher aussi les alertes quand l'application est ouverte.
    func userNotificationCenter(_ center: UNUserNotificationCenter, willPresent notification: UNNotification) async -> UNNotificationPresentationOptions {
        [.banner, .sound, .list]
    }
}

struct RootView: View {
    @State private var tab = 0
    @AppStorage("welcome_done") private var welcomeDone = false
    var body: some View {
        tabs
            // Premier lancement : choisir entre suivre la compétition sans compte ou se connecter (compte club)
            .fullScreenCover(isPresented: Binding(get: { !welcomeDone && !AccountStore.shared.isSignedIn }, set: { if !$0 { welcomeDone = true } })) {
                WelcomeGate { club in
                    welcomeDone = true
                    if club { tab = 4 }
                }
            }
    }

    private var tabs: some View {
        TabView(selection: $tab) {
            HomeView(openTab: { tab = $0 })
                .tabItem { Label("Accueil", systemImage: "house.fill") }.tag(0)
            CompetitionsView()
                .tabItem { Label("Compétitions", systemImage: "calendar") }.tag(1)
            DirectTabView()
                .tabItem { Label("Direct", systemImage: "dot.radiowaves.left.and.right") }.tag(2)
            BracketTabView()
                .tabItem { Label("Tableau", systemImage: "trophy") }.tag(3)
            ClubTabView()
                .tabItem { Label("Mon club", systemImage: "person.2.fill") }.tag(4)
        }
    }
}

/// Premier lancement : l'application s'utilise entièrement sans compte (résultats, direct, tableaux, favoris).
/// Le compte club ne sert qu'aux coachs pour gérer les inscriptions de leur club.
struct WelcomeGate: View {
    let done: (_ club: Bool) -> Void

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 18) {
                Image("Logo").resizable().scaledToFit().frame(width: 92, height: 62)
                    .padding(10).background(.white, in: RoundedRectangle(cornerRadius: 20, style: .continuous))
                    .padding(.top, 24)
                VStack(alignment: .leading, spacing: 8) {
                    Text("JCL Live").font(.score(40, weight: 900)).foregroundStyle(Theme.ink)
                    Text("Les compétitions du JC Leforest en direct : combats, tableaux, résultats et vos judokas favoris.")
                        .font(.body).foregroundStyle(Theme.muted)
                }
                VStack(spacing: 8) {
                    feature("dot.radiowaves.left.and.right", "Combats en direct, tapis par tapis")
                    feature("trophy.fill", "Tableaux et classements à jour")
                    feature("star.fill", "Alertes pour vos judokas favoris")
                }
                .card(padding: 16)

                Button { done(false) } label: {
                    Text("Continuer sans compte").font(.headline).foregroundStyle(.white)
                        .frame(maxWidth: .infinity).frame(height: 54)
                        .background(Theme.red, in: RoundedRectangle(cornerRadius: 16, style: .continuous))
                }
                .buttonStyle(.plain)

                Button { done(true) } label: {
                    HStack(spacing: 12) {
                        Image(systemName: "person.2.fill").font(.system(size: 17, weight: .semibold)).foregroundStyle(Theme.red)
                            .frame(width: 42, height: 42).background(Theme.redSoft, in: Circle())
                        VStack(alignment: .leading, spacing: 2) {
                            Text("Je suis coach d'un club").font(.subheadline.weight(.bold)).foregroundStyle(Theme.ink)
                            Text("Se connecter ou créer un compte club pour gérer les inscriptions.").font(.caption).foregroundStyle(Theme.muted)
                        }
                        Spacer(minLength: 4)
                        Image(systemName: "chevron.right").foregroundStyle(Theme.faint)
                    }
                    .padding(14)
                    .background(Theme.surface, in: RoundedRectangle(cornerRadius: Theme.radius, style: .continuous))
                }
                .buttonStyle(.plain)

                Text("Sans compte, tout est consultable. Seul l'espace « Mon club » (inscriptions, pesées, présences) demande un compte club.")
                    .font(.footnote).foregroundStyle(Theme.muted).multilineTextAlignment(.center).frame(maxWidth: .infinity)
            }
            .padding(20).fixedScreenWidth()
        }
        .screenBackground(glow: 1)
    }

    private func feature(_ icon: String, _ text: String) -> some View {
        HStack(spacing: 12) {
            Image(systemName: icon).font(.system(size: 15, weight: .semibold)).foregroundStyle(Theme.red).frame(width: 26)
            Text(text).font(.subheadline.weight(.medium)).foregroundStyle(Theme.ink)
            Spacer()
        }
    }
}
