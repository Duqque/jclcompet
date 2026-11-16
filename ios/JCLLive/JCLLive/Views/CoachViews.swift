import SwiftUI
import PhotosUI

// Onglet « Mon club » : connexion des coachs, demande de compte, inscriptions du club en direct.

struct ClubTabView: View {
    @State private var store = AccountStore.shared
    var body: some View {
        NavigationStack {
            Group {
                if store.isSignedIn { CoachHomeView() } else { CoachWelcomeView() }
            }
            .screenBackground(glow: 0.9)
            .toolbar(.hidden, for: .navigationBar)
        }
    }
}

// MARK: - Accueil non connecté + connexion

struct CoachWelcomeView: View {
    @State private var store = AccountStore.shared
    @State private var email = UserDefaults.standard.string(forKey: "coach_email") ?? ""
    @State private var password = ""
    @State private var busy = false
    @State private var error: String?
    @State private var signup = false
    @FocusState private var focus: Field?
    private enum Field { case email, password }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 18) {
                hero
                if let n = store.notice { Notice(text: n, tint: Theme.red) }
                loginCard
                Button { signup = true } label: {
                    HStack(spacing: 12) {
                        Image(systemName: "person.badge.plus").font(.system(size: 18, weight: .semibold)).foregroundStyle(.white)
                            .frame(width: 42, height: 42).background(Theme.red, in: Circle())
                        VStack(alignment: .leading, spacing: 2) {
                            Text("Coach : pas encore de compte ?").font(.subheadline.weight(.bold)).foregroundStyle(Theme.ink)
                            Text("Créez-le en une minute, l'organisation le valide.").font(.caption).foregroundStyle(Theme.muted)
                        }
                        Spacer()
                        Image(systemName: "chevron.right").foregroundStyle(Theme.red)
                    }
                    .padding(14)
                    .background(Theme.redSoft, in: RoundedRectangle(cornerRadius: Theme.radius, style: .continuous))
                }
                .buttonStyle(.plain)
                Text("Commissaires, accueil et buvette : utilisez le site avec les comptes fournis par l'organisation.")
                    .font(.footnote).foregroundStyle(Theme.muted).frame(maxWidth: .infinity).multilineTextAlignment(.center).padding(.top, 4)
            }
            .padding(16)
        }
        .scrollDismissesKeyboard(.interactively)
        .sheet(isPresented: $signup) { SignUpView { mail in email = mail } }
    }

    private var hero: some View {
        VStack(alignment: .leading, spacing: 14) {
            Text("ESPACE COACH").font(.system(size: 11, weight: .heavy)).tracking(1.2)
                .padding(.horizontal, 10).padding(.vertical, 5).background(.white.opacity(0.2), in: Capsule())
            Text("Vos judokas,\nen direct").font(.score(34, weight: 900)).lineSpacing(-4)
            Text("Inscrivez vos combattants, validez votre liste, pointez présences et pesées le jour J. Le site et l'organisation sont à jour à la seconde.")
                .font(.subheadline).opacity(0.92)
            HStack(spacing: 8) {
                ForEach([("person.fill.badge.plus", "Inscrire"), ("checkmark.seal.fill", "Valider"), ("scalemass.fill", "Peser")], id: \.1) { icon, label in
                    Label(label, systemImage: icon).font(.caption.weight(.bold))
                        .padding(.horizontal, 10).padding(.vertical, 7).background(.white.opacity(0.18), in: Capsule())
                }
            }
        }
        .foregroundStyle(.white)
        .padding(20)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(
            LinearGradient(colors: [Color(hex: 0xE2404F), Theme.red, Theme.redDeep], startPoint: .topLeading, endPoint: .bottomTrailing),
            in: RoundedRectangle(cornerRadius: 26, style: .continuous))
    }

    private var loginCard: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text("Connexion").font(.system(size: 22, weight: .bold)).foregroundStyle(Theme.ink)
            InputField(title: "Adresse e-mail", text: $email, prompt: "prenom.nom@club.fr", keyboard: .emailAddress, content: .username)
                .focused($focus, equals: .email).submitLabel(.next).onSubmit { focus = .password }
            SecureInput(title: "Mot de passe", text: $password, content: .password)
                .focused($focus, equals: .password).submitLabel(.go).onSubmit { Task { await login() } }
            if let error { InlineError(text: error) }
            PrimaryButton(title: "Se connecter", busy: busy, disabled: email.isEmpty || password.isEmpty) { Task { await login() } }
        }
        .card(padding: 18)
    }

    private func login() async {
        guard !email.isEmpty, !password.isEmpty, !busy else { return }
        busy = true; error = nil
        defer { busy = false }
        do {
            try await store.signIn(email: email.trimmingCharacters(in: .whitespaces), password: password)
            UserDefaults.standard.set(email, forKey: "coach_email")
            password = ""
        } catch { self.error = readable(error) }
    }
}

// MARK: - Demande de compte

struct SignUpView: View {
    var done: (String) -> Void
    @Environment(\.dismiss) private var dismiss
    @State private var first = ""
    @State private var last = ""
    @State private var email = ""
    @State private var phone = ""
    @State private var club: ClubRef?
    @State private var newClub = false
    @State private var clubName = ""
    @State private var clubCity = ""
    @State private var password = ""
    @State private var confirm = ""
    @State private var message = ""
    @State private var picker = false
    @State private var busy = false
    @State private var error: String?
    @State private var sent: String?

    var body: some View {
        NavigationStack {
            ScrollView {
                if let sent { success(sent) } else { form }
            }
            .scrollDismissesKeyboard(.interactively)
            .screenBackground(glow: 0.5)
            .navigationTitle(sent == nil ? "Créer un compte coach" : "")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar { ToolbarItem(placement: .cancellationAction) { Button(sent == nil ? "Annuler" : "Fermer") { dismiss() } } }
            .sheet(isPresented: $picker) { ClubPickerSheet(selection: $club) }
        }
    }

