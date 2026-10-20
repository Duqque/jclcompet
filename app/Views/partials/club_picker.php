<?php
/**
 * Sélecteur de club en fenêtre : grille de cartes aux couleurs de chaque club (logo + nom).
 * @var list<array> $pickerClubs clubs décorés par ClubColorService::decorate() (id, name, short_name, logo_path, color, n?)
 * @var ?int $pickerCurrent club sélectionné (null = tous)
 * @var string $pickerId identifiant unique de la fenêtre
 * @var string $pickerMode 'filter-link' (liens), 'filter-js' (boutons data-club) ou 'field' (champ de formulaire $pickerName)
 * @var ?callable $pickerHref fn(?int $clubId): string pour le mode 'filter-link'
 * @var ?string $pickerAllLabel libellé du choix « tous » (null = pas de choix « tous »)
 * @var ?string $pickerName nom du champ caché (mode 'field')
 * @var ?int $pickerTotal nombre total affiché sur « tous »
 */
$pickerAllLabel = $pickerAllLabel ?? 'Tous les clubs';
$pickerHref = $pickerHref ?? null;
$pickerTotal = $pickerTotal ?? null;
$pickerName = $pickerName ?? 'club_id';
$cur = null;
foreach ($pickerClubs as $pc) { if ((int) $pc['id'] === (int) ($pickerCurrent ?? 0)) { $cur = $pc; } }
$card = function (?array $cl) use ($pickerMode, $pickerHref, $pickerAllLabel, $pickerCurrent, $pickerTotal) {
    $id = $cl ? (int) $cl['id'] : null;
    $on = (int) ($pickerCurrent ?? 0) === (int) ($id ?? 0);
    $style = $cl ? ' style="--club:' . e($cl['color']) . '"' : '';
    $logo = $cl ? (function () use ($cl) { ob_start(); $logo = $cl['logo_path']; $name = $cl['name']; $size = 64; include BASE_PATH . '/app/Views/partials/club_logo.php'; return ob_get_clean(); })() : '<span class="club-mono all">' . icon('users') . '</span>';
    $inner = '<span class="crest">' . $logo . '</span><span class="nm">' . e($cl ? ($cl['short_name'] ?: $cl['name']) : $pickerAllLabel) . '</span>'
        . (isset($cl['n']) || (!$cl && $pickerTotal !== null) ? '<span class="cnt">' . (int) ($cl['n'] ?? $pickerTotal) . ' judokas</span>' : '')
        . (!empty($cl['list_ok']) ? '<span class="ok">' . icon('check') . 'Liste validée</span>' : '');
    $attrs = ' class="club-pick' . ($cl ? '' : ' all') . '"' . $style . ($on ? ' aria-current="true"' : '') . ' data-name="' . e(mb_strtolower($cl ? $cl['name'] . ' ' . ($cl['short_name'] ?? '') . ' ' . ($cl['city'] ?? '') : '')) . '" title="' . e($cl['name'] ?? $pickerAllLabel) . '"';
    if ($pickerMode === 'filter-link') {
        return '<a href="' . e($pickerHref($id)) . '"' . $attrs . '>' . $inner . '</a>';
    }
    return '<button type="button"' . $attrs . ' data-pick="' . ($id ?? '') . '" data-pick-label="' . e($cl ? ($cl['short_name'] ?: $cl['name']) : $pickerAllLabel) . '">' . $inner . '</button>';
};
?>
<div class="club-picker" data-picker="<?= e($pickerId) ?>"<?= $pickerMode === 'field' ? ' data-picker-field="' . e($pickerName) . '"' : '' ?>>
  <?php if ($pickerMode === 'field'): ?><input type="hidden" name="<?= e($pickerName) ?>" value="<?= (int) ($pickerCurrent ?? 0) ?: '' ?>" data-picker-input><?php endif; ?>
  <button type="button" class="club-picker-btn" data-dialog-open="<?= e($pickerId) ?>"<?= $cur ? ' style="--club:' . e($cur['color']) . '"' : '' ?>>
    <span class="cp-logo" data-picker-logo><?php if ($cur): $logo = $cur['logo_path']; $name = $cur['name']; $size = 26; include BASE_PATH . '/app/Views/partials/club_logo.php'; else: ?><?= icon('users') ?><?php endif; ?></span>
    <span class="cp-txt"><small>Club</small><strong data-picker-label><?= e($cur ? ($cur['short_name'] ?: $cur['name']) : ($pickerAllLabel ?? 'Choisir un club')) ?></strong></span>
    <?= icon('chevron') ?>
  </button>
  <dialog class="club-dialog" id="<?= e($pickerId) ?>" aria-labelledby="<?= e($pickerId) ?>-t">
    <div class="cd-head">
      <h2 id="<?= e($pickerId) ?>-t">Choisir un club</h2>
      <label class="search"><?= icon('search') ?><span class="sr-only">Rechercher un club</span><input type="search" placeholder="Rechercher un club ou une ville…" data-dialog-filter autocomplete="off"></label>
      <button type="button" class="icon-btn" data-dialog-close aria-label="Fermer"><?= icon('x') ?></button>
    </div>
    <div class="cd-grid">
      <?php if ($pickerAllLabel !== null && $pickerMode !== 'field'): ?><?= $card(null) ?><?php endif; ?>
      <?php foreach ($pickerClubs as $pc): ?><?= $card($pc) ?><?php endforeach; ?>
    </div>
    <p class="cd-empty" hidden>Aucun club ne correspond à la recherche.</p>
  </dialog>
</div>
