/* JCL Compét — utilitaires communs (aucune dépendance). */
(function () {
  'use strict';
  const meta = document.querySelector('meta[name="csrf-token"]');
  const JCL = window.JCL = {
    csrf: meta ? meta.content : '',
    base: (document.querySelector('meta[name="base-path"]') || {}).content || '',

    /** Requête JSON vers l'API interne. Lève une erreur avec .status, .data en cas d'échec. */
    async api(url, { method = 'GET', body = null, signal = null, timeout = 10000 } = {}) {
      const ctrl = new AbortController();
      const timer = setTimeout(() => ctrl.abort(), timeout);
      if (signal) signal.addEventListener('abort', () => ctrl.abort());
      try {
        const res = await fetch(url, {
          method,
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': JCL.csrf, 'X-Requested-With': 'XMLHttpRequest' },
          body: body ? JSON.stringify(body) : null,
          credentials: 'same-origin',
          cache: 'no-store',
          signal: ctrl.signal,
        });
        let data = null;
        try { data = await res.json(); } catch (e) { data = null; }
        if (!res.ok) {
          const err = new Error((data && data.error) || 'Erreur ' + res.status);
          err.status = res.status; err.data = data || {};
          throw err;
        }
        return data;
      } catch (e) {
        if (e.name === 'AbortError') { const err = new Error('Pas de réponse du serveur (connexion ?).'); err.status = 0; err.offline = true; throw err; }
        if (e instanceof TypeError) { const err = new Error('Connexion au serveur impossible.'); err.status = 0; err.offline = true; throw err; }
        throw e;
      } finally { clearTimeout(timer); }
    },

    toast(message, type = 'info', ms = 4000) {
      let box = document.querySelector('[data-toasts]');
      if (!box) { box = document.createElement('div'); box.className = 'toasts'; box.setAttribute('data-toasts', ''); box.setAttribute('aria-live', 'polite'); document.body.appendChild(box); }
      const t = document.createElement('div');
      t.className = 'toast toast-' + type;
      t.setAttribute('role', type === 'error' ? 'alert' : 'status');
      t.textContent = message;
      box.appendChild(t);
      setTimeout(() => t.remove(), ms);
    },

    /** Boîte de confirmation. options : { title, confirmLabel, danger, reason (motif obligatoire), reasonLabel } → Promise<false|{reason}> */
    confirm(message, options = {}) {
      return new Promise((resolve) => {
        const d = document.createElement('dialog');
        d.innerHTML = '<form method="dialog"><div class="dialog-body"><h3></h3><p class="msg"></p>' +
          (options.reason ? '<div class="field"><label for="dlg-reason"></label><textarea id="dlg-reason" required minlength="5" rows="3"></textarea></div>' : '') +
          '</div><div class="dialog-actions"><button class="btn btn-ghost" value="cancel" type="button" data-cancel>Annuler</button><button class="btn ' + (options.danger ? 'btn-red' : 'btn-ink') + '" value="ok" data-ok></button></div></form>';
        d.querySelector('h3').textContent = options.title || 'Confirmer';
        d.querySelector('.msg').textContent = message;
        d.querySelector('[data-ok]').textContent = options.confirmLabel || 'Confirmer';
        if (options.reason) d.querySelector('label').textContent = options.reasonLabel || 'Motif (obligatoire)';
        document.body.appendChild(d);
        const close = (v) => { d.close(); d.remove(); resolve(v); };
        d.querySelector('[data-cancel]').addEventListener('click', () => close(false));
        d.addEventListener('cancel', (e) => { e.preventDefault(); close(false); });
        d.querySelector('form').addEventListener('submit', (e) => {
          e.preventDefault();
          if (options.reason) {
            const r = d.querySelector('textarea').value.trim();
            if (r.length < 5) { d.querySelector('textarea').focus(); return; }
            close({ reason: r });
          } else { close({}); }
        });
        d.showModal();
        (d.querySelector('textarea') || d.querySelector('[data-ok]')).focus();
      });
    },

    clock(ms) {
      const neg = ms < 0; ms = Math.abs(ms);
      const total = Math.floor(ms / 1000);
      return (neg ? '-' : '') + Math.floor(total / 60) + ':' + String(total % 60).padStart(2, '0');
    },

    esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); },
    uid() { return (crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2)); },
  };

  // Menu mobile
  document.addEventListener('click', (e) => {
    const toggle = e.target.closest('[data-nav-toggle]');
    if (toggle) {
      const nav = document.getElementById(toggle.getAttribute('aria-controls'));
      const open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (e.target.closest('[data-history-back]')) { history.back(); }
    if (e.target.closest('[data-print]')) { window.print(); }
  });

  // Formulaires à confirmer : data-confirm="message" [data-reason] [data-danger]
  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') return;
    e.preventDefault();
    const res = await JCL.confirm(form.dataset.confirm, {
      title: form.dataset.confirmTitle || 'Confirmer', confirmLabel: form.dataset.confirmLabel || 'Confirmer',
      danger: form.hasAttribute('data-danger'), reason: form.hasAttribute('data-reason'), reasonLabel: form.dataset.reasonLabel,
    });
    if (!res) return;
    if (res.reason !== undefined) {
      let input = form.querySelector('input[name="reason"]');
      if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = 'reason'; form.appendChild(input); }
      input.value = res.reason;
    }
    form.dataset.confirmed = '1';
    const btn = e.submitter;
    if (btn && btn.name) { const h = document.createElement('input'); h.type = 'hidden'; h.name = btn.name; h.value = btn.value; form.appendChild(h); }
    form.submit();
  });

  // Empêche la double soumission des formulaires classiques
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented || !(form instanceof HTMLFormElement) || form.hasAttribute('data-allow-resubmit')) return;
    if (form.dataset.submitting === '1') { e.preventDefault(); return; }
    form.dataset.submitting = '1';
    setTimeout(() => { form.dataset.submitting = '0'; }, 4000);
  });

  // Sélecteurs qui soumettent leur formulaire (filtres)
  document.addEventListener('change', (e) => {
    if (e.target.matches('[data-autosubmit]')) e.target.form.requestSubmit();
  });
})();
