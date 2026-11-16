import Foundation
import Observation
import Security
import UIKit

// Espace coach de l'application : compte (connexion, demande de compte) et inscriptions du club.
// API privée /api/v1/compte et /api/v1/club (app/Controllers/Api/CoachApiController.php), mêmes règles que
// l'espace club du site. Le jeton de connexion est gardé dans le trousseau iOS, jamais dans les réglages.

// MARK: - Modèles

struct ClubRef: Codable, Hashable, Identifiable {
    let id: Int
    let name: String
    let short: String
    let city: String?
    let logo: String?
    let color: String?
}

struct Account: Codable, Hashable {
    let id: Int
    let name: String
    let email: String
    let role: String
    let club: ClubRef?
}

struct LoginResponse: Codable { let token: String; let account: Account }
struct OkMessage: Codable { let ok: Bool?; let message: String? }
struct ClubsResponse: Codable { let clubs: [ClubRef] }
struct StampResponse: Codable { let stamp: String }

struct CoachCompetition: Codable, Identifiable, Hashable {
    let id: Int
    let slug: String
    let name: String
    let date: String
    let city: String?
    let venue: String?
    let status: String
    let statusLabel: String
    let canEdit: Bool
    let closesAt: String?
    let attendanceOpen: Bool
    let live: Bool
    let mine: Int?
    let present: Int?

    var day: Date? { DateFormatter.apiDay.date(from: date) }
    var isPast: Bool { ["finished", "archived"].contains(status) }
    var closesLabel: String? {
        guard let s = closesAt, let d = DateFormatter.apiDateTime.date(from: s) else { return nil }
        return "jusqu'au " + d.formatted(.dateTime.day().month(.wide).hour().minute().locale(Locale(identifier: "fr_FR")))
    }
}

struct CoachHome: Codable { let account: Account; let competitions: [CoachCompetition] }

struct CoachFighter: Codable, Identifiable, Hashable {
    let id: Int
    let athleteId: Int
    let firstName: String
    let lastName: String
    let sex: String
    let birthDate: String
    let grade: String?
    let licenseNumber: String?
    let category: String
    let weight: String?
    let weightCategoryId: Int?
    let weightByCategory: Bool
    let declaredWeightKg: Double?
    let measuredWeightKg: Double?
    let status: String
    let attendance: String
    let inPool: Bool
    let photo: String?
    let late: Bool
    let onsite: Bool

    var fullName: String { "\(firstName) \(lastName)" }
    var active: Bool { status == "pending" || status == "confirmed" }
    var birthYear: String { String(birthDate.prefix(4)) }
    var weightLine: String {
        if let w = weight, !w.isEmpty { return w.hasPrefix("-") || w.hasPrefix("+") ? "\(w) kg" : w }
        if let d = declaredWeightKg { return kg(d) }
        return "—"
    }
}

struct ClubAthlete: Codable, Identifiable, Hashable {
    let id: Int
    let firstName: String
    let lastName: String
    let sex: String
    let birthDate: String
    let grade: String?
    let licenseNumber: String?
    var fullName: String { "\(firstName) \(lastName)" }
}

struct WeightCat: Codable, Hashable, Identifiable {
    let id: Int
    let ageId: Int
    let sex: String
    let label: String
}

struct ListValidation: Codable, Hashable {
    let at: String
    let by: String?
    let count: Int
    let changedSince: Int
}

struct CoachCompetitionDetail: Codable {
    let id: Int
    let slug: String
    let name: String
    let date: String
    let status: String
    let statusLabel: String
    let canEdit: Bool
    let closesAt: String?
    let attendanceOpen: Bool
    let live: Bool
    let stamp: String
    let weighIn: Bool
    let weightByCategory: Bool
    let requireLicense: Bool
    let listValidation: ListValidation?
    let fighters: [CoachFighter]
    let clubAthletes: [ClubAthlete]
    let weightCategories: [WeightCat]
    let grades: [String]

    var active: [CoachFighter] { fighters.filter(\.active) }
}

