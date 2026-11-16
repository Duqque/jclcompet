import SwiftUI
import PDFKit

// Vue « Tableau » : l'arbre façon feuille officielle (mêmes positions que le site et le PDF), zoomable.
// Chaque combattant d'entrée sur une ligne ; le vainqueur avance sur la ligne du tour suivant, score dessous.

// MARK: - Modèles

struct TreeJoin: Codable, Hashable { let col: Int; let y1: Double; let y2: Double }

struct TreeItem: Codable, Hashable {
    let leaf: Bool
    let type: String?
    let athlete: BracketAthlete?
    let seed: Int?
    let text: String?
    let key: String?
    let label: String?
    let winner: BracketAthlete?
    let score: String?
    let status: String?
    let boutId: Int?
    let mat: Int?
    let y: Double
    let col: Int
    let span: Int

    var person: BracketAthlete? { leaf ? athlete : winner }
    var isLive: Bool { status == "in_progress" || status == "finished" }
}

struct TreeDrawing: Codable, Identifiable, Hashable {
    let title: String
    let cols: Int
    let rows: Int
    let joins: [TreeJoin]
    let items: [TreeItem]
    var id: String { title }
}

// MARK: - Arbre zoomable

struct BracketTreeView: View {
    let tree: TreeDrawing
    @Environment(Favorites.self) private var favorites
    @State private var scale: CGFloat = 1
    @State private var pinchBase: CGFloat = 1
    @State private var fitted = false

    // Dimensions de référence (points) d'une colonne et d'une ligne
    private let W: CGFloat = 176
    private let H: CGFloat = 46
    private var lineY: CGFloat { H * 0.58 }
    private var baseSize: CGSize { CGSize(width: (CGFloat(tree.cols) + 0.4) * W, height: (CGFloat(tree.rows) + 0.4) * H) }

    var body: some View {
        GeometryReader { geo in
            VStack(spacing: 8) {
                zoomBar(viewport: geo.size)
                ScrollView([.horizontal, .vertical], showsIndicators: true) {
                    canvas
                        .frame(width: baseSize.width, height: baseSize.height, alignment: .topLeading)
                        .scaleEffect(scale, anchor: .topLeading)
                        .frame(width: baseSize.width * scale, height: baseSize.height * scale, alignment: .topLeading)
                        .padding(10)
                }
                .background(Theme.surface, in: RoundedRectangle(cornerRadius: Theme.radius, style: .continuous))
                .simultaneousGesture(
                    MagnifyGesture()
                        .onChanged { v in scale = clamp(pinchBase * v.magnification) }
                        .onEnded { _ in pinchBase = scale }
                )
            }
            .onAppear {
                guard !fitted else { return }
                fitted = true
                fit(geo.size, widthOnly: true)
            }
        }
    }

    private func zoomBar(viewport: CGSize) -> some View {
        HStack(spacing: 8) {
            Text(tree.title).font(.caption.weight(.heavy)).foregroundStyle(Theme.muted).lineLimit(1)
            Spacer(minLength: 4)
            HStack(spacing: 0) {
                zoomButton("minus") { setScale(scale * 0.8) }
                Button { withAnimation(.snappy) { fit(viewport, widthOnly: false) } } label: {
                    Text("Tout voir").font(.caption.weight(.bold)).padding(.horizontal, 10).frame(height: 34)
                }
                .buttonStyle(.plain)
                zoomButton("plus") { setScale(scale * 1.25) }
            }
            .foregroundStyle(Theme.ink)
            .background(Theme.surface, in: Capsule())
        }
    }

