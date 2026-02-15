<?php
// doctor_dashboard.php - MODERN DOKTOR PANELİ (GÜNCELLENMİŞ LAYOUT)
ob_start();
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php"); exit;
}

$doctorId   = $_SESSION['user_id'];
$doctorName = $_SESSION['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $apptId = $_POST['appointment_id'];
    $newStatus = ($_POST['action'] === 'approve') ? 'approved' : 'cancelled';
    $updateStmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
    $updateStmt->execute([$newStatus, $apptId, $doctorId]);
    header("Location: doctor_dashboard.php?msg=updated"); exit;
}

$stmtDoc = $conn->prepare("SELECT department, hospital_name FROM users WHERE id = ?");
$stmtDoc->execute([$doctorId]);
$docInfo = $stmtDoc->fetch(PDO::FETCH_ASSOC);

$sqlAppt = "SELECT a.*, u.name as patient_name, u.blood_group 
            FROM appointments a 
            JOIN users u ON a.patient_id = u.id 
            WHERE a.doctor_id = ? 
              AND a.appointment_date >= CURDATE() 
              AND a.status = 'approved' 
            ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$stmtAppt = $conn->prepare($sqlAppt);
$stmtAppt->execute([$doctorId]);
$todayAppts = $stmtAppt->fetchAll(PDO::FETCH_ASSOC);

