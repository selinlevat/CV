<?php
ob_start();
session_start();
require 'includes/db.php';

// Oturum Kontrolü
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$userId = $_SESSION['user_id'];
$role   = $_SESSION['role']; 
$msg    = $_GET['msg'] ?? '';

// --- GÖRÜNÜM MODU (Sadece Hasta İçin) ---
// Varsayılan mod: 'list' (Listeleme)
$viewMode = $_GET['mode'] ?? 'list'; 

// =========================================================
//  1. HASTA İŞLEMLERİ (Soru Gönderme)
// =========================================================
if ($role === 'patient' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_question'])) {
    $docId = $_POST['doctor_id'];
    $qText = trim($_POST['question_text']);

    if (!empty($docId) && !empty($qText)) {
        $stmt = $conn->prepare("INSERT INTO questions (patient_id, doctor_id, question_text, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        $stmt->execute([$userId, $docId, $qText]);
        // Soru gönderilince listeye yönlendir
        header("Location: questions.php?mode=list&msg=sent"); exit;
    }
}

// =========================================================
//  2. DOKTOR İŞLEMLERİ (Cevap Verme)
// =========================================================
if ($role === 'doctor' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_answer'])) {
    $qId = $_POST['question_id'];
    $ans = trim($_POST['answer_text']);

    if (!empty($qId) && !empty($ans)) {
        $stmt = $conn->prepare("UPDATE questions SET answer_text = ?, status = 'answered', answered_at = NOW() WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$ans, $qId, $userId]);
        header("Location: questions.php?msg=replied"); exit;
    }
}

// =========================================================
//  3. VERİ ÇEKME
// =========================================================
$doctors = [];
$questions = [];

if ($role === 'patient') {
    // A. Hasta: Doktor Listesi (Sadece Ask modunda lazım ama dursun)
    $stmtDocs = $conn->prepare("SELECT DISTINCT u.id, u.name, u.department FROM users u JOIN appointments a ON u.id = a.doctor_id WHERE a.patient_id = ?");
    $stmtDocs->execute([$userId]);
    $doctors = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

    // B. Hasta: Soru Listesi (Sadece List modunda lazım)
    if ($viewMode === 'list') {
        $stmtQ = $conn->prepare("SELECT q.*, d.name as other_name, d.department FROM questions q JOIN users d ON q.doctor_id = d.id WHERE q.patient_id = ? ORDER BY q.created_at DESC");
        $stmtQ->execute([$userId]);
        $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
    }

} elseif ($role === 'doctor') {
    // C. Doktor: Gelen Sorular
    $stmtQ = $conn->prepare("SELECT q.*, p.name as other_name FROM questions q JOIN users p ON q.patient_id = p.id WHERE q.doctor_id = ? ORDER BY q.answer_text IS NULL DESC, q.created_at DESC");
    $stmtQ->execute([$userId]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
}

require 'includes/header.php';
?>

<style>
    body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; margin: 0; }
    
    .dashboard-wrapper {
        display: flex;
        max-width: 1400px;
        margin: 40px auto;
        gap: 30px;
        padding: 0 20px;
        align-items: flex-start;
    }

    /* SOL MENÜ */
    .sidebar { width: 260px; flex-shrink: 0; display: flex; flex-direction: column; gap: 12px; }
    .sidebar-btn {
        display: flex; align-items: center; padding: 16px 24px; border-radius: 50px;
        text-decoration: none; font-weight: 700; font-size: 0.95rem; color: white;
        transition: transform 0.2s ease, box-shadow 0.2s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    .sidebar-btn:hover { transform: translateX(5px); }
    .btn-red { background-color: #dc2626; }
    .btn-blue { background-color: #2563eb; }
    .btn-dark { background-color: #1e293b; }

    /* SAĞ İÇERİK */
    .main-content { flex-grow: 1; }
    .page-title { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }

    /* KART TASARIMLARI */
    .card-box {
        background: white; border-radius: 16px; padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; border: 1px solid #e2e8f0;
    }

    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #334155; }
    .form-control { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 0.95rem; transition: 0.2s; font-family: inherit; }
    .form-control:focus { border-color: #2563eb; outline: none; }
    
    .btn-submit { 
        background: #2563eb; color: white; border: none; padding: 12px 25px; 
        border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 1rem;
        transition: background 0.2s;
    }
    .btn-submit:hover { background: #1d4ed8; }

    /* SORU LİSTESİ */
    .q-item {
        background: white; border-radius: 16px; padding: 20px;
        margin-bottom: 20px; border: 1px solid #e2e8f0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.03); border-left: 5px solid #cbd5e1;
    }
    .q-item.unanswered { border-left-color: #ef4444; }
    .q-item.answered { border-left-color: #22c55e; }

    .q-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .user-name { font-weight: 700; color: #1e293b; font-size: 1.05rem; }
    .q-date { font-size: 0.85rem; color: #64748b; }

    .q-text-box { background: #f8fafc; padding: 15px; border-radius: 10px; color: #334155; margin-bottom: 15px; }
    .a-box { background: #f0fdf4; padding: 15px; border-radius: 10px; color: #166534; border: 1px solid #bbf7d0; }
    .a-waiting { background: #fff7ed; padding: 15px; border-radius: 10px; color: #9a3412; border: 1px solid #ffedd5; font-size: 0.9rem; }

    /* Yeni Buton (Ask New Question) */
    .btn-new-q {
        text-decoration: none; background: #2563eb; color: white; 
        padding: 10px 20px; border-radius: 50px; font-weight: 700; font-size: 0.9rem;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .btn-back-q {
        text-decoration: none; background: #e2e8f0; color: #334155; 
        padding: 10px 20px; border-radius: 50px; font-weight: 700; font-size: 0.9rem;
    }
</style>

<div class="dashboard-wrapper">
    
 

    <div class="main-content">
        
        <?php if ($msg === 'sent'): ?>
            <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:10px; margin-bottom:20px; font-weight:bold;">
                ✅ Your question has been sent successfully. You can see it below.
            </div>
        <?php elseif ($msg === 'replied'): ?>
            <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:10px; margin-bottom:20px; font-weight:bold;">
                ✅ Answer sent successfully.
            </div>
        <?php endif; ?>

        <?php if ($role === 'patient' && $viewMode === 'ask'): ?>
            
        
                
                <form method="post">
                    <div class="form-group">
                        <label>Select Doctor</label>
                        <select name="doctor_id" class="form-control" required>
                            <?php if (empty($doctors)): ?>
                                <option value="">You haven't visited any doctor yet</option>
                            <?php else: ?>
                                <option value="">-- Choose a Doctor --</option>
                                <?php foreach($doctors as $d): ?>
                                    <option value="<?php echo $d['id']; ?>">
                                        Dr. <?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['department']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Your Question</label>
                        <textarea name="question_text" class="form-control" rows="5" placeholder="Type your medical question here..." required></textarea>
                    </div>

                    <button type="submit" name="send_question" class="btn-submit" <?php echo empty($doctors)?'disabled style="opacity:0.5; cursor:not-allowed;"':''; ?>>
                        Send Question
                    </button>
                </form>
            </div>

        <?php elseif ($role === 'patient' && $viewMode === 'list'): ?>

           

            <?php if (empty($questions)): ?>
                <div style="text-align:center; padding:50px; background:white; border-radius:16px; border:2px dashed #e2e8f0; color:#94a3b8;">
                    <div style="font-size:3rem; margin-bottom:10px;">📭</div>
                    You haven't asked any questions yet.
                </div>
            <?php else: ?>
                <?php foreach($questions as $q): ?>
                    <?php 
                        $isAnswered = !empty($q['answer_text']);
                        $statusClass = $isAnswered ? 'answered' : 'unanswered';
                    ?>
                    <div class="q-item <?php echo $statusClass; ?>">
                        <div class="q-header">
                            <div class="user-name">To: <?php echo htmlspecialchars($q['other_name']); ?></div>
                            <div class="q-date"><?php echo date('d M Y H:i', strtotime($q['created_at'])); ?></div>
                        </div>

                        <div class="q-text-box">
                            <strong> You asked:</strong><br>
                            <?php echo nl2br(htmlspecialchars($q['question_text'])); ?>
                        </div>

                        <?php if ($isAnswered): ?>
                            <div class="a-box">
                                <strong> Doctor's Answer:</strong><br>
                                <?php echo nl2br(htmlspecialchars($q['answer_text'])); ?>
                                <div style="margin-top:5px; font-size:0.8rem; opacity:0.8;">
                                    Replied on: <?php echo date('d M Y', strtotime($q['answered_at'])); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="a-waiting">⏳ Waiting for reply...</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php elseif ($role === 'doctor'): ?>
            
            <h1 class="page-title"> Patient Questions</h1>

            <?php if (empty($questions)): ?>
                <div style="text-align:center; padding:40px; background:white; border-radius:16px; border:2px dashed #e2e8f0; color:#94a3b8;">
                    No incoming questions.
                </div>
            <?php else: ?>
                <?php foreach($questions as $q): ?>
                    <?php 
                        $isAnswered = !empty($q['answer_text']);
                        $statusClass = $isAnswered ? 'answered' : 'unanswered';
                    ?>
                    <div class="q-item <?php echo $statusClass; ?>">
                        <div class="q-header">
                            <div class="user-name">From: <?php echo htmlspecialchars($q['other_name']); ?></div>
                            <div class="q-date"><?php echo date('d M Y H:i', strtotime($q['created_at'])); ?></div>
                        </div>

                        <div class="q-text-box">
                            <strong>Question:</strong><br>
                            <?php echo nl2br(htmlspecialchars($q['question_text'])); ?>
                        </div>

                        <?php if ($isAnswered): ?>
                            <div class="a-box">
                                <strong>Your Answer:</strong><br>
                                <?php echo nl2br(htmlspecialchars($q['answer_text'])); ?>
                            </div>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                <div class="form-group">
                                    <textarea name="answer_text" class="form-control" rows="2" placeholder="Write your answer here..." required></textarea>
                                </div>
                                <button type="submit" name="send_answer" class="btn-submit" style="padding:8px 16px; font-size:0.9rem;">Reply</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<?php require 'includes/footer.php'; ?>