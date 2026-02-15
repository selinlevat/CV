<?php
// login.php - Logic Kısmı
ob_start();
session_start();
require 'includes/db.php';

// Zaten giriş yapmışsa yönlendir
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'patient') header("Location: patient_dashboard.php");
    elseif ($_SESSION['role'] === 'doctor') header("Location: doctor_dashboard.php");
    elseif ($_SESSION['role'] === 'admin') header("Location: admin_panel.php");
    exit;
}

$error = "";
$email_value = "";

function verify_password_flexible(string $inputPassword, string $dbPassword): bool {
    $dbPassword = trim($dbPassword);
    $looksHashed = str_starts_with($dbPassword, '$2y$') || str_starts_with($dbPassword, '$2a$') || str_starts_with($dbPassword, '$argon2');
    if ($looksHashed) {
        return password_verify($inputPassword, $dbPassword);
    }
    return hash_equals($dbPassword, $inputPassword);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $email_value = $email;

    if ($email === '' || $password === '') {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && verify_password_flexible($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            if ($user['role'] === 'patient') header("Location: patient_dashboard.php");
            elseif ($user['role'] === 'doctor') header("Location: doctor_dashboard.php");
            elseif ($user['role'] === 'admin') header("Location: admin_dashboard.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}

$pageTitle = "Login";
require 'includes/header.php'; 
?>

<div class="auth-wrapper">
    <div class="card">
        <div class="auth-header">
            <h1 class="auth-title">🏥 Healthcare</h1>
            <p class="auth-subtitle">Sign in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" 
                       placeholder="admin@example.com" 
                       value="<?php echo htmlspecialchars($email_value); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" 
                       placeholder="••••••" required>
            </div>

            <button type="submit" class="btn">Sign In</button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="register.php" class="link-primary">Sign Up</a>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>