struct CategoryPreview: Codable, Equatable {
    let category: String?
    let ageId: Int?
    let weight: String?
    let error: String?
}

func kg(_ v: Double) -> String {
    let s = v.truncatingRemainder(dividingBy: 1) == 0 ? String(format: "%.0f", v) : String(format: "%.1f", v)
    return s.replacingOccurrences(of: ".", with: ",") + " kg"
}

extension DateFormatter {
    static let apiDateTime: DateFormatter = {
        let f = DateFormatter(); f.locale = Locale(identifier: "en_US_POSIX"); f.dateFormat = "yyyy-MM-dd HH:mm:ss"; return f
    }()
}

// MARK: - Trousseau

enum Keychain {
    private static let service = "fr.jcleforest.live.coach"
    private static let account = "token"

    static func read() -> String? {
        let q: [String: Any] = [kSecClass as String: kSecClassGenericPassword, kSecAttrService as String: service,
                                kSecAttrAccount as String: account, kSecReturnData as String: true, kSecMatchLimit as String: kSecMatchLimitOne]
        var out: AnyObject?
        guard SecItemCopyMatching(q as CFDictionary, &out) == errSecSuccess, let d = out as? Data else { return nil }
        return String(data: d, encoding: .utf8)
    }

    static func save(_ token: String?) {
        let base: [String: Any] = [kSecClass as String: kSecClassGenericPassword, kSecAttrService as String: service, kSecAttrAccount as String: account]
        SecItemDelete(base as CFDictionary)
        guard let token, let d = token.data(using: .utf8) else { return }
        var add = base
        add[kSecValueData as String] = d
        add[kSecAttrAccessible as String] = kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly
        SecItemAdd(add as CFDictionary, nil)
    }
}

// MARK: - Client

enum CoachAPI {
    static func send<T: Decodable>(_ method: String, _ path: String, body: [String: Any?]? = nil, auth: Bool = true, as: T.Type = T.self) async throws -> T {
        guard let url = URL(string: API.server + "/api/v1" + path) else { throw APIError.badServer }
        var req = URLRequest(url: url)
        req.httpMethod = method
        req.setValue("application/json", forHTTPHeaderField: "Accept")
        if let body {
            req.setValue("application/json", forHTTPHeaderField: "Content-Type")
            req.httpBody = try JSONSerialization.data(withJSONObject: body.compactMapValues { $0 })
        }
        if auth { try sign(&req) }
        return try await run(req, auth: auth)
    }

    /// Envoi d'une photo (multipart) pour un judoka du club.
    static func upload(_ path: String, image: UIImage) async throws -> OkMessage {
        guard let url = URL(string: API.server + "/api/v1" + path), let jpeg = image.resizedForUpload().jpegData(compressionQuality: 0.82) else { throw APIError.badServer }
        let boundary = "JCL-\(UUID().uuidString)"
        var req = URLRequest(url: url)
        req.httpMethod = "POST"
        req.setValue("application/json", forHTTPHeaderField: "Accept")
        req.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        var data = Data()
        data.append("--\(boundary)\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"photo.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
        data.append(jpeg)
        data.append("\r\n--\(boundary)--\r\n".data(using: .utf8)!)
        req.httpBody = data
        try sign(&req)
        return try await run(req, auth: true)
    }

    /// Image privée (photo d'un judoka du club) chargée avec le jeton.
    static func image(_ path: String) async -> UIImage? {
        guard let url = API.media(path) else { return nil }
        var req = URLRequest(url: url)
        try? sign(&req)
        guard let (d, r) = try? await API.session.data(for: req), (r as? HTTPURLResponse)?.statusCode == 200 else { return nil }
        return UIImage(data: d)
    }

    private static func sign(_ req: inout URLRequest) throws {
        guard let t = AccountStore.shared.token else { throw APIError.http(401, "Connectez-vous pour continuer.") }
        req.setValue("Bearer \(t)", forHTTPHeaderField: "Authorization")
    }

    private static func run<T: Decodable>(_ req: URLRequest, auth: Bool) async throws -> T {
        let (data, resp) = try await API.session.data(for: req)
        let code = (resp as? HTTPURLResponse)?.statusCode ?? 0
        guard (200..<300).contains(code) else {
            let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any]
            var msg = json?["error"] as? String
            if let errs = json?["errors"] as? [String: Any], let first = errs.values.first as? String { msg = first }
            let reason = msg
            if code == 401 && auth { await MainActor.run { AccountStore.shared.expire(reason) } }
            throw APIError.http(code, msg)
        }
        do { return try API.decoder.decode(T.self, from: data) } catch {
            #if DEBUG
            print("Décodage \(req.url?.path ?? "") :", error)
            #endif
            throw APIError.decoding
        }
    }

