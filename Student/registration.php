<?php
session_start();

// 1. Check if user_id exists (Are they logged in?)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: ../Student/Home.php?error=access_denied");
    } else {
        header("Location: ../login.php");
    }
    exit();
}

/* DATABASE CONNECTION */
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");

// 1. Get the Reg No from the Session
$student_reg_no = $_SESSION['reg_number'] ?? ''; 
$reg_number = $student_reg_no; 

// 2. Fetch the Student's real name and department from the users table
$user_id = $_SESSION['user_id'];
$user_query = mysqli_query($conn, "SELECT full_name, department, survey_done FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_assoc($user_query);

$student_name = $user_data['full_name'] ?? 'Unknown Student';
$student_dept = $user_data['department'] ?? 'General';
$survey_done_status = $user_data['survey_done'] ?? 0;

$semester = "Jan/Apr";
$academic_year = "2026";

// Get current semester from session or default to 1
$current_semester_num = $_SESSION['current_semester'] ?? 1;
$current_semester_name = ($current_semester_num == 1) ? '1st Semester' : '2nd Semester';

// Get student's year level from session (set during login)
$student_year_level = $_SESSION['student_year'] ?? 'First Year';

/* ===============================
    CORE LOGIC: REGISTRATION
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['register_btn'])) {
    
    // Check current total (Confirmed + Provisional)
    $check_count_sql = "SELECT COUNT(*) as total FROM registered_courses WHERE student_reg_no = '$student_reg_no'";
    $count_res = mysqli_query($conn, $check_count_sql);
    $current_count_row = mysqli_fetch_assoc($count_res);
    $current_total = $current_count_row['total'];

    $max_allowed = 8;

    if ($current_total >= $max_allowed) {
        echo "<script>alert('Error: You have already registered the maximum limit of $max_allowed units.'); window.location='" . $_SERVER['PHP_SELF'] . "';</script>";
        exit();
    }

    $added_this_session = 0;

    for ($i = 1; $i <= 6; $i++) {
        if (!empty($_POST["courseCode$i"])) {
            
            if (($current_total + $added_this_session) >= $max_allowed) {
                break; 
            }

            $unit_code   = mysqli_real_escape_string($conn, trim($_POST["courseCode$i"]));
            $exam_type   = mysqli_real_escape_string($conn, $_POST["examType$i"]);
            $class_group = mysqli_real_escape_string($conn, $_POST["classCode$i"]);

            // Check if the unit exists in the timetable AND belongs to student's department AND is for current semester
            $check = mysqli_query($conn, "SELECT t.unit_code FROM timetable t 
                                          INNER JOIN academic_workload aw ON t.unit_code = aw.unit_code 
                                          WHERE t.unit_code='$unit_code' 
                                          AND aw.department='$student_dept'
                                          AND t.year_level='$student_year_level'
                                          AND t.semester='$current_semester_num'
                                          LIMIT 1");
            
            if (mysqli_num_rows($check) > 0) {
                $insert = mysqli_query($conn, "INSERT IGNORE INTO registered_courses 
                    (student_reg_no, unit_code, exam_type, class_group, semester, academic_year, status, department)
                    VALUES ('$student_reg_no','$unit_code','$exam_type','$class_group','$semester','$academic_year', 'Provisional', '$student_dept')");
                
                if ($insert && mysqli_affected_rows($conn) > 0) {
                    $added_this_session++;
                }
            }
        }
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?added=" . $added_this_session);
    exit();
}

/* ===============================
    CORE LOGIC: CONFIRM / DROP
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['selected_units'])) {
    $units = $_POST['selected_units']; 

    // REDIRECT TO SURVEY: Only if they click Confirm and haven't done the survey
    if (isset($_POST['btn_confirm_action']) && $survey_done_status == 0) {
        header("Location: survey.php");
        exit();
    }

    // PROCESS ACTION: Confirm or Drop
    foreach ($units as $u_code) {
        $u_code = mysqli_real_escape_string($conn, $u_code);
        
        if (isset($_POST['btn_confirm_action'])) {
            mysqli_query($conn, "UPDATE registered_courses SET status='Confirmed' WHERE student_reg_no='$student_reg_no' AND unit_code='$u_code'");
        } elseif (isset($_POST['btn_drop_action'])) {
            mysqli_query($conn, "DELETE FROM registered_courses WHERE student_reg_no='$student_reg_no' AND unit_code='$u_code' AND status='Provisional'");
        }
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
    exit();
}

/* ===============================
    DATA FETCHING
================================ */
$confirmed = mysqli_query($conn, "SELECT rc.*, t.course_title FROM registered_courses rc JOIN timetable t ON rc.unit_code = t.unit_code WHERE rc.student_reg_no = '$student_reg_no' AND (rc.status = 'Confirmed' OR rc.status = 'Approved')");
$provisional = mysqli_query($conn, "SELECT rc.*, t.course_title FROM registered_courses rc JOIN timetable t ON rc.unit_code = t.unit_code WHERE rc.student_reg_no = '$student_reg_no' AND (rc.status = 'Provisional' OR rc.status IS NULL OR rc.status = '')");

// Fetch counts for the restriction display
$ui_count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM registered_courses WHERE student_reg_no = '$student_reg_no'");
$ui_row = mysqli_fetch_assoc($ui_count_res);
$slots_used = $ui_row['total'];
$remaining = 8 - $slots_used;

if (!$confirmed || !$provisional) {
    die("Query Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal | Course Registration</title>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --bg: #f8fafc;
            --white: #ffffff;
            --text-main: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text-main); margin: 0; line-height: 1.5; }

        header { background: white; border-bottom: 1px solid var(--border); padding: 1rem 5%; display: flex; align-items: center; justify-content: space-between; }
        .branding { display: flex; align-items: center; gap: 15px; }
        .logoimg { height: 50px; border-radius: 8px; }
        .branding h1 { margin: 0; font-size: 1.4rem; color: var(--primary); font-weight: 800; }
        .branding small { color: var(--text-light); display: block; font-size: 0.85rem; }

        nav { background: var(--primary); padding: 0 5%; display: flex; gap: 10px; flex-wrap: wrap; }
        nav a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 14px 20px; font-size: 0.9rem; font-weight: 600; transition: 0.3s; border-bottom: 3px solid transparent; }
        nav a:hover { color: white; background: rgba(255,255,255,0.1); }
        nav a.active { color: white; border-bottom: 3px solid white; background: rgba(255,255,255,0.15); }

        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .student-strip { background: #e0e7ff; padding: 12px 20px; border-radius: 10px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; color: var(--primary-dark); font-weight: 700; font-size: 0.9rem; flex-wrap: wrap; gap: 10px; }

        .card { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 30px; border: 1px solid var(--border); }
        .card-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 20px; color: var(--text-main); display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--border); padding-bottom: 12px; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f1f5f9; padding: 12px; font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); border-bottom: 1px solid var(--border); }
        td { padding: 14px 12px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }

        .btn { padding: 10px 22px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: 0.2s; display: inline-flex; align-items: center; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-main); }

        input[type="text"], select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; }

        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
        .bg-success { background: #dcfce7; color: #166534; }
        .bg-warning { background: #fef3c7; color: #92400e; }

        footer { text-align: center; padding: 40px; color: var(--text-light); font-size: 0.85rem; }
    </style>
</head>
<body>

<header>
    <div class="branding">
        <img src="../Images/logo.jpg" class="logoimg" alt="Logo">
        <div>
            <h1>Student Support Agent</h1>
            <small>Infinite support for infinite possibilities.</small>
        </div>
    </div>
    <div style="font-size: 0.85rem; color: var(--text-light);">
        Academic Year: <strong>2026</strong> | Semester: <strong>Jan-Apr</strong>
    </div>
</header>

<nav>
    <a href="home.php?page=dashboard">Dashboard</a>
    <a href="home.php?page=materials">📚 Course Materials</a>
    <a href="home.php?page=assignments">📝 Assignments</a>
    <a href="home.php?page=my_submissions">📋 My Submissions</a>
    <a href="personal_information.php">👤 Information Update</a>
    <a href="teaching_timetable.php">📅 Timetables</a>
    <a href="registration.php" class="active">📖 Course Registration</a>        
    <a href="../logout.php">🚪 Sign Out</a>
</nav>

<div class="container">
   <div class="student-strip">
    <span><?php echo htmlspecialchars($reg_number); ?> | <?php echo htmlspecialchars($student_name); ?></span>
    <span><?php echo htmlspecialchars($student_dept); ?></span>
    <span><?php echo $student_year_level; ?> - <?php echo $current_semester_name; ?></span>
</div>

    <?php if(isset($_GET['survey_complete'])): ?>
        <div class="card" style="background: #dcfce7; border-left: 5px solid #10b981; color: #166534; padding: 15px; margin-bottom: 20px;">
            🎉 <strong>Survey Recorded!</strong> You can now proceed to confirm your units.
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['added']) && $_GET['added'] > 0): ?>
        <div class="card" style="background: #dcfce7; border-left: 5px solid #10b981; color: #166534; padding: 15px; margin-bottom: 20px;">
            ✅ <strong>Success!</strong> <?php echo htmlspecialchars($_GET['added']); ?> unit(s) added to your provisional registration.
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['updated'])): ?>
        <div class="card" style="background: #dcfce7; border-left: 5px solid #10b981; color: #166534; padding: 15px; margin-bottom: 20px;">
            ✅ <strong>Success!</strong> Your registration has been updated.
        </div>
    <?php endif; ?>

    <!-- Confirmed Units Card -->
    <div class="card">
        <div class="card-title">✅ Confirmed Units</div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Unit Code & Title</th>
                        <th>Exam Type</th>
                        <th>Group</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while($row = mysqli_fetch_assoc($confirmed)): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['unit_code']); ?></strong> - <?php echo htmlspecialchars($row['course_title']); ?></td>
                            <td><?php echo htmlspecialchars($row['exam_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['class_group']); ?></td>
                            <td><span class="badge bg-success">Confirmed</span></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if($i==1): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; color:#666; padding: 30px;">No confirmed units yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Provisional Units Card - FIXED -->
    <form method="POST">
    <div class="card">
        <div class="card-title" style="color: var(--warning);">⏳ Provisional Units</div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th width="40">Select</th>
                        <th>Unit Code & Title</th>
                        <th width="100">Exam Type</th>
                        <th width="80">Group</th>
                        <th width="80">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $has_provisional = false;
                    while($row = mysqli_fetch_assoc($provisional)): 
                        $has_provisional = true;
                    ?>
                        <tr>
                            <td align="center"><input type="checkbox" name="selected_units[]" value="<?php echo $row['unit_code']; ?>"></td>
                            <td><strong><?php echo htmlspecialchars($row['unit_code']); ?></strong> - <?php echo htmlspecialchars($row['course_title']); ?></td>
                            <td><?php echo htmlspecialchars($row['exam_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['class_group']); ?></td>
                            <td><span class="badge bg-warning">Pending</span></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if(!$has_provisional): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; color:#666; padding: 30px;">No provisional units. Add units below.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($has_provisional): ?>
        <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
            <button type="submit" name="btn_drop_action" class="btn btn-outline" style="color:red;">Drop Selected</button>
            <button type="submit" name="btn_confirm_action" class="btn btn-success">Confirm Units</button>
        </div>
        <?php endif; ?>
    </div>
    </form>

    <!-- Register New Courses Card -->
    <div class="card">
        <div class="card-title">
            ➕ Register New Courses 
            <span style="margin-left: auto; font-size: 0.8rem; color: <?php echo ($remaining <= 0) ? '#ef4444' : 'var(--text-light)'; ?>;">
                Units: <?php echo $slots_used; ?>/8 (<?php echo $remaining; ?> left)
            </span>
        </div>
        
        <?php
        // Fetch ONLY units for the student's current semester from timetable
        $available_units_query = "SELECT DISTINCT t.unit_code, t.course_title 
                                  FROM timetable t 
                                  INNER JOIN academic_workload aw ON t.unit_code = aw.unit_code
                                  WHERE aw.department = '$student_dept' 
                                  AND t.year_level = '$student_year_level'
                                  AND t.semester = '$current_semester_num'
                                  ORDER BY t.unit_code";
        
        $available_units_result = mysqli_query($conn, $available_units_query);
        $available_units_list = [];
        while ($au = mysqli_fetch_assoc($available_units_result)) {
            $available_units_list[] = $au;
        }
        ?>
        
        <?php if(empty($available_units_list)): ?>
            <div class="card" style="background: #fef3c7; border-left: 5px solid #f59e0b; color: #92400e; padding: 15px; margin-bottom: 20px;">
                ⚠️ <strong>No units available for registration!</strong><br>
                There are no units scheduled for <?php echo $student_year_level; ?> - <?php echo $current_semester_name; ?> in your department (<?php echo $student_dept; ?>).<br>
                Please contact the academic office.
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="register_btn" value="1">
                <datalist id="unit-codes">
                    <?php foreach($available_units_list as $au): ?>
                        <option value="<?php echo $au['unit_code']; ?>"><?php echo $au['unit_code'] . ' - ' . $au['course_title']; ?></option>
                    <?php endforeach; ?>
                </datalist>
                
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Unit Code</th>
                                <th width="120">Exam Type</th>
                                <th width="120">Class Group</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($k=1; $k<=6; $k++): ?>
                                <tr>
                                    <td align="center"><?php echo $k; ?></td>
                                    <td>
                                        <input type="text" name="courseCode<?php echo $k; ?>" 
                                               placeholder="Select unit code" 
                                               list="unit-codes"
                                               autocomplete="off"
                                               <?php if($remaining <= 0) echo 'disabled'; ?>>
                                    </td>
                                    <td>
                                        <select name="examType<?php echo $k; ?>" <?php if($remaining <= 0) echo 'disabled'; ?>>
                                            <option value="Regular">Regular</option>
                                            <option value="Retake">Retake</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="classCode<?php echo $k; ?>" <?php if($remaining <= 0) echo 'disabled'; ?>>
                                            <option value="Day">Day</option>
                                            <option value="Evening">Evening</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align: center; margin-top: 25px;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 40px;" <?php if($remaining <= 0 || empty($available_units_list)) echo 'disabled style="background:#94a3b8; cursor:not-allowed;"'; ?>>
                        <?php 
                        if($remaining <= 0) echo 'Limit Reached (8/8)';
                        elseif(empty($available_units_list)) echo 'No Units Available';
                        else echo 'Submit Registration';
                        ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<footer>
    &copy;  Portal Assistant AI System
</footer>

</body>
</html>