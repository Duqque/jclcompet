import SwiftUI
import UIKit

/// Jetons de design repris du site (public/assets/css/app.css) : fond gris chaud, cartes blanches arrondies,
/// rouge du club en touche, halo rosé, chiffres en Poppins italique.
enum Theme {
    static let canvas = Color(light: 0xECEBE8, dark: 0x111113)
    static let panel = Color(light: 0xF5F5F3, dark: 0x18181B)
    static let surface = Color(light: 0xFFFFFF, dark: 0x1F1F23)
    static let surface2 = Color(light: 0xF4F4F2, dark: 0x27272A)
    static let ink = Color(light: 0x18181B, dark: 0xF4F4F5)
    static let onInk = Color(light: 0xFFFFFF, dark: 0x18181B)
    static let muted = Color(light: 0x71717A, dark: 0xA1A1AA)
    static let faint = Color(light: 0xA1A1AA, dark: 0x71717A)
    static let line = Color(light: 0xE6E6E3, dark: 0x2E2E33)
    static let red = Color(hex: 0xC21A2C)
    static let redDeep = Color(hex: 0x8F1220)
    static let redSoft = Color(light: 0xFDECEE, dark: 0x3A1418)
    static let yellow = Color(hex: 0xF5C400)
    static let green = Color(hex: 0x15803D)
    static let greenSoft = Color(light: 0xE7F7EC, dark: 0x12301D)
    static let gold = Color(hex: 0xD4A72C)
    static let silver = Color(hex: 0xA3AAB3)
    static let bronze = Color(hex: 0xB8743F)

    static let radius: CGFloat = 20
    static let radiusSmall: CGFloat = 12

    static func medal(_ m: String?) -> Color {
        switch m {
        case "gold": return gold
        case "silver": return silver
        case "bronze": return bronze
        default: return faint
        }
    }

    static func medalLabel(_ place: Int?) -> String {
        switch place {
        case 1: return "Or"
        case 2: return "Argent"
        case 3: return "Bronze"
        case let p?: return "\(p)e"
        default: return "—"
        }
    }
}

extension Font {
    /// Chiffres et titres « tableau d'affichage » : Poppins italique, comme sur le site et la TV.
    static func score(_ size: CGFloat, weight: Int = 800) -> Font {
        let name = weight >= 900 ? "Poppins-BlackItalic" : weight >= 800 ? "Poppins-ExtraBoldItalic" : "Poppins-BoldItalic"
        return .custom(name, size: size)
    }
}

extension Color {
    init(hex: UInt32, alpha: Double = 1) {
        self.init(.sRGB, red: Double((hex >> 16) & 0xFF) / 255, green: Double((hex >> 8) & 0xFF) / 255,
                  blue: Double(hex & 0xFF) / 255, opacity: alpha)
    }

    init(light: UInt32, dark: UInt32) {
        self.init(UIColor { trait in
            let v = trait.userInterfaceStyle == .dark ? dark : light
            return UIColor(red: CGFloat((v >> 16) & 0xFF) / 255, green: CGFloat((v >> 8) & 0xFF) / 255,
                           blue: CGFloat(v & 0xFF) / 255, alpha: 1)
        })
    }

    /// Couleur d'un club envoyée par l'API (« #eb1a1b »).
    init?(css: String?) {
        guard var s = css?.trimmingCharacters(in: .whitespaces), s.hasPrefix("#") else { return nil }
        s.removeFirst()
        if s.count == 3 { s = s.map { "\($0)\($0)" }.joined() }
        guard s.count == 6, let v = UInt32(s, radix: 16) else { return nil }
        self.init(hex: v)
    }
}

/// Halo rosé du site (variable CSS --glow), en haut à droite.
struct GlowBackground: View {
    var intensity: Double = 1
    var body: some View {
        ZStack {
            Theme.canvas
            RadialGradient(colors: [Color(hex: 0xF26A77, alpha: 0.85 * intensity), Color(hex: 0xF9BFC3, alpha: 0.5 * intensity), .clear],
                           center: UnitPoint(x: 0.9, y: -0.05), startRadius: 0, endRadius: 520)
        }
        .ignoresSafeArea()
    }
}

struct CardStyle: ViewModifier {
    var padding: CGFloat = 16
    func body(content: Content) -> some View {
        content
            .padding(padding)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Theme.surface, in: RoundedRectangle(cornerRadius: Theme.radius, style: .continuous))
            .shadow(color: .black.opacity(0.05), radius: 14, y: 6)
    }
}

extension View {
    func card(padding: CGFloat = 16) -> some View { modifier(CardStyle(padding: padding)) }

    /// Fond commun des écrans : gris chaud + halo.
    func screenBackground(glow: Double = 0.6) -> some View {
        background(GlowBackground(intensity: glow).background(SwipeBackLock()))
    }
}

/// Écrans fixes : depuis iOS 26, glisser vers la droite n'importe où sur l'écran ramène à la page précédente et fait
/// bouger tout le contenu. Ce geste est coupé dans toute l'application ; on revient avec le bouton ‹ (ou depuis le bord
/// gauche de l'écran, geste habituel d'iOS).
struct SwipeBackLock: UIViewRepresentable {
    func makeUIView(context: Context) -> LockView { LockView() }
    func updateUIView(_ view: LockView, context: Context) { view.lock() }

    final class LockView: UIView {
        override func didMoveToWindow() {
            super.didMoveToWindow()
            DispatchQueue.main.async { [weak self] in self?.lock() }
        }

        func lock() {
            var responder: UIResponder? = self
            while let r = responder, !(r is UIViewController) { responder = r.next }
            guard let nav = (responder as? UIViewController)?.navigationController else { return }
            if #available(iOS 26.0, *) { nav.interactiveContentPopGestureRecognizer?.isEnabled = false }
        }
    }
}

/// Couleur des combattants. Par défaut le premier appelé (côté « a ») porte le rouge et le second le blanc ;
/// le réglage « Premier appelé : Blanc » inverse l'affichage partout. Les vues lisent
/// `@AppStorage(SideColor.key)` pour se mettre à jour instantanément.
enum SideColor {
    static let key = "first_called_color"
    static let defaultValue = "red"

    static func isRed(_ side: String?, firstColor: String) -> Bool {
        guard let side else { return false }
        return (side == "a") == (firstColor != "white")
    }
}
