<?php
/**
 * Shared shell for 80mm thermal receipt pages (no sidebar / no app chrome).
 * Usage:  thermal_begin('Receipt INV-00001', $backUrl);  ... receipt HTML ...  thermal_end();
 */
function thermal_begin(string $title, string $backUrl): void
{
    $s = clinic_settings();
    $paper = receipt_paper();
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="<?= asset('css/thermal.css') ?>">
<style>@page { size: <?= $paper === 'a4' ? 'A4' : h($paper) . ' auto' ?>; margin: <?= $paper === 'a4' ? '10mm' : '0' ?>; }</style>
</head>
<body class="paper-<?= h($paper) ?>">
<div class="toolbar">
  <button type="button" onclick="window.print()">🖨 Print</button>
  <select id="paper-switch" onchange="var u=new URL(location.href);u.searchParams.set('paper',this.value);u.searchParams.delete('auto');location.href=u;">
    <?php foreach (RECEIPT_PAPERS as $k => $label): ?><option value="<?= h($k) ?>" <?= $paper===$k?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?>
  </select>
  <a class="alt" href="<?= h($backUrl) ?>">← Back</a>
</div>
<div class="hint"><?= $paper === 'a4' ? 'A4 printer: normal margins' : 'Paper: ' . h($paper) . ' · margins: none · headers/footers: off' ?></div>
<div class="receipt">
<?php if (!empty($s['logo_url'])): ?>
  <img class="logo" src="<?= h($s['logo_url']) ?>" alt="">
<?php endif; ?>
  <div class="title"><?= h($s['clinic_name']) ?></div>
<?php if (!empty($s['clinic_address'])): ?><div class="sub"><?= h($s['clinic_address']) ?></div><?php endif; ?>
<?php if (!empty($s['clinic_phone'])): ?><div class="sub">Tel: <?= h($s['clinic_phone']) ?></div><?php endif; ?>
  <div class="sep"></div>
<?php
}

function thermal_end(): void
{
    $s = clinic_settings();
    ?>
  <div class="sep"></div>
<?php if (!empty($s['receipt_footer'])): ?>
  <div class="center"><?= h($s['receipt_footer']) ?></div>
<?php endif; ?>
  <div class="center muted">Printed <?= h(date('d/m/Y H:i')) ?> by <?= h(current_staff()['name']) ?></div>
  <div class="cut">- - - - - - - - - - - - -</div>
</div>
<?php if (!empty($_GET['auto'])): ?>
<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
</script>
<?php endif; ?>
</body>
</html>
<?php
}
