<?php
ob_start();
session_start();
require 'includes/db.php';

// Güvenlik: Sadece Hasta
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId   = (int)$_SESSION['user_id'];
$patientName = $_SESSION['name'] ?? 'Patient';

// XSS Koruması
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- FİLTRELEME MANTIĞI ---
$yearFrom = isset($_GET['year_from']) ? (int)$_GET['year_from'] : (int)date('Y')-1;
$yearTo   = isset($_GET['year_to']) ? (int)$_GET['year_to'] : (int)date('Y');
$q        = trim($_GET['q'] ?? '');

$minYear = 2000;
$maxYear = (int)date('Y') + 1;
if ($yearFrom < $minYear) $yearFrom = $minYear;
if ($yearTo > $maxYear) $yearTo = $maxYear;
if ($yearTo < $yearFrom) $yearTo = $yearFrom;

// SQL Hazırla
$params = [':pid' => $patientId];

$sql = "
    SELECT r.id,
           r.issue_date,
           r.start_date,
           r.end_date,
           r.leave_days,
           r.diagnosis,
           r.department,
           d.name AS doctor_name
    FROM reports r
    LEFT JOIN users d ON d.id = r.doctor_id
    WHERE r.patient_id = :pid
      AND YEAR(r.issue_date) BETWEEN :yfrom AND :yto
";
$params[':yfrom'] = $yearFrom;
$params[':yto']   = $yearTo;

// Arama Filtresi
if ($q !== '') {
    $sql .= " AND (
        r.diagnosis LIKE :q1
        OR r.department LIKE :q2
        OR d.name LIKE :q3
    )";
    $params[':q1'] = "%{$q}%";
    $params[':q2'] = "%{$q}%";
    $params[':q3'] = "%{$q}%";
}

$sql .= " ORDER BY r.issue_date DESC, r.id DESC LIMIT 200";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// MERKEZİ HEADER ÇAĞIRILIYOR
require 'includes/header.php';
?>

<style>
    body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }

    .page { max-width: 1400px; margin: 26px auto 60px; padding: 0 18px; }
    
    .card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 14px 30px rgba(15,23,42,0.12);
        border: 1px solid rgba(148,163,184,0.25);
        padding: 22px 22px 14px;
    }

    .top {
        display: flex; align-items: flex-start; justify-content: space-between;
        gap: 14px; flex-wrap: wrap; margin-bottom: 14px;
    }
    
    .title { margin: 0; font-size: 24px; font-weight: 900; letter-spacing: .02em; }
    .subtitle { margin: 6px 0 0; font-size: 13px; color: #6b7280; }

    /* FİLTRELER */
    .filters { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; justify-content: flex-end; }
    
    .chip {
        display: flex; align-items: center; gap: 8px;
        background: #f9fafb; border: 1px solid #e5e7eb;
        border-radius: 999px; padding: 8px 12px;
    }
    .chip label { font-size: 12px; font-weight: 800; color: #374151; margin: 0; }
    .chip select, .chip input {
        border: none; background: transparent; outline: none;
        font-size: 14px; padding: 0 4px;
    }

    .btn-search {
        display: inline-flex; align-items: center; gap: 8px;
        border: none; cursor: pointer; border-radius: 999px;
        padding: 10px 14px; background: #4b5563; color: #fff; font-weight: 900;
    }

    .searchbox {
        display: flex; align-items: center; gap: 8px;
        background: #f9fafb; border: 1px solid #e5e7eb;
        border-radius: 999px; padding: 8px 12px;
    }
    .searchbox input { border: none; outline: none; background: transparent; font-size: 14px; width: 240px; }

    /* TABLO */
    table { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 13px; }
    thead { background: #f3f4f6; }
    th, td {
        padding: 12px 12px; text-align: left;
        border-bottom: 1px solid #e5e7eb; vertical-align: top;
    }
    tbody tr:hover { background: #fafafa; }

    .empty {
        margin-top: 14px; padding: 16px; border-radius: 14px;
        background: #f9fafb; border: 1px dashed #d1d5db; color: #6b7280;
    }

    @media (max-width: 700px){
        .searchbox input { width: 160px; }
        th, td { padding: 10px 8px; }
    }
</style>

<div class="page">
    <div class="card">
        <div class="top">
            <div>
                <h1 class="title">My Reports</h1>
                <p class="subtitle">Rest reports issued by your doctors.</p>
            </div>

            <form class="filters" method="get">
                <div class="chip">
                    <label for="year_from">Start Year</label>
                    <select id="year_from" name="year_from">
                        <?php for($y=$maxYear; $y>=$minYear; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($y===$yearFrom)?'selected':''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="chip">
                    <label for="year_to">End Year</label>
                    <select id="year_to" name="year_to">
                        <?php for($y=$maxYear; $y>=$minYear; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($y===$yearTo)?'selected':''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <button class="btn-search" type="submit">🔍 Search</button>

                <div class="searchbox">
                    <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="Search (diagnosis, doctor)">
                    <button class="btn-search" type="submit" style="background:#111827;">🔎</button>
                </div>
            </form>
        </div>

        <?php if (empty($rows)): ?>
            <div class="empty">No reports found for the selected filters.</div>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th style="width:12%;">Issue Date</th>
                    <th style="width:12%;">Leave Days</th>
                    <th style="width:12%;">Start Date</th>
                    <th style="width:12%;">End Date</th>
                    <th>Diagnosis</th>
                    <th style="width:15%;">Doctor</th>
                    <th style="width:15%;">Department</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo h($r['issue_date']); ?></td>
                        <td><strong><?php echo h($r['leave_days']); ?> Days</strong></td>
                        <td><?php echo h($r['start_date']); ?></td>
                        <td><?php echo h($r['end_date']); ?></td>
                        <td><?php echo h($r['diagnosis'] ?? '—'); ?></td>
                        <td><?php echo h($r['doctor_name'] ?? '—'); ?></td>
                        <td><?php echo h($r['department'] ?? '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>