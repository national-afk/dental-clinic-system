<?php
require_login();
$staff = current_staff();
$page_title = $page_title ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?> · <?= h(clinic_settings()['clinic_name']) ?></title>
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<?php
$themeDark = app_setting('theme', 'light') === 'dark';
$sidebarColor = app_setting('sidebar_color', '#16302C');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $sidebarColor)) { $sidebarColor = '#16302C'; }
?>
<style>:root { --sidebar-bg: <?= h($sidebarColor) ?>; }</style>
</head>
<body class="<?= $themeDark ? 'theme-dark' : '' ?> <?= app_setting('compact', '0') === '1' ? 'compact' : '' ?>">
<div class="app-shell">
<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleSidebar(false)"></div>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button type="button" class="hamburger-btn" id="hamburger-btn" onclick="toggleSidebar()" aria-label="Menu">☰</button>
    <div class="date"><?= date('l, d F Y') ?></div>
    <div class="who">
      <?= h($staff['name']) ?> <span class="badge teal"><?= h(ucfirst($staff['role'])) ?></span>
      <a href="<?= base_url('logout.php') ?>">Log out</a>
    </div>
  </div>
  <div class="content">
    <?php if ($msg = flash('success')): ?><div class="alert success"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($msg = flash('error')): ?><div class="alert error"><?= h($msg) ?></div><?php endif; ?>
<script>
  function toggleSidebar(force) {
    const sb = document.querySelector('.sidebar');
    const bd = document.getElementById('sidebar-backdrop');
    const shouldOpen = typeof force === 'boolean' ? force : !sb.classList.contains('open');
    sb.classList.toggle('open', shouldOpen);
    bd.classList.toggle('open', shouldOpen);
  }
  // Close the drawer automatically when a nav link is tapped (mobile/tablet)
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.sidebar nav a').forEach(a => a.addEventListener('click', () => toggleSidebar(false)));
  });
</script>