    private var form: some View {
        VStack(alignment: .leading, spacing: 14) {
            Text("L'organisation du JC Leforest vérifie chaque demande. Dès qu'elle est acceptée, vous vous connectez ici et sur le site avec la même adresse et le même mot de passe.")
                .font(.subheadline).foregroundStyle(Theme.muted)
            VStack(alignment: .leading, spacing: 12) {
                HStack(spacing: 10) {
                    InputField(title: "Prénom", text: $first, content: .givenName)
                    InputField(title: "Nom", text: $last, content: .familyName)
                }
                InputField(title: "Adresse e-mail", text: $email, prompt: "prenom.nom@club.fr", keyboard: .emailAddress, content: .emailAddress)
                InputField(title: "Téléphone", text: $phone, prompt: "06 12 34 56 78", keyboard: .phonePad, content: .telephoneNumber)
            }
            .card(padding: 16)

            VStack(alignment: .leading, spacing: 12) {
                Text("Votre club").font(.subheadline.weight(.semibold)).foregroundStyle(Theme.ink)
                if !newClub {
                    Button { picker = true } label: {
                        HStack(spacing: 12) {
                            if let club { ClubLogo(path: club.logo, name: club.name, size: 40) }
                            else { Image(systemName: "person.3.fill").foregroundStyle(Theme.muted).frame(width: 40, height: 40).background(Theme.surface2, in: RoundedRectangle(cornerRadius: 11)) }
                            VStack(alignment: .leading, spacing: 1) {
                                Text(club?.short ?? "Choisir mon club").font(.body.weight(.semibold)).foregroundStyle(Theme.ink)
                                if let c = club?.city, !c.isEmpty { Text(c).font(.caption).foregroundStyle(Theme.muted) }
                            }
                            Spacer()
                            Image(systemName: "chevron.down").foregroundStyle(Theme.muted)
                        }
                        .padding(10)
                        .background((Color(css: club?.color) ?? Theme.surface2).opacity(club == nil ? 1 : 0.16), in: RoundedRectangle(cornerRadius: 14, style: .continuous))
                    }
                    .buttonStyle(.plain)
                } else {
                    HStack(spacing: 10) {
                        InputField(title: "Nom du club", text: $clubName)
                        InputField(title: "Ville", text: $clubCity, content: .addressCity)
                    }
                }
                Toggle("Mon club n'est pas dans la liste", isOn: $newClub.animation(.snappy)).font(.subheadline).tint(Theme.red)
            }
            .card(padding: 16)

            VStack(alignment: .leading, spacing: 12) {
                SecureInput(title: "Mot de passe", text: $password, content: .newPassword)
                SecureInput(title: "Confirmer le mot de passe", text: $confirm, content: .newPassword)
                Text("Au moins 12 caractères, avec des lettres et des chiffres.").font(.caption).foregroundStyle(Theme.muted)
                InputField(title: "Message pour l'organisation (facultatif)", text: $message, prompt: "Ex. : entraîneur des poussins")
            }
            .card(padding: 16)

            if let error { InlineError(text: error) }
            PrimaryButton(title: "Envoyer ma demande", busy: busy, disabled: first.isEmpty || last.isEmpty || email.isEmpty || password.isEmpty) { Task { await send() } }
            Text("Vos coordonnées servent uniquement à l'organisation de la compétition.").font(.caption).foregroundStyle(Theme.muted)
                .frame(maxWidth: .infinity).multilineTextAlignment(.center)
        }
        .padding(16)
    }

    private func success(_ text: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "paperplane.fill").font(.system(size: 30, weight: .semibold)).foregroundStyle(.white)
                .frame(width: 76, height: 76).background(Theme.red, in: Circle())
            Text("Demande envoyée").font(.score(28, weight: 900)).foregroundStyle(Theme.ink)
            Text(text).font(.subheadline).foregroundStyle(Theme.muted).multilineTextAlignment(.center)
            PrimaryButton(title: "Retour à la connexion") { done(email); dismiss() }
        }
        .padding(24).padding(.top, 40)
    }

    private func send() async {
        guard !busy else { return }
        if password != confirm { error = "Les deux mots de passe ne correspondent pas."; return }
        if !newClub && club == nil { error = "Choisissez votre club, ou indiquez qu'il n'est pas dans la liste."; return }
        busy = true; error = nil
        defer { busy = false }
        do {
            let r = try await CoachAPI.send("POST", "/compte/demande", body: [
                "first_name": first, "last_name": last, "email": email.trimmingCharacters(in: .whitespaces), "phone": phone,
                "club_id": newClub ? nil : club?.id, "club_name": newClub ? clubName : nil, "club_city": newClub ? clubCity : nil,
                "password": password, "password_confirmation": confirm, "message": message,
            ], auth: false, as: OkMessage.self)
            withAnimation(.snappy) { sent = r.message ?? "L'organisation valide votre compte." }
        } catch { self.error = readable(error) }
    }
}

/// Choix du club : cartes aux couleurs de chaque club, logo dans une carte blanche, nom dessous.
struct ClubPickerSheet: View {
    @Binding var selection: ClubRef?
    @Environment(\.dismiss) private var dismiss
    @State private var clubs: [ClubRef] = []
    @State private var query = ""
    @State private var error: String?

