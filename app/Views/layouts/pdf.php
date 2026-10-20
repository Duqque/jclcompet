<?php
use App\Services\PdfService;
$engine = PdfService::available();
$font = fn (string $f) => $engine ? BASE_PATH . '/public/assets/fonts/pdf/' . $f : asset('fonts/pdf/' . $f);
$logoSrc = $engine ? (pdf_image('assets/img/logo-jcl.png') ?? '') : asset('img/logo-jcl.png');
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title><?= e($docTitle) ?> — <?= e($c['name']) ?></title>
<style>
  @font-face { font-family: "Inter"; font-weight: 400; font-style: normal; src: url("<?= e($font('inter-400.ttf')) ?>") format("truetype"); }
  @font-face { font-family: "Inter"; font-weight: 500; font-style: normal; src: url("<?= e($font('inter-500.ttf')) ?>") format("truetype"); }
  @font-face { font-family: "Inter"; font-weight: 600; font-style: normal; src: url("<?= e($font('inter-600.ttf')) ?>") format("truetype"); }
  @font-face { font-family: "Inter"; font-weight: 700; font-style: normal; src: url("<?= e($font('inter-700.ttf')) ?>") format("truetype"); }
  @page { margin: 12mm 11mm 14mm; }
  * { box-sizing: border-box; }
  body { font-family: "Inter", "DejaVu Sans", Arial, sans-serif; font-size: 8.6pt; color: #18181b; line-height: 1.35; }
  h1 { font-size: 17pt; font-weight: 700; letter-spacing: -.3pt; margin: 0; }
  h2 { font-size: 11.5pt; font-weight: 700; margin: 0 0 2mm; letter-spacing: -.1pt; }
  h3 { font-size: 10pt; font-weight: 600; margin: 0; }
  .eyebrow { color: #c21a2c; font-size: 7.4pt; font-weight: 600; text-transform: uppercase; letter-spacing: .6pt; margin: 0 0 .6mm; }
  .muted { color: #71717a; }
  .small { font-size: 7.4pt; }
  .num { text-align: center; }
  .right { text-align: right; }
  .nowrap { white-space: nowrap; }
  .break { page-break-after: always; }
  .avoid { page-break-inside: avoid; }

  .doc-head { background: #f5f5f3; border-radius: 4mm; padding: 4mm 5mm; margin-bottom: 5mm; }
  .doc-head table { width: 100%; border-collapse: collapse; }
  .doc-head td { border: 0; padding: 0; vertical-align: middle; }
  .doc-head img { height: 13mm; width: 19.5mm; }
  .doc-head .meta { color: #71717a; font-size: 7.6pt; margin-top: .8mm; }
  .doc-head .stamp { text-align: right; color: #71717a; font-size: 7.2pt; }

  .card { background: #ffffff; border: .6pt solid #e4e4e7; border-radius: 4mm; padding: 3.5mm 4mm; margin-bottom: 4mm; page-break-inside: avoid; }
  .card-head { width: 100%; border-collapse: collapse; margin-bottom: 2.2mm; }
  .card-head td { border: 0; padding: 0; vertical-align: bottom; }
  .pill { display: inline-block; padding: .7mm 2.4mm; border-radius: 3mm; background: #f4f4f5; font-size: 7pt; font-weight: 500; color: #3f3f46; margin-left: 1mm; }
  .pill.ink { background: #18181b; color: #fff; }
  .pill.green { background: #e7f7ec; color: #15803d; }
  .pill.red { background: #fdecee; color: #c21a2c; }

  /* Tableaux « tuiles » */
  table.t { width: 100%; border-collapse: separate; border-spacing: 0 1.1mm; }
  table.t th { font-size: 6.6pt; font-weight: 500; color: #71717a; text-transform: uppercase; letter-spacing: .4pt; text-align: left; padding: 0 2mm .4mm; border: 0; }
  table.t td { background: #f5f5f3; padding: 1.8mm 2mm; border: 0; vertical-align: middle; }
  table.t td:first-child { border-top-left-radius: 2.4mm; border-bottom-left-radius: 2.4mm; }
  table.t td:last-child { border-top-right-radius: 2.4mm; border-bottom-right-radius: 2.4mm; }
  table.t th.num, table.t td.num { text-align: center; }
  .logo { width: 5mm; height: 5mm; vertical-align: middle; margin-right: 1.4mm; }
  .mono { display: inline-block; width: 5mm; height: 5mm; line-height: 5mm; border-radius: 2.5mm; background: #e4e4e7; font-size: 5pt; font-weight: 700; text-align: center; vertical-align: middle; margin-right: 1.4mm; color: #3f3f46; }

  /* Feuille de poule */
  table.grid { width: 100%; border-collapse: separate; border-spacing: 1mm; }
  table.grid th { font-size: 6.4pt; font-weight: 500; color: #71717a; text-transform: uppercase; letter-spacing: .3pt; text-align: center; padding: 0 0 .3mm; border: 0; }
  table.grid th.l { text-align: left; padding-left: 1.6mm; }
  table.grid td { background: #f5f5f3; border-radius: 2.2mm; height: 10mm; padding: 0 1.6mm; border: 0; vertical-align: middle; }
  table.grid td.seed { width: 6mm; text-align: center; font-weight: 600; color: #71717a; padding: 0; }
  table.grid td.x { width: 11mm; text-align: center; background: #ececea; padding: 0; }
  table.grid td.diag { background: #18181b; }
  table.grid td.win { background: #e7f7ec; color: #15803d; font-weight: 700; font-size: 9.5pt; }
  table.grid td.loss { background: #ffffff; border: .6pt solid #e4e4e7; color: #a1a1aa; font-weight: 600; }
  table.grid td.sum { width: 11mm; text-align: center; font-weight: 700; padding: 0; }
  table.grid td.place { background: #fff; border: .6pt solid #e4e4e7; }
  table.grid .how { display: block; font-size: 5.6pt; font-weight: 500; color: #71717a; }
  table.grid .weight { display: block; font-size: 6.4pt; color: #71717a; }
  .medal { display: inline-block; width: 6.4mm; padding: 1.3mm 0 1.1mm; line-height: 1; border-radius: 3.2mm; text-align: center; font-weight: 700; font-size: 7.6pt; background: #f4f4f5; }
  .m1 { background: #f2c94c; } .m2 { background: #d4d7dd; } .m3 { background: #e0a370; }
  .order { margin-top: 2mm; padding-top: 2mm; border-top: .6pt solid #e4e4e7; font-size: 7pt; color: #71717a; }
  .order span.o { display: inline-block; padding: .5mm 2mm; margin: 0 .8mm .8mm 0; border-radius: 3mm; background: #f4f4f5; color: #3f3f46; font-weight: 500; }
  .order span.o.done { background: #e7f7ec; color: #15803d; }

  .print-bar { position: fixed; top: 8px; right: 8px; }
  @media print { .print-bar { display: none; } }
</style></head>
<body>
<?php if (!$engine): ?><div class="print-bar"><button type="button" data-print>Imprimer / enregistrer en PDF</button></div><script src="<?= e(asset('js/app.js')) ?>"></script><?php endif; ?>
<div class="doc-head">
  <table><tr>
    <td style="width:26mm"><?php if ($logoSrc): ?><img src="<?= e($logoSrc) ?>" alt=""><?php endif; ?></td>
    <td>
      <?php if ($opt['show_competition'] ?? true): ?><p class="eyebrow"><?= e($c['name']) ?><?= $c['edition_label'] ? ' · ' . e($c['edition_label']) : '' ?></p><?php endif; ?>
      <h1><?= e($docTitle) ?></h1>
      <div class="meta"><?= e(fr_date($c['event_date'], true)) ?><?= $c['city'] ? ' · ' . e($c['city']) : '' ?><?= !empty($docSubtitle) ? ' · ' . e($docSubtitle) : '' ?></div>
    </td>
    <td class="stamp">Édité le <?= e($generatedAt) ?></td>
  </tr></table>
</div>
<?= $content ?>
</body></html>
