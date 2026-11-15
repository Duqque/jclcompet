import SwiftUI

/// Logo de club, toujours dans une carte blanche, jamais déformé (règle du site).
struct ClubLogo: View {
    let path: String?
    var name: String? = nil
    var size: CGFloat = 36

    var body: some View {
        RoundedRectangle(cornerRadius: size * 0.28, style: .continuous)
            .fill(Color.white)
            .overlay {
                if let url = API.media(path) {
                    CachedImage(url: url) { initials }.padding(size * 0.12)
                } else { initials }
            }
            .frame(width: size, height: size)
            .overlay(RoundedRectangle(cornerRadius: size * 0.28, style: .continuous).stroke(Color.black.opacity(0.06)))
    }

    private var initials: some View {
        let words = (name ?? "").split(whereSeparator: { $0 == " " || $0 == "-" }).filter { $0.count > 2 }.prefix(3)
        return Text(words.map { String($0.prefix(1)) }.joined().uppercased())
            .font(.system(size: size * 0.3, weight: .bold)).foregroundStyle(Color(hex: 0x71717A))
    }
}

/// Pastille « EN DIRECT » qui pulse.
struct LiveBadge: View {
    var label = "EN DIRECT"
    var inverted = false
    @State private var on = false
    var body: some View {
        HStack(spacing: 6) {
            Circle().fill(inverted ? Theme.red : .white).frame(width: 7, height: 7).opacity(on ? 1 : 0.35)
            Text(label).font(.system(size: 11, weight: .heavy)).tracking(0.8)
        }
        .padding(.horizontal, 10).padding(.vertical, 5)
        .foregroundStyle(inverted ? Theme.red : .white)
        .background(inverted ? Color.white : Theme.red, in: Capsule())
        .onAppear { withAnimation(.easeInOut(duration: 0.8).repeatForever()) { on = true } }
        .accessibilityLabel("En direct")
    }
}

/// Dessin de ceinture : le premier appelé porte la ceinture rouge, le second la blanche.
struct BeltMark: View {
    let side: String
    var width: CGFloat = 18
    var body: some View {
        ZStack {
            Capsule().fill(side == "a" ? Theme.red : Color.white)
                .overlay(Capsule().stroke(side == "a" ? Theme.redDeep : Color(hex: 0xD4D4D8), lineWidth: 1))
                .frame(width: width, height: width * 0.36)
            RoundedRectangle(cornerRadius: 1.5).fill(side == "a" ? Theme.redDeep : Color(hex: 0xD4D4D8))
                .frame(width: width * 0.22, height: width * 0.5)
        }
        .accessibilityLabel(side == "a" ? "Ceinture rouge" : "Ceinture blanche")
    }
}

struct Chip: View {
    let text: String
    var color: Color = Theme.muted
    var fill: Color = Theme.surface2
    var body: some View {
        Text(text).font(.system(size: 12, weight: .semibold)).foregroundStyle(color)
            .padding(.horizontal, 9).padding(.vertical, 4)
            .background(fill, in: Capsule())
    }
}

struct StatusChip: View {
    let bout: Bout
    var body: some View {
        switch bout.status {
        case "in_progress": LiveBadge(label: "EN COURS")
        case "validated", "finished", "forfeit": Chip(text: bout.methodLabel ?? bout.statusLabel, color: Theme.green, fill: Theme.greenSoft)
        default: Chip(text: bout.statusLabel)
        }
    }
}

struct SectionTitle: View {
    let title: String
    var trailing: String? = nil
    var body: some View {
        HStack(alignment: .firstTextBaseline) {
            Text(title).font(.system(size: 20, weight: .bold)).foregroundStyle(Theme.ink)
            Spacer()
            if let t = trailing { Text(t).font(.subheadline).foregroundStyle(Theme.muted) }
        }
        .padding(.top, 6)
    }
}

struct EmptyState: View {
    let icon: String
    let title: String
    var message: String? = nil
    var body: some View {
        VStack(spacing: 10) {
            Image(systemName: icon).font(.system(size: 30, weight: .semibold)).foregroundStyle(Theme.red)
                .frame(width: 64, height: 64).background(Theme.redSoft, in: Circle())
            Text(title).font(.headline).foregroundStyle(Theme.ink).multilineTextAlignment(.center)
            if let m = message { Text(m).font(.subheadline).foregroundStyle(Theme.muted).multilineTextAlignment(.center) }
        }
        .frame(maxWidth: .infinity).padding(.vertical, 36).padding(.horizontal, 20)
    }
}

