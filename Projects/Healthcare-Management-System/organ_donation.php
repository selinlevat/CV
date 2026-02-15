<?php
// organ_donation.php - ORGAN BAĞIŞI TERCİHİ
ob_start();
session_start();

// 1. DB Bağlantısı
if (file_exists('includes/db.php')) require 'includes/db.php';
else require 'db.php';

// 2. Güvenlik
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId   = $_SESSION['user_id'];
$patientName = $_SESSION['name'] ?? 'Patient';
$message = null;

// 3. Tercihi Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $choice = isset($_POST['organ_donor']) ? (int)$_POST['organ_donor'] : 0;
    
    $sql = "UPDATE users SET organ_donor = :choice WHERE id = :id";
    $stmt = $conn->prepare($sql);
    if ($stmt->execute([':choice' => $choice, ':id' => $patientId])) {
        $message = "Your preference has been saved successfully.";
    } else {
        $message = "Error saving preference.";
    }
}

// 4. Mevcut Tercihi Çek
$stmtPref = $conn->prepare("SELECT organ_donor FROM users WHERE id = :id");
$stmtPref->execute([':id' => $patientId]);
$current = (int)$stmtPref->fetchColumn();

// --- MERKEZİ HEADER ÇAĞIRMA ---
// Bu satır tüm navigasyonu ve genel stilleri getirir.
require 'includes/header.php';
?>

<style>
    /* SADECE BU SAYFAYA ÖZEL STİLLER */
    body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; }

    .page-container {
        max-width: 800px; /* Biraz daha dar ve odaklı olsun */
        margin: 40px auto;
        padding: 0 20px;
    }

    .donation-card {
        background: white;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
        border: 1px solid #e2e8f0;
        text-align: center; /* Ortalanmış tasarım */
    }

    .icon-heart {
        font-size: 3rem; 
        color: #ef4444; 
        margin-bottom: 20px;
        display: inline-block;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    h1 { font-size: 2rem; color: #1e293b; margin-bottom: 10px; font-weight: 800; }
    .lead { color: #64748b; font-size: 1.1rem; margin-bottom: 30px; line-height: 1.6; }

    /* Radyo Butonları İçin Modern Kartlar */
    .radio-group {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-bottom: 30px;
        text-align: left;
    }

    .radio-option {
        display: flex;
        align-items: center;
        padding: 20px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .radio-option:hover { border-color: #cbd5e1; background: #f8fafc; }
    
    /* Seçili Radyo Butonu Stili */
    .radio-option.selected {
        border-color: #2563eb;
        background: #eff6ff;
    }

    .radio-option input { margin-right: 15px; transform: scale(1.5); accent-color: #2563eb; }
    .radio-text { font-weight: 600; font-size: 1.1rem; color: #334155; }

    .btn-save {
        background: #2563eb; color: white; border: none;
        padding: 15px 40px; font-size: 1.1rem; font-weight: 700;
        border-radius: 50px; cursor: pointer; transition: background 0.2s;
        width: 100%;
    }
    .btn-save:hover { background: #1d4ed8; }

    .alert-success {
        background: #dcfce7; color: #166534; padding: 15px;
        border-radius: 10px; margin-bottom: 20px; font-weight: 600;
    }
</style>

<div class="page-container">
    <div class="donation-card">
        

        <h1>Organ Donation Preference</h1>
        <p class="lead">
            Your choice can save lives. Please select your preference below. 
            You can change this setting at any time.
        </p>

        <?php if ($message): ?>
            <div class="alert-success">✅ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="radio-group">
                <label class="radio-option <?php echo $current === 1 ? 'selected' : ''; ?>" onclick="selectOption(this)">
                    <input type="radio" name="organ_donor" value="1" <?php echo $current === 1 ? 'checked' : ''; ?>>
                    <span class="radio-text">Yes, I want to be an organ donor.</span>
                </label>

                <label class="radio-option <?php echo $current === 0 ? 'selected' : ''; ?>" onclick="selectOption(this)">
                    <input type="radio" name="organ_donor" value="0" <?php echo $current === 0 ? 'checked' : ''; ?>>
                    <span class="radio-text">No, I do not want to be an organ donor.</span>
                </label>
            </div>

            <button type="submit" class="btn-save">Save My Preference</button>
        </form>
    </div>
</div>

<script>
    // Tıklanan kutuyu seçili gibi göstermek için basit JS
    function selectOption(label) {
        // Tüm seçeneklerden 'selected' sınıfını kaldır
        document.querySelectorAll('.radio-option').forEach(el => el.classList.remove('selected'));
        // Tıklanan etikete ekle
        label.classList.add('selected');
    }
</script>

<?php require 'includes/footer.php'; ?>