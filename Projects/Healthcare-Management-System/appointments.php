<?php
ob_start();
session_start();
require 'includes/db.php'; // Database connection

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];
// Default action is 'book' now, since this page is primarily for booking
$action = $_GET['action'] ?? 'book'; 
$msg    = $_GET['msg'] ?? '';

// --- DOCTOR: UPDATE STATUS ---
if ($role === 'doctor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $apptId = $_POST['appointment_id'] ?? 0;
    $status = $_POST['status'] ?? ''; 
    if ($apptId && in_array($status, ['approved', 'cancelled'])) {
        $stmt = $conn->prepare("UPDATE appointments SET status = :st WHERE id = :id AND doctor_id = :did");
        $stmt->execute([':st' => $status, ':id' => $apptId, ':did' => $userId]);
        header("Location: appointments.php?msg=updated"); exit;
    }
}

// --- PATIENT: BOOK APPOINTMENT ---
$docs = [];
$bookedAppointments = [];

if ($role === 'patient') {
    
    // 1. Fetch All Doctors
    $sqlDocs = "SELECT id, name, city, district, hospital_name, department FROM users WHERE role='doctor' AND city IS NOT NULL";
    $docs = $conn->query($sqlDocs)->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Booked Slots (For JS disabling)
    $sqlBooked = "SELECT doctor_id, appointment_date, TIME_FORMAT(appointment_time, '%H:%i') as appt_time 
                  FROM appointments 
                  WHERE status != 'cancelled' AND appointment_date >= CURDATE()";
    $bookedAppointments = $conn->query($sqlBooked)->fetchAll(PDO::FETCH_ASSOC);

    // Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $docId = $_POST['doctor_id'];
        $date  = $_POST['date'];
        $time  = $_POST['time'];
        
        // Time Validation
        $valid_times = [];
        for ($h = 9; $h <= 17; $h++) {
            $valid_times[] = sprintf("%02d:00", $h);
            if ($h < 17) $valid_times[] = sprintf("%02d:30", $h);
        }

        if (!in_array($time, $valid_times)) {
            $error = "Invalid time selected.";
        } else {
            // Get Doctor Info
            $docInfo = $conn->prepare("SELECT hospital_name, department, city, district FROM users WHERE id = ?");
            $docInfo->execute([$docId]);
            $info = $docInfo->fetch(PDO::FETCH_ASSOC);

            if ($info) {
                // Final Double Check (Server Side)
                $check = $conn->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status!='cancelled'");
                $check->execute([$docId, $date, $time]);
                
                if ($check->fetch()) {
                    $error = "Sorry, this time slot is already booked. Please choose another time.";
                } else {
                    // Save Appointment
                    $sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, hospital_name, department, city, district) VALUES (?,?,?,?,'pending', ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $userId, $docId, $date, $time, 
                        $info['hospital_name'], $info['department'],
                        $info['city'], $info['district']
                    ]);
                    header("Location: appointments.php?msg=booked"); exit;
                }
            } else {
                $error = "Doctor not found.";
            }
        }
    }
}