    private var shown: [ClubRef] {
        let q = query.folded
        return q.isEmpty ? clubs : clubs.filter { ($0.name + " " + $0.short + " " + ($0.city ?? "")).folded.contains(q) }
    }

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 12) {
                    SearchField(text: $query, prompt: "Club ou ville")
                    if let error { ErrorCard(message: error) { Task { await load() } } }
                    LazyVGrid(columns: [GridItem(.adaptive(minimum: 104), spacing: 10)], spacing: 10) {
                        ForEach(shown) { c in
                            Button { selection = c; dismiss() } label: {
                                VStack(spacing: 8) {
                                    ClubLogo(path: c.logo, name: c.name, size: 58)
                                    Text(c.short).font(.caption.weight(.semibold)).foregroundStyle(Theme.ink).lineLimit(2).multilineTextAlignment(.center)
                                }
                                .frame(maxWidth: .infinity, minHeight: 118)
                                .padding(8)
                                .background((Color(css: c.color) ?? Theme.muted).opacity(0.16), in: RoundedRectangle(cornerRadius: 18, style: .continuous))
                                .overlay(RoundedRectangle(cornerRadius: 18, style: .continuous).stroke(selection?.id == c.id ? Theme.red : .clear, lineWidth: 2))
                            }
                            .buttonStyle(.plain)
                        }
                    }
                    if !clubs.isEmpty && shown.isEmpty { EmptyState(icon: "magnifyingglass", title: "Aucun club pour « \(query) »", message: "Fermez et indiquez que votre club n'est pas dans la liste.") }
                }
                .padding(16)
            }
            .screenBackground(glow: 0.3)
            .navigationTitle("Choisir mon club").navigationBarTitleDisplayMode(.inline)
            .toolbar { ToolbarItem(placement: .cancellationAction) { Button("Fermer") { dismiss() } } }
            .task { await load() }
        }
    }

    private func load() async {
        do { clubs = try await CoachAPI.clubs(); error = nil } catch { self.error = readable(error) }
    }
}

// MARK: - Mon club (connecté)

struct CoachHomeView: View {
    @State private var store = AccountStore.shared
    @State private var home: CoachHome?
    @State private var error: String?
    @State private var logout = false

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                header
                if let error, home == nil { ErrorCard(message: error) { Task { await load() } } }
                if let home {
                    let open = home.competitions.filter(\.canEdit)
                    let day = home.competitions.filter { !$0.canEdit && $0.attendanceOpen }
                    let other = home.competitions.filter { !$0.canEdit && !$0.attendanceOpen && !$0.isPast }
                    let past = home.competitions.filter(\.isPast)
                    if !day.isEmpty { SectionTitle(title: "Jour J"); cards(day) }
                    if !open.isEmpty { SectionTitle(title: "Inscriptions ouvertes"); cards(open) }
                    if !other.isEmpty { SectionTitle(title: "À venir"); cards(other) }
                    if !past.isEmpty { SectionTitle(title: "Terminées"); cards(Array(past.prefix(4))) }
                    if home.competitions.isEmpty { EmptyState(icon: "calendar", title: "Aucune compétition pour l'instant") }
                } else if error == nil {
                    ProgressView().frame(maxWidth: .infinity).padding(.top, 60)
                }
            }
            .padding(16)
        }
        .refreshable { await load() }
        .polling(every: 10) { await load() }
        .navigationDestination(for: CoachCompetition.self) { CoachCompetitionView(summary: $0) }
        .confirmationDialog("Se déconnecter de l'espace coach ?", isPresented: $logout, titleVisibility: .visible) {
            Button("Se déconnecter", role: .destructive) { Task { await store.signOut() } }
        }
    }

    private var header: some View {
        HStack(spacing: 12) {
            ClubLogo(path: store.account?.club?.logo, name: store.account?.club?.name, size: 52)
            VStack(alignment: .leading, spacing: 1) {
                Text(store.account?.club?.short ?? "Mon club").font(.score(24, weight: 900)).foregroundStyle(Theme.ink).lineLimit(1)
                Text(store.account?.name ?? "").font(.caption).foregroundStyle(Theme.muted)
            }
            Spacer()
            Menu {
                if let a = store.account { Text(a.email) }
                Button(role: .destructive) { logout = true } label: { Label("Se déconnecter", systemImage: "rectangle.portrait.and.arrow.right") }
            } label: {
                Image(systemName: "person.crop.circle").font(.system(size: 19)).foregroundStyle(Theme.ink)
                    .frame(width: 42, height: 42).background(Theme.surface, in: Circle())
            }
            .accessibilityLabel("Compte")
        }
        .padding(.top, 4)
    }

    private func cards(_ list: [CoachCompetition]) -> some View {
        ForEach(list) { c in
            NavigationLink(value: c) { CoachCompetitionCard(c: c) }.buttonStyle(.plain)
        }
    }

    private func load() async {
        do {
            let h = try await CoachAPI.home()
            home = h; error = nil
            store.setAccount(h.account)
        } catch { if home == nil { self.error = readable(error) } }
    }
}

struct CoachCompetitionCard: View {
    let c: CoachCompetition
    var body: some View {
        HStack(spacing: 14) {
            VStack(spacing: 0) {
                Text(c.day.map { $0.formatted(.dateTime.day().locale(Locale(identifier: "fr_FR"))) } ?? "–").font(.score(26, weight: 900))
                Text(c.day.map { $0.formatted(.dateTime.month(.abbreviated).locale(Locale(identifier: "fr_FR"))) }?.uppercased() ?? "").font(.system(size: 11, weight: .heavy))
            }
            .foregroundStyle(c.live ? .white : Theme.red)
            .frame(width: 58, height: 64)
            .background(c.live ? Theme.red : Theme.redSoft, in: RoundedRectangle(cornerRadius: 16, style: .continuous))
            VStack(alignment: .leading, spacing: 5) {
                Text(c.name).font(.system(size: 16, weight: .bold)).foregroundStyle(Theme.ink).lineLimit(2).multilineTextAlignment(.leading)
                HStack(spacing: 6) {
                    if c.live { LiveBadge(label: "EN COURS") }
                    else if c.canEdit { Chip(text: "Inscriptions ouvertes", color: Theme.green, fill: Theme.greenSoft) }
                    else { Chip(text: c.statusLabel) }
                }
                Text(line).font(.caption).foregroundStyle(Theme.muted)
            }
            Spacer(minLength: 0)
            Image(systemName: "chevron.right").font(.footnote.weight(.bold)).foregroundStyle(Theme.faint)
        }
        .card(padding: 14)
    }

