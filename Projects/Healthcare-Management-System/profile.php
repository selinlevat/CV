<?php
// profile.php - PROFİL VE İLETİŞİM YÖNETİMİ (Tek Dosya)
ob_start();
session_start();

// DB Bağlantısı
if (file_exists('includes/db.php')) require 'includes/db.php';
elseif (file_exists('db.php')) require 'db.php';
else die("Veritabanı dosyası bulunamadı.");

// Güvenlik
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$userId = $_SESSION['user_id'];
$msg    = "";
$error  = "";

// --- İŞLEM 1: KİŞİ SİLME (Delete Contact) ---
if (isset($_GET['delete_contact'])) {
    $contactId = $_GET['delete_contact'];
    $stmt = $conn->prepare("DELETE FROM emergency_contacts WHERE id = ? AND patient_id = ?");
    $stmt->execute([$contactId, $userId]);
    header("Location: profile.php?msg=contact_deleted"); exit;
}

// --- İŞLEM 2: KİŞİ EKLEME (Add Contact - MAX 3 KURALI) ---
if (isset($_POST['add_contact'])) {
    // Önce mevcut sayıyı kontrol et
    $count = $conn->query("SELECT COUNT(*) FROM emergency_contacts WHERE patient_id=$userId")->fetchColumn();
    
    if ($count >= 3) {
        $error = "You can add maximum 3 emergency contacts.";
    } else {
        $cName  = trim($_POST['contact_name']);
        $cPhone = trim($_POST['contact_phone']);
        $cRel   = trim($_POST['relation']);
        
        if ($cName && $cPhone) {
            $stmt = $conn->prepare("INSERT INTO emergency_contacts (patient_id, contact_name, phone, relation) VALUES (?,?,?,?)");
            $stmt->execute([$userId, $cName, $cPhone, $cRel]);
            header("Location: profile.php?msg=contact_added"); exit;
        } else {
            $error = "Name and Phone are required for emergency contact.";
        }
    }
}

// --- İŞLEM 3: PROFİL GÜNCELLEME ---
if (isset($_POST['update_profile'])) {
    $name    = trim($_POST['name']);
    $phone   = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $height  = $_POST['height'];
    $weight  = $_POST['weight'];
    $blood   = $_POST['blood_group'];
    
    $newPass = $_POST['new_password'];
    $cnfPass = $_POST['confirm_password'];

    // Şifre değişikliği var mı?
    $passSql = "";
    $params  = [$name, $phone, $address, $height, $weight, $blood];

    if (!empty($newPass)) {
        if ($newPass === $cnfPass && strlen($newPass) >= 6) {
            $passSql = ", password_hash = ?";
            $params[] = password_hash($newPass, PASSWORD_DEFAULT);
        } else {
            $error = "Passwords do not match or too short.";
        }
    }

    if (empty($error)) {
        $params[] = $userId; // WHERE id = ? için
        $sql = "UPDATE users SET name=?, phone=?, address=?, height=?, weight=?, blood_group=? $passSql WHERE id=?";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute($params)) {
            $_SESSION['name'] = $name; // Session adını da güncelle
            $msg = "Profile updated successfully.";
        } else {
            $error = "Update failed.";
        }
    }
}

// --- VERİLERİ ÇEK ---
// Kullanıcı Bilgileri
$user = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch(PDO::FETCH_ASSOC);

// Acil Durum Kişileri
$contacts = $conn->query("SELECT * FROM emergency_contacts WHERE patient_id=$userId")->fetchAll(PDO::FETCH_ASSOC);
$contactCount = count($contacts); // Mevcut sayı

$pageTitle = "My Profile";
if(file_exists('includes/header.php')) require 'includes/header.php';
?>

<div class="dashboard-grid">
    
    <div class="card">
        <h2 class="section-title">👤 Edit Profile</h2>
        
        <?php if ($msg || isset($_GET['msg'])): ?>
            <div style="background:#dcfce7; color:#166534; padding:10px; border-radius:5px; margin-bottom:15px;">
                ✅ <?php echo $msg ?: "Action completed successfully."; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:10px; border-radius:5px; margin-bottom:15px;">
                ⚠️ <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Email (Read Only)</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background:#eee;">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="2"><?php echo htmlspecialchars($user['address']); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Height (cm)</label>
                    <input type="number" name="height" value="<?php echo htmlspecialchars($user['height']); ?>" 
                           min="50" max="300"
                           oninvalid="this.setCustomValidity('Please enter a height between 50 and 300 cm.')"
                           oninput="this.setCustomValidity('')">
                </div>
                <div class="form-group">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight" value="<?php echo htmlspecialchars($user['weight']); ?>" 
                           min="20" max="300"
                           oninvalid="this.setCustomValidity('Please enter a weight between 20 and 300 kg.')"
                           oninput="this.setCustomValidity('')">
                </div>
                <div class="form-group">
                    <label>Blood Group</label>
                    <select name="blood_group">
                        <option value="">Select</option>
                        <?php 
                        $bloods = ['A Rh+', 'A Rh-', 'B Rh+', 'B Rh-', 'AB Rh+', 'AB Rh-', '0 Rh+', '0 Rh-'];
                        foreach ($bloods as $b) {
                            $sel = ($user['blood_group'] == $b) ? 'selected' : '';
                            echo "<option value='$b' $sel>$b</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
            <h3 style="font-size:16px; margin-bottom:10px;">Change Password <small style="color:gray; font-weight:normal;">(Leave blank to keep current)</small></h3>

            <div class="form-row">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password">
                </div>
            </div>

            <button type="submit" name="update_profile" class="btn w-100">Save Changes</button>
        </form>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2 class="section-title" style="margin:0;">📞 Emergency Contacts</h2>
            <span style="font-size:12px; color:gray;">(<?php echo $contactCount; ?>/3)</span>
        </div>
        
        <?php if (empty($contacts)): ?>
            <p style="color:gray; font-style:italic; margin-top:10px;">No contacts added yet.</p>
        <?php else: ?>
            <ul class="list-none" style="margin-top:15px;">
                <?php foreach ($contacts as $c): ?>
                    <li class="list-item" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:5px;">
                        <div>
                            <strong><?php echo htmlspecialchars($c['contact_name']); ?></strong>
                            <div style="font-size:12px; color:gray;">
                                <?php echo htmlspecialchars($c['relation']); ?> • <?php echo htmlspecialchars($c['phone']); ?>
                            </div>
                        </div>
                        <a href="profile.php?delete_contact=<?php echo $c['id']; ?>" 
                           onclick="return confirm('Are you sure?')"
                           style="color:red; text-decoration:none; font-size:18px;">&times;</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
        
        <?php if ($contactCount < 3): ?>
            <h3>Add New Contact</h3>
            <form method="post">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="contact_name" required placeholder="e.g. Ali Yilmaz">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="contact_phone" required placeholder="e.g. 0555...">
                </div>
                <div class="form-group">
                    <label>Relation</label>
                    <select name="relation">
                        <option value="Parent">Parent</option>
                        <option value="Spouse">Spouse</option>
                        <option value="Sibling">Sibling</option>
                        <option value="Friend">Friend</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <button type="submit" name="add_contact" class="btn w-100" style="background:#6366f1;">Add Contact</button>
            </form>
        <?php else: ?>
            <div style="background:#fff7ed; color:#9a3412; padding:15px; border-radius:10px; border:1px solid #ffedd5; text-align:center;">
                <strong>Limit Reached</strong><br>
                You have reached the maximum limit of 3 emergency contacts. Please delete one to add a new contact.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if(file_exists('includes/footer.php')) require 'includes/footer.php'; ?>