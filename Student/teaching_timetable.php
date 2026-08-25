<?php
session_start();

// 1. Check if user_id exists (Are they logged in?)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: ../admin/Admin-index.php?error=access_denied");
    } else {
        header("Location: ../login.php");
    }
    exit();
}

/* ==========================
   DATABASE CONNECTION
========================== */
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

/* ==========================
   FETCH LOGGED-IN STUDENT
========================== */
$user_id = $_SESSION['user_id'] ?? 1;

$user_q = mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'");
$user = mysqli_fetch_assoc($user_q);

$reg_number = $user['reg_number'];
$full_name  = $user['full_name'];
$student_department = $user['department'];

/* ==========================
   DETERMINE CORRECT SEMESTER FOR STUDENT
========================== */
// Helper function to check if student is new (admitted in current year)
function isNewStudent($reg_number) {
    if (preg_match('/\/(\d{4})\//', $reg_number, $matches)) {
        $admission_year = intval($matches[1]);
        $current_year = date('Y');
        return ($admission_year == $current_year);
    }
    return false;
}

// Helper function to get student's current semester
function getStudentCurrentSemester($reg_number) {
    // Check if student is new (admitted this year)
    $is_new = isNewStudent($reg_number);
    
    if ($is_new) {
        // New students start with 1st Semester
        return 1;
    }
    
    // For returning students, follow the academic calendar
    $current_month = date('n');
    if ($current_month >= 9 && $current_month <= 12) {
        return 1; // 1st Semester
    } elseif ($current_month >= 1 && $current_month <= 4) {
        return 2; // 2nd Semester
    } else {
        // Holiday period (May-August) - default to next semester
        return 1;
    }
}

// Determine semester
$semester_code = getStudentCurrentSemester($reg_number);
$semester = ($semester_code == 1) ? "1st Semester" : "2nd Semester";
$academic_year = "2025/2026";

// Check if student is new (for display purposes)
$is_new_student = isNewStudent($reg_number);

/* ==========================
   FETCH TIMETABLE DATA - FILTERED BY DEPARTMENT AND SEMESTER
========================== */
// IMPORTANT: Filter by student's department AND the correct semester!
$timetable_q = mysqli_query(
    $conn, 
    "SELECT * FROM timetable 
     WHERE department = '$student_department' 
     AND semester = '$semester_code'
     ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), 
     time_from ASC"
);

if (!$timetable_q) {
    die("Timetable query failed: " . mysqli_error($conn));
}