    private var line: String {
        var parts = ["\(c.mine ?? 0) inscrit\((c.mine ?? 0) > 1 ? "s" : "")"]
        if c.attendanceOpen, let p = c.present { parts.append("\(p) présent\(p > 1 ? "s" : "")") }
        if c.canEdit, let l = c.closesLabel { parts.append(l) }
        return parts.joined(separator: " · ")
    }
}

// MARK: - Compétition : mes judokas

struct CoachCompetitionView: View {
    let summary: CoachCompetition
    @State private var d: CoachCompetitionDetail?
    @State private var error: String?
    @State private var toast: String?
    @State private var adding = false
    @State private var editing: CoachFighter?
    @State private var confirmValidate = false
    @State private var filter = "all"
    @State private var query = ""
    @State private var showWithdrawn = false

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 14) {
                if let error, d == nil { ErrorCard(message: error) { Task { await load() } } }
                if let d { content(d) } else if error == nil { ProgressView().frame(maxWidth: .infinity).padding(.top, 60) }
            }
            .padding(16)
        }
        .scrollDismissesKeyboard(.interactively)
        .screenBackground(glow: 0.5)
        .navigationTitle(summary.name)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar(.visible, for: .navigationBar)
        .refreshable { await load() }
        // Direct : l'empreinte est consultée toutes les 3 s, la fiche n'est rechargée que si quelque chose a changé
        // (sur le site, à l'accueil, par l'organisation ou sur un autre téléphone).
        .polling(every: 3) { await sync() }
        .overlay(alignment: .top) { if let toast { Toast(text: toast).transition(.move(edge: .top).combined(with: .opacity)) } }
        .safeAreaInset(edge: .bottom) {
            if let d, d.canEdit {
                PrimaryButton(title: "Ajouter un combattant", icon: "plus") { adding = true }
                    .padding(.horizontal, 16).padding(.bottom, 8)
            }
        }
        .sheet(isPresented: $adding) {
            if let d { FighterFormView(detail: d, fighter: nil) { msg in show(msg) } }
        }
        .sheet(item: $editing) { f in
            if let d { FighterFormView(detail: d, fighter: f) { msg in show(msg) } }
        }
        .alert("Valider la liste du club ?", isPresented: $confirmValidate) {
            Button("Annuler", role: .cancel) {}
            Button("Valider") { Task { await act("/club/competitions/\(summary.id)/valider-liste") } }
        } message: { Text("Toutes vos inscriptions sont confirmées et l'organisation est prévenue. Vous pourrez encore modifier la liste jusqu'à la date limite.") }
    }

    @ViewBuilder private func content(_ d: CoachCompetitionDetail) -> some View {
        let act = d.active
        HStack(spacing: 8) {
            StatTile(value: "\(act.count)", label: "inscrits")
            if d.attendanceOpen {
                StatTile(value: "\(act.filter { $0.attendance == "present" }.count)", label: "présents")
                StatTile(value: "\(act.filter { $0.attendance == "expected" }.count)", label: "attendus")
                StatTile(value: "\(act.filter { $0.attendance == "absent" }.count)", label: "absents")
            } else {
                StatTile(value: "\(act.filter { $0.sex == "F" }.count)", label: "filles")
                StatTile(value: "\(act.filter { $0.sex == "M" }.count)", label: "garçons")
            }
        }
        banner(d)
        if d.canEdit && !act.isEmpty { validationCard(d) }
        if act.count > 6 { SearchField(text: $query, prompt: "Rechercher un judoka") }
        if d.attendanceOpen && !act.isEmpty {
            PillTabs(items: [("all", "Tous"), ("expected", "Attendus"), ("present", "Présents"), ("absent", "Absents")], selection: $filter)
                .padding(.horizontal, -16)
        }
        let list = shown(act)
        if act.isEmpty {
            EmptyState(icon: "person.badge.plus", title: "Aucun judoka inscrit", message: d.canEdit ? "Ajoutez vos combattants avec le bouton en bas de l'écran." : nil)
        } else if list.isEmpty {
            EmptyState(icon: "line.3.horizontal.decrease.circle", title: "Aucun judoka pour ce filtre")
        }
        ForEach(list) { f in
            FighterCard(f: f, detail: d, onEdit: { editing = f }, onMark: { status, weight in
                Task { await mark(f, status: status, weight: weight) }
            })
        }
        let gone = d.fighters.filter { !$0.active }
        if !gone.isEmpty {
            Button { withAnimation(.snappy) { showWithdrawn.toggle() } } label: {
                Label("\(gone.count) inscription\(gone.count > 1 ? "s" : "") retirée\(gone.count > 1 ? "s" : "")", systemImage: showWithdrawn ? "chevron.up" : "chevron.down")
                    .font(.subheadline.weight(.semibold)).foregroundStyle(Theme.muted)
            }
            .padding(.top, 4)
            if showWithdrawn {
                ForEach(gone) { f in
                    HStack { Text(f.fullName).strikethrough(); Spacer(); Text(f.status == "rejected" ? "Refusée" : "Retirée").font(.caption) }
                        .font(.subheadline).foregroundStyle(Theme.muted).padding(12)
                        .background(Theme.surface.opacity(0.6), in: RoundedRectangle(cornerRadius: 14))
                }
            }
        }
        Color.clear.frame(height: d.canEdit ? 60 : 0)
    }

    private func shown(_ list: [CoachFighter]) -> [CoachFighter] {
        let q = query.folded
        return list.filter { f in
            (filter == "all" || f.attendance == filter) && (q.isEmpty || (f.fullName + " " + f.category).folded.contains(q))
        }
        .sorted { ($0.category, $0.lastName, $0.firstName) < ($1.category, $1.lastName, $1.firstName) }
    }

    @ViewBuilder private func banner(_ d: CoachCompetitionDetail) -> some View {
        if d.canEdit {
            Notice(text: "Inscriptions ouvertes" + (summary.closesLabel.map { " \($0)" } ?? "") + ". Chaque ajout apparaît aussitôt chez l'organisation et sur le site.", tint: Theme.green, icon: "checkmark.circle.fill")
        } else if d.attendanceOpen {
            Notice(text: "Jour J : pointez la présence\(d.weighIn ? " et le poids" : "") de vos judokas. Pour un nouvel inscrit, passez à l'accueil.", tint: Theme.red, icon: "flag.checkered")
        } else {
            Notice(text: "Inscriptions fermées : pour toute modification, contactez l'organisation.", tint: Theme.muted, icon: "lock.fill")
        }
    }

    @ViewBuilder private func validationCard(_ d: CoachCompetitionDetail) -> some View {
        let v = d.listValidation
        let upToDate = v != nil && v!.changedSince == 0
        HStack(spacing: 12) {
            Image(systemName: upToDate ? "checkmark.seal.fill" : "list.bullet.clipboard.fill").font(.system(size: 22))
                .foregroundStyle(upToDate ? Theme.green : Theme.red)
            VStack(alignment: .leading, spacing: 2) {
                Text(upToDate ? "Liste validée" : (v == nil ? "Liste à valider" : "Liste modifiée depuis la validation")).font(.subheadline.weight(.bold)).foregroundStyle(Theme.ink)
                Text(upToDate ? "\(v!.count) judokas confirmés\(v!.by.map { " par \($0)" } ?? "")." : "Validez quand votre liste est complète : l'organisation est prévenue aussitôt.")
                    .font(.caption).foregroundStyle(Theme.muted)
            }
            Spacer(minLength: 4)
            if !upToDate {
                Button("Valider") { confirmValidate = true }
                    .font(.subheadline.weight(.bold)).foregroundStyle(.white)
                    .padding(.horizontal, 14).padding(.vertical, 9).background(Theme.ink, in: Capsule())
            }
        }
        .card(padding: 14)
    }

    private func load() async {
        do { d = try await CoachAPI.competition(summary.id); error = nil } catch { if d == nil { self.error = readable(error) } }
    }

    private func sync() async {
        guard let cur = d else { await load(); return }
        if let s = try? await CoachAPI.stamp(summary.id), s != cur.stamp { await load() }
    }

    private func act(_ path: String, _ body: [String: Any?] = [:]) async {
        do { let r = try await CoachAPI.post(path, body); show(r.message ?? "Enregistré."); await load() }
        catch { show(readable(error)) }
    }

    private func mark(_ f: CoachFighter, status: String, weight: String?) async {
        await act("/club/competitions/\(summary.id)/inscriptions/\(f.id)/presence", ["status": status, "measured_weight_kg": weight])
    }

    private func show(_ msg: String) {
        withAnimation(.snappy) { toast = msg }
        Task { try? await Task.sleep(for: .seconds(3)); withAnimation(.snappy) { if toast == msg { toast = nil } } }
        Task { await load() }
    }
}

