import Foundation
import Observation
import UserNotifications
import BackgroundTasks

/// Judokas suivis, enregistrés sur le téléphone uniquement (aucun compte, rien n'est envoyé au serveur
/// hormis les numéros d'inscription au moment de la consultation).
struct FollowedAthlete: Codable, Identifiable, Hashable {
    let registrationId: Int
    let competition: String
    let firstName: String
    let lastName: String
    let clubShort: String?
    let clubLogo: String?
    let category: String?
    var id: Int { registrationId }
    var fullName: String { "\(firstName) \(lastName)" }
}

@Observable final class Favorites {
    static let shared = Favorites()
    private let key = "followed_athletes"
    private(set) var items: [FollowedAthlete] = []
    var follow: [Int: FollowItem] = [:]

    init() {
        if let d = UserDefaults.standard.data(forKey: key), let v = try? JSONDecoder().decode([FollowedAthlete].self, from: d) { items = v }
    }

    var ids: Set<Int> { Set(items.map(\.registrationId)) }
    func contains(_ id: Int) -> Bool { items.contains { $0.registrationId == id } }

    func toggle(_ a: FollowedAthlete) {
        if contains(a.registrationId) { items.removeAll { $0.registrationId == a.registrationId }; unprime(a.registrationId) }
        else { unprime(a.registrationId); items.append(a); Notifier.shared.requestIfNeeded() }
        save()
    }

    func remove(_ id: Int) { items.removeAll { $0.registrationId == id }; unprime(id); save() }

    private func unprime(_ id: Int) {
        let p = (UserDefaults.standard.array(forKey: "primed_athletes") as? [Int] ?? []).filter { $0 != id }
        UserDefaults.standard.set(p, forKey: "primed_athletes")
    }

    private func save() {
        if let d = try? JSONEncoder().encode(items) { UserDefaults.standard.set(d, forKey: key) }
    }

    /// Met à jour les prochains combats des judokas suivis et déclenche les alertes.
    @MainActor func refresh() async {
        guard !items.isEmpty, let list = try? await API.follow(items.map(\.registrationId)) else { return }
        follow = Dictionary(uniqueKeysWithValues: list.map { ($0.registrationId, $0) })
        Notifier.shared.evaluate(list, athletes: items)
    }
}

extension FollowedAthlete {
    init(_ a: Athlete) {
        self.init(registrationId: a.registrationId, competition: a.competition, firstName: a.firstName, lastName: a.lastName,
                  clubShort: a.clubShort ?? a.club, clubLogo: a.clubLogo, category: a.categoryLine)
    }
    init(_ f: Fighter, competition: String, category: String?) {
        self.init(registrationId: f.registrationId, competition: competition, firstName: f.firstName, lastName: f.lastName,
                  clubShort: f.clubShort ?? f.club, clubLogo: f.clubLogo, category: category)
    }
}

/// Alertes locales : combat bientôt (2 combats avant), combat en cours, résultat validé.
/// Les vérifications se font par interrogation régulière du serveur (au premier plan et en arrière-plan iOS).
final class Notifier {
    static let shared = Notifier()
    static let refreshTask = "fr.jcleforest.live.refresh"
    private let sentKey = "sent_notifications"
    var enabled: Bool {
        get { UserDefaults.standard.object(forKey: "notifications_on") as? Bool ?? true }
        set { UserDefaults.standard.set(newValue, forKey: "notifications_on") }
    }

    func requestIfNeeded() {
        guard enabled else { return }
        UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound, .badge]) { _, _ in }
    }

    func evaluate(_ list: [FollowItem], athletes: [FollowedAthlete]) {
        guard enabled else { return }
        var sent = Set(UserDefaults.standard.stringArray(forKey: sentKey) ?? [])
        var primed = Set(UserDefaults.standard.array(forKey: "primed_athletes") as? [Int] ?? [])
        for item in list {
            guard let who = athletes.first(where: { $0.registrationId == item.registrationId }) else { continue }
            // Premier passage après l'ajout aux favoris : le résultat déjà connu n'est pas une nouveauté.
            if primed.insert(item.registrationId).inserted, let l = item.last { sent.insert("result-\(l.id)") }
            if let n = item.next {
                let mat = n.mat.map { "tapis \($0)" } ?? "son tapis"
                if n.isLive, sent.insert("live-\(n.id)").inserted {
                    post("\(who.fullName) combat maintenant", "En direct sur le \(mat).", id: "live-\(n.id)")
                } else if let before = n.boutsBefore, before <= 2, !n.isLive, sent.insert("soon-\(n.id)").inserted {
                    let when = before == 0 ? "C'est le prochain combat" : "Dans \(before) combat\(before > 1 ? "s" : "")"
                    post("\(who.fullName) va combattre", "\(when) sur le \(mat).", id: "soon-\(n.id)")
                }
            }
            if let l = item.last, l.status == "validated", let w = l.winner, sent.insert("result-\(l.id)").inserted {
                let mine = (w == "a" ? l.a : l.b)?.registrationId == who.registrationId
                post(mine ? "Victoire de \(who.fullName)" : "Combat de \(who.fullName) terminé",
                     [l.methodLabel, l.mat.map { "tapis \($0)" }].compactMap { $0 }.joined(separator: " · "), id: "result-\(l.id)")
            }
        }
        UserDefaults.standard.set(Array(sent.suffix(400)), forKey: sentKey)
        UserDefaults.standard.set(Array(primed), forKey: "primed_athletes")
    }

    private func post(_ title: String, _ body: String, id: String) {
        let c = UNMutableNotificationContent()
        c.title = title; c.body = body; c.sound = .default
        UNUserNotificationCenter.current().add(UNNotificationRequest(identifier: id, content: c, trigger: nil))
    }

    func scheduleBackgroundRefresh() {
        let r = BGAppRefreshTaskRequest(identifier: Self.refreshTask)
        r.earliestBeginDate = Date(timeIntervalSinceNow: 5 * 60)
        try? BGTaskScheduler.shared.submit(r)
    }
}
