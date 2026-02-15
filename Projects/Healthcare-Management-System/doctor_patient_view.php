<?php
ob_start();
session_start();
require 'includes/db.php';

// Güvenlik
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') { header("Location: login.php"); exit; }

$doctorId = $_SESSION['user_id'];
$patientId = $_GET['patient_id'] ?? 0;
$apptId = $_GET['appt_id'] ?? 0;
$msg = $_GET['msg'] ?? '';

// Arşiv Kontrolü: Eğer randevu ID'si yoksa arşivden geliyordur ve işlem yapılabilir.
$isArchive = !isset($_GET['appt_id']) || empty($_GET['appt_id']);

// Doktorun Kendi Bilgilerini Çek
$docInfo = $conn->query("SELECT department FROM users WHERE id=$doctorId")->fetch(PDO::FETCH_ASSOC);
$docDept = $docInfo['department'] ?? 'General';

// Hasta Bilgilerini Çek
$patient = $conn->query("SELECT * FROM users WHERE id=$patientId")->fetch(PDO::FETCH_ASSOC);
if (!$patient) die("Patient not found.");

// --- İŞLEMLER (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isArchive) { // Sadece arşiv modundaysa işlemleri yap
    
    // 1. TIBBİ KAYIT EKLEME
    if ($_POST['action'] === 'add_record') {
        $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, diagnosis, treatment, prescription) VALUES (?,?,?,?,?)");
        $stmt->execute([$patientId, $doctorId, $_POST['diagnosis'], $_POST['treatment'], $_POST['prescription']]);
        header("Location: ?patient_id=$patientId&msg=record_added"); exit;
    }

    // 2. TEST İSTEME
    if ($_POST['action'] === 'request_test' && !empty($_POST['tests'])) {
        $stmt = $conn->prepare("INSERT INTO tests (patient_id, doctor_id, test_name, status, requested_at) VALUES (?,?,?, 'istek', NOW())");
        foreach($_POST['tests'] as $t) $stmt->execute([$patientId, $doctorId, $t]);
        header("Location: ?patient_id=$patientId&msg=test_sent"); exit;
    }

    // 3. RAPOR YAZMA
    if ($_POST['action'] === 'write_report') {
        $sqlReport = "INSERT INTO reports (patient_id, doctor_id, start_date, end_date, leave_days, diagnosis, department, issue_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sqlReport);
        $stmt->execute([
            $patientId,
            $doctorId,
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['leave_days'],
            $_POST['diagnosis'],
            $docDept
        ]);
        header("Location: ?patient_id=$patientId&msg=report_saved"); exit;
    }
}

require 'includes/header.php';
?>