    // Raccourcis
    static func clubs() async throws -> [ClubRef] { try await send("GET", "/compte/clubs", auth: false, as: ClubsResponse.self).clubs }
    static func home() async throws -> CoachHome { try await send("GET", "/club") }
    static func competition(_ id: Int) async throws -> CoachCompetitionDetail { try await send("GET", "/club/competitions/\(id)") }
    static func stamp(_ id: Int) async throws -> String { try await send("GET", "/club/competitions/\(id)/etat", as: StampResponse.self).stamp }
    static func preview(_ id: Int, _ body: [String: Any?]) async throws -> CategoryPreview { try await send("POST", "/club/competitions/\(id)/apercu", body: body) }
    static func post(_ path: String, _ body: [String: Any?] = [:]) async throws -> OkMessage { try await send("POST", path, body: body) }
}

extension UIImage {
    /// Photo réduite (1 200 px au plus) avant l'envoi.
    func resizedForUpload(max: CGFloat = 1200) -> UIImage {
        let side = Swift.max(size.width, size.height)
        guard side > max else { return self }
        let scale = max / side
        let target = CGSize(width: size.width * scale, height: size.height * scale)
        return UIGraphicsImageRenderer(size: target).image { _ in draw(in: CGRect(origin: .zero, size: target)) }
    }
}

// MARK: - Compte

@Observable final class AccountStore {
    static let shared = AccountStore()
    private static let serverKey = "coach_server"

    private(set) var token: String?
    var account: Account?
    /// Message affiché quand la session a expiré (jeton révoqué, compte désactivé).
    var notice: String?

    var isSignedIn: Bool { token != nil }

    init() {
        let t = Keychain.read()
        // Le jeton appartient au serveur qui l'a délivré : changer d'adresse dans Réglages déconnecte.
        if t != nil && UserDefaults.standard.string(forKey: Self.serverKey) == API.server { token = t } else { Keychain.save(nil) }
        if let d = UserDefaults.standard.data(forKey: "coach_account"), let a = try? JSONDecoder().decode(Account.self, from: d) { account = a }
    }

    @MainActor func signIn(email: String, password: String) async throws {
        let device = UIDevice.current.name
        let r = try await CoachAPI.send("POST", "/compte/connexion", body: ["email": email, "password": password, "device": device], auth: false, as: LoginResponse.self)
        Keychain.save(r.token)
        UserDefaults.standard.set(API.server, forKey: Self.serverKey)
        token = r.token
        setAccount(r.account)
        notice = nil
    }

    @MainActor func signOut() async {
        if token != nil { _ = try? await CoachAPI.post("/compte/deconnexion") }
        clear()
    }

    @MainActor func expire(_ message: String?) {
        guard token != nil else { return }
        clear()
        notice = message ?? "Session expirée : reconnectez-vous."
    }

    @MainActor func setAccount(_ a: Account) {
        account = a
        if let d = try? JSONEncoder().encode(a) { UserDefaults.standard.set(d, forKey: "coach_account") }
    }

    @MainActor private func clear() {
        Keychain.save(nil)
        token = nil
        account = nil
        UserDefaults.standard.removeObject(forKey: "coach_account")
    }
}
