<?php
ob_start();
session_start();
require 'includes/db.php';

// Güvenlik: Sadece Doktor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit;
}

$doctorId = (int)$_SESSION['user_id'];

// --- VERİ ÇEKME: DOKTORUN İSTEDİĞİ TÜM TESTLER ---
$sql = "
    SELECT 
        t.id,
        t.test_name,
        t.status,
        t.requested_at,
        u.name AS patient_name,
        u.id AS patient_id
    FROM tests t
    JOIN users u ON t.patient_id = u.id
    WHERE t.doctor_id = :did
    ORDER BY t.requested_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute([':did' => $doctorId]);
$testRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>

<style>
    body { background-color: #f3f4f6; font-family: 'Inter', sans-serif; }
    .page-wrapper { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
    
    .test-card {
        background: white;
        border-radius: 24px;
        padding: 30px;
        box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05);
    }

    .modern-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
    .modern-table th { text-align: left; color: #94a3b8; font-size: 0.85rem; font-weight: 700; padding: 0 15px; }
    .modern-table td { background: #f8fafc; padding: 15px; vertical-align: middle; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; }
    .modern-table tr td:first-child { border-left: 1px solid #f1f5f9; border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    .modern-table tr td:last-child { border-right: 1px solid #f1f5f9; border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
</style>

<div class="page-wrapper">
    <div class="test-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1 style="font-size:1.5rem; font-weight:800; margin:0;">Laboratory Test Requests</h1>
        </div>

        <?php if (empty($testRequests)): ?>
            <p style="text-align:center; padding:40px; color:#94a3b8;">You haven't requested any tests yet.</p>
        <?php else: ?>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Test Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($testRequests as $test): ?>
                        <tr>
                            <td style="font-size:0.85rem; color:#64748b;">
                                <?php echo date('d M Y, H:i', strtotime($test['requested_at'])); ?>
                            </td>
                            <td style="font-weight:700; color:#1e293b;">
                                <?php echo htmlspecialchars($test['patient_name']); ?>
                            </td>
                            <td>
                                <span style="color:#2563eb; font-weight:600;"><?php echo htmlspecialchars($test['test_name']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>