struct ErrorCard: View {
    let message: String
    var retry: (() -> Void)? = nil
    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            Image(systemName: "wifi.exclamationmark").foregroundStyle(Theme.red)
            VStack(alignment: .leading, spacing: 8) {
                Text(message).font(.subheadline).foregroundStyle(Theme.ink)
                if let retry { Button("Réessayer", action: retry).font(.subheadline.weight(.semibold)) }
            }
        }
        .card()
    }
}

/// Ligne d'un combattant dans une carte de combat.
struct FighterLine: View {
    let fighter: Fighter?
    let side: String
    let winner: String?
    var showScore = true

    var body: some View {
        HStack(spacing: 10) {
            BeltMark(side: side)
            ClubLogo(path: fighter?.clubLogo, name: fighter?.club, size: 30)
            VStack(alignment: .leading, spacing: 1) {
                if let f = fighter {
                    Text("\(f.firstName) \(Text(f.lastName).fontWeight(.bold))").font(.system(size: 15)).foregroundStyle(Theme.ink).lineLimit(1)
                    Text(f.clubShort ?? f.club ?? "").font(.caption).foregroundStyle(Theme.muted).lineLimit(1)
                } else {
                    Text("À désigner").font(.subheadline).foregroundStyle(Theme.faint)
                }
            }
            Spacer(minLength: 4)
            if winner == side { Image(systemName: "checkmark.circle.fill").foregroundStyle(Theme.green).font(.system(size: 15)) }
            if showScore, let f = fighter { ScoreDigits(fighter: f, compact: true, highlight: winner == side) }
        }
    }
}

/// Chiffres I-W-Y et cartons, comme sur le tableau d'affichage.
struct ScoreDigits: View {
    let fighter: Fighter
    var compact = false
    var highlight = false
    var useYuko = true
    var onRed = false

    var body: some View {
        HStack(spacing: compact ? 3 : 10) {
            digit(fighter.ippon)
            digit(fighter.wazaari)
            if useYuko { digit(fighter.yuko) }
            if fighter.shido > 0 || fighter.hansoku > 0 {
                HStack(spacing: 2) {
                    if fighter.hansoku > 0 { card(Theme.red, wide: true) }
                    else { ForEach(0..<min(fighter.shido, 3), id: \.self) { _ in card(Theme.yellow) } }
                }.padding(.leading, compact ? 3 : 4)
            }
        }
    }

    private func digit(_ v: Int) -> some View {
        Text("\(v)").font(.score(compact ? 17 : 44, weight: 900))
            .foregroundStyle(onRed ? .white : highlight ? Theme.ink : (compact ? Theme.muted : Theme.ink))
            .frame(minWidth: compact ? 11 : 30)
            .monospacedDigit()
    }

    private func card(_ c: Color, wide: Bool = false) -> some View {
        RoundedRectangle(cornerRadius: 2).fill(c)
            .frame(width: (compact ? 7 : 13) * (wide ? 1.6 : 1), height: compact ? 11 : 20)
            .overlay(RoundedRectangle(cornerRadius: 2).stroke(.black.opacity(0.12)))
    }
}

/// Carte de combat (liste, ordre des combats, historique).
struct BoutRow: View {
    let bout: Bout
    var showMeta = true
    @Environment(Favorites.self) private var favorites

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            if showMeta {
                HStack(spacing: 8) {
                    if let m = bout.mat { Text("Tapis \(m)").font(.caption.weight(.bold)).foregroundStyle(Theme.red) }
                    if let o = bout.orderOnMat { Text("Combat \(o)").font(.caption).foregroundStyle(Theme.muted) }
                    if let p = bout.poolNumber { Text("Poule \(p)").font(.caption).foregroundStyle(Theme.muted) }
                    if bout.involves(favorites.ids) { Image(systemName: "star.fill").font(.caption2).foregroundStyle(Theme.yellow) }
                    Spacer()
                    StatusChip(bout: bout)
                }
                if !bout.categoryLine.isEmpty { Text(bout.categoryLine).font(.caption).foregroundStyle(Theme.muted) }
            }
            FighterLine(fighter: bout.a, side: "a", winner: bout.winner, showScore: bout.isDone || bout.isLive)
            FighterLine(fighter: bout.b, side: "b", winner: bout.winner, showScore: bout.isDone || bout.isLive)
        }
        .card(padding: 14)
    }
}

/// Barre d'onglets façon JudoTV (pilules horizontales défilantes).
struct PillTabs<T: Hashable>: View {
    let items: [(T, String)]
    @Binding var selection: T
    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                ForEach(items, id: \.0) { value, label in
                    Button { withAnimation(.snappy) { selection = value } } label: {
                        Text(label).font(.system(size: 14, weight: .semibold))
                            .padding(.horizontal, 14).padding(.vertical, 8)
                            .foregroundStyle(selection == value ? Theme.onInk : Theme.ink)
                            .background(selection == value ? Theme.ink : Theme.surface, in: Capsule())
                            .overlay(Capsule().stroke(Theme.line, lineWidth: selection == value ? 0 : 1))
                    }
                    .buttonStyle(.plain)
                }
            }
            .padding(.horizontal, 16)
        }
    }
}

