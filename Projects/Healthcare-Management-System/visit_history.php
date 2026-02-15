<?php
ob_start();
session_start();
require 'includes/db.php';

// Güvenlik: Sadece Hastalar
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId = $_SESSION['user_id'];
$patientName = $_SESSION['name'];

// --- TÜM RANDEVULARI ÇEK (Eskiden olduğu gibi) ---
// Tarihe göre yeniden eskiye sıralıyoruz.
$sql = "
    SELECT a.*, d.name as doctor_name 
    FROM appointments a 
    JOIN users d ON a.doctor_id = d.id 
    WHERE a.patient_id = ? 
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute([$patientId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>

<style>
    body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; margin: 0; }
    
    .dashboard-wrapper {
        display: flex;
        max-width: 1400px;
        margin: 40px auto;
        gap: 30px;
        padding: 0 20px;
        align-items: flex-start;
    }

    /* SOL MENÜ (Sidebar) - Dashboard ile aynı */
    .sidebar { width: 260px; flex-shrink: 0; display: flex; flex-direction: column; gap: 12px; }
    .sidebar-btn {
        display: flex; align-items: center; padding: 16px 24px; border-radius: 50px;
        text-decoration: none; font-weight: 700; font-size: 0.95rem; color: white;
        transition: transform 0.2s ease, box-shadow 0.2s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    .sidebar-btn:hover { transform: translateX(5px); }
    
    .btn-red { background-color: #dc2626; }
    .btn-blue { background-color: #2563eb; }
    .btn-dark { background-color: #1e293b; }

    /* SAĞ İÇERİK */
    .main-content { flex-grow: 1; }

    .card {
        background: white; border-radius: 20px; padding: 30px;
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05);
    }
    .card-title { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 25px; }

    /* TABLO TASARIMI */
    .history-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
    .history-table th { text-align: left; padding: 15px; color: #64748b; font-weight: 600; font-size: 0.9rem; border-bottom: 2px solid #e2e8f0; }
    .history-table td { background: #f8fafc; padding: 20px; vertical-align: middle; color: #334155; }
    
    /* Satırların köşelerini yuvarlama */
    .history-table tr td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    .history-table tr td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
    
    .history-table tr:hover td { background: #f1f5f9; }

    /* DURUM ROZETLERİ */
    .badge { padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; }
    .badge-approved { background: #dcfce7; color: #166534; }
    .badge-pending { background: #fef3c7; color: #b45309; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }
    .badge-completed { background: #dbeafe; color: #1e40af; }
</style>

<div class="dashboard-wrapper">
    


    <div class="main-content">
        <div class="card">
            <h1 class="card-title">🕒 Visit History</h1>

            <?php if (empty($history)): ?>
                <div style="text-align:center; padding:40px; color:#64748b; background:#f8fafc; border-radius:12px; border: 2px dashed #e2e8f0;">
                    <h3>No visits found</h3>
                    <p>You haven't made any appointments yet.</p>
                    <a href="appointments.php" style="display:inline-block; margin-top:10px; color:#2563eb; font-weight:bold; text-decoration:none;">Book your first appointment &rarr;</a>
                </div>
            <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Doctor</th>
                            <th>Hospital / Dept</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $appt): ?>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:#0f172a;">
                                    <?php echo date('d M Y', strtotime($appt['appointment_date'])); ?>
                                </div>
                                <div style="color:#64748b; font-size:0.9rem; margin-top:3px;">
                                    <?php echo substr($appt['appointment_time'], 0, 5); ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600;"><?php echo htmlspecialchars($appt['doctor_name']); ?></div>
                            </td>
                            <td>
                                <div style="font-weight:600; font-size:0.9rem; color:#334155;">
                                    <?php echo htmlspecialchars($appt['hospital_name']); ?>
                                </div>
                                <div style="color:#64748b; font-size:0.85rem;">
                                    <?php echo htmlspecialchars($appt['department']); ?>
                                </div>
                                <div style="font-size:0.8rem; color:#94a3b8;">
                                    <?php echo htmlspecialchars(($appt['city'] ?? '') . ' / ' . ($appt['district'] ?? '')); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $appt['status']; ?>">
                                    <?php echo ucfirst($appt['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require 'includes/footer.php'; ?>