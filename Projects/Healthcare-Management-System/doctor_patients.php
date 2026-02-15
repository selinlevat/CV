<?php
ob_start();
session_start();
require 'includes/db.php';

// Güvenlik: Sadece Doktor Erişebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit;
}

$doctorId = (int)$_SESSION['user_id'];
$doctorName = $_SESSION['name'];

// XSS Koruması
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- TÜM HASTA ARŞİVİNİ ÇEK ---
// Bu sorgu, doktorun medical_records tablosuna girdiği tüm kayıtları hasta isimleriyle birlikte getirir
$sql = "
    SELECT 
        m.id,
        m.patient_id,
        m.diagnosis,
        m.treatment,
        m.prescription,
        m.created_at,
        u.name AS patient_name,
        u.blood_group,
        u.email
    FROM medical_records m
    JOIN users u ON m.patient_id = u.id
    WHERE m.doctor_id = :did
    ORDER BY m.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute([':did' => $doctorId]);
$archive = $stmt->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>

<style>
    body { background-color: #f3f4f6; font-family: 'Inter', sans-serif; color: #1e293b; }
    .page-wrapper { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
    
    .archive-card {
        background: white;
        border-radius: 24px;
        padding: 30px;
        box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05);
    }

    .archive-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 20px;
    }

    .archive-title { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0; }
    
    /* Modern Tablo Tasarımı */
    .modern-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
    .modern-table th { 
        text-align: left; color: #94a3b8; font-size: 0.85rem; 
        font-weight: 700; text-transform: uppercase; padding: 0 15px; 
    }
    .modern-table td { 
        background: #f8fafc; padding: 15px; vertical-align: middle;
        border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;
    }
    .modern-table tr td:first-child { border-left: 1px solid #f1f5f9; border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    .modern-table tr td:last-child { border-right: 1px solid #f1f5f9; border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
    .modern-table tr:hover td { background: #f1f5f9; }

    .btn-view {
        background: #2563eb; color: white; padding: 8px 16px; 
        border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.8rem;
    }
    
    .badge-diag {
        background: #e0e7ff; color: #3730a3; padding: 4px 10px; 
        border-radius: 6px; font-size: 0.75rem; font-weight: 700;
    }
</style>

<div class="page-wrapper">
    <div class="archive-card">
        <div class="archive-header">
            <div>
                <h1 class="archive-title">Patient Archive</h1>
                
            </div>
           
        </div>

        <?php if (empty($archive)): ?>
            <div style="text-align: center; padding: 50px; color: #94a3b8;">
                <p>You haven't created any medical records yet.</p>
            </div>
        <?php else: ?>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient Name</th>
                        <th>Diagnosis</th>
                        <th>Prescription</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archive as $row): ?>
                        <tr>
                            <td style="font-weight: 600; color: #64748b;">
                                <?php echo date('d M Y', strtotime($row['created_at'])); ?>
                            </td>
                            <td>
                                <div style="font-weight: 800; color: #1e293b;"><?php echo h($row['patient_name']); ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8;"><?php echo h($row['email']); ?></div>
                            </td>
                            <td>
                                <span class="badge-diag"><?php echo h($row['diagnosis']); ?></span>
                            </td>
                            <td style="font-size: 0.8rem; color: #475569; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?php echo h($row['prescription']); ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="doctor_patient_view.php?patient_id=<?php echo $row['patient_id']; ?>" class="btn-view">Open Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>