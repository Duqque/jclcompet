<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title><?= e($docTitle) ?> — <?= e($c['name']) ?></title>
<style>
  @page { margin: 14mm 12mm 16mm; }
  body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9pt; color: #0b0d12; }
  h1 { font-size: 15pt; margin: 0 0 2mm; text-transform: uppercase; font-style: italic; }
  h2 { font-size: 11pt; margin: 4mm 0 2mm; }
  .head { border-bottom: 2.5pt solid #c21a2c; padding-bottom: 2mm; margin-bottom: 4mm; }
  .head .meta { color: #5d6472; font-size: 8pt; }
  .head img { height: 12mm; float: right; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
  th, td { border: 0.6pt solid #9aa0aa; padding: 1.4mm 1.6mm; text-align: left; vertical-align: top; }
  th { background: #eef0f3; font-size: 7.5pt; text-transform: uppercase; }
  .num { text-align: center; }
  .box { height: 7mm; }
  .pool { page-break-inside: avoid; border: 1pt solid #0b0d12; margin-bottom: 4mm; padding: 2mm; }
  .pool h2 { margin: 0 0 1.5mm; background: #0b0d12; color: #fff; padding: 1.4mm 2mm; font-size: 10pt; }
  .row2 { width: 100%; clear: both; }
  .row2:after { content: ""; display: block; clear: both; }
  .half { float: left; width: 49%; margin-right: 1%; }
  .half .pool { font-size: 8pt; }
  .break { page-break-after: always; }
  .muted { color: #5d6472; }
  .small { font-size: 7.5pt; }
  .print-bar { position: fixed; top: 8px; right: 8px; }
  @media print { .print-bar { display: none; } }
</style></head>
<body>
<?php if (!\App\Services\PdfService::available()): ?><div class="print-bar"><button type="button" data-print>Imprimer / enregistrer en PDF</button></div><script src="<?= e(asset('js/app.js')) ?>"></script><?php endif; ?>
<div class="head">
  <?php $logo = BASE_PATH . '/public/assets/img/logo-jcl.png'; if (is_file($logo)): ?><img src="<?= \App\Services\PdfService::available() ? e($logo) : e(asset('img/logo-jcl.png')) ?>" alt=""><?php endif; ?>
  <h1><?= e($docTitle) ?></h1>
  <?php if ($opt['show_competition'] ?? true): ?><div><strong><?= e($c['name']) ?></strong><?= $c['edition_label'] ? ' — ' . e($c['edition_label']) : '' ?></div><?php endif; ?>
  <div class="meta"><?= e(fr_date($c['event_date'], true)) ?> · <?= e($c['city']) ?> · édité le <?= e($generatedAt) ?></div>
</div>
<?= $content ?>
</body></html>
