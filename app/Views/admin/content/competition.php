<?php $heading = 'Programme de la compétition'; $actions = $c['status'] !== 'draft' ? '<a class="btn btn-ghost" href="' . e(url('/competitions/' . $c['slug'])) . '" target="_blank">Voir la page publique ↗</a>' : ''; include dirname(__DIR__) . '/_head.php'; ?>
<p class="muted">Tous les textes et listes de la page publique de la compétition. Catégories, durées, buvette, clubs présents et partenaires se gèrent dans leurs rubriques dédiées et s'affichent automatiquement.</p>
<div class="grid grid-2">
  <section class="card"><h2 style="font-size:1.15rem">Sections de texte</h2>
    <?php foreach ($sections as $sec): ?>
      <details style="border-bottom:1px solid var(--line);padding:.45rem 0"><summary style="cursor:pointer"><strong><?= e($sec['title'] ?: '(sans titre)') ?></strong> <code class="small muted"><?= e($sec['section_key']) ?></code><?= $sec['is_published'] ? '' : ' <span class="badge">masquée</span>' ?></summary>
        <?php $pageKey = 'competition'; include __DIR__ . '/_section_form.php'; ?></details>
    <?php endforeach; ?>
    <details style="margin-top:.8rem"><summary class="btn btn-ghost btn-sm">Ajouter une section</summary><?php $sec = null; $pageKey = 'competition'; include __DIR__ . '/_section_form.php'; ?></details>
  </section>
  <div class="stack">
    <?php foreach ($lists as $listKey => $def): $items = $listItems[$listKey] ?? []; ?>
      <section class="card"><h2 style="font-size:1.1rem"><?= e($def['label']) ?></h2><?php include __DIR__ . '/_items.php'; ?></section>
    <?php endforeach; ?>
  </div>
</div>