<style>
    body { background-color: #f3f4f6; font-family: 'Inter', sans-serif; }
    .layout-wrapper { display: flex; max-width: 1400px; margin: 40px auto; gap: 30px; padding: 0 20px; }
    .sidebar { width: 280px; flex-shrink: 0; display: flex; flex-direction: column; gap: 15px; }
    .sidebar-btn { display: block; padding: 18px 25px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 1rem; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.2s; color:white; }
    .sidebar-btn:hover { transform: translateY(-2px); }
    .btn-back { background: #64748b; color: white; text-align:center; }
    .main-content { flex-grow: 1; }
    .content-card { background: white; border-radius: 24px; padding: 40px; box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05); }
    .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
    .tab-btn { padding: 10px 20px; border-radius: 20px; border: none; font-weight: 600; cursor: pointer; background: #f1f5f9; color: #64748b; transition: 0.2s; }
    .tab-btn.active { background: #2563eb; color: white; box-shadow: 0 4px 10px rgba(37,99,235,0.3); }
    .tab-content { display: none; animation: fadeIn 0.3s; }
    .tab-content.active { display: block; }
    @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #334155; }
    .form-control { width: 100%; padding: 15px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 1rem; transition: 0.2s; font-family: inherit; }
    .form-control:focus { border-color: #2563eb; outline: none; }
    .btn-submit { background: #2563eb; color: white; border: none; padding: 15px 30px; border-radius: 12px; font-weight: 700; cursor: pointer; width: 100%; font-size: 1rem; }
    .btn-submit:hover { background: #1d4ed8; }
    .form-row { display: flex; gap: 20px; }
    .form-col { flex: 1; }
    .report-box { border: 2px dashed #cbd5e1; padding: 25px; border-radius: 15px; background: #f8fafc; }
    .readonly-notice { background:#fef3c7; color:#b45309; padding:20px; border-radius:12px; border:1px solid #fde68a; margin-bottom: 20px; }
</style>

<div class="layout-wrapper">
    <div class="sidebar">
        <div style="background: white; padding: 25px; border-radius: 20px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
            <div style="width: 80px; height: 80px; background: #e0e7ff; color: #3730a3; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 15px; font-weight: bold;">
                <?php echo strtoupper(substr($patient['name'], 0, 1)); ?>
            </div>
            <h3 style="margin: 0; color: #1e293b;"><?php echo htmlspecialchars($patient['name']); ?></h3>
            <p style="color: #64748b; margin: 5px 0;">Blood: <strong><?php echo htmlspecialchars($patient['blood_group'] ?? '-'); ?></strong></p>
        </div>
    </div>

    <div class="main-content">
        <div class="content-card">
            <?php if ($msg): ?>
                <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:10px; margin-bottom:20px; font-weight:bold;">✅ Success! Operation completed.</div>
            <?php endif; ?>

            <div class="tabs">
                <button class="tab-btn active" onclick="openTab('record')">📝 Medical Record</button>
                <button class="tab-btn" onclick="openTab('tests')">🧪 Request Tests</button>
                <button class="tab-btn" onclick="openTab('report')">📄 Issue Report</button>
            </div>

            <div id="tab-record" class="tab-content active">
                <?php if ($isArchive): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="add_record">
                        <div class="form-group">
                            <label>Diagnosis</label>
                            <input type="text" name="diagnosis" class="form-control" placeholder="Example: Acute Tonsillitis" required>
                        </div>
                        <div class="form-group">
                            <label>Treatment Plan</label>
                            <textarea name="treatment" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Prescription</label>
                            <textarea name="prescription" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Save Medical Record</button>
                    </form>
                <?php else: ?>
                    <div class="readonly-notice">
                        <strong>Read-Only Mode:</strong> This patient has an upcoming appointment. You can only view past records. 
                        To prescribe medication, please wait until the appointment is completed and access the patient via <b>Patient Lists</b>.
                    </div>
                <?php endif; ?>
            </div>

            <div id="tab-tests" class="tab-content">
                <?php if ($isArchive): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="request_test">
                        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:15px; margin-bottom:20px;">
                            <?php 
                            $tList = ['Hemogram (CBC)', 'Glucose', 'Urine Analysis', 'Liver Function', 'Kidney Function', 'X-Ray', 'MRI', 'CT Scan', 'COVID-19 PCR', 'Thyroid Panel']; 
                            foreach($tList as $t): ?>
                                <label style="background:#f8fafc; padding:15px; border-radius:10px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:10px; cursor:pointer;">
                                    <input type="checkbox" name="tests[]" value="<?php echo $t; ?>" style="width:18px; height:18px;">
                                    <?php echo $t; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn-submit">Request Selected Tests</button>
                    </form>
                <?php else: ?>
                    <div class="readonly-notice"><strong>Read-Only Mode:</strong> Laboratory requests are disabled for upcoming appointments.</div>
                <?php endif; ?>
            </div>

            <div id="tab-report" class="tab-content">
                <?php if ($isArchive): ?>
                    <div class="report-box">
                        <form method="post">
                            <input type="hidden" name="action" value="write_report">
                            <div class="form-group">
                                <label>Diagnosis for Report</label>
                                <input type="text" name="diagnosis" class="form-control" required>
                            </div>
                            <div class="form-row">
                                <div class="form-col"><label>Start Date</label><input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                                <div class="form-col"><label>End Date</label><input type="date" name="end_date" id="end_date" class="form-control" required></div>
                                <div class="form-col" style="flex: 0.5;"><label>Days</label><input type="number" name="leave_days" id="leave_days" class="form-control" readonly style="background:#e2e8f0;"></div>
                            </div>
                            <button type="submit" class="btn-submit" style="margin-top:20px;">Issue Official Report</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="readonly-notice"><strong>Read-Only Mode:</strong> Report issuance is disabled for upcoming appointments.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function openTab(id) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-'+id).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    const startInput = document.getElementById('start_date');
    const endInput = document.getElementById('end_date');
    const daysInput = document.getElementById('leave_days');

    function calculateDays() {
        if (startInput && endInput) {
            const start = new Date(startInput.value);
            const end = new Date(endInput.value);
            if (start && end && end >= start) {
                const diffTime = Math.abs(end - start);
                daysInput.value = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            } else { daysInput.value = ''; }
        }
    }
    if(startInput) startInput.addEventListener('change', calculateDays);
    if(endInput) endInput.addEventListener('change', calculateDays);
</script>

<?php require 'includes/footer.php'; ?>