// Get the program name based on department
$program_name = "";
switch($student_department) {
    case 'Information Technology':
        $program_name = "Bachelor of Science in Information Technology";
        break;
    case 'Management':
        $program_name = "Bachelor of Business Management";
        break;
    case 'Economics':
        $program_name = "Bachelor of Economics";
        break;
    case 'Accounting and Finance':
        $program_name = "Bachelor of Accounting and Finance";
        break;
    case 'Travel and Tourism Management':
        $program_name = "Bachelor of Travel and Tourism Management";
        break;
    case 'Hospitality Management':
        $program_name = "Bachelor of Hospitality Management";
        break;
    default:
        $program_name = $student_department;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable | Student Support Agent</title>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --bg: #f8fafc;
            --white: #ffffff;
            --text-main: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
        }

        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text-main); margin: 0; line-height: 1.5; }

        header { background: var(--white); border-bottom: 1px solid var(--border); padding: 1rem 5%; display: flex; align-items: center; justify-content: space-between; }
        .branding { display: flex; align-items: center; gap: 15px; }
        .logoimg { height: 50px; border-radius: 8px; }
        .branding h1 { margin: 0; font-size: 1.4rem; color: var(--primary); font-weight: 800; }
        .branding small { color: var(--text-light); display: block; font-size: 0.85rem; }

        nav { background: var(--primary); padding: 0 5%; display: flex; gap: 10px; flex-wrap: wrap; }
        nav a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 14px 20px; font-size: 0.9rem; font-weight: 600; transition: 0.3s; border-bottom: 3px solid transparent; }
        nav a:hover { color: white; background: rgba(255,255,255,0.1); }
        nav a.active { color: white; border-bottom: 3px solid white; background: rgba(255,255,255,0.15); }

        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .student-strip { background: #e0e7ff; padding: 12px 20px; border-radius: 10px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; color: var(--primary-dark); font-weight: 700; font-size: 0.9rem; flex-wrap: wrap; gap: 10px; }
        .new-student-badge { background: #10b981; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; }

        .card { background: var(--white); border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 25px; border: 1px solid var(--border); overflow-x: auto; }
        .card-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 20px; color: var(--text-main); border-bottom: 1px solid var(--border); padding-bottom: 12px; text-align: center; }

        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { text-align: center; background: #f1f5f9; padding: 12px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light); border: 1px solid var(--border); }
        td { padding: 12px; border: 1px solid var(--border); font-size: 0.85rem; text-align: center; }
        tr:hover { background: #fdfdfd; }
        
        .unit-code { font-weight: 700; color: var(--primary); }
        .day-badge { background: #e0e7ff; color: #4338ca; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; }
        .no-data { text-align: center; padding: 40px; color: var(--text-light); }
        
        footer { text-align: center; padding: 40px; color: var(--text-light); font-size: 0.85rem; }
        
        .semester-info { text-align: center; padding: 10px; background: #f1f5f9; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem; }
        .semester-info strong { color: var(--primary); }
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
    <div style="text-align: right;">
        <span style="font-size: 0.8rem; color: var(--text-light);">Academic Session</span><br>
        <strong><?php echo $academic_year; ?></strong>
    </div>
</header>

<nav>
    <a href="home.php?page=dashboard">Dashboard</a>
    <a href="home.php?page=materials">📚 Course Materials</a>
    <a href="home.php?page=assignments">📝 Assignments</a>
    <a href="home.php?page=my_submissions">📋 My Submissions</a>
    <a href="personal_information.php">👤 Information Update</a>
    <a href="teaching_timetable.php" class="active">📅 Timetables</a>
    <a href="registration.php">📖 Course Registration</a>        
    <a href="../logout.php">🚪 Sign Out</a>
</nav>

<div class="container">
    <div class="student-strip">
        <span><?php echo "$reg_number | $full_name"; ?></span>
        <span>
            <?php echo $student_department; ?>
            <?php if ($is_new_student): ?>
                <span class="new-student-badge">🎓 New Student</span>
            <?php endif; ?>
        </span>
    </div>

    <div class="card">
        <div class="card-title">
            WEEKLY TEACHING SCHEDULE<br>
            <span style="font-size: 0.8rem; font-weight: 400; color: var(--text-light);">
                <?php echo $program_name; ?> (<?php echo $semester; ?>)
            </span>
        </div>

        <div class="semester-info">
            📅 <strong>Current Semester:</strong> <?php echo $semester; ?> 
            <?php if ($is_new_student): ?>
                <span style="color: #10b981; margin-left: 10px;">✅ Welcome! You are seeing 1st Semester units as a new student.</span>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Code</th>
                    <th>Course Title</th>
                    <th>Time From</th>
                    <th>Time To</th>
                    <th>Day</th>
                    <th>Venue</th>
                    <th>Group</th>
                    <th>Lecturer</th>
                    <th>Exam Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $counter = 1;
                if (mysqli_num_rows($timetable_q) > 0) {
                    while ($row = mysqli_fetch_assoc($timetable_q)) {
                        echo "<tr>
                            <td>{$counter}</td>
                            <td class='unit-code'>{$row['unit_code']}</td>
                            <td style='text-align:left;'>{$row['course_title']}</td>
                            <td>" . date("H:i", strtotime($row['time_from'])) . "</td>
                            <td>" . date("H:i", strtotime($row['time_to'])) . "</td>
                            <td><span class='day-badge'>{$row['day_of_week']}</span></td>
                            <td><strong>{$row['venue']}</strong></td>
                            <td>{$row['unit_group']}</td>
                            <td>{$row['lecturer']}</td>
                            <td>" . ($row['exam_date'] ?: '<span style="color:#ccc;">TBD</span>') . "</td>
                        </tr>";
                        $counter++;
                    }
                } else {
                    echo "<tr><td colspan='10' class='no-data'>
                        <strong>⚠️ No timetable found for your department.</strong><br><br>
                        Your department: <strong>{$student_department}</strong><br>
                        Current Semester: <strong>{$semester}</strong><br><br>
                        <?php if ($is_new_student): ?>
                            💡 <i>As a new student, you should be seeing 1st Semester units. If no units are showing, please contact the academic office to ensure your department's 1st Semester units are scheduled.</i><br><br>
                        <?php else: ?>
                            💡 <i>Please contact the academic office or ask the admin to schedule units for your department.</i><br><br>
                        <?php endif; ?>
                        📌 <b>Tip:</b> Try asking the chatbot 'What to register' to see your required units!
                    </td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<footer>
    &copy; Portal Assistant AI System
</footer>

</body>
</html>