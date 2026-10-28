<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Dental Chart';

$patientId = $_GET['patient_id'] ?? '';

if (!$patientId) {
    $search = trim($_GET['q'] ?? '');
    $filters = ['order' => 'full_name.asc'];
    if ($search !== '') {
        $filters['or'] = "(full_name.ilike.*{$search}*,phone.ilike.*{$search}*,patient_code.ilike.*{$search}*)";
    }
    $patients = Supabase::select('patients', $filters);
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="section-title">🦷 Dental Chart — choose a patient</div>

    <form method="get" class="card" style="margin-bottom:16px;display:flex;gap:10px;">
      <input type="text" name="q" placeholder="Search by name, phone, or patient ID…" value="<?= h($search) ?>">
      <button class="btn outline">Search</button>
    </form>

    <table class="data">
      <tr><th>Patient ID</th><th>Name</th><th>Phone</th><th></th></tr>
      <?php if (!count($patients)): ?>
        <tr><td colspan="4" style="color:var(--slate);">No patients found.</td></tr>
      <?php endif; ?>
      <?php foreach ($patients as $p): ?>
        <tr>
          <td><?= h($p['patient_code']) ?></td>
          <td><?= h($p['full_name']) ?></td>
          <td><?= h($p['phone']) ?: '—' ?></td>
          <td><a class="btn small" href="?patient_id=<?= h($p['id']) ?>">Open chart</a></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$patient = Supabase::selectOne('patients', ['id' => 'eq.' . $patientId]);
if (!$patient) { flash('error', 'Patient not found.'); redirect('patients/index.php'); }

$chartRows = Supabase::select('dental_chart', ['patient_id' => 'eq.' . $patientId]);
$chart = [];
foreach ($chartRows as $row) { $chart[$row['tooth_number']] = $row; }

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  🦷 Dental chart — <?= h($patient['full_name']) ?>
  <a class="btn outline small" href="<?= base_url('patients/view.php?id=' . $patientId) ?>">← Back to patient</a>
</div>

<div class="card">
  <div class="arch-label">Upper arch (1 – 16)</div>
  <div class="tooth-row" id="upper-row"></div>
  <div class="arch-label">Lower arch (17 – 32)</div>
  <div class="tooth-row" id="lower-row"></div>

  <div class="section-title" style="margin-top:20px;">Legend</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:13px;">
    <span class="badge gray">Healthy</span>
    <span class="badge red">Cavity</span>
    <span class="badge teal">Filling</span>
    <span class="badge gray">Extraction</span>
    <span class="badge amber">Crown</span>
    <span class="badge teal">Root canal</span>
    <span class="badge gray">Missing</span>
  </div>
</div>

<!-- Tooth edit modal (plain, no framework) -->
<div id="tooth-modal" style="display:none;position:fixed;inset:0;background:rgba(22,48,44,.45);align-items:center;justify-content:center;z-index:50;">
  <div class="card" style="width:380px;">
    <h3 id="modal-title">Tooth #</h3>
    <form id="tooth-form">
      <input type="hidden" name="patient_id" value="<?= h($patientId) ?>">
      <input type="hidden" name="tooth_number" id="f-tooth-number">
      <label>Condition</label>
      <select name="condition" id="f-condition">
        <?php foreach (TOOTH_CONDITIONS as $k => $v): ?>
          <option value="<?= h($k) ?>"><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
      <div style="height:10px;"></div>
      <label>Treatment status</label>
      <select name="treatment_status" id="f-status">
        <?php foreach (TREATMENT_STATUSES as $k => $v): ?>
          <option value="<?= h($k) ?>"><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
      <div style="height:10px;"></div>
      <label>Before / after notes</label>
      <textarea name="notes" id="f-notes"></textarea>
      <div style="margin-top:14px;display:flex;gap:8px;">
        <button type="submit" class="btn">Save</button>
        <button type="button" class="btn outline" onclick="closeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
const chartData = <?= json_encode($chart) ?>;
const conditionClass = c => 'tooth cond-' + c;

function toothLabel(n){ return n; }

function renderRow(el, start, end){
  const row = document.getElementById(el);
  for (let n = start; n <= end; n++){
    const rec = chartData[n] || {condition:'healthy', treatment_status:'none'};
    const div = document.createElement('div');
    div.className = conditionClass(rec.condition);
    div.innerHTML = '#' + n + '<small>' + rec.condition.replace('_',' ') + '</small>';
    div.onclick = () => openModal(n, rec);
    row.appendChild(div);
  }
}
renderRow('upper-row', 1, 16);
renderRow('lower-row', 17, 32);

function openModal(n, rec){
  document.getElementById('modal-title').innerText = 'Tooth #' + n;
  document.getElementById('f-tooth-number').value = n;
  document.getElementById('f-condition').value = rec.condition || 'healthy';
  document.getElementById('f-status').value = rec.treatment_status || 'none';
  document.getElementById('f-notes').value = rec.notes || '';
  document.getElementById('tooth-modal').style.display = 'flex';
}
function closeModal(){ document.getElementById('tooth-modal').style.display = 'none'; }

document.getElementById('tooth-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const res = await fetch('<?= base_url('dental-chart/save_tooth.php') ?>', { method: 'POST', body: fd });
  const json = await res.json();
  if (json.ok) { location.reload(); } else { alert('Could not save: ' + (json.error || 'unknown error')); }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