struct FighterCard: View {
    let f: CoachFighter
    let detail: CoachCompetitionDetail
    var onEdit: () -> Void
    var onMark: (String, String?) -> Void
    @State private var weight = ""

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            Button(action: onEdit) {
                HStack(spacing: 12) {
                    FighterAvatar(f: f, size: 48)
                    VStack(alignment: .leading, spacing: 2) {
                        Text("\(f.firstName) \(Text(f.lastName).fontWeight(.bold))").font(.system(size: 16)).foregroundStyle(Theme.ink)
                        Text([f.birthYear, f.grade].compactMap { $0 }.filter { !$0.isEmpty }.joined(separator: " · ")).font(.caption).foregroundStyle(Theme.muted)
                    }
                    Spacer(minLength: 4)
                    statusChip
                }
            }
            .buttonStyle(.plain)
            HStack(spacing: 8) {
                infoBox("Catégorie", f.category)
                infoBox("Poids", f.measuredWeightKg.map { "pesé \(kg($0))" } ?? f.weightLine)
            }
            if detail.attendanceOpen { attendance }
        }
        .card(padding: 14)
        .overlay(RoundedRectangle(cornerRadius: Theme.radius, style: .continuous).stroke(border, lineWidth: 1.5))
        .id(f.id)
    }

    private var border: Color {
        switch f.attendance {
        case "present": return Theme.green.opacity(0.35)
        case "absent": return Theme.red.opacity(0.35)
        default: return .clear
        }
    }

    @ViewBuilder private var statusChip: some View {
        if detail.attendanceOpen {
            switch f.attendance {
            case "present": Chip(text: "Présent", color: Theme.green, fill: Theme.greenSoft)
            case "absent": Chip(text: "Absent", color: Theme.red, fill: Theme.redSoft)
            case "to_check": Chip(text: "À vérifier", color: Color(hex: 0xC2410C), fill: Color(hex: 0xFFEDD5))
            default: Chip(text: "Attendu")
            }
        } else if f.status == "pending" {
            Chip(text: "En attente", color: Color(hex: 0xC2410C), fill: Color(hex: 0xFFEDD5))
        } else {
            Chip(text: "Confirmée", color: Theme.green, fill: Theme.greenSoft)
        }
    }

    private func infoBox(_ label: String, _ value: String) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(label).font(.caption2).foregroundStyle(Theme.muted)
            Text(value).font(.subheadline.weight(.semibold)).foregroundStyle(Theme.ink).lineLimit(1)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(10)
        .background(Theme.surface2, in: RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    @ViewBuilder private var attendance: some View {
        VStack(spacing: 8) {
            if detail.weighIn && f.attendance != "absent" && !f.inPool {
                HStack(spacing: 8) {
                    TextField(f.measuredWeightKg.map { kg($0) } ?? "Poids pesé (kg)", text: $weight)
                        .keyboardType(.decimalPad)
                        .padding(.horizontal, 14).frame(height: 44)
                        .background(Theme.surface2, in: Capsule())
                    if !weight.isEmpty {
                        Button("Peser") { onMark("present", weight); weight = "" }
                            .font(.subheadline.weight(.bold)).foregroundStyle(.white)
                            .padding(.horizontal, 16).frame(height: 44).background(Theme.ink, in: Capsule())
                    }
                }
            }
            HStack(spacing: 6) {
                markButton("Présent", icon: "checkmark", on: f.attendance == "present", tint: Theme.green) { onMark("present", weight.isEmpty ? nil : weight) }
                markButton("Absent", icon: "xmark", on: f.attendance == "absent", tint: Theme.red) { onMark("absent", nil) }
            }
            .padding(4)
            .background(Theme.surface2, in: Capsule())
        }
    }

    private func markButton(_ title: String, icon: String, on: Bool, tint: Color, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Label(title, systemImage: icon).font(.subheadline.weight(.semibold))
                .frame(maxWidth: .infinity).frame(height: 40)
                .foregroundStyle(on ? .white : Theme.ink)
                .background(on ? tint : .clear, in: Capsule())
        }
        .buttonStyle(.plain)
        .sensoryFeedback(.success, trigger: on)
    }
}

