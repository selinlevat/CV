<?php
// includes/header.php

// 1. Session Yönetimi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Veritabanı Bağlantısı
// Dosyanın bulunduğu klasöre (includes) göre db.php'yi çağırır.
require_once __DIR__ . '/db.php'; 

// 3. Kullanıcı Bilgilerini Alalım
$isLoggedIn = isset($_SESSION['user_id']);
$role       = $_SESSION['role'] ?? 'guest'; // patient, doctor, admin veya guest
$userName   = $_SESSION['name'] ?? 'Misafir';
$userId     = $_SESSION['user_id'] ?? 0;

// 4. Sayfa Başlığı: Sayfadan $pageTitle gelirse onu kullan, yoksa varsayılan
$title = isset($pageTitle) ? $pageTitle . ' - Healthcare System' : 'Healthcare Record System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <span style="font-size: 24px;">🏥</span> HealthBase
    </a>

    <div class="navbar-right">
        <?php if ($isLoggedIn): ?>
            
            <?php 
                // Rolüne göre dashboard linkini belirle
                $dashboardLink = "#";
                if ($role === 'patient') $dashboardLink = 'patient_dashboard.php';
                elseif ($role === 'doctor') $dashboardLink = 'doctor_dashboard.php';
                elseif ($role === 'admin') $dashboardLink = 'admin_panel.php';
            ?>

            <a href="<?php echo $dashboardLink; ?>" class="nav-btn outline">
                👤 <?php echo htmlspecialchars($userName); ?>
            </a>

            <?php if ($role !== 'admin'): ?>
                <a href="questions.php" class="nav-btn outline" style="border-color: #f59e0b; color: #b45309; background: rgba(245, 158, 11, 0.1);">
                    <?php echo ($role === 'doctor') ? 'Questions' : 'Ask Doctor'; ?>
                </a>
            <?php endif; ?>

            <a class="nav-btn" href="#" onclick="if(history.length>1){history.back();}return false;">
                ← Back
            </a>

            <a class="nav-btn danger" href="logout.php">
                Log out
            </a>

        <?php else: ?>
            <a class="nav-btn primary" href="login.php">Login</a>
            <a class="nav-btn outline" href="register.php">Register</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">