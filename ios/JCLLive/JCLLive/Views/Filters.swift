import SwiftUI

/// Filtres des listes de judokas : sexe, catégorie d'âge, catégorie de poids, club.
struct AthleteFilter: Equatable {
    var sex = ""        // "", "F", "M"
    var category = ""
    var weight = ""
    var club = ""

    var isActive: Bool { !sex.isEmpty || !category.isEmpty || !weight.isEmpty || !club.isEmpty }

    func matches(_ a: Athlete, ignoring: KeyPath<AthleteFilter, String>? = nil) -> Bool {
        (ignoring == \.sex || sex.isEmpty || a.sex == sex)
            && (ignoring == \.category || category.isEmpty || a.category == category)
            && (ignoring == \.weight || weight.isEmpty || a.weightLabel == weight)
            && (ignoring == \.club || club.isEmpty || a.clubName == club)
    }
}

extension Athlete {
    var weightLabel: String { weight.map { $0.text.isEmpty ? "" : "\($0.text) kg" } ?? "" }
    var clubName: String { clubShort ?? club ?? "" }
}

/// Barre de filtres en pastilles ; chaque menu ne propose que les valeurs encore possibles avec les autres filtres.
struct AthleteFilterBar: View {
    let athletes: [Athlete]
    @Binding var filter: AthleteFilter

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                menu("Sexe", \.sex, options: [("F", "Filles"), ("M", "Garçons")].filter { o in athletes.contains { $0.sex == o.0 && filter.matches($0, ignoring: \.sex) } })
                menu("Catégorie", \.category, options: values(\.category) { $0.category ?? "" }.map { ($0, $0) })
                menu("Poids", \.weight, options: values(\.weight) { $0.weightLabel }.sorted(by: weightOrder).map { ($0, $0) })
                menu("Club", \.club, options: values(\.club) { $0.clubName }.map { ($0, $0) })
                if filter.isActive {
                    Button { withAnimation(.snappy) { filter = AthleteFilter() } } label: {
                        Label("Effacer", systemImage: "xmark").font(.system(size: 13, weight: .semibold))
                            .padding(.horizontal, 12).padding(.vertical, 8)
                            .foregroundStyle(Theme.red)
                            .background(Theme.redSoft, in: Capsule())
                    }
                    .buttonStyle(.plain)
                }
            }
            .padding(.horizontal, 16)
        }
    }

    /// Valeurs distinctes présentes parmi les judokas qui passent les autres filtres.
    private func values(_ key: KeyPath<AthleteFilter, String>, _ get: (Athlete) -> String) -> [String] {
        let v = Set(athletes.filter { filter.matches($0, ignoring: key) }.map(get).filter { !$0.isEmpty })
        return v.sorted { $0.localizedStandardCompare($1) == .orderedAscending }
    }

    /// -24 kg, -27 kg … puis +73 kg.
    private func weightOrder(_ a: String, _ b: String) -> Bool {
        func key(_ s: String) -> (Int, Double) {
            let n = Double(s.filter { $0.isNumber || $0 == "." || $0 == "," }.replacingOccurrences(of: ",", with: ".")) ?? 0
            return (s.hasPrefix("+") ? 1 : 0, n)
        }
        return key(a) < key(b)
    }

    private func menu(_ title: String, _ key: WritableKeyPath<AthleteFilter, String>, options: [(String, String)]) -> some View {
        let current = filter[keyPath: key]
        let label = options.first { $0.0 == current }?.1 ?? (current.isEmpty ? title : current)
        return Menu {
            Picker(title, selection: Binding(get: { filter[keyPath: key] }, set: { v in withAnimation(.snappy) { filter[keyPath: key] = v } })) {
                Text("Tous").tag("")
                ForEach(options, id: \.0) { Text($0.1).tag($0.0) }
            }
        } label: {
            HStack(spacing: 5) {
                Text(label).lineLimit(1)
                Image(systemName: "chevron.down").font(.system(size: 10, weight: .bold))
            }
            .font(.system(size: 14, weight: .semibold))
            .padding(.horizontal, 13).padding(.vertical, 8)
            .foregroundStyle(current.isEmpty ? Theme.ink : Theme.onInk)
            .background(current.isEmpty ? Theme.surface : Theme.ink, in: Capsule())
            .overlay(Capsule().stroke(Theme.line, lineWidth: current.isEmpty ? 1 : 0))
        }
        .accessibilityLabel("\(title) : \(current.isEmpty ? "tous" : label)")
    }
}