/// Photo du judoka (privée, chargée avec le jeton) ou initiales.
struct FighterAvatar: View {
    let f: CoachFighter
    var size: CGFloat = 48
    @State private var image: UIImage?
    var body: some View {
        ZStack {
            Circle().fill(Theme.surface2)
            if let image { Image(uiImage: image).resizable().scaledToFill() }
            else { Text(String(f.firstName.prefix(1)) + String(f.lastName.prefix(1))).font(.system(size: size * 0.32, weight: .bold)).foregroundStyle(Theme.muted) }
        }
        .frame(width: size, height: size).clipShape(Circle())
        .task(id: f.photo) { image = nil; if let p = f.photo { image = await CoachAPI.image(p) } }
    }
}

// MARK: - Ajouter / modifier un combattant

struct FighterFormView: View {
    let detail: CoachCompetitionDetail
    let fighter: CoachFighter?
    var done: (String) -> Void
    @Environment(\.dismiss) private var dismiss
    @State private var mode = 0
    @State private var athleteId: Int?
    @State private var last = ""
    @State private var first = ""
    @State private var sex = "F"
    @State private var birth = Calendar.current.date(byAdding: .year, value: -10, to: Date()) ?? Date()
    @State private var weight = ""
    @State private var weightCat: Int?
    @State private var grade = ""
    @State private var license = ""
    @State private var preview: CategoryPreview?
    @State private var busy = false
    @State private var error: String?
    @State private var query = ""
    @State private var photoItem: PhotosPickerItem?
    @State private var confirmWithdraw = false

