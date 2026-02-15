<?php
session_start();
require 'includes/db.php';

/**
 * tests.php (Patient - My Tests)
 * - English UI
 * - Connected to Central Header
 */

// Patient only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId   = (int)$_SESSION['user_id'];
$patientName = $_SESSION['name'] ?? 'Patient';

// Helper: Map status
function mapStatusLabel(string $dbStatus): string {
    $s = mb_strtolower(trim($dbStatus));
    if ($s === 'istek' || $s === 'requested') return 'Requested';
    if ($s === 'tamamlandi' || $s === 'completed' || $s === 'done') return 'Completed';
    if ($s === 'iptal' || $s === 'cancelled' || $s === 'canceled') return 'Cancelled';
    return ucfirst($s);
}

function mapStatusClass(string $dbStatus): string {
    $s = mb_strtolower(trim($dbStatus));
    if ($s === 'tamamlandi' || $s === 'completed' || $s === 'done') return 'status-completed';
    if ($s === 'iptal' || $s === 'cancelled' || $s === 'canceled') return 'status-cancelled';
    return 'status-pending';
}

// Fetch tests
$sql = "
    SELECT
        t.id,
        t.test_name,
        t.status,
        t.requested_at,
        d.name AS doctor_name
    FROM tests t
    LEFT JOIN users d ON t.doctor_id = d.id
    WHERE t.patient_id = :pid
    ORDER BY t.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute([':pid' => $patientId]);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// MERKEZİ HEADER ÇAĞIRILIYOR
require 'includes/header.php';
?>

<style>
    body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; }

    .page-container {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }

    .tests-card {
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 14px 30px rgba(15,23,42,0.12);
        border: 1px solid #e2e8f0;
        padding: 30px;
    }

    .tests-header { margin-bottom: 20px; }
    .tests-title {
        font-size: 1.8rem; margin: 0 0 5px; 
        display: flex; align-items: center; gap: 10px; 
        color: #1e293b; font-weight: 800;
    }
    .tests-sub { margin: 0; font-size: 0.95rem; color: #64748b; }

    .tests-table {
        width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.95rem;
    }
    .tests-table thead { background: #f8fafc; }
    .tests-table th, .tests-table td {
        padding: 15px; text-align: left; border-bottom: 1px solid #e2e8f0;
    }
    .tests-table th { font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase; }
    .tests-table tbody tr:hover { background: #f1f5f9; }

    .status-pill {
        display: inline-flex; align-items: center; padding: 4px 12px;
        border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
    }
    .status-pending { background: #fef3c7; color: #b45309; }
    .status-completed { background: #dcfce7; color: #166534; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }

    .empty-state {
        text-align: center; padding: 40px; 
        background: #f8fafc; border-radius: 12px; border: 2px dashed #e2e8f0; color: #94a3b8;
    }
</style>

<div class="page-container">
    <div class="tests-card">
        <div class="tests-header">
            <h1 class="tests-title">
                <span style="background:#e0e7ff; color:#4338ca; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.5rem;">🧪</span>
                My Tests
            </h1>
            <p class="tests-sub">
                Here you can see all tests ordered for you and their latest status.
            </p>
        </div>

        <?php if (count($tests) === 0): ?>
            <div class="empty-state">
                You do not have any recorded tests yet.
            </div>
        <?php else: ?>
            <table class="tests-table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Test Name</th>
                    <th>Doctor</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tests as $t): ?>
                    <?php
                        $displayName = (string)($t['test_name'] ?? '');
                        $rawStatus   = (string)($t['status'] ?? 'istek');
                        $statusLabel = mapStatusLabel($rawStatus);
                        $statusClass = mapStatusClass($rawStatus);
                        
                        $dateStr = '—';
                        if (!empty($t['requested_at'])) {
                            $dateStr = date('d M Y H:i', strtotime($t['requested_at']));
                        }
                        $doctorName = $t['doctor_name'] ?? '—';
                    ?>
                    <tr>
                        <td style="font-weight:600; color:#334155;"><?php echo htmlspecialchars($dateStr); ?></td>
                        <td><?php echo htmlspecialchars($displayName); ?></td>
                        <td><?php echo htmlspecialchars($doctorName); ?></td>
                        <td>
                            <span class="status-pill <?php echo htmlspecialchars($statusClass); ?>">
                                <?php echo htmlspecialchars($statusLabel); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>