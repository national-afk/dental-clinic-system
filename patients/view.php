<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Patient profile';

$id = $_GET['id'] ?? '';
$patient = Supabase::selectOne('patients', ['id' => 'eq.' . $id]);
if (!$patient) { flash('error', 'Patient not found.'); redirect('patients/index.php'); }

// handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['doc_file'])) {
    $uploadDir = __DIR__ . '/../uploads/' . $id . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $file = $_FILES['doc_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']);
        move_uploaded_file($file['tmp_name'], $uploadDir . $safeName);
        Supabase::insert('patient_documents', [
            'patient_id' => $id,
            'file_name' => $file['name'],
            'file_url' => base_url('uploads/' . $id . '/' . $safeName),
            'doc_type' => $_POST['doc_type'] ?? 'document',
        ]);
        flash('success', 'File uploaded.');
        redirect('patients/view.php?id=' . $id);
    }
}

$documents = Supabase::select('patient_documents', ['patient_id' => 'eq.' . $id, 'order' => 'uploaded_at.desc']);
$appointments = Supabase::select('appointments', ['patient_id' => 'eq.' . $id, 'select' => '*,services(name)', 'order' => 'appointment_date.desc']);
$invoices = Supabase::select('invoices', ['patient_id' => 'eq.' . $id, 'order' => 'created_at.desc']);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  <?= h($patient['full_name']) ?> <span class="badge teal"><?= h($patient['patient_code']) ?></span>
  <span>
    <a class="btn small outline" href="<?= base_url('patients/save.php?id=' . $id) ?>">Edit</a>
    <a class="btn small" href="<?= base_url('dental-chart/index.php?patient_id=' . $id) ?>">🦷 Dental chart</a>
    <a class="btn small" href="<?= base_url('appointments/save.php?patient_id=' . $id) ?>">+ Book appointment</a>
  </span>
</div>

<div class="form-grid">
  <div class="card">
    <h3>Patient info</h3>
    <p><strong>Phone:</strong> <?= $patient['phone'] ? '<a href="tel:' . h($patient['phone']) . '">' . h($patient['phone']) . '</a>' : '—' ?></p>
    <p><strong>Age / Gender:</strong> <?= h($patient['age']) ?: '—' ?> / <?= h($patient['gender']) ?: '—' ?></p>
    <p><strong>Address:</strong> <?= h($patient['address']) ?: '—' ?></p>
    <p><strong>Allergies:</strong> <?= $patient['allergies'] ? '<span class="badge red">' . h($patient['allergies']) . '</span>' : '—' ?></p>
  </div>
  <div class="card">
    <h3>Medical &amp; dental history</h3>
    <p><strong>Medical history date:</strong> <?= $patient['medical_history'] ? h(date('d M Y', strtotime($patient['medical_history']))) : '—' ?></p>
    <p><strong>Dental history date:</strong> <?= $patient['dental_history'] ? h(date('d M Y', strtotime($patient['dental_history']))) : '—' ?></p>
    <p><strong>Doctor's notes:</strong><br><?= nl2br(h($patient['doctor_notes'])) ?: '—' ?></p>
  </div>
</div>

<div class="section-title">🖼️ X-rays &amp; documents</div>
<div class="card">
  <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:end;margin-bottom:16px;">
    <div style="flex:1;">
      <label>Upload file</label>
      <input type="file" name="doc_file" required>
    </div>
    <div>
      <label>Type</label>
      <select name="doc_type">
        <option value="xray">X-ray</option>
        <option value="document">Document</option>
        <option value="photo">Photo</option>
      </select>
    </div>
    <button class="btn">Upload</button>
  </form>
  <?php if (!count($documents)): ?>
    <p style="color:var(--slate);">No files uploaded yet.</p>
  <?php else: ?>
    <table class="data">
      <tr><th>File</th><th>Type</th><th>Uploaded</th><th></th></tr>
      <?php foreach ($documents as $d): ?>
        <tr>
          <td><?= h($d['file_name']) ?></td>
          <td><span class="badge gray"><?= h($d['doc_type']) ?></span></td>
          <td><?= h(date('d M Y H:i', strtotime($d['uploaded_at']))) ?></td>
          <td><a href="<?= h($d['file_url']) ?>" target="_blank">View</a></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<div class="section-title">📅 Appointment history</div>
<table class="data">
  <tr><th>Date</th><th>Time</th><th>Service</th><th>Status</th></tr>
  <?php if (!count($appointments)): ?><tr><td colspan="4" style="color:var(--slate);">No appointments yet.</td></tr><?php endif; ?>
  <?php foreach ($appointments as $a): ?>
    <tr>
      <td><?= h($a['appointment_date']) ?></td>
      <td><?= h(substr($a['appointment_time'],0,5)) ?></td>
      <td><?= h($a['services']['name'] ?? '—') ?></td>
      <td><span class="badge <?= $a['status']==='completed'?'green':($a['status']==='cancelled'?'red':'amber') ?>"><?= h(ucfirst($a['status'])) ?></span></td>
    </tr>
  <?php endforeach; ?>
</table>

<div class="section-title">💳 Invoices</div>
<table class="data">
  <tr><th>Invoice #</th><th>Total</th><th>Balance</th><th>Status</th><th></th></tr>
  <?php if (!count($invoices)): ?><tr><td colspan="5" style="color:var(--slate);">No invoices yet.</td></tr><?php endif; ?>
  <?php foreach ($invoices as $inv): ?>
    <tr>
      <td><?= h($inv['invoice_no']) ?></td>
      <td><?= money($inv['total_amount']) ?></td>
      <td><?= money($inv['balance']) ?></td>
      <td><span class="badge <?= $inv['status']==='paid'?'green':($inv['status']==='partial'?'amber':'red') ?>"><?= h(ucfirst($inv['status'])) ?></span></td>
      <td><a href="<?= base_url('billing/invoice.php?id=' . $inv['id']) ?>">Open</a></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
