<?php
/* ==========================
   1. DATABASE & POST LOGIC
   ========================== */
include_once('db_connect.php');
if (!$conn) {
    $conn = mysqli_connect("localhost", "root", "", "portal-asisstant-ai");
}

$message = ""; // Initialize message variable

/* ============================================================
   CONFLICT DETECTION FUNCTIONS
============================================================ */
function convertTimeToMinutes($time) {
    if (empty($time)) return 0;
    $parts = explode(':', $time);
    return (intval($parts[0]) * 60) + intval($parts[1]);
}

function timeOverlaps($start1, $end1, $start2, $end2) {
    $start1_min = convertTimeToMinutes($start1);
    $end1_min = convertTimeToMinutes($end1);
    $start2_min = convertTimeToMinutes($start2);
    $end2_min = convertTimeToMinutes($end2);
    return ($start1_min < $end2_min && $start2_min < $end1_min);
}

function checkTimetableConflicts($conn, $unit_code, $day_of_week, $time_from, $time_to, $venue, $lecturer, $semester, $academic_year) {
    $conflicts = [];
    
    // Check venue conflicts
    if (!empty($venue)) {
        $query = "SELECT unit_code, course_title, time_from, time_to 
                  FROM timetable 
                  WHERE venue = ? AND day_of_week = ? AND semester = ? AND academic_year = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssss", $venue, $day_of_week, $semester, $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if ($row['unit_code'] != $unit_code && timeOverlaps($time_from, $time_to, $row['time_from'], $row['time_to'])) {
                $conflicts[] = "Venue '{$venue}' already booked for {$row['unit_code']} on {$day_of_week} at {$row['time_from']} - {$row['time_to']}";
            }
        }
        $stmt->close();
    }
    
    // Check lecturer conflicts
    if (!empty($lecturer)) {
        $query = "SELECT unit_code, course_title, time_from, time_to 
                  FROM timetable 
                  WHERE lecturer = ? AND day_of_week = ? AND semester = ? AND academic_year = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssss", $lecturer, $day_of_week, $semester, $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if ($row['unit_code'] != $unit_code && timeOverlaps($time_from, $time_to, $row['time_from'], $row['time_to'])) {
                $conflicts[] = "Lecturer '{$lecturer}' already teaching {$row['unit_code']} on {$day_of_week} at {$row['time_from']} - {$row['time_to']}";
            }
        }
        $stmt->close();
    }
    
    return $conflicts;
}

// Handle the Timetable Modal Submission (UPDATED with day_of_week)
if (isset($_POST['save_to_timetable'])) {
    $u_code = mysqli_real_escape_string($conn, $_POST['unit_code']);
    $u_title = mysqli_real_escape_string($conn, $_POST['course_title']);
    $u_sem = mysqli_real_escape_string($conn, $_POST['semester']);
    $day_of_week = mysqli_real_escape_string($conn, $_POST['day_of_week']); // ← ADDED DAY OF WEEK
    $time_from = mysqli_real_escape_string($conn, $_POST['time_from']);
    $time_to = mysqli_real_escape_string($conn, $_POST['time_to']);
    $venue = mysqli_real_escape_string($conn, $_POST['venue']);
    $lecturer = mysqli_real_escape_string($conn, $_POST['lecturer']);
    $u_group = mysqli_real_escape_string($conn, $_POST['unit_group']); 
    $academic_year = date("Y");
    
    // Validate time order
    if (convertTimeToMinutes($time_from) >= convertTimeToMinutes($time_to)) {
        echo "<script>alert('❌ End time must be after start time!'); window.location='Admin-index.php?section=units';</script>";
        exit;
    }
    
    // CHECK FOR CONFLICTS BEFORE INSERTING
    $conflicts = checkTimetableConflicts($conn, $u_code, $day_of_week, $time_from, $time_to, $venue, $lecturer, $u_sem, $academic_year);
    
    if (!empty($conflicts)) {
        // Show conflicts and don't save
        $conflict_msg = "❌ Cannot schedule due to conflicts:\n" . implode("\n", $conflicts);
        echo "<script>alert('$conflict_msg'); window.location='Admin-index.php?section=units';</script>";
        exit;
    }
    
    // No conflicts - proceed with insert (UPDATED with day_of_week)
    $insert_sql = "INSERT INTO timetable (unit_code, course_title, day_of_week, time_from, time_to, venue, unit_group, lecturer, semester, academic_year) 
                   VALUES ('$u_code', '$u_title', '$day_of_week', '$time_from', '$time_to', '$venue', '$u_group', '$lecturer', '$u_sem', '$academic_year')";
    
    if(mysqli_query($conn, $insert_sql)) {
        echo "<script>alert('✅ Unit successfully scheduled for $day_of_week at $time_from - $time_to!'); window.location='Admin-index.php?section=units';</script>";
    } else {
        echo "<script>alert('Error: " . mysqli_error($conn) . "');</script>";
    }
}