    private func zoomButton(_ icon: String, _ action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Image(systemName: icon).font(.system(size: 13, weight: .bold)).frame(width: 36, height: 34)
        }
        .buttonStyle(.plain)
        .accessibilityLabel(icon == "plus" ? "Zoomer" : "Dézoomer")
    }

    private func clamp(_ s: CGFloat) -> CGFloat { min(2.5, max(0.2, s)) }
    private func setScale(_ s: CGFloat) { withAnimation(.snappy) { scale = clamp(s); pinchBase = scale } }

    /// « Tout voir » : le tableau entier tient dans l'écran (ou en largeur à l'ouverture)
    private func fit(_ viewport: CGSize, widthOnly: Bool) {
        let w = (viewport.width - 20) / baseSize.width
        let h = (viewport.height - 70) / baseSize.height
        scale = clamp(widthOnly ? min(1, w) : min(w, h))
        pinchBase = scale
    }

    // MARK: Dessin

    private var canvas: some View {
        let finalCol = tree.cols - 1
        return ZStack(alignment: .topLeading) {
            // Lignes et raccords
            Canvas { ctx, _ in
                for j in tree.joins {
                    var p = Path()
                    let x = CGFloat(j.col) * W
                    p.move(to: CGPoint(x: x, y: CGFloat(j.y1) * H + lineY))
                    p.addLine(to: CGPoint(x: x, y: CGFloat(j.y2) * H + lineY))
                    ctx.stroke(p, with: .color(Theme.ink.opacity(0.75)), lineWidth: 1.4)
                }
                for it in tree.items {
                    let root = !it.leaf && it.col == finalCol
                    let x1 = CGFloat(it.col) * W
                    let x2 = CGFloat(it.col + it.span) * W + (root ? W * 0.35 : 0)
                    let y = CGFloat(it.y) * H + lineY
                    var p = Path()
                    p.move(to: CGPoint(x: x1, y: y))
                    p.addLine(to: CGPoint(x: x2, y: y))
                    if it.leaf && it.type == "bye" {
                        ctx.stroke(p, with: .color(Theme.line), style: StrokeStyle(lineWidth: 1, dash: [4, 4]))
                    } else {
                        let color: Color = root ? Theme.gold : (it.isLive ? Theme.red : (it.leaf ? Theme.faint : Theme.ink.opacity(0.75)))
                        ctx.stroke(p, with: .color(color), lineWidth: root ? 3 : (it.leaf ? 1 : 1.4))
                    }
                }
            }
            .frame(width: baseSize.width, height: baseSize.height)

            // Noms, clubs, scores
            ForEach(Array(tree.items.enumerated()), id: \.offset) { _, it in
                label(it, root: !it.leaf && it.col == finalCol)
                    .frame(width: CGFloat(it.span) * W + (!it.leaf && it.col == finalCol ? W * 0.35 : 0) - 8, height: H, alignment: .topLeading)
                    .offset(x: CGFloat(it.col) * W + 6, y: CGFloat(it.y) * H)
            }
        }
    }

    @ViewBuilder private func label(_ it: TreeItem, root: Bool) -> some View {
        let fav = it.person.map { favorites.contains($0.registrationId) } ?? false
        VStack(alignment: .leading, spacing: 0) {
            // Au-dessus de la ligne : le nom
            HStack(alignment: .lastTextBaseline, spacing: 4) {
                if let a = it.person {
                    if it.leaf { ClubLogo(path: a.clubLogo, name: a.club, size: 16) }
                    Text("\(Text(a.lastName).fontWeight(.heavy)) \(a.firstName)")
                        .font(.system(size: root ? 13.5 : 12))
                        .foregroundStyle(fav ? Theme.red : Theme.ink)
                        .lineLimit(1).minimumScaleFactor(0.65)
                    if let s = it.seed { Text("TS\(s)").font(.system(size: 9, weight: .heavy)).foregroundStyle(Theme.red) }
                    if fav { Image(systemName: "star.fill").font(.system(size: 9)).foregroundStyle(Theme.yellow) }
                } else if it.leaf, it.type == "pending", let t = it.text {
                    Text(t).font(.system(size: 10).italic()).foregroundStyle(Theme.faint).lineLimit(1).minimumScaleFactor(0.7)
                }
            }
            .frame(height: lineY - 2, alignment: .bottomLeading)
            Spacer().frame(height: 4)
            // Sous la ligne : club, score ou état
            Group {
                if it.leaf, let a = it.athlete {
                    Text(a.clubShort ?? a.club ?? "").font(.system(size: 9.5)).foregroundStyle(Theme.muted)
                } else if let sc = it.score {
                    Text(sc.replacingOccurrences(of: "\\", with: " \\ ")).font(.score(11, weight: 800)).foregroundStyle(Theme.ink)
                } else if it.isLive {
                    Text("EN COURS · tapis \(it.mat ?? 0)").font(.system(size: 9, weight: .heavy)).foregroundStyle(Theme.red)
                } else if it.status == "scheduled" {
                    Text("Tapis \(it.mat ?? 0)").font(.system(size: 9.5, weight: .semibold)).foregroundStyle(Theme.muted)
                }
            }
            .lineLimit(1)
        }
        .contentShape(Rectangle())
        .overlay(alignment: .topLeading) {
            if !it.leaf, let id = it.boutId {
                NavigationLink(value: Route.bout(id)) { Color.clear }.buttonStyle(.plain)
            }
        }
    }
}

// MARK: - PDF à jour

/// Télécharge le PDF du tableau à l'instant (résultats du moment), l'affiche et propose de le partager / l'enregistrer.
struct BracketPDFSheet: View {
    let path: String
    let title: String
    @Environment(\.dismiss) private var dismiss
    @State private var file: URL?
    @State private var error: String?

    var body: some View {
        NavigationStack {
            Group {
                if let file {
                    PDFKitView(url: file)
                } else if let error {
                    ErrorCard(message: error) { Task { await load() } }.padding(16)
                    Spacer()
                } else {
                    VStack(spacing: 12) { ProgressView(); Text("Préparation du tableau à jour…").font(.subheadline).foregroundStyle(Theme.muted) }
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                }
            }
            .navigationTitle(title)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Fermer") { dismiss() } }
                if let file {
                    ToolbarItem(placement: .confirmationAction) {
                        ShareLink(item: file) { Label("Partager", systemImage: "square.and.arrow.up") }
                    }
                }
            }
            .task { await load() }
        }
    }

    private func load() async {
        error = nil
        guard let url = URL(string: API.server + path) else { error = "Adresse invalide."; return }
        do {
            let (data, resp) = try await API.session.data(from: url)
            guard (resp as? HTTPURLResponse)?.statusCode == 200, data.starts(with: Data("%PDF".utf8)) else {
                error = "Le tableau n'est pas disponible pour le moment."; return
            }
            let stamp = Date().formatted(.dateTime.day().month(.twoDigits).hour().minute().locale(Locale(identifier: "fr_FR")))
                .replacingOccurrences(of: "/", with: "-").replacingOccurrences(of: ":", with: "h")
            let name = "Tableau \(title) \(stamp).pdf".replacingOccurrences(of: "/", with: "-")
            let target = FileManager.default.temporaryDirectory.appendingPathComponent(name)
            try data.write(to: target, options: .atomic)
            file = target
        } catch { self.error = readable(error) }
    }
}

struct PDFKitView: UIViewRepresentable {
    let url: URL
    func makeUIView(context: Context) -> PDFView {
        let v = PDFView()
        v.autoScales = true
        v.backgroundColor = .secondarySystemBackground
        v.document = PDFDocument(url: url)
        return v
    }
    func updateUIView(_ v: PDFView, context: Context) {
        if v.document?.documentURL != url { v.document = PDFDocument(url: url) }
    }
}