// --- LIST APPOINTMENTS (Only for Doctor View or Fallback) ---
$appointments = [];
if ($role === 'doctor') {
    // Sadece Approved olanları getirmek için WHERE şartı eklendi
    $appointments = $conn->prepare("SELECT a.*, p.name as patient_name FROM appointments a JOIN users p ON a.patient_id = p.id WHERE a.doctor_id = ? AND a.status = 'approved' ORDER BY appointment_date ASC");
    $appointments->execute([$userId]);
    $appointments = $appointments->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = "Book Appointment";
require 'includes/header.php';
?>

<div class="card" style="padding: 30px;">
    
    <?php if ($msg === 'booked'): ?>
        <div class="alert alert-success">Appointment request sent successfully!</div>
    <?php elseif ($msg === 'updated'): ?>
        <div class="alert alert-success">Appointment status updated.</div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>


    <?php if ($role === 'patient'): ?>
        <h2 class="section-title" style="margin-bottom:25px;">📅 Book New Appointment</h2>
        
        <form method="post" id="bookingForm">
            <div class="form-row">
                <div class="form-group">
                    <label>City</label>
                    <select id="citySelect" name="city" class="form-control" required>
                        <option value="">-- Select City --</option>
                        <?php 
                        $cities = array_unique(array_column($docs, 'city'));
                        sort($cities);
                        foreach ($cities as $c) {
                            if(!empty($c)) echo "<option value='".htmlspecialchars($c)."'>".htmlspecialchars($c)."</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>District</label>
                    <select id="districtSelect" name="district" class="form-control" required disabled>
                        <option value="">Select City First</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Hospital</label>
                    <select id="hospitalSelect" name="hospital" class="form-control" required disabled>
                         <option value="">Select District First</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select id="deptSelect" name="department" class="form-control" required disabled>
                        <option value="">Select Hospital First</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Doctor</label>
                <select id="doctorSelect" name="doctor_id" class="form-control" required disabled>
                    <option value="">Select Department First</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" id="dateInput" name="date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required disabled>
                </div>
                
                <div class="form-group">
                    <label>Time</label>
                    <select id="timeSelect" name="time" class="form-control" required disabled>
                        <option value="">-- Select Date First --</option>
                        <?php
                        for ($h = 9; $h <= 17; $h++) {
                            $timeStr = sprintf("%02d:00", $h);
                            echo "<option value='$timeStr'>$timeStr</option>";
                            if ($h < 17) {
                                $timeStrHalf = sprintf("%02d:30", $h);
                                echo "<option value='$timeStrHalf'>$timeStrHalf</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100" style="margin-top:20px; padding:12px; font-size:1rem;">Confirm Booking</button>
        </form>

        <script>
            const allDoctors = <?php echo json_encode($docs, JSON_UNESCAPED_UNICODE); ?>;
            const bookedApps = <?php echo json_encode($bookedAppointments); ?>;

            const citySelect = document.getElementById('citySelect');
            const districtSelect = document.getElementById('districtSelect');
            const hospitalSelect = document.getElementById('hospitalSelect');
            const deptSelect = document.getElementById('deptSelect');
            const doctorSelect = document.getElementById('doctorSelect');
            const dateInput = document.getElementById('dateInput');
            const timeSelect = document.getElementById('timeSelect');

            citySelect.addEventListener('change', function() {
                const selectedCity = this.value;
                resetDropdown(districtSelect, '-- Select District --');
                resetDropdown(hospitalSelect, 'Select District First');
                resetDropdown(deptSelect, 'Select Hospital First');
                resetDropdown(doctorSelect, 'Select Department First');
                dateInput.value = ''; dateInput.disabled = true;
                timeSelect.value = ''; timeSelect.disabled = true;

                if (selectedCity) {
                    const districts = [...new Set(allDoctors.filter(d => d.city === selectedCity).map(d => d.district))].sort();
                    fillDropdown(districtSelect, districts);
                }
            });

            districtSelect.addEventListener('change', function() {
                const selectedCity = citySelect.value;
                const selectedDistrict = this.value;
                
                resetDropdown(hospitalSelect, '-- Select Hospital --');
                resetDropdown(deptSelect, 'Select Hospital First');
                resetDropdown(doctorSelect, 'Select Department First');
                dateInput.value = ''; dateInput.disabled = true;
                timeSelect.value = ''; timeSelect.disabled = true;

                if (selectedDistrict) {
                    const hospitals = [...new Set(allDoctors
                        .filter(d => d.city === selectedCity && d.district === selectedDistrict)
                        .map(d => d.hospital_name))].sort();
                    fillDropdown(hospitalSelect, hospitals);
                }
            });

            hospitalSelect.addEventListener('change', function() {
                const selectedCity = citySelect.value;
                const selectedDistrict = districtSelect.value;
                const selectedHospital = this.value;

                resetDropdown(deptSelect, '-- Select Department --');
                resetDropdown(doctorSelect, 'Select Department First');
                dateInput.value = ''; dateInput.disabled = true;
                timeSelect.value = ''; timeSelect.disabled = true;

                if (selectedHospital) {
                    const depts = [...new Set(allDoctors
                        .filter(d => d.city === selectedCity && d.district === selectedDistrict && d.hospital_name === selectedHospital)
                        .map(d => d.department))].sort();
                    fillDropdown(deptSelect, depts);
                }
            });

            deptSelect.addEventListener('change', function() {
                const selectedCity = citySelect.value;
                const selectedDistrict = districtSelect.value;
                const selectedHospital = hospitalSelect.value;
                const selectedDept = this.value;

                doctorSelect.innerHTML = '<option value="">-- Select Doctor --</option>';
                doctorSelect.disabled = true;
                dateInput.value = ''; dateInput.disabled = true;
                timeSelect.value = ''; timeSelect.disabled = true;

                if (selectedDept) {
                    const doctors = allDoctors.filter(d => 
                        d.city === selectedCity && 
                        d.district === selectedDistrict && 
                        d.hospital_name === selectedHospital && 
                        d.department === selectedDept
                    );

                    doctors.forEach(doc => {
                        const opt = document.createElement('option');
                        opt.value = doc.id;
                        opt.textContent = doc.name;
                        doctorSelect.appendChild(opt);
                    });
                    doctorSelect.disabled = false;
                }
            });

            doctorSelect.addEventListener('change', function() {
                dateInput.value = ''; 
                timeSelect.value = ''; 
                timeSelect.disabled = true;

                if (this.value) {
                    dateInput.disabled = false; 
                }
            });

            dateInput.addEventListener('change', function() {
                const selectedDocId = parseInt(doctorSelect.value);
                const selectedDate = this.value;

                timeSelect.value = '';
                
                if (selectedDocId && selectedDate) {
                    timeSelect.disabled = false;
                    const busyTimes = bookedApps
                        .filter(b => b.doctor_id == selectedDocId && b.appointment_date === selectedDate)
                        .map(b => b.appt_time); 

                    Array.from(timeSelect.options).forEach(opt => {
                        if (opt.value === "") return;

                        if (busyTimes.includes(opt.value)) {
                            opt.disabled = true;
                            opt.textContent = opt.value + " (Full)";
                            opt.style.color = "red";
                        } else {
                            opt.disabled = false;
                            opt.textContent = opt.value;
                            opt.style.color = "black";
                        }
                    });
                } else {
                    timeSelect.disabled = true;
                }
            });

            function resetDropdown(element, defaultText) {
                element.innerHTML = '<option value="">' + defaultText + '</option>';
                element.disabled = true;
            }
            function fillDropdown(element, dataArray) {
                dataArray.forEach(item => {
                    if(item) {
                        const opt = document.createElement('option');
                        opt.value = item;
                        opt.textContent = item;
                        element.appendChild(opt);
                    }
                });
                element.disabled = false;
            }
        </script>

    <?php else: ?>
        <h2 class="section-title">Appointments</h2>
        
        <?php if (empty($appointments)): ?>
            <div class="empty-state" style="padding:20px; text-align:center; background:#f9fafb; border:1px dashed #ccc; border-radius:10px;">
                <p class="text-muted">No approved appointments found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f3f4f6; text-align:left;">
                            <th style="padding:10px;">Date</th>
                            <th style="padding:10px;">Time</th>
                            <th style="padding:10px;">Patient</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($appointments as $a): ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:10px;"><?php echo $a['appointment_date']; ?></td>
                            <td style="padding:10px;"><?php echo substr($a['appointment_time'],0,5); ?></td>
                            <td style="padding:10px; font-weight:500;"><?php echo htmlspecialchars($a['patient_name'] ?? 'Unknown'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>