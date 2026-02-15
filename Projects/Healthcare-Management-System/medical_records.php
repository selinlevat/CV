<?php
ob_start();
session_start();
require 'includes/db.php';

// Güvenlik: Sadece Hasta Erişebilir
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId   = (int)$_SESSION['user_id'];
$patientName = $_SESSION['name'] ?? 'Patient';

// XSS Koruması için yardımcı fonksiyon
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- TIBBİ GEÇMİŞİ ÇEK ---
$sql = "
    SELECT 
        m.id,
        m.diagnosis,
        m.treatment,      /* Bizim DB sütunu */
        m.prescription,   /* Bizim DB sütunu */
        m.created_at,
        d.name AS doctor_name,
        d.hospital_name,
        d.department
    FROM medical_records m
    JOIN users d ON m.doctor_id = d.id
    WHERE m.patient_id = :pid
    ORDER BY m.created_at DESC
    LIMIT 200
";
$stmt = $conn->prepare($sql);
$stmt->execute([':pid' => $patientId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// MERKEZİ HEADER ÇAĞIRILIYOR
require 'includes/header.php';
?>

<style>
    body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }

    .page { max-width: 1100px; margin: 28px auto 60px; padding: 0 18px; }
    h1 { margin: 0 0 6px; text-align: center; }
    .sub { text-align: center; color: var(--muted); margin: 0 0 18px; }

    .card {
        background: #fff;
        border: 1px solid rgba(148,163,184,0.25);
        border-radius: 18px;
        box-shadow: 0 14px 30px rgba(15,23,42,0.10);
        padding: 16px 16px;
    }

    .rec {
        border: 1px solid rgba(226,232,240,.9);
        border-radius: 14px;
        padding: 14px 14px;
        background: #fbfdff;
        margin-bottom: 12px;
    }
    .rec-head {
        display: flex; flex-wrap: wrap; align-items: center;
        gap: 10px; justify-content: space-between; margin-bottom: 8px;
    }
    .rec-title { font-weight: 900; font-size: 14px; }
    
    .pill {
        font-size: 11px; padding: 3px 10px; border-radius: 999px;
        background: rgba(37,99,235,0.10);
        border: 1px solid rgba(37,99,235,0.20);
        color: #1d4ed8; font-weight: 900;
    }
    
    .label { font-weight: 900; font-size: 12px; color: #111827; margin-top: 8px; }
    .text { font-size: 13px; color: #334155; margin-top: 3px; white-space: pre-wrap; }

    /* Reçete için özel stil (Yeşil Ton) */
    .text-rx { 
        color: #166534; background: #f0fdf4; padding: 8px; 
        border-radius: 6px; border: 1px solid #bbf7d0;
        font-family: monospace; font-weight: 600;
    }

    .empty { color: #6b7280; margin: 10px 0 0; text-align: center; }

    @media (max-width:760px){
        .rec-head { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="page">
    <h1>My Medical Records</h1>

    <div class="card">
        <?php if (empty($records)): ?>
            <p class="empty">You do not have any medical records yet.</p>
        <?php else: ?>
            <?php foreach ($records as $r): ?>
                <?php
                    // Verileri Hazırla
                    $date = date('d M Y', strtotime($r['created_at']));
                    $doc  = $r['doctor_name'] ?? '—';
                    $hosp = $r['hospital_name'] ?? '';
                    $dept = $r['department'] ?? '';
                    
                    $diag  = trim((string)($r['diagnosis'] ?? ''));
                    $treat = trim((string)($r['treatment'] ?? ''));     
                    $presc = trim((string)($r['prescription'] ?? ''));  
                ?>
                <div class="rec">
                    <div class="rec-head">
                        <div class="rec-title">
                            <?php echo h($date); ?> — <?php echo h($doc); ?> 
                            <span style="font-weight:400; color:#64748b; font-size:12px;">
                                (<?php echo h($hosp); ?> - <?php echo h($dept); ?>)
                            </span>
                        </div>
                        <div class="pill">Medical Record</div>
                    </div>

                    <?php if ($diag !== ''): ?>
                        <div class="label" style="color:#000000;">DIAGNOSIS</div>
                        <div class="text" style="font-weight:bold; font-size:14px;"><?php echo h($diag); ?></div>
                    <?php endif; ?>

                    <?php if ($treat !== ''): ?>
                        <div class="label">TREATMENT PLAN</div>
                        <div class="text"><?php echo nl2br(h($treat)); ?></div>
                    <?php endif; ?>

                    <?php if ($presc !== ''): ?>
                        <div class="label" style="color:#000000;">PRESCRIPTION</div>
                        <div class="text text-rx"><?php echo nl2br(h($presc)); ?></div>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>