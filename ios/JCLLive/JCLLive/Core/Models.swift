import Foundation

// Modèles de l'API publique en lecture seule (/api/v1/…, app/Controllers/Api/MobileController.php).
// Clés JSON en snake_case, converties par le décodeur (convertFromSnakeCase).

/// Valeur texte qui peut arriver en nombre ou en texte (« -48 », 24.1).
struct Loose: Codable, Hashable {
    let text: String
    init(from decoder: Decoder) throws {
        let c = try decoder.singleValueContainer()
        if let s = try? c.decode(String.self) { text = s }
        else if let i = try? c.decode(Int.self) { text = String(i) }
        else if let d = try? c.decode(Double.self) { text = String(format: "%g", d) }
        else { text = "" }
    }
    func encode(to encoder: Encoder) throws { var c = encoder.singleValueContainer(); try c.encode(text) }
}

struct Competition: Codable, Identifiable, Hashable {
    let id: Int
    let slug: String
    let name: String
    let edition: String?
    let date: String
    let venue: String?
    let city: String?
    let status: String
    let statusLabel: String
    let live: Bool
    let registrationsOpen: Bool
    let matsCount: Int
    let clubsCount: Int
    let athletesCount: Int
    let poster: String?
    /// Tableaux consultables : poules publiées ou au moins un tirage au sort validé
    let bracketsAvailable: Bool?

    var isFinished: Bool { ["finished", "archived"].contains(status) }
    var isUpcoming: Bool { !live && !isFinished }
    var day: Date? { DateFormatter.apiDay.date(from: date) }
    var place: String { [venue, city].compactMap { $0 }.filter { !$0.isEmpty }.joined(separator: " · ") }
}

struct CompetitionDetail: Codable {
    let id: Int
    let slug: String
    let name: String
    let edition: String?
    let date: String
    let venue: String?
    let city: String?
    let status: String
    let statusLabel: String
    let live: Bool
    let registrationsOpen: Bool
    let matsCount: Int
    let clubsCount: Int
    let athletesCount: Int
    let poster: String?
    let description: String?
    let practicalInfo: String?
    let address: String?
    let startTime: String?
    let boutsTotal: Int
    let boutsDone: Int
    let poolsPublished: Bool
    let livePublic: Bool

    var summary: Competition {
        Competition(id: id, slug: slug, name: name, edition: edition, date: date, venue: venue, city: city, status: status,
                    statusLabel: statusLabel, live: live, registrationsOpen: registrationsOpen, matsCount: matsCount,
                    clubsCount: clubsCount, athletesCount: athletesCount, poster: poster, bracketsAvailable: poolsPublished)
    }
}

struct Home: Codable {
    struct LiveBlock: Codable, Identifiable {
        let id: Int
        let slug: String
        let name: String
        let city: String?
        let venue: String?
        let date: String
        let matsCount: Int
        let athletesCount: Int
        let clubsCount: Int
        let mats: [Mat]
    }
    let live: [LiveBlock]
    let competitions: [Competition]
    let serverNowMs: Int
}

struct CompetitionsList: Codable { let competitions: [Competition] }

struct Mat: Codable, Identifiable {
    let number: Int
    let mode: String
    let current: Bout?
    let next: [Bout]
    var id: Int { number }
}

struct LivePayload: Codable {
    let competition: Competition
    let mats: [Mat]
    let serverNowMs: Int
}

struct Stamp: Codable { let stamp: String; let serverNowMs: Int }

struct Fighter: Codable, Hashable {
    let registrationId: Int
    let firstName: String
    let lastName: String
    let club: String?
    let clubShort: String?
    let clubLogo: String?
    let ippon: Int
    let wazaari: Int
    let yuko: Int
    let shido: Int
    let hansoku: Int
    let score: String

    var fullName: String { "\(firstName) \(lastName)" }
}

struct BoutClock: Codable, Hashable {
    let durationMs: Int
    let running: Bool
    let startedAtMs: Int?
    let elapsedMs: Int
    let goldenScore: Bool
    let goldenScoreMode: String?
    let goldenScoreMs: Int?
    let finalElapsedMs: Int?
}

struct OsaekomiState: Codable, Hashable { let side: String; let startedAtMs: Int }

struct ScoreModel: Codable, Hashable {
    struct Thresholds: Codable, Hashable { let yuko: Int?; let wazaari: Int?; let ippon: Int? }
    let osaekomi: Thresholds?
    let useYuko: Bool
    let shidoMax: Int?
}

struct Bout: Codable, Identifiable, Hashable {
    let id: Int
    let status: String
    let statusLabel: String
    let phase: String?
    let mat: Int?
    let orderOnMat: Int?
    let poolNumber: Int?
    let poolId: Int?
    let poolPosition: Int?
    let poolBouts: Int?
    let category: String?
    let weight: Loose?
    let a: Fighter?
    let b: Fighter?
    let winner: String?
    let method: String?
    let methodLabel: String?
    let clock: BoutClock?
    let osaekomi: OsaekomiState?
    let model: ScoreModel?
    let boutsBefore: Int?

    var isLive: Bool { status == "in_progress" }
    /// « Prochain combat », « Dans 3 combats » : place dans la file du tapis (calculée par le serveur).
    var queueLabel: String? {
        guard status == "scheduled", let n = boutsBefore else { return nil }
        return n == 0 ? "Prochain combat" : "Dans \(n) combat\(n > 1 ? "s" : "")"
    }
    var isDone: Bool { ["finished", "validated", "forfeit"].contains(status) || winner != nil }
    var categoryLine: String { [category, weight?.text.isEmpty == false ? "\(weight!.text) kg" : nil].compactMap { $0 }.joined(separator: " · ") }
    func involves(_ ids: Set<Int>) -> Bool { [a?.registrationId, b?.registrationId].contains { $0.map(ids.contains) ?? false } }
}

