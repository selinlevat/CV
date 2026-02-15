<?php
// register.php - Logic
ob_start();
session_start();
require 'includes/db.php';

// Zaten giriş yapmışsa yönlendir
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$errors = [];
$name = ''; $email = ''; $phone = ''; $address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $pass2   = $_POST['password_confirm'] ?? '';

    if ($name === '') $errors[] = "Full name is required.";
    if ($email === '') $errors[] = "E-mail is required.";
    if ($pass === '') $errors[] = "Password is required.";
    if ($pass !== $pass2) $errors[] = "Passwords do not match.";

    // E-posta kontrolü
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = "This e-mail is already registered.";
        }
    }

    // Kayıt İşlemi
    if (empty($errors)) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (name, email, password_hash, phone, address, role, created_at) 
                VALUES (:name, :email, :pass, :phone, :addr, 'patient', NOW())";
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':name' => $name, ':email' => $email, ':pass' => $hash, 
                ':phone' => $phone, ':addr' => $address
            ]);
            
            $lastId = $conn->lastInsertId();
            $_SESSION['user_id'] = $lastId;
            $_SESSION['role'] = 'patient';
            $_SESSION['name'] = $name;
            
            header("Location: patient_dashboard.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

$pageTitle = "Register";
require 'includes/header.php';
?>

<div class="auth-wrapper wide">
    <div class="card">
        <div class="auth-header">
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Register as a new patient</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul class="list-none">
                    <?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
            </div>

            <div class="form-group">
                <label>E-mail *</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>

            <div class="form-row">
                <div>
                    <label>Phone *</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
                </div>
                <div>
                    <label>Role</label>
                    <select disabled><option>Patient</option></select>
                </div>
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address" style="min-height:80px;"><?php echo htmlspecialchars($address); ?></textarea>
            </div>

            <div class="form-row">
                <div>
                    <label>Password *</label>
                    <input type="password" name="password" required>
                </div>
                <div>
                    <label>Confirm Password *</label>
                    <input type="password" name="password_confirm" required>
                </div>
            </div>

            <button type="submit" class="btn">Sign Up</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="login.php" class="link-primary">Log In</a>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>