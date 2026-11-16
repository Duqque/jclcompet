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
    var body: some View {
        TabView(selection: $tab) {
            HomeView(openTab: { tab = $0 })
                .tabItem { Label("Accueil", systemImage: "house.fill") }.tag(0)
            CompetitionsView()
                .tabItem { Label("Compétitions", systemImage: "calendar") }.tag(1)
            DirectTabView()
                .tabItem { Label("Direct", systemImage: "dot.radiowaves.left.and.right") }.tag(2)
            FavoritesView()
                .tabItem { Label("Favoris", systemImage: "star.fill") }.tag(3)
            ClubTabView()
                .tabItem { Label("Mon club", systemImage: "person.2.fill") }.tag(4)
        }
    }
}
