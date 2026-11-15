import Foundation
import Observation

/// Client de l'API publique. Aucune authentification : l'application ne lit que ce que le site rend public.
struct API {
    /// Adresse par défaut : localhost sur le simulateur, sinon celle de Config/Base.xcconfig (JCL_DEFAULT_SERVER).
    static var defaultServer: String {
        #if targetEnvironment(simulator)
        return "http://localhost:8095"
        #else
        let s = (Bundle.main.object(forInfoDictionaryKey: "JCLDefaultServer") as? String ?? "").trimmingCharacters(in: .whitespaces)
        return s.isEmpty || s.hasPrefix("$(") ? "http://localhost:8095" : s
        #endif
    }
    static let serverKey = "server_url"

    static var server: String {
        let s = UserDefaults.standard.string(forKey: serverKey)?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
        return s.isEmpty ? defaultServer : s.replacingOccurrences(of: "/+$", with: "", options: .regularExpression)
    }

    static let decoder: JSONDecoder = {
        let d = JSONDecoder(); d.keyDecodingStrategy = .convertFromSnakeCase; return d
    }()

    static let session: URLSession = {
        let c = URLSessionConfiguration.default
        c.requestCachePolicy = .reloadIgnoringLocalCacheData
        c.timeoutIntervalForRequest = 12
        c.waitsForConnectivity = false
        return URLSession(configuration: c)
    }()

    /// Adresse absolue d'un média (« /media/64 ») ou nil.
    static func media(_ path: String?) -> URL? {
        guard let p = path, !p.isEmpty else { return nil }
        if p.hasPrefix("http") { return URL(string: p) }
        return URL(string: server + (p.hasPrefix("/") ? p : "/" + p))
    }

    static func get<T: Decodable>(_ path: String, query: [String: String?] = [:], as: T.Type = T.self) async throws -> T {
        guard var comps = URLComponents(string: server + "/api/v1" + path) else { throw APIError.badServer }
        let items = query.compactMap { k, v in v.flatMap { $0.isEmpty ? nil : URLQueryItem(name: k, value: $0) } }
        if !items.isEmpty { comps.queryItems = items }
        guard let url = comps.url else { throw APIError.badServer }
        var req = URLRequest(url: url)
        req.setValue("application/json", forHTTPHeaderField: "Accept")
        let (data, resp) = try await session.data(for: req)
        let code = (resp as? HTTPURLResponse)?.statusCode ?? 0
        guard (200..<300).contains(code) else {
            let msg = (try? JSONSerialization.jsonObject(with: data) as? [String: Any])?["error"] as? String
            throw APIError.http(code, msg)
        }
        do { return try decoder.decode(T.self, from: data) } catch {
            #if DEBUG
            print("Décodage \(path) :", error)
            #endif
            throw APIError.decoding
        }
    }

    // Raccourcis
    static func home() async throws -> Home { try await get("/accueil") }
    static func competitions() async throws -> [Competition] { try await get("/competitions", as: CompetitionsList.self).competitions }
    static func competition(_ slug: String) async throws -> CompetitionDetail { try await get("/competitions/\(slug)") }
    static func live(_ slug: String) async throws -> LivePayload { try await get("/competitions/\(slug)/direct") }
    static func bouts(_ slug: String, mat: Int? = nil, status: String? = nil) async throws -> [Bout] {
        try await get("/competitions/\(slug)/combats", query: ["tapis": mat.map(String.init), "statut": status], as: BoutsList.self).bouts
    }
    static func pools(_ slug: String) async throws -> [Pool] { try await get("/competitions/\(slug)/poules", as: PoolsList.self).pools }
    static func athletes(_ slug: String, q: String? = nil) async throws -> [Athlete] {
        try await get("/competitions/\(slug)/judokas", query: ["q": q], as: AthletesList.self).athletes
    }
    static func results(_ slug: String) async throws -> Results { try await get("/competitions/\(slug)/resultats") }
    static func bout(_ id: Int) async throws -> BoutDetail { try await get("/combats/\(id)") }
    static func athlete(_ id: Int) async throws -> AthleteDetail { try await get("/judokas/\(id)") }
    static func follow(_ ids: [Int]) async throws -> [FollowItem] {
        guard !ids.isEmpty else { return [] }
        return try await get("/suivi", query: ["ids": ids.map(String.init).joined(separator: ",")], as: FollowList.self).follow
    }
}

enum APIError: LocalizedError {
    case badServer, decoding, http(Int, String?)
    var errorDescription: String? {
        switch self {
        case .badServer: return "Adresse du serveur invalide. Corrigez-la dans Réglages."
        case .decoding: return "Réponse du serveur illisible. Mettez à jour l'application ou le site."
        case let .http(code, msg): return msg ?? (code == 404 ? "Introuvable ou pas encore publié." : "Le serveur a répondu \(code).")
        }
    }
}

func readable(_ error: Error) -> String {
    if let e = error as? APIError { return e.errorDescription ?? "Erreur" }
    if (error as? URLError)?.code == .cancelled { return "" }
    if error is URLError { return "Serveur injoignable. Vérifiez la connexion ou l'adresse dans Réglages." }
    return error.localizedDescription
}

/// Décalage entre l'horloge du serveur et celle du téléphone, pour que le chrono tourne juste.
@Observable final class ServerClock {
    static let shared = ServerClock()
    var offsetMs: Double = 0
    func sync(_ serverNowMs: Int) { offsetMs = Double(serverNowMs) - Date().timeIntervalSince1970 * 1000 }
    func now(_ date: Date = Date()) -> Double { date.timeIntervalSince1970 * 1000 + offsetMs }
}