struct BoutsList: Codable { let bouts: [Bout] }

struct TimelineItem: Codable, Hashable {
    let type: String
    let label: String
    let side: String?
    let clockMs: Int?
}

struct BoutDetail: Codable {
    let bout: Bout
    let timeline: [TimelineItem]
    let competition: Competition
    let otherA: [Bout]
    let otherB: [Bout]
    let serverNowMs: Int
}

struct PoolAthlete: Codable, Hashable, Identifiable {
    let seed: Int
    let registrationId: Int
    let firstName: String
    let lastName: String
    let club: String?
    let clubShort: String?
    let clubLogo: String?
    let clubColor: String?
    let weight: Loose?
    let wins: Int
    let points: Int
    let rank: Int?
    var id: Int { registrationId }
}

struct PoolCell: Codable, Hashable {
    let row: Int
    let col: Int
    let boutId: Int
    let status: String
    let won: Bool?
    let score: String?
    let method: String?
    let order: Int
}

struct PoolOrder: Codable, Hashable {
    let boutId: Int
    let order: Int
    let a: Int
    let b: Int
    let status: String
    let winner: String?
}

struct Pool: Codable, Identifiable, Hashable {
    let id: Int
    let number: Int
    let mat: Int?
    let category: String?
    let weight: Loose?
    let boutsDone: Int
    let boutsTotal: Int
    let complete: Bool
    let athletes: [PoolAthlete]
    let cells: [PoolCell]
    let order: [PoolOrder]

    var title: String { [category, weight?.text.isEmpty == false ? "\(weight!.text) kg" : nil].compactMap { $0 }.joined(separator: " · ") }
    func cell(_ row: Int, _ col: Int) -> PoolCell? { cells.first { $0.row == row && $0.col == col } }
}

struct PoolsList: Codable { let pools: [Pool] }

struct Athlete: Codable, Identifiable, Hashable {
    let registrationId: Int
    let competition: String
    let firstName: String
    let lastName: String
    let sex: String?
    let grade: String?
    let category: String?
    let weight: Loose?
    let club: String?
    let clubShort: String?
    let clubLogo: String?
    let clubColor: String?
    let photo: String?
    let poolNumber: Int?
    let mat: Int?
    let poolId: Int?
    let poolKind: String?
    let categoryPoolId: Int?
    let present: Bool?
    let place: Int?
    let medal: String?
    var id: Int { registrationId }
    var fullName: String { "\(firstName) \(lastName)" }
    var categoryLine: String { [category, weight?.text.isEmpty == false ? "\(weight!.text) kg" : nil].compactMap { $0 }.joined(separator: " · ") }
}

struct AthletesList: Codable { let athletes: [Athlete] }

struct AthleteDetail: Codable {
    let athlete: Athlete
    let competition: Competition
    let bouts: [Bout]
}

struct PodiumAthlete: Codable, Hashable {
    let place: Int
    let medal: String?
    let firstName: String
    let lastName: String
    let club: String?
    let clubShort: String?
    let logo: String?
    let color: String?
    let photo: String?
}

struct Podium: Codable, Identifiable, Hashable {
    let poolId: Int
    let poolNumber: Int
    let at: String?
    let category: String?
    let weight: Loose?
    let athletes: [PodiumAthlete]
    var id: Int { poolId }
    var title: String { [category, weight?.text.isEmpty == false ? "\(weight!.text) kg" : nil].compactMap { $0 }.joined(separator: " · ") }
}

struct ClubRank: Codable, Hashable, Identifiable {
    let club: String
    let clubShort: String?
    let clubLogo: String?
    let points: Int
    let gold: Int
    let silver: Int
    let bronze: Int
    let participants: Int
    let rankPoints: Int?
    let rankMedals: Int?
    var id: String { club }
}

struct Results: Codable {
    struct Totals: Codable { let gold: Int; let silver: Int; let bronze: Int; let ranked: Int }
    let podiums: [Podium]
    let clubsPoints: [ClubRank]
    let clubsMedals: [ClubRank]
    let totals: Totals
    let stamp: String?
}

struct FollowItem: Codable, Identifiable {
    let registrationId: Int
    let competition: String?
    let next: Bout?
    let last: Bout?
    var id: Int { registrationId }
}

struct FollowList: Codable { let follow: [FollowItem] }

extension DateFormatter {
    static let apiDay: DateFormatter = {
        let f = DateFormatter(); f.locale = Locale(identifier: "en_US_POSIX"); f.dateFormat = "yyyy-MM-dd"; return f
    }()
    static let longDay: DateFormatter = {
        let f = DateFormatter(); f.locale = Locale(identifier: "fr_FR"); f.dateFormat = "EEEE d MMMM yyyy"; return f
    }()
    static let shortDay: DateFormatter = {
        let f = DateFormatter(); f.locale = Locale(identifier: "fr_FR"); f.dateFormat = "d MMM"; return f
    }()
}

func clockText(_ ms: Int) -> String {
    let t = max(0, Int((Double(ms) / 1000).rounded(.up)))
    return "\(t / 60):" + String(format: "%02d", t % 60)
}
