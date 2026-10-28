<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Patients';

$q = trim($_GET['q'] ?? '');
$filters = ['order' => 'created_at.desc'];
if ($q !== '') {
    // PostgREST "or" filter across name / phone / code
    $filters['or'] = "(full_name.ilike.*{$q}*,phone.ilike.*{$q}*,patient_code.ilike.*{$q}*)";
}
$patients = Supabase::select('patients', $filters);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  🧑‍⚕️ Patients
  <a class="btn" href="<?= base_url('patients/save.php') ?>">+ New patient</a>
</div>

<form method="get" class="card" style="margin-bottom:18px;display:flex;gap:10px;">
  <input type="text" name="q" placeholder="Search by name, phone or patient ID…" value="<?= h($q) ?>">
  <button class="btn outline">Search</button>
</form>

<table class="data">
  <tr><th>Patient ID</th><th>Magaca (Name)</th><th>Telefoon</th><th>Da'da / Jinsiga</th><th>Registered</th><th></th></tr>
  <?php if (!count($patients)): ?>
    <tr><td colspan="6" style="color:var(--slate);">No patients found.</td></tr>
  <?php endif; ?>
  <?php foreach ($patients as $p): ?>
    <tr>
      <td><?= h($p['patient_code']) ?></td>
      <td><a href="<?= base_url('patients/view.php?id=' . $p['id']) ?>"><?= h($p['full_name']) ?></a></td>
      <td><?= h($p['phone']) ? '<a href="tel:' . h($p['phone']) . '">' . h($p['phone']) . '</a>' : '—' ?></td>
      <td><?= h($p['age']) ?> / <?= h($p['gender']) ?></td>
      <td><?= h(date('d M Y', strtotime($p['created_at']))) ?></td>
      <td>
        <a class="btn small outline" href="<?= base_url('patients/view.php?id=' . $p['id']) ?>">View</a>
        <a class="btn small outline" href="<?= base_url('patients/save.php?id=' . $p['id']) ?>">Edit</a>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
