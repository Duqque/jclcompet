import SwiftUI

/// Tableau d'affichage façon TV : ligne rouge (premier appelé) et ligne blanche, chiffres Poppins italique,
/// chrono qui tourne en direct (horloge serveur) et barre d'osaekomi avec ses seuils.
struct Scoreboard: View {
    let bout: Bout
    var large = true

    var body: some View {
        VStack(spacing: 0) {
            row("a")
            row("b")
            footer
        }
        .clipShape(RoundedRectangle(cornerRadius: Theme.radius, style: .continuous))
        .overlay(RoundedRectangle(cornerRadius: Theme.radius, style: .continuous).stroke(Theme.line))
        .shadow(color: .black.opacity(0.08), radius: 18, y: 8)
    }

    private func fighter(_ s: String) -> Fighter? { s == "a" ? bout.a : bout.b }

    private func row(_ side: String) -> some View {
        let red = side == "a"
        let f = fighter(side)
        return VStack(spacing: 0) {
            HStack(spacing: large ? 12 : 10) {
                ClubLogo(path: f?.clubLogo, name: f?.club, size: large ? 46 : 34)
                VStack(alignment: .leading, spacing: 1) {
                    if let f {
                        Text(f.firstName).font(.system(size: large ? 14 : 12, weight: .medium)).opacity(0.85).lineLimit(1)
                        Text(f.lastName).font(.score(large ? 22 : 17, weight: 800)).lineLimit(1).minimumScaleFactor(0.6)
                        Text(f.clubShort ?? f.club ?? "").font(.system(size: 11, weight: .medium)).opacity(0.75).lineLimit(1)
                    } else {
                        Text(red ? "En attente" : " ").font(.headline).opacity(0.7)
                    }
                }
                Spacer(minLength: 4)
                if bout.winner == side {
                    Image(systemName: "trophy.fill").font(.system(size: large ? 18 : 14)).foregroundStyle(red ? .white : Theme.gold)
                }
                if let f {
                    ScoreDigits(fighter: f, compact: !large, highlight: true, useYuko: bout.model?.useYuko ?? true, onRed: red)
                }
            }
            .padding(.horizontal, large ? 16 : 12).padding(.vertical, large ? 14 : 10)
            OsaekomiBar(bout: bout, side: side, onRed: red)
        }
        .foregroundStyle(red ? Color.white : Theme.ink)
        .background(red ? AnyShapeStyle(LinearGradient(colors: [Color(hex: 0xD3243A), Theme.red], startPoint: .topLeading, endPoint: .bottomTrailing))
                        : AnyShapeStyle(Color.white))
        .environment(\.colorScheme, .light)
    }

    private var footer: some View {
        HStack {
            VStack(alignment: .leading, spacing: 2) {
                Text(bout.mat.map { "Tapis \($0)" } ?? "Combat").font(.system(size: 13, weight: .bold))
                Text(bout.categoryLine).font(.caption).foregroundStyle(.white.opacity(0.65)).lineLimit(1)
            }
            Spacer()
            ClockText(bout: bout, size: large ? 38 : 26)
        }
        .foregroundStyle(.white)
        .padding(.horizontal, large ? 16 : 12).padding(.vertical, large ? 12 : 8)
        .background(Color(hex: 0x18181B))
    }
}

struct ClockText: View {
    let bout: Bout
    var size: CGFloat = 38
    var body: some View {
        TimelineView(.periodic(from: .now, by: 0.25)) { ctx in
            let (text, label) = compute(at: ServerClock.shared.now(ctx.date))
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                if let label { Text(label).font(.system(size: 11, weight: .heavy)).tracking(0.6).foregroundStyle(bout.clock?.goldenScore == true ? Theme.yellow : .white.opacity(0.7)) }
                Text(text).font(.score(size, weight: 900)).monospacedDigit()
                    .foregroundStyle(bout.clock?.goldenScore == true ? Theme.yellow : (bout.clock?.running == true ? .white : .white.opacity(0.75)))
            }
        }
    }

    private func compute(at now: Double) -> (String, String?) {
        guard let c = bout.clock else { return (bout.isDone ? "—" : "", nil) }
        var elapsed = Double(c.elapsedMs)
        if c.running, let s = c.startedAtMs { elapsed += max(0, now - Double(s)) }
        if bout.isDone {
            return (c.goldenScore ? clockText(c.elapsedMs) : clockText(max(0, c.durationMs - (c.finalElapsedMs ?? c.elapsedMs))), c.goldenScore ? "GOLDEN SCORE" : "FIN")
        }
        if c.goldenScore {
            if c.goldenScoreMode == "limited", let g = c.goldenScoreMs { return (clockText(Int(Double(g) - elapsed)), "GOLDEN SCORE") }
            return (clockText(Int(elapsed)), "GOLDEN SCORE")
        }
        let label: String? = !c.running && c.elapsedMs > 0 ? "MATTE" : nil
        return (clockText(Int(Double(c.durationMs) - elapsed)), bout.isLive ? label : nil)
    }
}

struct OsaekomiBar: View {
    let bout: Bout
    let side: String
    let onRed: Bool

    var body: some View {
        if let o = bout.osaekomi, o.side == side, bout.isLive {
            TimelineView(.periodic(from: .now, by: 0.1)) { ctx in
                let th = bout.model?.osaekomi
                let ippon = Double(th?.ippon ?? 20), waza = Double(th?.wazaari ?? 10), yuko = Double(th?.yuko ?? 5)
                let sec = max(0, ServerClock.shared.now(ctx.date) - Double(o.startedAtMs)) / 1000
                VStack(alignment: .leading, spacing: 4) {
                    HStack {
                        Text("OSAEKOMI").font(.system(size: 11, weight: .heavy)).tracking(0.8)
                        Spacer()
                        Text("\(Int(sec)) s").font(.score(16, weight: 800)).monospacedDigit()
                    }
                    GeometryReader { g in
                        ZStack(alignment: .leading) {
                            Capsule().fill(onRed ? Color.white.opacity(0.25) : Theme.surface2)
                            Capsule().fill(onRed ? Color.white : Theme.red).frame(width: g.size.width * min(1, sec / ippon))
                            ForEach([(yuko, "Y"), (waza, "W")], id: \.1) { v, _ in
                                Rectangle().fill(onRed ? Theme.redDeep : Theme.ink.opacity(0.5)).frame(width: 2)
                                    .offset(x: g.size.width * v / ippon)
                                    .opacity(v == yuko && bout.model?.useYuko == false ? 0 : 1)
                            }
                        }
                    }
                    .frame(height: 8)
                }
                .padding(.horizontal, 16).padding(.bottom, 12)
            }
        }
    }
}