// Handle Add Student Logic (unchanged)
if (isset($_POST['full_name'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $dept = mysqli_real_escape_string($conn, $_POST['department']);

    $dept_codes = [
        "Information Technology" => "BIT",
        "Information Science"    => "BIS",
        "Management"             => "BBM",
        "Economics"              => "BEC",
        "Accounting and Finance" => "BAF",
        "Community Health"       => "BCH",
        "Environmental Health"   => "BEH",
        "Nursing"                => "BSN",
        "Pharmacy"               => "BPH",
        "Medical School"         => "MBB",
        "Clinical Medicine"      => "BCM",
        "Educational Management" => "BED",
        "Educational Psychology" => "BEP",
        "Special Needs Education" => "BSN",
        "Energy Engineering"     => "BEE",
        "Electrical Engineering" => "BEL",
        "Natural Sciences"       => "BNS",
        "Animal Health"          => "BAH",
        "Psychology"             => "BPS",
        "Law"                    => "LLB",
        "Security Studies"       => "BSS",
        "Journalism"             => "BJM",
        "Hospitality Management" => "BHM",
        "Travel and Tourism"     => "BTT"
    ];

    $prefix = isset($dept_codes[$dept]) ? $dept_codes[$dept] : "STU";
    $year = date("Y");

    $query = "SELECT reg_number FROM users WHERE role = 'student' ORDER BY id DESC LIMIT 1";
    $result_reg = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result_reg) > 0) {
        $row_reg = mysqli_fetch_assoc($result_reg);
        $last_reg = $row_reg['reg_number'];
        $last_sequence = (int)substr($last_reg, -5);
        $new_sequence = str_pad($last_sequence + 1, 5, '0', STR_PAD_LEFT);
    } else {
        $new_sequence = "43255"; 
    }
    
    $reg_number = "$prefix/$year/$new_sequence";
    $password_hashed = password_hash($reg_number, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (full_name, reg_number, email, department, role, password) 
            VALUES ('$full_name', '$reg_number', '$email', '$dept', 'student', '$password_hashed')";

    if (mysqli_query($conn, $sql)) {
        $message = "success|Student registered! Reg No: $reg_number. (Email simulation successful)";
    } else {
        $message = "error|Database Error: " . mysqli_error($conn);
    }
}

/* ==========================
   2. SEARCH & FILTER LOGIC
   ========================== */
$search = $_GET['search'] ?? '';
$year_filter = $_GET['year_level'] ?? 'all';
$dept_filter = $_GET['department'] ?? 'all';

$query = "SELECT * FROM academic_workload WHERE 1=1";
if ($year_filter !== 'all') {
    $query .= " AND year_level = '" . mysqli_real_escape_string($conn, $year_filter) . "'";
}
if ($dept_filter !== 'all') {
    $query .= " AND department = '" . mysqli_real_escape_string($conn, $dept_filter) . "'";
}
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $query .= " AND (unit_code LIKE '%$s%' OR unit_name LIKE '%$s%')";
}