$stmtPending = $conn->prepare("SELECT a.*, u.name as patient_name 
                               FROM appointments a 
                               JOIN users u ON a.patient_id = u.id 
                               WHERE a.doctor_id = ? AND a.status = 'pending' 
                               ORDER BY a.appointment_date ASC LIMIT 5");
$stmtPending->execute([$doctorId]);
$pendingAppts = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

$totalToday = count($todayAppts);
$pendingRequestsCount = $conn->query("SELECT COUNT(*) FROM appointments WHERE doctor_id=$doctorId AND status='pending'")->fetchColumn();
$unreadQuestions = 0; 
try { 
    $unreadQuestions = $conn->query("SELECT COUNT(*) FROM questions WHERE doctor_id=$doctorId AND (answer_text IS NULL OR answer_text='')")->fetchColumn(); 
} catch(Exception $e) {}

require 'includes/header.php';
?>
<div style="max-width: 1400px; margin: 30px auto 20px; padding: 0 20px;">
    <h1 style="font-size: 28px; color: #111827; margin: 0 0 5px 0;">
        Hello, <?php echo htmlspecialchars($doctorName); ?>
    </h1>
    
</div>

<style>
    body { background-color: #f3f4f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .dashboard-layout { display: grid; grid-template-columns: 260px 1fr 320px; gap: 25px; max-width: 1400px; margin: 30px auto; padding: 0 20px; align-items: start; }
    .sidebar-container, .sidebar-right { display: flex; flex-direction: column; gap: 20px; }
    .menu-btn { display: block; width: 100%; padding: 14px 20px; margin-bottom: 12px; border-radius: 50px; text-align: center; text-decoration: none; font-weight: 600; font-size: 14px; transition: transform 0.2s; border: none; color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .menu-btn:hover { transform: translateY(-2px); filter: brightness(1.05); }
    .btn-red { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .btn-blue { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
    .btn-dark { background: linear-gradient(135deg, #374151, #1f2937); }
    .card-panel { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    .panel-title { font-size: 17px; font-weight: 700; margin-bottom: 15px; color: #111827; display: flex; align-items: center; gap: 10px; }
    .stat-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 15px; padding: 12px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center; }
    .stat-val { font-size: 18px; font-weight: 800; color: #2563eb; }
    .stat-label { font-size: 12px; color: #64748b; font-weight: 600; }
    .appt-card { background: #f9fafb; border-left: 5px solid #10b981; border-radius: 12px; padding: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
    .time-tag { background: #e0f2fe; color: #0369a1; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 13px; text-align: center; }
    .patient-info { flex-grow: 1; margin-left: 15px; }
    .patient-name { font-size: 15px; font-weight: 700; color: #1f2937; }
    .patient-meta { font-size: 12px; color: #6b7280; }
    .action-link { background: #2563eb; color: white; padding: 8px 14px; border-radius: 10px; text-decoration: none; font-size: 12px; font-weight: bold; }
    .mini-calendar { text-align:center; padding:15px; background:#ffffff; border-radius:20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    .btn-approve { background: #10b981; color: white; padding: 6px 10px; border-radius: 8px; border:none; cursor:pointer; font-weight:bold; font-size:11px; flex:1; }
    .btn-reject { background: #ef4444; color: white; padding: 6px 10px; border-radius: 8px; border:none; cursor:pointer; font-weight:bold; font-size:11px; flex:1; }
</style>

<div class="dashboard-layout">
    
    <div class="sidebar-container">
        <div class="mini-calendar">
             <div style="font-size:15px; font-weight:800; color:#1e293b; margin-bottom:5px;"><?php echo date('F Y'); ?></div>
             <div style="font-size:11px; color:#64748b; margin-bottom: 10px;">Today: <?php echo date('l, dS'); ?></div>
             <div style="display:grid; grid-template-columns: repeat(7, 1fr); gap:4px; font-size:10px;">
                 <span style="color:#94a3b8; font-weight: bold;">S</span><span style="color:#94a3b8; font-weight: bold;">M</span><span style="color:#94a3b8; font-weight: bold;">T</span><span style="color:#94a3b8; font-weight: bold;">W</span><span style="color:#94a3b8; font-weight: bold;">T</span><span style="color:#94a3b8; font-weight: bold;">F</span><span style="color:#94a3b8; font-weight: bold;">S</span>
                 <?php for($i=1; $i<=28; $i++): $isToday = ($i == date('j')); ?>
                    <span style="padding:2px; <?php echo $isToday ? 'background:#2563eb; color:white; border-radius:4px; font-weight:bold;' : 'color:#64748b;'; ?>"><?php echo $i; ?></span>
                 <?php endfor; ?>
             </div>
        </div>
        <div class="navigation-menu">
            <a href="appointments.php" class="menu-btn btn-red">All Appointments</a>
            <a href="doctor_patients.php" class="menu-btn btn-dark">Patient Lists</a>
            <a href="doctor_test_requests.php" class="menu-btn btn-dark">Test Requests</a>
            <a href="questions.php" class="menu-btn btn-dark">Patient Questions 
                <?php if($unreadQuestions > 0): ?>
                    <span style="background:white; color:#1f2937; padding:1px 6px; border-radius:50%; font-size:10px; margin-left:5px; font-weight:bold;"><?php echo $unreadQuestions; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <div class="main-middle">
        <div class="card-panel">
            <div class="panel-title">Upcoming Appointments</div>
            <?php if(empty($todayAppts)): ?>
                <div style="text-align:center; padding: 40px; color: #94a3b8;">
                    <p style="font-size: 14px;">No approved appointments found.</p>
                </div>
            <?php else: ?>
                <?php foreach($todayAppts as $app): ?>
                <div class="appt-card">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
                        <div class="time-tag"><?php echo substr($app['appointment_time'], 0, 5); ?></div>
                        <small style="font-size: 10px; color: #64748b; font-weight: 700;"><?php echo date('d M', strtotime($app['appointment_date'])); ?></small>
                    </div>
                    <div class="patient-info">
                        <div class="patient-name"><?php echo htmlspecialchars($app['patient_name']); ?></div>
                        <div class="patient-meta">Blood Type: <?php echo htmlspecialchars($app['blood_group'] ?? 'N/A'); ?></div>
                    </div>
                    <a href="doctor_patient_view.php?patient_id=<?php echo $app['patient_id']; ?>&appt_id=<?php echo $app['id']; ?>" class="action-link">View Profile</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="sidebar-right">
        <div class="card-panel" style="text-align: center; padding: 20px;">
            <div style="font-weight: 800; font-size: 18px; color: #1e293b;"><?php echo htmlspecialchars($doctorName); ?></div>
            <div style="color: #2563eb; font-weight: 700; font-size: 12px; margin-top: 5px; text-transform: uppercase;"><?php echo htmlspecialchars($docInfo['department'] ?? 'General'); ?></div>
            <div style="color: #64748b; font-size: 11px; margin-top: 3px;"><?php echo htmlspecialchars($docInfo['hospital_name'] ?? ''); ?></div>
            <a href="profile.php" style="display:inline-block; margin-top:12px; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; padding: 5px 12px; border-radius: 20px; text-decoration: none; font-size: 11px; font-weight: bold;">Edit Profile</a>
        </div>

        <div class="card-panel" style="padding: 15px;">
            <div class="panel-title" style="color: #f59e0b; font-size: 15px;">Pending Requests</div>
            <?php if(empty($pendingAppts)): ?>
                <p style="text-align:center; font-size: 12px; color: #94a3b8; padding: 10px;">No pending requests.</p>
            <?php else: ?>
                <?php foreach($pendingAppts as $pen): ?>
                <div class="appt-card" style="border-left-color: #f59e0b; padding: 10px; margin-bottom: 10px; flex-direction: column; align-items: flex-start;">
                    <div class="patient-name" style="font-size: 14px;"><?php echo htmlspecialchars($pen['patient_name']); ?></div>
                    <div class="patient-meta" style="font-size: 11px; margin-bottom: 8px;">
                        <?php echo date('d M', strtotime($pen['appointment_date'])); ?> - <?php echo substr($pen['appointment_time'], 0, 5); ?>
                    </div>
                    <form method="POST" style="margin:0; width: 100%; display: flex; gap: 5px;">
                        <input type="hidden" name="appointment_id" value="<?php echo $pen['id']; ?>">
                        <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card-panel" style="padding: 15px;">
            <div class="panel-title" style="font-size: 15px;">Quick Insights</div>
            <div class="stat-box">
                <div class="stat-label">Upcoming</div>
                <div class="stat-val"><?php echo $totalToday; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Pending</div>
                <div class="stat-val" style="color: #ef4444;"><?php echo $pendingRequestsCount; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Questions</div>
                <div class="stat-val" style="color: #f59e0b;"><?php echo $unreadQuestions; ?></div>
            </div>
        </div>
    </div>
</div>

<?php if(file_exists('includes/footer.php')) require 'includes/footer.php'; ?>