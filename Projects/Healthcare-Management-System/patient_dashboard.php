<?php
// patient_dashboard.php - ÖZEL TASARIMLI HASTA PANELİ
ob_start();
session_start();
require 'includes/db.php';

// Güvenlik
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php"); exit;
}

$patientId   = $_SESSION['user_id'];
$patientName = $_SESSION['name'];

// --- VERİLERİ ÇEK ---
// 1. Profil ve Kan Grubu
$user = $conn->query("SELECT blood_group, height, weight FROM users WHERE id=$patientId")->fetch(PDO::FETCH_ASSOC);

// 2. Gelecek Randevular
$sqlAppt = "SELECT a.*, u.name as doctor_name, u.hospital_name, u.department
            FROM appointments a 
            JOIN users u ON a.doctor_id = u.id 
            WHERE a.patient_id=$patientId 
              AND a.appointment_date >= CURDATE() 
              AND a.status != 'cancelled' 
            ORDER BY a.appointment_date ASC LIMIT 5";
$appts = $conn->query($sqlAppt)->fetchAll(PDO::FETCH_ASSOC);

// 3. İlaçlar (DÜZELTİLDİ: 'prescription' sütununu çekiyoruz)
$meds = $conn->query("SELECT prescription FROM medical_records WHERE patient_id=$patientId AND prescription IS NOT NULL AND prescription != '' ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);

// 4. Acil Durum Kişileri
$contacts = $conn->query("SELECT * FROM emergency_contacts WHERE patient_id=$patientId LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Patient Dashboard";
// Header varsa çağır, yoksa basit HTML başlığı
if(file_exists('includes/header.php')) {
    require 'includes/header.php';
} else {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Dashboard</title></head><body>';
}
?>

<style>
    body { background-color: #f3f4f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    
    /* Ana Düzen (Grid) */
    .dashboard-layout {
        display: grid;
        grid-template-columns: 260px 1fr 320px; /* Sol - Orta - Sağ */
        gap: 25px;
        max-width: 1400px;
        margin: 30px auto;
        padding: 0 20px;
        align-items: start;
    }

    /* Sol Menü Butonları */
    .menu-btn {
        display: block;
        width: 100%;
        padding: 14px 20px;
        margin-bottom: 12px;
        border-radius: 50px; /* Yuvarlak kenarlar */
        text-align: center;
        text-decoration: none;
        font-weight: 600;
        font-size: 15px;
        transition: transform 0.2s;
        border: none;
        color: white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .menu-btn:hover { transform: translateY(-2px); filter: brightness(1.05); }

    .btn-red { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .btn-blue { background: linear-gradient(135deg, #2563eb, #1d4ed8); } /* Organ Bağışı */
    .btn-dark { background: linear-gradient(135deg, #374151, #1f2937); }

    /* Kart Tasarımı */
    .card-panel {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        margin-bottom: 25px;
    }

    /* Başlıklar */
    .panel-title { font-size: 18px; font-weight: 700; margin-bottom: 15px; color: #111827; display: flex; align-items: center; gap: 10px; }
    
    /* Profil Bölümü (Sağ) */
    .profile-header { text-align: center; margin-bottom: 20px; }
    .btn-edit-profile {
        background: #10b981; color: white; padding: 6px 15px; border-radius: 20px; 
        text-decoration: none; font-size: 12px; font-weight: bold;
    }
    .blood-tag {
        background: #fee2e2; color: #b91c1c; padding: 8px 15px; border-radius: 12px;
        font-weight: bold; font-size: 13px; display: inline-block; margin-top: 10px;
    }

    /* İletişim Kutusu */
    .contact-box {
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 15px; padding: 15px; margin-top: 15px;
    }
    .contact-item { font-size: 13px; margin-bottom: 5px; color: #4b5563; border-bottom: 1px solid #eee; padding-bottom: 5px; }

    /* İlaç Kutusu */
    .med-box {
        background: #fff1f2; border: 1px solid #fecdd3; border-radius: 15px; padding: 15px; margin-top: 20px;
    }
    .med-empty { color: #be123c; font-size: 13px; font-weight: 500; }

    /* Orta Alan (Randevular) */
    .appt-card {
        background: #f9fafb; border-left: 5px solid #2563eb; border-radius: 8px;
        padding: 15px; margin-bottom: 10px;
    }

    @media (max-width: 1024px) {
        .dashboard-layout { grid-template-columns: 1fr; } 
    }
</style>

<div style="max-width: 1400px; margin: 30px auto 20px; padding: 0 20px;">
    <h1 style="font-size: 28px; color: #111827; margin: 0 0 5px 0;">
        Welcome back, <?php echo htmlspecialchars($patientName); ?>
    </h1>
    <p style="color: #6b7280; margin: 0; font-size: 16px;">
        Here is your health overview and schedule for today.
    </p>
</div>

<div class="dashboard-layout">
    
    <div>
        <a href="appointments.php" class="menu-btn btn-red">New Appointment</a>
        <a href="organ_donation.php" class="menu-btn btn-blue">Organ Donation Preference</a>
        
        <a href="visit_history.php" class="menu-btn btn-dark">Visit History</a>
        <a href="tests.php" class="menu-btn btn-dark">My Tests</a>
        <a href="reports.php" class="menu-btn btn-dark">My Reports</a>
        <a href="medical_records.php" class="menu-btn btn-dark">My Medical Records</a>
    </div>

    <div class="card-panel" style="min-height: 400px;">
        <div class="panel-title">🗓️ Upcoming Appointments</div>
        
        <?php if(empty($appts)): ?>
            <div style="text-align:center; padding: 40px; color: #6b7280;">
                <p style="font-size: 16px;">You do not have any upcoming appointments.</p>
                <small>Use the 'New Appointment' button to book one.</small>
            </div>
        <?php else: ?>
            <?php foreach($appts as $a): ?>
            <div class="appt-card">
                <div style="font-weight:bold; font-size:16px;"><?php echo htmlspecialchars($a['doctor_name']); ?></div>
                <div style="color:#4b5563; font-size:14px; margin: 4px 0;">
                    <?php echo htmlspecialchars($a['hospital_name']); ?> - <?php echo htmlspecialchars($a['department']); ?>
                </div>
                <div style="font-size:13px; color:#2563eb; font-weight:600;">
                    📅 <?php echo date('d M Y', strtotime($a['appointment_date'])); ?> 
                    🕒 <?php echo substr($a['appointment_time'],0,5); ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div>
        <div class="card-panel">
            <div class="profile-header">
                <a href="profile.php" class="btn-edit-profile">Edit Profile</a>
                <br>
                <div class="blood-tag">
                    🩸 Your Blood Type: <?php echo htmlspecialchars($user['blood_group'] ?? 'Not set yet'); ?>
                </div>
            </div>

            <div class="contact-box">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <div style="font-weight:bold; color:#1f2933;">Emergency Contacts</div>
                    <a href="profile.php" style="font-size:12px; text-decoration:none; color:#2563eb;">Manage</a>
                </div>
                
                <?php if(empty($contacts)): ?>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <span style="font-size:24px;">📞</span>
                        <div>
                            <strong>Add Contact</strong><br>
                            <small style="color:#6b7280;">Priority contacts for emergencies</small>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($contacts as $c): ?>
                        <div class="contact-item">
                            <strong><?php echo htmlspecialchars($c['contact_name']); ?></strong> 
                            (<?php echo htmlspecialchars($c['relation']); ?>)
                            <br><?php echo htmlspecialchars($c['phone']); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="med-box">
                <div style="font-weight:bold; color:#be123c; margin-bottom:5px;">💊 Recent Medications</div>
                <?php if(empty($meds)): ?>
                    <div class="med-empty">You do not have any active prescriptions yet.</div>
                <?php else: ?>
                    <ul style="margin:0; padding-left:20px; font-size:13px; color:#be123c;">
                        <?php foreach($meds as $m): ?>
                            <li><?php echo htmlspecialchars($m); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<a href="questions.php?mode=ask" style="position:fixed; bottom:30px; right:30px; background:#f59e0b; color:white; padding:15px 25px; border-radius:50px; text-decoration:none; font-weight:bold; box-shadow:0 4px 15px rgba(245, 158, 11, 0.4); display:flex; align-items:center; gap:8px;">
     Ask a Doctor
</a>

<?php if(file_exists('includes/footer.php')) require 'includes/footer.php'; ?>