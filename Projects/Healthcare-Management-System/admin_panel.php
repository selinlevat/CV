<?php

ob_start();

session_start();

require 'includes/db.php';



// Güvenlik: Sadece Admin

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {

    header("Location: login.php"); exit;

}



$adminName = $_SESSION['name'];

$tab = $_GET['tab'] ?? 'dashboard';

$msg = $_GET['msg'] ?? '';

$error = '';



// --- İŞLEMLER ---



// 1. Kullanıcı Silme (Doktor veya Hasta)

if (isset($_GET['delete_user'])) {

    $delId = $_GET['delete_user'];

    if ($delId != $_SESSION['user_id']) {

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");

        $stmt->execute([$delId]);

        header("Location: admin_panel.php?tab=$tab&msg=deleted"); exit;

    }

}



// 2. Yeni Doktor Ekleme

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {

    $name  = trim($_POST['name']);

    $email = trim($_POST['email']);

    $pass  = $_POST['password'];

    $hosp  = trim($_POST['hospital']);

    $dept  = trim($_POST['department']);

    $city  = trim($_POST['city']);



    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");

    $check->execute([$email]);

    if ($check->rowCount() > 0) {

        $error = "Email already exists!";

    } else {

        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (name, email, password_hash, role, hospital_name, department, city, created_at) VALUES (?, ?, ?, 'doctor', ?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);

        $stmt->execute([$name, $email, $hash, $hosp, $dept, $city]);

        header("Location: admin_panel.php?tab=doctors&msg=added"); exit;

    }

}



// --- İSTATİSTİKLER ---

$stats = [

    'doctors'  => $conn->query("SELECT COUNT(*) FROM users WHERE role='doctor'")->fetchColumn(),

    'patients' => $conn->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn(),

    'appts'    => $conn->query("SELECT COUNT(*) FROM appointments")->fetchColumn()

];



require 'includes/header.php';

?>



