<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Save patient';

$id = $_GET['id'] ?? null;
$patient = $id ? Supabase::selectOne('patients', ['id' => 'eq.' . $id]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name' => trim($_POST['full_name']),
        'phone' => trim($_POST['phone']),
        'email' => trim($_POST['email'] ?? ''),
        'age' => $_POST['age'] !== '' ? (int)$_POST['age'] : null,
        'gender' => $_POST['gender'],
        'address' => trim($_POST['address']),
        'medical_history' => trim($_POST['medical_history']),
        'allergies' => trim($_POST['allergies']),
        'dental_history' => trim($_POST['dental_history']),
        'doctor_notes' => trim($_POST['doctor_notes']),
        'updated_at' => date('c'),
    ];

    if ($id) {
        Supabase::update('patients', ['id' => 'eq.' . $id], $data);
        flash('success', 'Patient updated.');
        redirect('patients/view.php?id=' . $id);
    } else {
        $data['patient_code'] = generate_patient_code();
        $created = Supabase::insert('patients', $data);
        $newId = $created[0]['id'] ?? null;
        log_audit('create_patient', 'patient', $newId, $data['full_name']);
        flash('success', 'Patient registered.');
        redirect($newId ? 'patients/view.php?id=' . $newId : 'patients/index.php');
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><?= $patient ? 'Edit patient' : 'New patient' ?></div>

<form method="post" class="card">
  <div class="form-grid">
    <div>
      <label>Magaca (Full name) *</label>
      <input type="text" name="full_name" required value="<?= h($patient['full_name'] ?? '') ?>">
    </div>
    <div>
      <label>Telefoon (Phone)</label>
      <input type="text" name="phone" value="<?= h($patient['phone'] ?? '') ?>">
    </div>
    <div>
      <label>Email (for reminders)</label>
      <input type="email" name="email" value="<?= h($patient['email'] ?? '') ?>">
    </div>
    <div>
      <label>Da'da (Age)</label>
      <input type="number" name="age" min="0" value="<?= h($patient['age'] ?? '') ?>">
    </div>
    <div>
      <label>Jinsiga (Gender)</label>
      <select name="gender">
        <option value="">Select…</option>
        <option value="Male" <?= ($patient['gender'] ?? '')==='Male'?'selected':'' ?>>Male</option>
        <option value="Female" <?= ($patient['gender'] ?? '')==='Female'?'selected':'' ?>>Female</option>
      </select>
    </div>
    <div class="full">
      <label>Address</label>
      <input type="text" name="address" value="<?= h($patient['address'] ?? '') ?>">
    </div>
    <div>
      <label>Taariikhda caafimaadka (Medical history date)</label>
      <input type="date" name="medical_history" value="<?= h($patient['medical_history'] ?? '') ?>">
    </div>
    <div>
      <label>Allergies</label>
      <select name="allergies">
        <?php
          $allergyOptions = ['' => 'Select…'];
          foreach (array_filter(array_map('trim', explode(',', app_setting('allergy_options', 'None, Penicillin, Latex, Local Anesthetic, Aspirin / NSAIDs, Iodine, Other')))) as $ao) { $allergyOptions[$ao] = $ao; }
          if (($patient['allergies'] ?? '') !== '' && !isset($allergyOptions[$patient['allergies']])) { $allergyOptions[$patient['allergies']] = $patient['allergies']; }
          $currentAllergy = $patient['allergies'] ?? '';
        ?>
        <?php foreach ($allergyOptions as $val => $label): ?>
          <option value="<?= h($val) ?>" <?= $currentAllergy === $val ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Dental history (date)</label>
      <input type="date" name="dental_history" value="<?= h($patient['dental_history'] ?? '') ?>">
    </div>
    <div class="full">
      <label>Notes-ka dhakhtarka (Doctor's notes)</label>
      <textarea name="doctor_notes"><?= h($patient['doctor_notes'] ?? '') ?></textarea>
    </div>
  </div>
  <div style="margin-top:18px;">
    <button class="btn"><?= $patient ? 'Save changes' : 'Register patient' ?></button>
    <a class="btn outline" href="<?= base_url('patients/index.php') ?>">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