    private var isNew: Bool { fighter == nil }
    private var locked: Bool { fighter?.inPool == true || !detail.canEdit }
    private var birthString: String { DateFormatter.apiDay.string(from: birth) }

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 14) {
                    if isNew && !detail.clubAthletes.isEmpty {
                        Picker("", selection: $mode) { Text("Nouveau").tag(0); Text("Déjà au club (\(detail.clubAthletes.count))").tag(1) }
                            .pickerStyle(.segmented)
                    }
                    if isNew && mode == 1 { clubList } else { form }
                }
                .padding(16)
            }
            .scrollDismissesKeyboard(.interactively)
            .screenBackground(glow: 0.3)
            .navigationTitle(isNew ? "Ajouter un combattant" : fighter!.fullName)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar { ToolbarItem(placement: .cancellationAction) { Button("Fermer") { dismiss() } } }
            .environment(\.locale, Locale(identifier: "fr_FR"))
            .task(id: "\(birthString)|\(sex)|\(weight)|\(weightCat ?? 0)") {
                try? await Task.sleep(for: .milliseconds(350))
                // On garde l'aperçu précédent pendant la frappe (pas de clignotement) ; il est remplacé dès la réponse.
                if let p = try? await CoachAPI.preview(detail.id, ["birth_date": birthString, "sex": sex, "declared_weight_kg": weight, "weight_category_id": weightCat]) { preview = p }
            }
            .onAppear(perform: fill)
            .onChange(of: photoItem) { _, item in Task { await upload(item) } }
            .alert("Retirer \(fighter?.fullName ?? "") ?", isPresented: $confirmWithdraw) {
                Button("Annuler", role: .cancel) {}
                Button("Retirer", role: .destructive) { Task { await withdraw() } }
            } message: { Text("L'inscription est retirée de la compétition. Vous pourrez l'inscrire à nouveau tant que les inscriptions sont ouvertes.") }
        }
    }

    private var clubList: some View {
        VStack(alignment: .leading, spacing: 8) {
            SearchField(text: $query, prompt: "Rechercher dans le club")
            let q = query.folded
            ForEach(detail.clubAthletes.filter { q.isEmpty || $0.fullName.folded.contains(q) }) { a in
                Button {
                    athleteId = a.id; first = a.firstName; last = a.lastName; sex = a.sex; grade = a.grade ?? ""; license = a.licenseNumber ?? ""
                    if let d = DateFormatter.apiDay.date(from: a.birthDate) { birth = d }
                    withAnimation(.snappy) { mode = 0 }
                } label: {
                    HStack(spacing: 12) {
                        Text(String(a.firstName.prefix(1)) + String(a.lastName.prefix(1))).font(.system(size: 14, weight: .bold)).foregroundStyle(Theme.muted)
                            .frame(width: 40, height: 40).background(Theme.surface2, in: Circle())
                        VStack(alignment: .leading, spacing: 1) {
                            Text("\(a.firstName) \(Text(a.lastName).fontWeight(.bold))").foregroundStyle(Theme.ink)
                            Text([String(a.birthDate.prefix(4)), a.sex == "F" ? "Fille" : "Garçon", a.grade].compactMap { $0 }.filter { !$0.isEmpty }.joined(separator: " · "))
                                .font(.caption).foregroundStyle(Theme.muted)
                        }
                        Spacer()
                        Image(systemName: "plus.circle.fill").font(.title3).foregroundStyle(Theme.red)
                    }
                    .padding(12)
                    .background(Theme.surface, in: RoundedRectangle(cornerRadius: 16, style: .continuous))
                }
                .buttonStyle(.plain)
            }
        }
    }

    private var form: some View {
        VStack(alignment: .leading, spacing: 14) {
            if let fighter { photoCard(fighter) }
            if fighter?.inPool == true { Notice(text: "Placé(e) en poule : la catégorie et le poids sont verrouillés. Le grade et la licence restent modifiables.", tint: Theme.muted, icon: "lock.fill") }
            VStack(alignment: .leading, spacing: 12) {
                if athleteId != nil && isNew {
                    HStack {
                        Label("Judoka du club", systemImage: "person.fill.checkmark").font(.caption.weight(.bold)).foregroundStyle(Theme.green)
                        Spacer()
                        Button("Effacer") { athleteId = nil; first = ""; last = ""; grade = ""; license = "" }.font(.caption.weight(.semibold))
                    }
                }
                HStack(spacing: 10) {
                    InputField(title: "Nom", text: $last, content: .familyName).disabled(locked)
                    InputField(title: "Prénom", text: $first, content: .givenName).disabled(locked)
                }
                VStack(alignment: .leading, spacing: 6) {
                    Text("Sexe").font(.subheadline.weight(.semibold)).foregroundStyle(Theme.ink)
                    Picker("Sexe", selection: $sex) { Text("Fille").tag("F"); Text("Garçon").tag("M") }.pickerStyle(.segmented).disabled(locked)
                }
                DatePicker("Date de naissance", selection: $birth, in: ...Date(), displayedComponents: .date)
                    .font(.subheadline.weight(.semibold)).disabled(locked)
            }
            .card(padding: 16)

            VStack(alignment: .leading, spacing: 12) {
                if detail.weightByCategory {
                    let cats = detail.weightCategories.filter { $0.sex == sex && $0.ageId == preview?.ageId }
                    Picker("Catégorie de poids", selection: $weightCat) {
                        Text("Choisir…").tag(Int?.none)
                        ForEach(cats) { w in Text(w.label + " kg").tag(Int?.some(w.id)) }
                    }
                    .font(.subheadline.weight(.semibold)).disabled(locked)
                } else {
                    InputField(title: "Poids déclaré (kg)", text: $weight, prompt: "Ex. 32,5", keyboard: .decimalPad).disabled(locked)
                }
                VStack(alignment: .leading, spacing: 6) {
                    Text("Grade").font(.subheadline.weight(.semibold)).foregroundStyle(Theme.ink)
                    ScrollView(.horizontal, showsIndicators: false) {
                        HStack(spacing: 6) {
                            ForEach(detail.grades, id: \.self) { g in
                                Button { grade = grade == g ? "" : g } label: {
                                    Text(g).font(.caption.weight(.semibold))
                                        .padding(.horizontal, 10).padding(.vertical, 7)
                                        .foregroundStyle(grade == g ? Theme.onInk : Theme.ink)
                                        .background(grade == g ? Theme.ink : Theme.surface2, in: Capsule())
                                }
                                .buttonStyle(.plain)
                            }
                        }
                    }
                }
                InputField(title: detail.requireLicense ? "Licence (obligatoire)" : "Licence (facultative)", text: $license, keyboard: .asciiCapable)
            }
            .card(padding: 16)

            if !locked, let p = preview {
                HStack(spacing: 10) {
                    Image(systemName: p.error == nil ? "tag.fill" : "exclamationmark.triangle.fill").foregroundStyle(p.error == nil ? Theme.red : Color(hex: 0xC2410C))
                    if let e = p.error { Text(e).font(.subheadline).foregroundStyle(Theme.ink) }
                    else {
                        Text("\(Text(p.category ?? "").fontWeight(.bold))\(p.weight.map { " · \($0) kg" } ?? (detail.weightByCategory ? "" : " · catégorie de poids selon le poids"))")
                            .font(.subheadline).foregroundStyle(Theme.ink)
                    }
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(14).background(Theme.redSoft, in: RoundedRectangle(cornerRadius: 16, style: .continuous))
            }
            if let error { InlineError(text: error) }
            if !(locked && fighter?.inPool != true) {
                PrimaryButton(title: isNew ? "Inscrire" : "Enregistrer", busy: busy, disabled: last.isEmpty || first.isEmpty) { Task { await save() } }
            }
            if let f = fighter, detail.canEdit, !f.inPool {
                Button(role: .destructive) { confirmWithdraw = true } label: {
                    Label("Retirer l'inscription", systemImage: "person.fill.xmark").font(.subheadline.weight(.semibold)).frame(maxWidth: .infinity).frame(height: 46)
                }
                .foregroundStyle(Theme.red)
            }
        }
    }

    private func photoCard(_ f: CoachFighter) -> some View {
        HStack(spacing: 14) {
            FighterAvatar(f: f, size: 64)
            VStack(alignment: .leading, spacing: 6) {
                Text("Photo").font(.subheadline.weight(.semibold)).foregroundStyle(Theme.ink)
                HStack(spacing: 8) {
                    PhotosPicker(selection: $photoItem, matching: .images) {
                        Label(f.photo == nil ? "Ajouter" : "Changer", systemImage: "camera.fill").font(.caption.weight(.bold))
                            .padding(.horizontal, 12).padding(.vertical, 8).background(Theme.ink, in: Capsule()).foregroundStyle(Theme.onInk)
                    }
                    if f.photo != nil {
                        Button("Retirer") { Task { await removePhoto() } }.font(.caption.weight(.semibold)).foregroundStyle(Theme.red)
                    }
                }
            }
            Spacer()
        }
        .card(padding: 14)
    }

    private func fill() {
        guard let f = fighter, last.isEmpty else { return }
        athleteId = f.athleteId; last = f.lastName; first = f.firstName; sex = f.sex; grade = f.grade ?? ""; license = f.licenseNumber ?? ""
        if let d = DateFormatter.apiDay.date(from: f.birthDate) { birth = d }
        if let w = f.declaredWeightKg { weight = String(format: w.truncatingRemainder(dividingBy: 1) == 0 ? "%.0f" : "%.1f", w).replacingOccurrences(of: ".", with: ",") }
        if f.weightByCategory { weightCat = f.weightCategoryId }
    }

    private var payload: [String: Any?] {
        var b: [String: Any?] = ["last_name": last, "first_name": first, "sex": sex, "birth_date": birthString,
                                 "grade": grade, "license_number": license]
        if detail.weightByCategory { b["weight_category_id"] = weightCat } else { b["declared_weight_kg"] = weight }
        if isNew, let a = athleteId { b["athlete_id"] = a }
        return b
    }

    private func save() async {
        guard !busy else { return }
        busy = true; error = nil
        defer { busy = false }
        do {
            let path = isNew ? "/club/competitions/\(detail.id)/inscriptions" : "/club/competitions/\(detail.id)/inscriptions/\(fighter!.id)"
            let r = try await CoachAPI.post(path, payload)
            done(r.message ?? "Enregistré.")
            dismiss()
        } catch { self.error = readable(error) }
    }

    private func withdraw() async {
        guard let f = fighter else { return }
        do { let r = try await CoachAPI.post("/club/competitions/\(detail.id)/inscriptions/\(f.id)/retirer"); done(r.message ?? "Inscription retirée."); dismiss() }
        catch { self.error = readable(error) }
    }

    private func upload(_ item: PhotosPickerItem?) async {
        guard let f = fighter, let item, let data = try? await item.loadTransferable(type: Data.self), let img = UIImage(data: data) else { return }
        do { let r = try await CoachAPI.upload("/club/competitions/\(detail.id)/inscriptions/\(f.id)/photo", image: img); done(r.message ?? "Photo enregistrée."); dismiss() }
        catch { self.error = readable(error) }
    }

    private func removePhoto() async {
        guard let f = fighter else { return }
        do { let r = try await CoachAPI.post("/club/competitions/\(detail.id)/inscriptions/\(f.id)/photo", ["remove": true]); done(r.message ?? "Photo retirée."); dismiss() }
        catch { self.error = readable(error) }
    }
}