<style>

    body { background-color: #f3f4f6; font-family: 'Inter', sans-serif; margin: 0; }

    .layout-wrapper { display: flex; max-width: 1400px; margin: 40px auto; gap: 30px; padding: 0 20px; }

    .sidebar { width: 260px; flex-shrink: 0; display: flex; flex-direction: column; gap: 12px; }

    .sidebar-btn { display: block; padding: 16px 24px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 0.95rem; color: white; transition: 0.2s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

    .sidebar-btn:hover { transform: translateX(5px); }

    .btn-dark { background-color: #1e293b; }

    .btn-blue { background-color: #2563eb; }

    .btn-purple { background-color: #7c3aed; }

    .btn-active { border-left: 5px solid #f59e0b; }

    .main-content { flex-grow: 1; display: flex; flex-direction: column; gap: 25px; }

    .card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05); }

    .page-title { font-size: 1.8rem; font-weight: 800; color: #0f172a; margin: 0 0 20px 0; }

    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }

    .stat-box { background: white; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; text-align: center; }

    .stat-num { font-size: 2.2rem; font-weight: 800; color: #2563eb; display: block; }

    .stat-label { font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; }

    .admin-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }

    .admin-table th { text-align: left; color: #64748b; padding: 10px 15px; font-size: 0.8rem; }

    .admin-table td { background: #f8fafc; padding: 12px 15px; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; }

    .form-control { padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; width: 100%; box-sizing: border-box; }

    .btn-submit { background: #2563eb; color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 700; }

    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; }

    .modal-content { background: white; padding: 30px; border-radius: 16px; width: 450px; }

</style>



<div class="layout-wrapper">

    <div class="sidebar">

        <div style="padding:10px 15px;">

            <small style="color:#64748b; font-weight:700;">ADMIN PANEL</small>

            <h3 style="margin:0; color:#1e293b;"><?php echo htmlspecialchars($adminName); ?></h3>

        </div>

        <a href="?tab=dashboard" class="sidebar-btn btn-dark <?php echo $tab=='dashboard'?'btn-active':''; ?>">📊 Dashboard</a>

        <a href="?tab=doctors" class="sidebar-btn btn-blue <?php echo $tab=='doctors'?'btn-active':''; ?>">👨‍⚕️ Doctors</a>

        <a href="?tab=patients" class="sidebar-btn btn-purple <?php echo $tab=='patients'?'btn-active':''; ?>">🏥 Patients</a>

        <a href="?tab=appointments" class="sidebar-btn btn-dark <?php echo $tab=='appointments'?'btn-active':''; ?>">📅 Appointments</a>

    </div>



    <div class="main-content">

        <?php if ($msg === 'deleted'): ?>

            <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:10px; font-weight:bold; margin-bottom:20px;">🗑️ Entry removed successfully.</div>

        <?php elseif ($msg === 'added'): ?>

            <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:10px; font-weight:bold; margin-bottom:20px;">✅ Doctor added successfully.</div>

        <?php endif; ?>



        <?php if ($tab === 'dashboard'): ?>

            <div class="card">

                <h2 class="page-title">System Overview</h2>

                <div class="stats-grid">

                    <div class="stat-box"><span class="stat-num"><?php echo $stats['doctors']; ?></span><span class="stat-label">Doctors</span></div>

                    <div class="stat-box"><span class="stat-num"><?php echo $stats['patients']; ?></span><span class="stat-label">Patients</span></div>

                    <div class="stat-box"><span class="stat-num"><?php echo $stats['appts']; ?></span><span class="stat-label">Total Appts</span></div>

                </div>

            </div>



        <?php elseif ($tab === 'doctors'): ?>

            <div class="card">

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">

                    <h2 class="page-title" style="margin:0;">Manage Doctors</h2>

                    <button onclick="document.getElementById('addDoctorModal').style.display='flex'" class="btn-submit" style="background:#10b981;">+ Add Doctor</button>

                </div>

                <table class="admin-table">

                    <thead><tr><th>Name</th><th>Hospital & Dept</th><th>Actions</th></tr></thead>

                    <tbody>

                        <?php

                        $docs = $conn->query("SELECT * FROM users WHERE role='doctor' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

                        foreach($docs as $d): ?>

                        <tr>

                            <td><strong>Dr. <?php echo htmlspecialchars($d['name']); ?></strong><br><small><?php echo htmlspecialchars($d['email']); ?></small></td>

                            <td><?php echo htmlspecialchars($d['hospital_name']); ?> (<?php echo htmlspecialchars($d['department']); ?>)</td>

                            <td><a href="?tab=doctors&delete_user=<?php echo $d['id']; ?>" style="color:#ef4444; font-weight:700; text-decoration:none;" onclick="return confirm('Delete doctor?')">Delete</a></td>

                        </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>



        <?php elseif ($tab === 'patients'): ?>

            <div class="card">

                <h2 class="page-title">Manage Patients</h2>

                <table class="admin-table">

                    <thead><tr><th>Name</th><th>Email</th><th>Registered At</th><th>Actions</th></tr></thead>

                    <tbody>

                        <?php

                        $patients = $conn->query("SELECT * FROM users WHERE role='patient' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

                        foreach($patients as $p): ?>

                        <tr>

                            <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>

                            <td><?php echo htmlspecialchars($p['email']); ?></td>

                            <td><?php echo date('d M Y', strtotime($p['created_at'])); ?></td>

                            <td><a href="?tab=patients&delete_user=<?php echo $p['id']; ?>" style="color:#ef4444; font-weight:700; text-decoration:none;" onclick="return confirm('Delete patient?')">Delete</a></td>

                        </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>



        <?php elseif ($tab === 'appointments'): ?>

            <div class="card">

                <h2 class="page-title">System Appointment Logs</h2>

                <table class="admin-table">

                    <thead><tr><th>Date & Time</th><th>Doctor</th><th>Patient</th><th>Status</th></tr></thead>

                    <tbody>

                        <?php

                        $allAppts = $conn->query("SELECT a.*, d.name as d_name, p.name as p_name FROM appointments a JOIN users d ON a.doctor_id=d.id JOIN users p ON a.patient_id=p.id ORDER BY a.id DESC LIMIT 30")->fetchAll();

                        foreach($allAppts as $app): ?>

                        <tr>

                            <td><?php echo $app['appointment_date']; ?> <small><?php echo substr($app['appointment_time'],0,5); ?></small></td>

                            <td>Dr. <?php echo htmlspecialchars($app['d_name']); ?></td>

                            <td><?php echo htmlspecialchars($app['p_name']); ?></td>

                            <td><span style="font-size:10px; font-weight:800;"><?php echo strtoupper($app['status']); ?></span></td>

                        </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>



    </div>

</div>



<div id="addDoctorModal" class="modal">

    <div class="modal-content">

        <span style="float:right; cursor:pointer; font-size:24px;" onclick="document.getElementById('addDoctorModal').style.display='none'">&times;</span>

        <h2 style="margin-top:0;">New Doctor Account</h2>

        <form method="post">

            <input type="hidden" name="add_doctor" value="1">

            <input type="text" name="name" class="form-control" placeholder="Full Name" required style="margin-bottom:10px;">

            <input type="email" name="email" class="form-control" placeholder="Email Address" required style="margin-bottom:10px;">

            <input type="password" name="password" class="form-control" placeholder="Password" required style="margin-bottom:10px;">

            <input type="text" name="city" class="form-control" placeholder="City" required style="margin-bottom:10px;">

            <input type="text" name="hospital" class="form-control" placeholder="Hospital Name" required style="margin-bottom:10px;">

            <select name="department" class="form-control" required style="margin-bottom:15px;">

                <option value="Cardiology">Cardiology</option>

                <option value="Neurology">Neurology</option>

                <option value="Dermatology">Dermatology</option>

                <option value="Pediatrics">Pediatrics</option>

                <option value="Orthopedics">Orthopedics</option>

                <option value="General Surgery">General Surgery</option>

            </select>

            <button type="submit" class="btn-submit" style="width:100%;">Create Account</button>

        </form>

    </div>

</div>



<?php require 'includes/footer.php'; ?>