struct StatTile: View {
    let value: String
    let label: String
    var body: some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(value).font(.score(26, weight: 800)).foregroundStyle(Theme.ink)
            Text(label).font(.caption).foregroundStyle(Theme.muted)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(14)
        .background(Theme.surface, in: RoundedRectangle(cornerRadius: 16, style: .continuous))
    }
}

struct FavoriteButton: View {
    let athlete: FollowedAthlete
    @Environment(Favorites.self) private var favorites
    var body: some View {
        let on = favorites.contains(athlete.registrationId)
        Button { withAnimation(.snappy) { favorites.toggle(athlete) } } label: {
            Image(systemName: on ? "star.fill" : "star")
                .font(.system(size: 17, weight: .semibold))
                .foregroundStyle(on ? Theme.yellow : Theme.faint)
                .frame(width: 36, height: 36)
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .sensoryFeedback(.selection, trigger: on)
        .accessibilityLabel(on ? "Ne plus suivre \(athlete.fullName)" : "Suivre \(athlete.fullName)")
    }
}

struct AthleteRow: View {
    let athlete: Athlete
    var body: some View {
        HStack(spacing: 12) {
            ClubLogo(path: athlete.clubLogo, name: athlete.club, size: 40)
            VStack(alignment: .leading, spacing: 2) {
                Text("\(athlete.firstName) \(Text(athlete.lastName).fontWeight(.bold))").font(.system(size: 15)).foregroundStyle(Theme.ink)
                Text([athlete.clubShort ?? athlete.club, athlete.categoryLine].compactMap { $0 }.filter { !$0.isEmpty }.joined(separator: " · "))
                    .font(.caption).foregroundStyle(Theme.muted).lineLimit(1)
            }
            Spacer(minLength: 4)
            if let p = athlete.place { MedalPill(place: p, medal: athlete.medal) }
            FavoriteButton(athlete: FollowedAthlete(athlete))
        }
        .padding(.vertical, 10).padding(.horizontal, 14)
        .background(Theme.surface, in: RoundedRectangle(cornerRadius: 16, style: .continuous))
    }
}

struct MedalPill: View {
    let place: Int
    let medal: String?
    var body: some View {
        HStack(spacing: 4) {
            Circle().fill(Theme.medal(medal)).frame(width: 9, height: 9)
            Text(place <= 3 ? "\(place)e" : "\(place)e").font(.system(size: 12, weight: .bold)).foregroundStyle(Theme.ink)
        }
        .padding(.horizontal, 8).padding(.vertical, 4)
        .background(Theme.surface2, in: Capsule())
        .accessibilityLabel("\(place)e place")
    }
}

/// Rafraîchit une vue tant qu'elle est affichée.
struct Poller: ViewModifier {
    let seconds: Double
    let action: () async -> Void
    func body(content: Content) -> some View {
        content.task {
            while !Task.isCancelled {
                await action()
                try? await Task.sleep(for: .seconds(seconds))
            }
        }
    }
}

extension View {
    func polling(every seconds: Double, _ action: @escaping () async -> Void) -> some View {
        modifier(Poller(seconds: seconds, action: action))
    }
}

/// Cache mémoire des logos et photos : les vues se rafraîchissent toutes les 2 s sans recharger les images.
@MainActor final class ImageCache {
    static let shared = ImageCache()
    private let cache = NSCache<NSURL, UIImage>()
    private var tasks: [URL: Task<UIImage?, Never>] = [:]

    func cached(_ url: URL) -> UIImage? { cache.object(forKey: url as NSURL) }

    func load(_ url: URL) async -> UIImage? {
        if let i = cached(url) { return i }
        let task = tasks[url] ?? Task.detached(priority: .utility) {
            guard let (data, _) = try? await URLSession.shared.data(from: url), let img = UIImage(data: data) else { return nil }
            return img.preparingForDisplay() ?? img
        }
        tasks[url] = task
        let img = await task.value
        tasks[url] = nil
        if let img { cache.setObject(img, forKey: url as NSURL) }
        return img
    }
}

struct CachedImage<Placeholder: View>: View {
    let url: URL
    @ViewBuilder var placeholder: () -> Placeholder
    @State private var image: UIImage?

    var body: some View {
        Group {
            if let img = image ?? ImageCache.shared.cached(url) {
                Image(uiImage: img).resizable().scaledToFit()
            } else { placeholder() }
        }
        .task(id: url) { if image == nil { image = await ImageCache.shared.load(url) } }
    }
}