// MARK: - Petits composants de formulaire

struct InputField: View {
    let title: String
    @Binding var text: String
    var prompt: String = ""
    var keyboard: UIKeyboardType = .default
    var content: UITextContentType? = nil
    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(title).font(.subheadline.weight(.semibold)).foregroundStyle(Theme.ink)
            TextField(prompt, text: $text)
                .keyboardType(keyboard)
                .textContentType(content)
                .textInputAutocapitalization(keyboard == .emailAddress || keyboard == .asciiCapable ? .never : .words)
                .autocorrectionDisabled()
                .padding(.horizontal, 14).frame(height: 48)
                .background(Theme.surface2, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
        }
    }
}

struct SecureInput: View {
    let title: String
    @Binding var text: String
    var content: UITextContentType = .password
    @State private var visible = false
    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(title).font(.subheadline.weight(.semibold)).foregroundStyle(Theme.ink)
            HStack {
                Group {
                    if visible { TextField("", text: $text) } else { SecureField("", text: $text) }
                }
                .textContentType(content).textInputAutocapitalization(.never).autocorrectionDisabled()
                Button { visible.toggle() } label: { Image(systemName: visible ? "eye.slash" : "eye").foregroundStyle(Theme.muted) }
                    .accessibilityLabel(visible ? "Masquer le mot de passe" : "Afficher le mot de passe")
            }
            .padding(.horizontal, 14).frame(height: 48)
            .background(Theme.surface2, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
        }
    }
}

struct PrimaryButton: View {
    let title: String
    var icon: String? = nil
    var busy = false
    var disabled = false
    let action: () -> Void
    var body: some View {
        Button(action: action) {
            HStack(spacing: 8) {
                if busy { ProgressView().tint(Theme.onInk) } else if let icon { Image(systemName: icon).font(.body.weight(.bold)) }
                Text(title).font(.body.weight(.semibold))
            }
            .frame(maxWidth: .infinity).frame(height: 54)
            .foregroundStyle(Theme.onInk)
            .background(Theme.ink.opacity(disabled ? 0.35 : 1), in: RoundedRectangle(cornerRadius: 16, style: .continuous))
        }
        .buttonStyle(.plain)
        .disabled(disabled || busy)
    }
}

struct InlineError: View {
    let text: String
    var body: some View {
        HStack(alignment: .top, spacing: 10) {
            Image(systemName: "exclamationmark.circle.fill").foregroundStyle(Theme.red)
            Text(text).font(.subheadline).foregroundStyle(Theme.ink)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(12).background(Theme.redSoft, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
    }
}

struct Notice: View {
    let text: String
    var tint: Color = Theme.red
    var icon: String = "info.circle.fill"
    var body: some View {
        HStack(alignment: .top, spacing: 10) {
            Image(systemName: icon).foregroundStyle(tint)
            Text(text).font(.subheadline).foregroundStyle(Theme.ink)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(12).background(tint.opacity(0.1), in: RoundedRectangle(cornerRadius: 16, style: .continuous))
    }
}

struct Toast: View {
    let text: String
    var body: some View {
        Text(text).font(.subheadline.weight(.semibold)).foregroundStyle(Theme.onInk).multilineTextAlignment(.center)
            .padding(.horizontal, 16).padding(.vertical, 11)
            .background(Theme.ink, in: Capsule())
            .shadow(color: .black.opacity(0.2), radius: 12, y: 6)
            .padding(.horizontal, 20).padding(.top, 8)
    }
}