$query .= " ORDER BY year_level ASC, semester_level ASC";
$result = mysqli_query($conn, $query);
$count = mysqli_num_rows($result); 
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Units - Admin</title>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --accent: #e0e7ff;
            --border: #e2e8f0;
            --text-light: #64748b;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f1f5f9; padding: 20px; }
        .search-container { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 25px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .section-box { background: white; border-radius: 12px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 15px; width: 500px; max-width: 90%; max-height: 90%; overflow-y: auto; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #475569; font-size: 0.85rem; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .form-row { display: flex; gap: 10px; margin-bottom: 15px; }
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .btn-primary { background: #4f46e5; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-secondary { background: #e2e8f0; color: #475569; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 12px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .unit-code { background: var(--accent); color: var(--primary-dark); padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 0.8rem; display: inline-block; }
    </style>
</head>
<body>

<form class="search-container" method="GET" action="Admin-index.php" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 25px; display: flex; gap: 15px; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); flex-wrap: wrap;">
    <input type="hidden" name="section" value="units">
    <div style="flex-grow: 1; min-width: 200px;">
        <input type="text" name="search" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;" 
               placeholder="Search by Code or Unit Name..." value="<?php echo htmlspecialchars($search); ?>">
    </div>

    <select name="department" style="padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: white;">
        <option value="all">All Departments</option>
        <option value="Information Technology" <?php if($dept_filter == 'Information Technology') echo 'selected'; ?>>IT</option>
        <option value="Nursing" <?php if($dept_filter == 'Nursing') echo 'selected'; ?>>Nursing</option>
    </select>

    <select name="year_level" style="padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: white;">
        <option value="all">All Year Levels</option>
        <option value="First Year" <?php if($year_filter == 'First Year') echo 'selected'; ?>>First Year</option>
        <option value="Second Year" <?php if($year_filter == 'Second Year') echo 'selected'; ?>>Second Year</option>
        <option value="Third Year" <?php if($year_filter == 'Third Year') echo 'selected'; ?>>Third Year</option>
        <option value="Fourth Year" <?php if($year_filter == 'Fourth Year') echo 'selected'; ?>>Fourth Year</option>
    </select>
    <button type="submit" style="background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600;">Filter</button>
</form>

<div class="section-box">
    <div style="padding: 15px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0; font-size: 1.1rem; color: var(--primary);">Academic Year Workload</h2>
        <span style="font-size: 0.8rem; color: var(--text-light);">Showing <strong><?php echo $count; ?></strong> Units</span>
    </div>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8fafc; text-align: left;">
                    <th style="padding: 12px; width: 40px;">#</th>
                    <th style="padding: 12px;">Unit Code</th>
                    <th style="padding: 12px;">Unit Name</th>
                    <th style="padding: 12px;">Year Level</th>
                    <th style="padding: 12px;">Semester</th>
                    <th style="padding: 12px;">Offering Time</th>
                    <th style="padding: 12px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($count > 0) {
                    $i = 1; 
                    while($row = mysqli_fetch_assoc($result)) {
                        $offering = $row['offering_time'];
                        $badgeStyle = ($offering == 'Every Semester') ? "badge-success" : "badge-danger";
                        $semVal = ($row['semester_level'] == '1st Semester') ? '1' : (($row['semester_level'] == '2nd Semester') ? '2' : '3');
                        
                        echo "<tr style='border-bottom: 1px solid #f1f5f9;'>
                                <td style='padding: 12px; color: var(--text-light);'>$i</td>
                                <td style='padding: 12px;'><span class='unit-code'>{$row['unit_code']}</span></td>
                                <td style='padding: 12px;'>{$row['unit_name']}</td>
                                <td style='padding: 12px;'>{$row['year_level']}</td>
                                <td style='padding: 12px;'>{$row['semester_level']}</td>
                                <td style='padding: 12px;'>
                                    <span class='badge $badgeStyle'>$offering</span>
                                </td>
                                <td style='padding: 12px; text-align: center;'>
                                    <button onclick=\"openScheduleModal('{$row['unit_code']}', '" . addslashes($row['unit_name']) . "', '$semVal')\" 
                                            style='background: #4f46e5; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: bold;'>
                                        + Schedule
                                    </button>
                                </td>
                               </tr>";
                        $i++; 
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Schedule Modal - UPDATED with Day of Week field -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle" style="margin-top: 0; color: #4f46e5; margin-bottom: 20px;">Schedule Unit</h3>
        <form method="POST" id="scheduleForm">
            <input type="hidden" name="unit_code" id="form_unit_code">
            <input type="hidden" name="course_title" id="form_course_title">
            <input type="hidden" name="semester" id="form_semester">
            
            <!-- DAY OF WEEK - ADDED HERE -->
            <div class="form-group">
                <label>Day of Week *</label>
                <select name="day_of_week" id="form_day_of_week" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px;">
                    <option value="">Select Day</option>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Lecturer</label>
                <input type="text" name="lecturer" id="form_lecturer" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px;">
            </div>
            
            <div class="form-group">
                <label>Group</label>
                <input type="text" name="unit_group" id="form_unit_group" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px;" placeholder="e.g., Class I, Core">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Time From</label>
                    <input type="time" name="time_from" id="form_time_from" required style="width: 100%; padding: 10px;">
                </div>
                <div class="form-group">
                    <label>Time To</label>
                    <input type="time" name="time_to" id="form_time_to" required style="width: 100%; padding: 10px;">
                </div>
            </div>
            
            <div class="form-group">
                <label>Venue</label>
                <input type="text" name="venue" id="form_venue" required style="width: 100%; padding: 10px;" placeholder="e.g., CT HALL, CC1, COMP LAB 2">
            </div>
            
            <div id="conflictWarning" style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; border-radius: 6px; margin: 15px 0; font-size: 0.85rem; display: none;"></div>
            
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" onclick="closeModal()" class="btn-secondary" style="background: #e2e8f0; color: #475569; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer;">Cancel</button>
                <button type="submit" name="save_to_timetable" id="submitBtn" class="btn-primary" style="background: #4f46e5; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer;">Push to Timetable</button>
            </div>
        </form>
    </div>
</div>

<script>
function openScheduleModal(code, title, sem) {
    document.getElementById('scheduleModal').style.display = 'flex';
    document.getElementById('modalTitle').innerHTML = "Schedule: " + code;
    document.getElementById('form_unit_code').value = code;
    document.getElementById('form_course_title').value = title;
    document.getElementById('form_semester').value = sem;
    // Clear form fields
    document.getElementById('form_day_of_week').value = '';
    document.getElementById('form_lecturer').value = '';
    document.getElementById('form_unit_group').value = '';
    document.getElementById('form_time_from').value = '';
    document.getElementById('form_time_to').value = '';
    document.getElementById('form_venue').value = '';
    document.getElementById('conflictWarning').style.display = 'none';
    document.getElementById('submitBtn').disabled = false;
}

function closeModal() {
    document.getElementById('scheduleModal').style.display = 'none';
}

// Real-time conflict checking
let checkTimeout;

function checkConflicts() {
    const day = document.getElementById('form_day_of_week').value;
    const timeFrom = document.getElementById('form_time_from').value;
    const timeTo = document.getElementById('form_time_to').value;
    const venue = document.getElementById('form_venue').value;
    const lecturer = document.getElementById('form_lecturer').value;
    const semester = document.getElementById('form_semester').value;
    const unitCode = document.getElementById('form_unit_code').value;
    
    if (!day || !timeFrom || !timeTo || !semester) return;
    
    if (timeFrom >= timeTo) {
        document.getElementById('conflictWarning').innerHTML = '⚠️ End time must be after start time.';
        document.getElementById('conflictWarning').style.display = 'block';
        document.getElementById('submitBtn').disabled = true;
        return;
    }
    
    if (checkTimeout) clearTimeout(checkTimeout);
    checkTimeout = setTimeout(() => {
        fetch('check_timetable_conflicts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                day_of_week: day,
                time_from: timeFrom,
                time_to: timeTo,
                venue: venue,
                lecturer: lecturer,
                semester: semester,
                academic_year: new Date().getFullYear().toString(),
                unit_code: unitCode
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.has_conflicts) {
                document.getElementById('conflictWarning').innerHTML = data.message;
                document.getElementById('conflictWarning').style.display = 'block';
                document.getElementById('submitBtn').disabled = true;
            } else {
                document.getElementById('conflictWarning').style.display = 'none';
                document.getElementById('submitBtn').disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('submitBtn').disabled = false;
        });
    }, 500);
}

// Attach event listeners
document.getElementById('form_day_of_week')?.addEventListener('change', checkConflicts);
document.getElementById('form_time_from')?.addEventListener('change', checkConflicts);
document.getElementById('form_time_to')?.addEventListener('change', checkConflicts);
document.getElementById('form_venue')?.addEventListener('input', checkConflicts);
document.getElementById('form_lecturer')?.addEventListener('input', checkConflicts);
</script>

</body>
</html>