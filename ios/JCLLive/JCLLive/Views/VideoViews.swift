import SwiftUI
import WebKit

// Vidéo des tapis : direct YouTube de la régie vidéo, replays « Revoir le combat », bandeau vidéo-arbitrage.
// Accessible à tous, sans compte.

/// Lecteur YouTube intégré (lecture dans l'application, sans cookie publicitaire : youtube-nocookie).
struct YouTubePlayer: UIViewRepresentable {
    let videoId: String
    var start: Int? = nil

    func makeUIView(context: Context) -> WKWebView {
        let conf = WKWebViewConfiguration()
        conf.allowsInlineMediaPlayback = true
        conf.mediaTypesRequiringUserActionForPlayback = []
        let web = WKWebView(frame: .zero, configuration: conf)
        web.isOpaque = false
        web.backgroundColor = .black
        web.scrollView.isScrollEnabled = false
        web.scrollView.bounces = false
        load(web)
        return web
    }

    func updateUIView(_ web: WKWebView, context: Context) {
        if context.coordinator.key != key { load(web); context.coordinator.key = key }
    }

    func makeCoordinator() -> Coordinator { Coordinator(key: key) }
    final class Coordinator { var key: String; init(key: String) { self.key = key } }

    private var key: String { "\(videoId)-\(start ?? -1)" }

    private func load(_ web: WKWebView) {
        var c = URLComponents(string: "https://www.youtube-nocookie.com/embed/\(videoId)")!
        c.queryItems = [
            .init(name: "playsinline", value: "1"), .init(name: "autoplay", value: "1"), .init(name: "rel", value: "0"),
            .init(name: "modestbranding", value: "1"),
        ] + (start.map { [.init(name: "start", value: String($0))] } ?? [])
        var req = URLRequest(url: c.url!)
        // YouTube exige l'origine de l'intégration
        req.setValue(API.server, forHTTPHeaderField: "Referer")
        web.load(req)
    }
}

/// Carte vidéo d'un tapis : lecteur 16:9 + bandeau « Vidéo-arbitrage en cours ».
struct MatVideoCard: View {
    let mat: Int
    let video: MatVideo

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            if video.review { VarBanner() }
            if let id = video.youtubeId {
                YouTubePlayer(videoId: id)
                    .aspectRatio(16 / 9, contentMode: .fit)
                    .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
                HStack(spacing: 6) {
                    LiveBadge(label: "VIDÉO")
                    Text("Tapis \(mat) en direct").font(.caption.weight(.semibold)).foregroundStyle(Theme.muted)
                    Spacer()
                    Link(destination: URL(string: "https://youtu.be/\(id)")!) {
                        Label("YouTube", systemImage: "arrow.up.right.square").font(.caption.weight(.semibold))
                    }
                }
            }
        }
    }
}

/// Bandeau « Vidéo-arbitrage en cours » (état partagé avec la régie, l'écran TV et le commissaire).
struct VarBanner: View {
    @State private var on = true
    var body: some View {
        HStack(spacing: 10) {
            Circle().fill(Theme.red).frame(width: 10, height: 10).opacity(on ? 1 : 0.25)
            Text("VIDÉO-ARBITRAGE EN COURS").font(.score(14, weight: 900)).foregroundStyle(.white)
            Spacer(minLength: 0)
        }
        .padding(.horizontal, 12).padding(.vertical, 9)
        .background(Color(hex: 0x18181B), in: RoundedRectangle(cornerRadius: 12, style: .continuous))
        .overlay(alignment: .leading) { Rectangle().fill(Theme.yellow).frame(width: 4).clipShape(RoundedRectangle(cornerRadius: 2)) }
        .onAppear { withAnimation(.easeInOut(duration: 0.6).repeatForever()) { on = false } }
        .accessibilityElement(children: .combine)
    }
}

/// « Revoir le combat » sur la page combat.
struct BoutReplayCard: View {
    let replay: BoutReplay
    let bout: Bout
    @State private var playing = false

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text(bout.isDone ? "Revoir le combat" : "Combat en vidéo").font(.headline).foregroundStyle(Theme.ink)
                Spacer()
                Link(destination: URL(string: "https://youtu.be/\(replay.youtubeId)?t=\(replay.startS)")!) {
                    Label("YouTube", systemImage: "arrow.up.right.square").font(.caption.weight(.semibold))
                }
            }
            if playing {
                YouTubePlayer(videoId: replay.youtubeId, start: replay.live && !bout.isDone ? nil : replay.startS)
                    .aspectRatio(16 / 9, contentMode: .fit)
                    .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
                if replay.live && bout.isDone {
                    Text("Le direct du tapis continue : le passage exact du combat sera disponible à la fin du direct.")
                        .font(.caption).foregroundStyle(Theme.muted)
                }
            } else {
                Button { playing = true } label: {
                    ZStack {
                        RoundedRectangle(cornerRadius: 16, style: .continuous).fill(Color(hex: 0x18181B))
                        HStack(spacing: 10) {
                            Image(systemName: "play.fill").font(.system(size: 18, weight: .bold))
                                .frame(width: 46, height: 46).background(Theme.red, in: Circle())
                            VStack(alignment: .leading, spacing: 2) {
                                Text(bout.isDone ? "Revoir le combat" : "Regarder le tapis \(bout.mat ?? 0)").font(.score(16, weight: 900))
                                Text(bout.isDone ? "À partir du hajime" : "En direct").font(.caption).opacity(0.75)
                            }
                        }
                        .foregroundStyle(.white)
                    }
                    .aspectRatio(16 / 9, contentMode: .fit)
                }
                .buttonStyle(.plain)
            }
        }
    }
}
