<?php
session_start();

/* ==========================
   ACCESS CONTROL
========================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit();
}

$lecturer_name = $_SESSION['user_name'];
$lecturer_dept = $_SESSION['department'];
$lecturer_reg = $_SESSION['reg_number'];
$lecturer_id = $_SESSION['user_id'];

/* ==========================
   DATABASE CONNECTION
========================== */
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check if password needs to be changed
$password_change_required = false;
$query = "SELECT password_changed FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $lecturer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($result);

if (!$user_data || !isset($user_data['password_changed']) || $user_data['password_changed'] == 0) {
    $password_change_required = true;
}

// Handle file upload for learning materials
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_material'])) {
        $unit_code = mysqli_real_escape_string($conn, $_POST['unit_code']);
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        $target_dir = "../uploads/materials/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES["material_file"]["name"]);
        $target_file = $target_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'png'];
        
        if (in_array($file_type, $allowed_types)) {
            if (move_uploaded_file($_FILES["material_file"]["tmp_name"], $target_file)) {
                $insert = "INSERT INTO course_materials (unit_code, title, description, file_path, file_type, uploaded_by, uploaded_at) 
                          VALUES ('$unit_code', '$title', '$description', '$file_name', '$file_type', '$lecturer_name', NOW())";
                if (mysqli_query($conn, $insert)) {
                    echo '<div class="alert alert-success">✅ Learning material uploaded successfully!</div>';
                }
            } else {
                echo '<div class="alert alert-error">❌ Error uploading file!</div>';
            }
        } else {
            echo '<div class="alert alert-error">❌ Invalid file type! Allowed: PDF, DOC, PPT, TXT, JPG, PNG</div>';
        }
    }
    
    // Handle YouTube link upload
    if (isset($_POST['upload_youtube'])) {
        $unit_code = mysqli_real_escape_string($conn, $_POST['unit_code']);
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $youtube_url = mysqli_real_escape_string($conn, $_POST['youtube_url']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $youtube_url, $matches);
        $video_id = $matches[1] ?? '';
        
        $insert = "INSERT INTO course_materials (unit_code, title, description, youtube_url, video_id, material_type, uploaded_by, uploaded_at) 
                  VALUES ('$unit_code', '$title', '$description', '$youtube_url', '$video_id', 'youtube', '$lecturer_name', NOW())";
        if (mysqli_query($conn, $insert)) {
            echo '<div class="alert alert-success">✅ YouTube tutorial added successfully!</div>';
        } else {
            echo '<div class="alert alert-error">❌ Error adding YouTube link!</div>';
        }
    }
    
    // Handle assignment upload
    if (isset($_POST['upload_assignment'])) {
        $unit_code = mysqli_real_escape_string($conn, $_POST['unit_code']);
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
        $assessment_type = mysqli_real_escape_string($conn, $_POST['assessment_type']);
        
        if ($assessment_type == 'CAT') {
            $total_marks = 20;
        } elseif ($assessment_type == 'Exam') {
            $total_marks = 70;
        } else {
            $total_marks = 10;
        }
        
        $target_dir = "../uploads/assignments/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES["assignment_file"]["name"]);
        $target_file = $target_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        $allowed_types = ['pdf', 'doc', 'docx', 'zip'];
        
        if (in_array($file_type, $allowed_types)) {
            if (move_uploaded_file($_FILES["assignment_file"]["tmp_name"], $target_file)) {
                $insert = "INSERT INTO assignments (unit_code, title, description, file_path, due_date, total_marks, assessment_type, created_by, created_at) 
                          VALUES ('$unit_code', '$title', '$description', '$file_name', '$due_date', '$total_marks', '$assessment_type', '$lecturer_name', NOW())";
                if (mysqli_query($conn, $insert)) {
                    echo '<div class="alert alert-success">✅ ' . $assessment_type . ' uploaded successfully! (' . $total_marks . ' marks)</div>';
                } else {
                    echo '<div class="alert alert-error">❌ Error: ' . $conn->error . '</div>';
                }
            } else {
                echo '<div class="alert alert-error">❌ Error uploading assignment!</div>';
            }
        } else {
            echo '<div class="alert alert-error">❌ Invalid file type! Allowed: PDF, DOC, DOCX, ZIP</div>';
        }
    }
    
    // Handle delete material
    if (isset($_POST['delete_material'])) {
        $material_id = intval($_POST['material_id']);
        mysqli_query($conn, "DELETE FROM course_materials WHERE id = $material_id");
        echo '<div class="alert alert-success">✅ Material deleted successfully!</div>';
    }
    
    // Handle delete assignment
    if (isset($_POST['delete_assignment'])) {
        $assignment_id = intval($_POST['assignment_id']);
        mysqli_query($conn, "DELETE FROM assignments WHERE id = $assignment_id");
        echo '<div class="alert alert-success">✅ Assignment deleted successfully!</div>';
    }
    
    // Handle grading submission
    if (isset($_POST['grade_submission'])) {
        $submission_id = intval($_POST['submission_id']);
        $obtained_marks = floatval($_POST['obtained_marks']);
        $feedback = mysqli_real_escape_string($conn, $_POST['feedback']);
        
        $update = "UPDATE assignment_submissions 
                   SET obtained_marks = $obtained_marks, 
                       feedback = '$feedback', 
                       status = 'graded' 
                   WHERE id = $submission_id";
        
        if (mysqli_query($conn, $update)) {
            echo '<div class="alert alert-success">✅ Marks awarded successfully!</div>';
        } else {
            echo '<div class="alert alert-error">❌ Error awarding marks: ' . $conn->error . '</div>';
        }
    }
    
    // Handle manual marks submission
    if (isset($_POST['save_manual_marks'])) {
        $student_reg = mysqli_real_escape_string($conn, $_POST['student_reg']);
        $unit_code = mysqli_real_escape_string($conn, $_POST['unit_code']);
        $assessment_type = mysqli_real_escape_string($conn, $_POST['assessment_type']);
        $assessment_name = mysqli_real_escape_string($conn, $_POST['assessment_name']);
        $obtained_marks = floatval($_POST['obtained_marks']);
        
        if ($assessment_type == 'CAT') {
            $total_marks = 20;
        } elseif ($assessment_type == 'Exam') {
            $total_marks = 70;
        } else {
            $total_marks = 10;
        }
        
        $feedback = mysqli_real_escape_string($conn, $_POST['feedback']);
        
        $insert = "INSERT INTO assignment_submissions (assignment_id, unit_code, student_reg, submission_text, obtained_marks, total_marks, feedback, submitted_at, status) 
                   VALUES (0, '$unit_code', '$student_reg', '[Manual Entry: $assessment_name - $unit_code]', $obtained_marks, $total_marks, '$feedback', NOW(), 'graded')";
        
        if (mysqli_query($conn, $insert)) {
            echo '<div class="alert alert-success">✅ Manual ' . $assessment_type . ' marks saved for ' . htmlspecialchars($student_reg) . '! (' . $obtained_marks . '/' . $total_marks . ')</div>';
        } else {
            echo '<div class="alert alert-error">❌ Error saving marks: ' . $conn->error . '</div>';
        }
    }
}

// Get statistics
$dept_students = 0;
$q1 = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role='student' AND department='$lecturer_dept'");
if ($q1) { $dept_students = mysqli_fetch_assoc($q1)['count']; }

$assigned_units_count = 0;
$q_assigned = mysqli_query($conn, "SELECT COUNT(*) as count FROM timetable WHERE lecturer = '$lecturer_name'");
if ($q_assigned) { $assigned_units_count = mysqli_fetch_assoc($q_assigned)['count']; }

$pending_registrations = 0;
$q3 = mysqli_query($conn, "SELECT COUNT(*) as count FROM registered_courses rc 
                           JOIN academic_workload aw ON rc.unit_code = aw.unit_code 
                           WHERE aw.department='$lecturer_dept' AND rc.status='Provisional'");
if ($q3) { $pending_registrations = mysqli_fetch_assoc($q3)['count']; }

// Get current section and filter parameters
$section = $_GET['section'] ?? 'dashboard';

// Filter parameters for submissions
$submission_filter_status = $_GET['submission_status'] ?? '';
$submission_filter_type = $_GET['submission_type'] ?? '';
$submission_filter_unit = $_GET['submission_unit'] ?? '';
$submission_search = $_GET['submission_search'] ?? '';

// Filter parameters for materials
$materials_filter_unit = $_GET['materials_unit'] ?? '';
$materials_filter_type = $_GET['materials_type'] ?? '';
$materials_search = $_GET['materials_search'] ?? '';
$materials_sort = $_GET['materials_sort'] ?? 'date_desc';

// Filter parameters for assignments
$assignments_filter_unit = $_GET['assignments_unit'] ?? '';
$assignments_filter_type = $_GET['assignments_type'] ?? '';
$assignments_search = $_GET['assignments_search'] ?? '';
$assignments_sort = $_GET['assignments_sort'] ?? 'due_asc';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $pass_query = "SELECT password FROM users WHERE id = ?";
    $pass_stmt = mysqli_prepare($conn, $pass_query);
    mysqli_stmt_bind_param($pass_stmt, "i", $lecturer_id);
    mysqli_stmt_execute($pass_stmt);
    $pass_result = mysqli_stmt_get_result($pass_stmt);
    $user_pass = mysqli_fetch_assoc($pass_result);
    
    if (password_verify($current_password, $user_pass['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = ?, password_changed = 1 WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $lecturer_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $password_change_required = false;
                    echo '<div class="alert alert-success">✅ Password changed successfully! Please login again to continue.</div>';
                    echo '<meta http-equiv="refresh" content="2;url=../logout.php">';
                } else {
                    echo '<div class="alert alert-error">❌ Error updating password. Please try again.</div>';
                }
            } else {
                echo '<div class="alert alert-error">❌ New password must be at least 6 characters long!</div>';
            }
        } else {
            echo '<div class="alert alert-error">❌ New password and confirm password do not match!</div>';
        }
    } else {
        echo '<div class="alert alert-error">❌ Current password is incorrect!</div>';
    }
}

// Get lecturer's units for filters
$lecturer_units = [];
$units_result = mysqli_query($conn, "SELECT DISTINCT t.unit_code, aw.unit_name FROM timetable t JOIN academic_workload aw ON t.unit_code = aw.unit_code WHERE t.lecturer = '$lecturer_name'");
while($u = mysqli_fetch_assoc($units_result)) {
    $lecturer_units[$u['unit_code']] = $u['unit_name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard | Portal Assistant AI</title>
    <link rel="icon" type="image/jpeg" href="../Images/logo.jpg">
    <link rel="shortcut icon" href="../Images/logo.jpg">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --bg: #f8fafc;
            --white: #ffffff;
            --text-main: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --accent: #e0e7ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --research: #8b5cf6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text-main); line-height: 1.5; }

        header { background: var(--white); border-bottom: 1px solid var(--border); padding: 1rem 5%; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .branding { display: flex; align-items: center; gap: 15px; }
        .logoimg { height: 50px; border-radius: 8px; }
        .branding h1 { font-size: 1.4rem; color: var(--primary); font-weight: 800; margin: 0; }
        .branding small { display: block; font-size: 0.7rem; color: var(--text-light); font-weight: normal; }
        .user-info { display: flex; align-items: center; gap: 20px; }
        .user-badge { background: var(--accent); padding: 8px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; color: var(--primary); }
        .logout-btn { background: var(--danger); color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: 0.3s; }
        .logout-btn:hover { background: #dc2626; }

        nav { background: var(--primary); padding: 0 5%; }
        .nav-top { display: flex; gap: 10px; flex-wrap: wrap; }
        nav a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 14px 20px; font-size: 0.9rem; font-weight: 600; transition: 0.3s; border-bottom: 3px solid transparent; }
        nav a:hover { color: white; background: rgba(255,255,255,0.1); }
        nav a.active { color: white; background: rgba(255,255,255,0.15); border-bottom: 3px solid white; }

        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .lecturer-strip { background: var(--accent); padding: 12px 20px; border-radius: 10px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; color: var(--primary-dark); font-weight: 700; font-size: 0.85rem; flex-wrap: wrap; gap: 10px; }
        
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--white); padding: 25px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .stat-card h3 { margin: 0; font-size: 2rem; color: var(--primary); font-weight: 800; }
        .stat-card p { margin: 5px 0 0 0; color: var(--text-light); font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; }
        .stat-card .stat-icon { font-size: 2rem; margin-bottom: 10px; }

        .section-box { background: var(--white); border-radius: 12px; border: 1px solid var(--border); padding: 25px; margin-bottom: 30px; overflow-x: auto; }
        .section-box h2 { font-size: 1.2rem; margin-bottom: 20px; color: var(--text-main); display: flex; align-items: center; gap: 10px; }
        
        .info-note { background: #fffbeb; border-left: 4px solid var(--warning); padding: 15px; border-radius: 8px; font-size: 0.85rem; color: #92400e; margin-bottom: 20px; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        .data-table th { background: var(--bg); font-weight: 700; color: var(--text-main); }
        .data-table tr:hover { background: var(--accent); }

        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-block; transition: 0.3s; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #0b9e6e; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-info { background: var(--info); color: white; }
        .btn-info:hover { background: #2563eb; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
        .btn-clear { background: #64748b; color: white; }

        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .password-banner { background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%); border-left: 4px solid #f59e0b; padding: 20px; margin-bottom: 30px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .change-password-btn { background: #f59e0b; color: white; padding: 10px 24px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 90%; max-width: 600px; max-height: 85vh; overflow-y: auto; }
        .modal-content h3 { margin-bottom: 20px; color: var(--text-main); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; }
        .form-group textarea { min-height: 80px; }

        .material-card { background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .material-title { font-weight: 700; color: var(--primary); margin-bottom: 5px; font-size: 1rem; }
        .youtube-preview { margin-top: 10px; }
        .youtube-preview iframe { width: 100%; max-width: 300px; height: 170px; border-radius: 8px; }

        .no-data { text-align: center; padding: 40px; color: #666; background: #f9fafb; border-radius: 8px; }
        
        /* Enhanced Filter Bar Styles */
        .filter-bar { margin-bottom: 20px; padding: 20px; background: #f1f5f9; border-radius: 12px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 0.7rem; font-weight: 600; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-group select, .filter-group input { padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.85rem; min-width: 150px; background: white; }
        .filter-group select:focus, .filter-group input:focus { outline: none; border-color: var(--primary); }
        .filter-group .search-input { min-width: 200px; }
        .filter-buttons { display: flex; gap: 10px; align-items: center; }
        .filter-buttons button, .filter-buttons a { padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-block; }
        
        .stats-summary { display: flex; gap: 20px; margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #e0e7ff, #f1f5f9); border-radius: 10px; flex-wrap: wrap; }
        .stat-item { text-align: center; flex: 1; min-width: 100px; }
        .stat-item .stat-number { font-size: 1.5rem; font-weight: 800; color: var(--primary); }
        .stat-item .stat-label { font-size: 0.7rem; color: var(--text-light); text-transform: uppercase; }
        
        .marks-awarded { font-weight: 700; color: var(--success); }
        .marks-pending { color: var(--warning); font-weight: 600; }
        
        .type-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
        .type-cat { background: #fef3c7; color: #92400e; }
        .type-assignment { background: #d1fae5; color: #065f46; }
        .type-exam { background: #fee2e2; color: #991b1b; }

        footer { text-align: center; padding: 40px; color: var(--text-light); font-size: 0.85rem; }
        
        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-group select, .filter-group input { width: 100%; }
            .filter-buttons { justify-content: flex-end; }
        }
    </style>
</head>
<body>

<header>
    <div class="branding">
        <img src="../Images/logo.jpg" class="logoimg" alt="Logo" onerror="this.style.display='none'">
        <div>
            <h1>📚 Lecturer Portal</h1>
            <small>Academic Management System</small>
        </div>
    </div>
    <div class="user-info">
        <span class="user-badge">👨‍🏫 <?php echo htmlspecialchars($lecturer_name); ?></span>
        <a href="../logout.php" class="logout-btn">Sign Out</a>
    </div>
</header>

<nav>
    <div class="nav-top">
        <a href="?section=dashboard" class="<?php echo $section === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
        <a href="?section=my_courses" class="<?php echo $section === 'my_courses' ? 'active' : ''; ?>">My Courses</a>
        <a href="?section=materials" class="<?php echo $section === 'materials' ? 'active' : ''; ?>">Course Materials</a>
        <a href="?section=assignments" class="<?php echo $section === 'assignments' ? 'active' : ''; ?>">Assignments</a>
        <a href="?section=submissions" class="<?php echo $section === 'submissions' ? 'active' : ''; ?>">📋 Submissions</a>
        <a href="?section=students" class="<?php echo $section === 'students' ? 'active' : ''; ?>">My Students</a>
        <a href="?section=timetable" class="<?php echo $section === 'timetable' ? 'active' : ''; ?>">My Timetable</a>
    </div>
</nav>

<div class="container">
    <div class="lecturer-strip">
        <span>Welcome back, <strong><?php echo htmlspecialchars($lecturer_name); ?></strong></span>
        <span>Department of <strong><?php echo htmlspecialchars($lecturer_dept); ?></strong></span>
        <?php if($pending_registrations > 0): ?>
            <span style="background: var(--warning); padding: 4px 12px; border-radius: 20px;">⚠️ <?php echo $pending_registrations; ?> Pending Approvals</span>
        <?php endif; ?>
    </div>

    <?php if ($password_change_required): ?>
    <div class="password-banner">
        <div>
            <h3>🔐 Password Change Required</h3>
            <p>For security reasons, you must change your temporary password.</p>
        </div>
        <button onclick="openPasswordModal()" class="change-password-btn">Change Password Now</button>
    </div>
    <?php endif; ?>

    <?php
    switch($section) {
        case 'dashboard': ?>
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-icon">👨‍🎓</div>
                    <h3><?php echo $dept_students; ?></h3>
                    <p>Students in Department</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <h3><?php echo $assigned_units_count; ?></h3>
                    <p>Units Assigned to You</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✍️</div>
                    <h3 style="color: var(--warning);"><?php echo $pending_registrations; ?></h3>
                    <p>Pending Approvals</p>
                </div>
            </div>

            <h2 style="font-size: 1.1rem; margin-bottom: 20px;">Quick Actions</h2>
            <div class="dashboard-grid">
                <button onclick="openModal('pdfModal')" class="stat-card" style="text-align: left; cursor: pointer; border: none; width: 100%;">
                    <div class="stat-icon">📄</div>
                    <p>Upload Learning Material</p>
                </button>
                <button onclick="openModal('assignmentModal')" class="stat-card" style="text-align: left; cursor: pointer; border: none; width: 100%;">
                    <div class="stat-icon">📝</div>
                    <p>Upload Assessment</p>
                </button>
                <button onclick="openModal('manualMarksModal')" class="stat-card" style="text-align: left; cursor: pointer; border: none; width: 100%;">
                    <div class="stat-icon">🎯</div>
                    <p>Add Manual Marks</p>
                </button>
                <a href="?section=submissions" class="stat-card" style="text-align: left; cursor: pointer; text-decoration: none; display: block;">
                    <div class="stat-icon">📋</div>
                    <p>Grade Submissions</p>
                </a>
            </div>

            <div class="section-box">
                <div class="info-note">
                    <strong>📢 Quick Guide:</strong> Use the buttons above to upload course materials, create assessments, and grade student submissions. All changes are saved in real-time.
                </div>
            </div>
        <?php break;

        case 'my_courses':
            $courses_query = "SELECT t.*, aw.unit_name, aw.year_level, aw.semester_level, aw.offering_time 
                             FROM timetable t 
                             JOIN academic_workload aw ON t.unit_code = aw.unit_code 
                             WHERE t.lecturer = '$lecturer_name'
                             ORDER BY aw.year_level, aw.semester_level";
            $courses_result = mysqli_query($conn, $courses_query);
            $total_assigned = mysqli_num_rows($courses_result);
            ?>
            <div class="section-box">
                <h2>📚 My Assigned Courses</h2>
                <?php if ($total_assigned == 0): ?>
                    <div class="no-data">No courses assigned yet. Contact administrator.</div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>Unit Code</th><th>Unit Name</th><th>Year</th><th>Semester</th><th>Day</th><th>Time</th><th>Venue</th></tr>
                        </thead>
                        <tbody>
                            <?php while($course = mysqli_fetch_assoc($courses_result)): ?>
                            <tr>
                                <td><strong><?php echo $course['unit_code']; ?></strong></td>
                                <td><?php echo htmlspecialchars($course['unit_name']); ?></div>
                                <td><?php echo $course['year_level']; ?></div>
                                <td><?php echo $course['semester_level']; ?></div>
                                <td><?php echo $course['day_of_week'] ?? 'TBA'; ?></div>
                                <td><?php echo ($course['time_from'] ? $course['time_from'] . ' - ' . $course['time_to'] : 'TBA'); ?></div>
                                <td><?php echo $course['venue'] ?? 'TBA'; ?></div>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    <table>
                <?php endif; ?>
            </div>
        <?php break;

        case 'materials':
            // Build query with filters for materials
            $where_conditions = ["uploaded_by = '$lecturer_name'"];
            
            if (!empty($materials_filter_unit)) {
                $where_conditions[] = "unit_code = '$materials_filter_unit'";
            }
            if (!empty($materials_filter_type)) {
                if ($materials_filter_type == 'video') {
                    $where_conditions[] = "material_type = 'youtube'";
                } elseif ($materials_filter_type == 'document') {
                    $where_conditions[] = "(material_type != 'youtube' OR material_type IS NULL)";
                }
            }
            if (!empty($materials_search)) {
                $search = mysqli_real_escape_string($conn, $materials_search);
                $where_conditions[] = "(title LIKE '%$search%' OR description LIKE '%$search%' OR unit_code LIKE '%$search%')";
            }
            
            // Sorting
            $order_by = "";
            switch($materials_sort) {
                case 'title_asc': $order_by = "title ASC"; break;
                case 'title_desc': $order_by = "title DESC"; break;
                case 'date_asc': $order_by = "uploaded_at ASC"; break;
                case 'unit_asc': $order_by = "unit_code ASC"; break;
                default: $order_by = "uploaded_at DESC";
            }
            
            $where_clause = implode(" AND ", $where_conditions);
            $materials_query = "SELECT * FROM course_materials WHERE $where_clause ORDER BY $order_by";
            $materials_result = mysqli_query($conn, $materials_query);
            ?>
            <div class="section-box">
                <h2>📄 Course Materials Management</h2>
                
                <!-- Enhanced Filter Bar for Materials -->
                <div class="filter-bar">
                    <div class="filter-group">
                        <label>📂 Filter by Unit:</label>
                        <select onchange="location.href='?section=materials&materials_unit='+this.value+'&materials_type=<?php echo $materials_filter_type; ?>&materials_search=<?php echo urlencode($materials_search); ?>&materials_sort=<?php echo $materials_sort; ?>'">
                            <option value="">All Units</option>
                            <?php foreach($lecturer_units as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo ($materials_filter_unit == $code) ? 'selected' : ''; ?>>
                                    <?php echo $code . ' - ' . htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>📄 Filter by Type:</label>
                        <select onchange="location.href='?section=materials&materials_unit=<?php echo $materials_filter_unit; ?>&materials_type='+this.value+'&materials_search=<?php echo urlencode($materials_search); ?>&materials_sort=<?php echo $materials_sort; ?>'">
                            <option value="">All Types</option>
                            <option value="video" <?php echo ($materials_filter_type == 'video') ? 'selected' : ''; ?>>▶️ YouTube Videos</option>
                            <option value="document" <?php echo ($materials_filter_type == 'document') ? 'selected' : ''; ?>>📄 Documents</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>🔽 Sort by:</label>
                        <select onchange="location.href='?section=materials&materials_unit=<?php echo $materials_filter_unit; ?>&materials_type=<?php echo $materials_filter_type; ?>&materials_search=<?php echo urlencode($materials_search); ?>&materials_sort='+this.value">
                            <option value="date_desc" <?php echo ($materials_sort == 'date_desc') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="date_asc" <?php echo ($materials_sort == 'date_asc') ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="title_asc" <?php echo ($materials_sort == 'title_asc') ? 'selected' : ''; ?>>Title (A-Z)</option>
                            <option value="title_desc" <?php echo ($materials_sort == 'title_desc') ? 'selected' : ''; ?>>Title (Z-A)</option>
                            <option value="unit_asc" <?php echo ($materials_sort == 'unit_asc') ? 'selected' : ''; ?>>Unit Code (A-Z)</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>🔍 Search:</label>
                        <input type="text" class="search-input" id="materials_search" placeholder="Search by title, description..." value="<?php echo htmlspecialchars($materials_search); ?>">
                    </div>
                    
                    <div class="filter-buttons">
                        <button onclick="applyMaterialsFilters()" class="btn btn-primary">Apply Filters</button>
                        <?php if(!empty($materials_filter_unit) || !empty($materials_filter_type) || !empty($materials_search)): ?>
                            <a href="?section=materials" class="btn-clear" style="background:#64748b; color:white; padding:8px 16px; border-radius:6px; text-decoration:none;">🗑️ Clear Filters</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                    <button onclick="openModal('pdfModal')" class="btn btn-primary">📄 Upload PDF/Document</button>
                    <button onclick="openModal('youtubeModal')" class="btn btn-primary">▶️ Add YouTube Tutorial</button>
                </div>
                
                <?php if (mysqli_num_rows($materials_result) == 0): ?>
                    <div class="no-data">No materials found matching your filters. Click "Upload PDF/Document" to get started.</div>
                <?php else: ?>
                    <?php while($material = mysqli_fetch_assoc($materials_result)): ?>
                        <div class="material-card">
                            <div class="material-title"><?php echo htmlspecialchars($material['title']); ?></div>
                            <div><strong>Unit:</strong> <?php echo $material['unit_code']; ?></div>
                            <div><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($material['description'])); ?></div>
                            <div><strong>Uploaded:</strong> <?php echo date('d M Y H:i', strtotime($material['uploaded_at'])); ?></div>
                            <?php if ($material['material_type'] == 'youtube' && $material['youtube_url']): ?>
                                <div class="youtube-preview">
                                    <iframe src="https://www.youtube.com/embed/<?php echo $material['video_id']; ?>" frameborder="0" allowfullscreen></iframe>
                                </div>
                                <div><a href="<?php echo $material['youtube_url']; ?>" target="_blank">🔗 Open on YouTube</a></div>
                            <?php elseif ($material['file_path']): ?>
                                <div style="margin-top: 10px;">
                                    <a href="../uploads/materials/<?php echo $material['file_path']; ?>" target="_blank" class="btn btn-primary btn-sm">📥 Download Material</a>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top: 10px;">
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                                    <button type="submit" name="delete_material" class="btn btn-danger btn-sm" onclick="return confirm('Delete this material?')">🗑️ Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
            
            <script>
            function applyMaterialsFilters() {
                const searchValue = document.getElementById('materials_search').value;
                location.href = '?section=materials&materials_unit=<?php echo $materials_filter_unit; ?>&materials_type=<?php echo $materials_filter_type; ?>&materials_sort=<?php echo $materials_sort; ?>&materials_search=' + encodeURIComponent(searchValue);
            }
            document.getElementById('materials_search').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') applyMaterialsFilters();
            });
            </script>
        <?php break;

        case 'assignments':
            // Build query with filters for assignments
            $where_conditions = ["created_by = '$lecturer_name'"];
            
            if (!empty($assignments_filter_unit)) {
                $where_conditions[] = "unit_code = '$assignments_filter_unit'";
            }
            if (!empty($assignments_filter_type)) {
                $where_conditions[] = "assessment_type = '$assignments_filter_type'";
            }
            if (!empty($assignments_search)) {
                $search = mysqli_real_escape_string($conn, $assignments_search);
                $where_conditions[] = "(title LIKE '%$search%' OR description LIKE '%$search%' OR unit_code LIKE '%$search%')";
            }
            
            // Sorting
            $order_by = "";
            switch($assignments_sort) {
                case 'due_desc': $order_by = "due_date DESC"; break;
                case 'title_asc': $order_by = "title ASC"; break;
                case 'title_desc': $order_by = "title DESC"; break;
                case 'marks_asc': $order_by = "total_marks ASC"; break;
                case 'marks_desc': $order_by = "total_marks DESC"; break;
                default: $order_by = "due_date ASC";
            }
            
            $where_clause = implode(" AND ", $where_conditions);
            $assignments_query = "SELECT * FROM assignments WHERE $where_clause ORDER BY $order_by";
            $assignments_result = mysqli_query($conn, $assignments_query);
            ?>
            <div class="section-box">
                <h2>📝 Assessments Management (CATs, Assignments, Exams)</h2>
                
                <!-- Enhanced Filter Bar for Assignments -->
                <div class="filter-bar">
                    <div class="filter-group">
                        <label>📂 Filter by Unit:</label>
                        <select onchange="location.href='?section=assignments&assignments_unit='+this.value+'&assignments_type=<?php echo $assignments_filter_type; ?>&assignments_search=<?php echo urlencode($assignments_search); ?>&assignments_sort=<?php echo $assignments_sort; ?>'">
                            <option value="">All Units</option>
                            <?php foreach($lecturer_units as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo ($assignments_filter_unit == $code) ? 'selected' : ''; ?>>
                                    <?php echo $code . ' - ' . htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>📋 Filter by Type:</label>
                        <select onchange="location.href='?section=assignments&assignments_unit=<?php echo $assignments_filter_unit; ?>&assignments_type='+this.value+'&assignments_search=<?php echo urlencode($assignments_search); ?>&assignments_sort=<?php echo $assignments_sort; ?>'">
                            <option value="">All Types</option>
                            <option value="CAT" <?php echo ($assignments_filter_type == 'CAT') ? 'selected' : ''; ?>>📝 CAT (20 marks)</option>
                            <option value="Assignment" <?php echo ($assignments_filter_type == 'Assignment') ? 'selected' : ''; ?>>📚 Assignment (10 marks)</option>
                            <option value="Exam" <?php echo ($assignments_filter_type == 'Exam') ? 'selected' : ''; ?>>📖 Main Exam (70 marks)</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>🔽 Sort by:</label>
                        <select onchange="location.href='?section=assignments&assignments_unit=<?php echo $assignments_filter_unit; ?>&assignments_type=<?php echo $assignments_filter_type; ?>&assignments_search=<?php echo urlencode($assignments_search); ?>&assignments_sort='+this.value">
                            <option value="due_asc" <?php echo ($assignments_sort == 'due_asc') ? 'selected' : ''; ?>>Due Date (Earliest First)</option>
                            <option value="due_desc" <?php echo ($assignments_sort == 'due_desc') ? 'selected' : ''; ?>>Due Date (Latest First)</option>
                            <option value="title_asc" <?php echo ($assignments_sort == 'title_asc') ? 'selected' : ''; ?>>Title (A-Z)</option>
                            <option value="title_desc" <?php echo ($assignments_sort == 'title_desc') ? 'selected' : ''; ?>>Title (Z-A)</option>
                            <option value="marks_asc" <?php echo ($assignments_sort == 'marks_asc') ? 'selected' : ''; ?>>Marks (Low to High)</option>
                            <option value="marks_desc" <?php echo ($assignments_sort == 'marks_desc') ? 'selected' : ''; ?>>Marks (High to Low)</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>🔍 Search:</label>
                        <input type="text" class="search-input" id="assignments_search" placeholder="Search by title, unit..." value="<?php echo htmlspecialchars($assignments_search); ?>">
                    </div>
                    
                    <div class="filter-buttons">
                        <button onclick="applyAssignmentsFilters()" class="btn btn-primary">Apply Filters</button>
                        <?php if(!empty($assignments_filter_unit) || !empty($assignments_filter_type) || !empty($assignments_search)): ?>
                            <a href="?section=assignments" class="btn-clear" style="background:#64748b; color:white; padding:8px 16px; border-radius:6px; text-decoration:none;">🗑️ Clear Filters</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <button onclick="openModal('assignmentModal')" class="btn btn-primary" style="margin-bottom: 20px;">📝 Upload New Assessment</button>
                
                <?php if (mysqli_num_rows($assignments_result) == 0): ?>
                    <div class="no-data">No assessments found matching your filters. Click "Upload New Assessment" to get started.</div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>Title</th><th>Unit</th><th>Type</th><th>Due Date</th><th>Max Marks</th><th>Created</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php while($assignment = mysqli_fetch_assoc($assignments_result)): 
                                $type_class = ($assignment['assessment_type'] == 'CAT') ? 'type-cat' : (($assignment['assessment_type'] == 'Exam') ? 'type-exam' : 'type-assignment');
                                $due_date_class = (strtotime($assignment['due_date']) < time()) ? 'style="color: red;"' : '';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($assignment['title']); ?></strong><br><small><?php echo htmlspecialchars($assignment['description']); ?></small></td>
                                <td><?php echo $assignment['unit_code']; ?></div>
                                <td><span class="type-badge <?php echo $type_class; ?>"><?php echo $assignment['assessment_type']; ?></span></div>
                                <td><span <?php echo $due_date_class; ?>><?php echo date('d M Y', strtotime($assignment['due_date'])); ?></span></div>
                                <td><?php echo $assignment['total_marks']; ?></div>
                                <td><?php echo date('d M Y', strtotime($assignment['created_at'])); ?></div>
                                <td>
                                    <a href="../uploads/assignments/<?php echo $assignment['file_path']; ?>" target="_blank" class="btn btn-primary btn-sm">📥 Download</a>
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                        <button type="submit" name="delete_assignment" class="btn btn-danger btn-sm" onclick="return confirm('Delete this assessment?')">🗑️ Delete</button>
                                    </form>
                                 </div>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <script>
            function applyAssignmentsFilters() {
                const searchValue = document.getElementById('assignments_search').value;
                location.href = '?section=assignments&assignments_unit=<?php echo $assignments_filter_unit; ?>&assignments_type=<?php echo $assignments_filter_type; ?>&assignments_sort=<?php echo $assignments_sort; ?>&assignments_search=' + encodeURIComponent(searchValue);
            }
            document.getElementById('assignments_search').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') applyAssignmentsFilters();
            });
            </script>
        <?php break;

        case 'submissions':
            // Enhanced submissions with multiple filters
            $submissions_where = ["a.created_by = '$lecturer_name'", "s.assignment_id != 0"];
            
            if (!empty($submission_filter_unit)) {
                $submissions_where[] = "a.unit_code = '$submission_filter_unit'";
            }
            if (!empty($submission_filter_status)) {
                if ($submission_filter_status == 'graded') {
                    $submissions_where[] = "s.status = 'graded'";
                } elseif ($submission_filter_status == 'pending') {
                    $submissions_where[] = "s.status = 'submitted'";
                }
            }
            if (!empty($submission_filter_type)) {
                $submissions_where[] = "a.assessment_type = '$submission_filter_type'";
            }
            if (!empty($submission_search)) {
                $search = mysqli_real_escape_string($conn, $submission_search);
                $submissions_where[] = "(u.full_name LIKE '%$search%' OR u.reg_number LIKE '%$search%' OR a.title LIKE '%$search%')";
            }
            
            $submissions_where_clause = implode(" AND ", $submissions_where);
            $submissions_query = "SELECT s.*, a.title as assignment_title, a.total_marks, a.assessment_type, a.unit_code as assignment_unit_code, u.full_name, u.reg_number, u.email 
                                 FROM assignment_submissions s 
                                 JOIN assignments a ON s.assignment_id = a.id 
                                 JOIN users u ON s.student_reg = u.reg_number 
                                 WHERE $submissions_where_clause 
                                 ORDER BY s.submitted_at DESC";
            $submissions_result = mysqli_query($conn, $submissions_query);
            
            // Get submission statistics
            $stats_where = ["a.created_by = '$lecturer_name'"];
            if (!empty($submission_filter_unit)) {
                $stats_where[] = "a.unit_code = '$submission_filter_unit'";
            }
            $stats_where_clause = implode(" AND ", $stats_where);
            $stats_query = "SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN s.status = 'graded' THEN 1 ELSE 0 END) as graded,
                                SUM(CASE WHEN s.status = 'submitted' THEN 1 ELSE 0 END) as pending
                            FROM assignment_submissions s 
                            JOIN assignments a ON s.assignment_id = a.id 
                            WHERE $stats_where_clause";
            $stats_result = mysqli_query($conn, $stats_query);
            $stats = mysqli_fetch_assoc($stats_result);
            
            // Get students list for manual marks dropdown
            $students_list = mysqli_query($conn, "SELECT id, full_name, reg_number FROM users WHERE role='student' AND department='$lecturer_dept' ORDER BY full_name");
            ?>
            <div class="section-box">
                <h2>📋 Student Submissions</h2>
                
                <!-- Enhanced Filter Bar for Submissions -->
                <div class="filter-bar">
                    <div class="filter-group">
                        <label>📂 Filter by Unit:</label>
                        <select onchange="location.href='?section=submissions&submission_unit='+this.value+'&submission_status=<?php echo $submission_filter_status; ?>&submission_type=<?php echo $submission_filter_type; ?>&submission_search=<?php echo urlencode($submission_search); ?>'">
                            <option value="">All Units</option>
                            <?php foreach($lecturer_units as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo ($submission_filter_unit == $code) ? 'selected' : ''; ?>>
                                    <?php echo $code . ' - ' . htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>📋 Filter by Status:</label>
                        <select onchange="location.href='?section=submissions&submission_unit=<?php echo $submission_filter_unit; ?>&submission_status='+this.value+'&submission_type=<?php echo $submission_filter_type; ?>&submission_search=<?php echo urlencode($submission_search); ?>'">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo ($submission_filter_status == 'pending') ? 'selected' : ''; ?>>⏳ Pending Grading</option>
                            <option value="graded" <?php echo ($submission_filter_status == 'graded') ? 'selected' : ''; ?>>✅ Graded</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>📝 Filter by Type:</label>
                        <select onchange="location.href='?section=submissions&submission_unit=<?php echo $submission_filter_unit; ?>&submission_status=<?php echo $submission_filter_status; ?>&submission_type='+this.value+'&submission_search=<?php echo urlencode($submission_search); ?>'">
                            <option value="">All Types</option>
                            <option value="CAT" <?php echo ($submission_filter_type == 'CAT') ? 'selected' : ''; ?>>📝 CAT</option>
                            <option value="Assignment" <?php echo ($submission_filter_type == 'Assignment') ? 'selected' : ''; ?>>📚 Assignment</option>
                            <option value="Exam" <?php echo ($submission_filter_type == 'Exam') ? 'selected' : ''; ?>>📖 Exam</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>🔍 Search:</label>
                        <input type="text" class="search-input" id="submission_search" placeholder="Search by student name, reg number..." value="<?php echo htmlspecialchars($submission_search); ?>">
                    </div>
                    
                    <div class="filter-buttons">
                        <button onclick="applySubmissionsFilters()" class="btn btn-primary">Apply Filters</button>
                        <?php if(!empty($submission_filter_unit) || !empty($submission_filter_status) || !empty($submission_filter_type) || !empty($submission_search)): ?>
                            <a href="?section=submissions" class="btn-clear" style="background:#64748b; color:white; padding:8px 16px; border-radius:6px; text-decoration:none;">🗑️ Clear Filters</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistics Summary -->
                <?php if($stats['total'] > 0): ?>
                <div class="stats-summary">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Submissions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" style="color: var(--warning);"><?php echo $stats['pending']; ?></div>
                        <div class="stat-label">Pending Grading</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" style="color: var(--success);"><?php echo $stats['graded']; ?></div>
                        <div class="stat-label">Graded</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="filter-group" style="margin-bottom: 20px;">
                    <button onclick="openModal('manualMarksModal')" class="btn btn-warning">📝 + Add Manual Marks</button>
                </div>
                
                <?php if (mysqli_num_rows($submissions_result) == 0): ?>
                    <div class="no-data">
                        <p>📭 No student submissions found with the selected filters.</p>
                        <p>Click "Add Manual Marks" to record marks for physical submissions.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Reg Number</th>
                                    <th>Assessment</th>
                                    <th>Unit</th>
                                    <th>Type</th>
                                    <th>Submitted On</th>
                                    <th>Marks</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($sub = mysqli_fetch_assoc($submissions_result)): 
                                    $type_class = ($sub['assessment_type'] == 'CAT') ? 'type-cat' : (($sub['assessment_type'] == 'Exam') ? 'type-exam' : 'type-assignment');
                                    $type_icon = ($sub['assessment_type'] == 'CAT') ? '📝' : (($sub['assessment_type'] == 'Exam') ? '📖' : '📚');
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($sub['full_name']); ?></strong><br><small><?php echo $sub['email']; ?></small></td>
                                        <td><?php echo $sub['reg_number']; ?></div>
                                        <td><?php echo htmlspecialchars($sub['assignment_title']); ?></div>
                                        <td><?php echo $sub['assignment_unit_code']; ?></div>
                                        <td><span class="type-badge <?php echo $type_class; ?>"><?php echo $type_icon . ' ' . $sub['assessment_type']; ?></span></div>
                                        <td><?php echo date('d M Y H:i', strtotime($sub['submitted_at'])); ?></div>
                                        <td>
                                            <?php if($sub['obtained_marks'] !== null): ?>
                                                <span class="marks-awarded"><?php echo $sub['obtained_marks']; ?> / <?php echo $sub['total_marks']; ?></span>
                                            <?php else: ?>
                                                <span class="marks-pending">Not graded</span>
                                            <?php endif; ?>
                                         </div>
                                        <td>
                                            <?php if($sub['status'] == 'graded'): ?>
                                                <span class="status-badge" style="background: #d1fae5; color: #065f46;">✅ Graded</span>
                                            <?php else: ?>
                                                <span class="status-badge" style="background: #fed7aa; color: #92400e;">⏳ Pending</span>
                                            <?php endif; ?>
                                         </div>
                                        <td>
                                            <button onclick="openGradeModal(<?php echo $sub['id']; ?>, '<?php echo addslashes($sub['full_name']); ?>', '<?php echo addslashes($sub['assignment_title']); ?>', <?php echo $sub['total_marks']; ?>, <?php echo $sub['obtained_marks'] ?? 'null'; ?>, '<?php echo addslashes($sub['feedback'] ?? ''); ?>', '<?php echo $sub['assessment_type']; ?>')" class="btn btn-primary btn-sm">
                                                <?php echo ($sub['obtained_marks'] !== null) ? '✏️ Edit Marks' : '🎯 Award Marks'; ?>
                                            </button>
                                            <?php if($sub['file_path']): ?>
                                                <a href="../uploads/submissions/<?php echo $sub['file_path']; ?>" target="_blank" class="btn btn-info btn-sm">📄 View File</a>
                                            <?php endif; ?>
                                            <?php if($sub['submission_text'] && !empty(trim($sub['submission_text']))): ?>
                                                <button onclick="viewTextSubmission('<?php echo addslashes($sub['submission_text']); ?>')" class="btn btn-info btn-sm">📝 View Answer</button>
                                            <?php endif; ?>
                                         </div>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <script>
            function applySubmissionsFilters() {
                const searchValue = document.getElementById('submission_search').value;
                location.href = '?section=submissions&submission_unit=<?php echo $submission_filter_unit; ?>&submission_status=<?php echo $submission_filter_status; ?>&submission_type=<?php echo $submission_filter_type; ?>&submission_search=' + encodeURIComponent(searchValue);
            }
            document.getElementById('submission_search').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') applySubmissionsFilters();
            });
            </script>
        <?php break;

        case 'students':
            $students_query = "SELECT id, full_name, reg_number, email, phone, created_at FROM users WHERE role='student' AND department='$lecturer_dept' ORDER BY full_name";
            $students_result = mysqli_query($conn, $students_query);
            ?>
            <div class="section-box">
                <h2>👨‍🎓 Students in <?php echo htmlspecialchars($lecturer_dept); ?> Department</h2>
                <table class="data-table">
                    <thead>
                        <tr><th>Full Name</th><th>Registration Number</th><th>Email</th><th>Phone</th><th>Registered On</th></tr>
                    </thead>
                    <tbody>
                        <?php while($student = mysqli_fetch_assoc($students_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo $student['reg_number']; ?></div>
                            <td><?php echo $student['email']; ?></div>
                            <td><?php echo $student['phone'] ?? 'N/A'; ?></div>
                            <td><?php echo date('d M Y', strtotime($student['created_at'])); ?></div>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php break;

        case 'timetable':
            $timetable_query = "SELECT t.*, aw.unit_name FROM timetable t JOIN academic_workload aw ON t.unit_code = aw.unit_code WHERE t.lecturer = '$lecturer_name' ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), t.time_from";
            $timetable_result = mysqli_query($conn, $timetable_query);
            ?>
            <div class="section-box">
                <h2>📅 My Teaching Timetable</h2>
                <?php if (mysqli_num_rows($timetable_result) == 0): ?>
                    <div class="no-data">No scheduled classes yet.</div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>Unit Code</th><th>Course Title</th><th>Day</th><th>Time</th><th>Venue</th></tr>
                        </thead>
                        <tbody>
                            <?php while($tt = mysqli_fetch_assoc($timetable_result)): ?>
                            <tr>
                                <td><strong><?php echo $tt['unit_code']; ?></strong></td>
                                <td><?php echo htmlspecialchars($tt['unit_name']); ?></div>
                                <td><?php echo $tt['day_of_week']; ?></div>
                                <td><?php echo $tt['time_from'] . ' - ' . $tt['time_to']; ?></div>
                                <td><?php echo $tt['venue']; ?></div>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php break;
    }
    ?>
</div>

<!-- PDF Upload Modal -->
<div id="pdfModal" class="modal">
    <div class="modal-content">
        <h3>📄 Upload Learning Material</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="upload_material" value="1">
            <div class="form-group">
                <label>Select Unit *</label>
                <select name="unit_code" required>
                    <option value="">-- Select Unit --</option>
                    <?php 
                    $units = mysqli_query($conn, "SELECT DISTINCT t.unit_code, aw.unit_name FROM timetable t JOIN academic_workload aw ON t.unit_code = aw.unit_code WHERE t.lecturer = '$lecturer_name'");
                    while($u = mysqli_fetch_assoc($units)): ?>
                        <option value="<?php echo $u['unit_code']; ?>"><?php echo $u['unit_code'] . ' - ' . $u['unit_name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required placeholder="e.g., Introduction to Programming">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Brief description of the material"></textarea>
            </div>
            <div class="form-group">
                <label>File (PDF, DOC, PPT, JPG, PNG) *</label>
                <input type="file" name="material_file" required accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.png">
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success">Upload</button>
                <button type="button" onclick="closeModal('pdfModal')" class="btn btn-danger">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- YouTube Modal -->
<div id="youtubeModal" class="modal">
    <div class="modal-content">
        <h3>▶️ Add YouTube Tutorial</h3>
        <form method="POST">
            <input type="hidden" name="upload_youtube" value="1">
            <div class="form-group">
                <label>Select Unit *</label>
                <select name="unit_code" required>
                    <option value="">-- Select Unit --</option>
                    <?php 
                    mysqli_data_seek($units, 0);
                    while($u = mysqli_fetch_assoc($units)): ?>
                        <option value="<?php echo $u['unit_code']; ?>"><?php echo $u['unit_code'] . ' - ' . $u['unit_name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required placeholder="e.g., Complete Python Tutorial">
            </div>
            <div class="form-group">
                <label>YouTube URL *</label>
                <input type="url" name="youtube_url" required placeholder="https://www.youtube.com/watch?v=...">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="What will students learn?"></textarea>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success">Add YouTube Link</button>
                <button type="button" onclick="closeModal('youtubeModal')" class="btn btn-danger">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Assignment Modal -->
<div id="assignmentModal" class="modal">
    <div class="modal-content">
        <h3>📝 Upload Assessment (CAT/Assignment/Exam)</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="upload_assignment" value="1">
            <div class="form-group">
                <label>Select Unit *</label>
                <select name="unit_code" required>
                    <option value="">-- Select Unit --</option>
                    <?php 
                    mysqli_data_seek($units, 0);
                    while($u = mysqli_fetch_assoc($units)): ?>
                        <option value="<?php echo $u['unit_code']; ?>"><?php echo $u['unit_code'] . ' - ' . $u['unit_name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Assessment Type *</label>
                <select name="assessment_type" required id="assessment_type" onchange="updateMaxMarks()">
                    <option value="">-- Select Type --</option>
                    <option value="CAT">📝 CAT (Continuous Assessment Test) - Max 20 marks</option>
                    <option value="Assignment">📚 Assignment - Max 10 marks</option>
                    <option value="Exam">📖 Main Exam - Max 70 marks</option>
                </select>
            </div>
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required placeholder="e.g., CAT 1 - Introduction to Programming">
            </div>
            <div class="form-group">
                <label>Description *</label>
                <textarea name="description" required placeholder="Instructions and requirements"></textarea>
            </div>
            <div class="form-group">
                <label>Due Date *</label>
                <input type="date" name="due_date" required>
            </div>
            <div class="form-group">
                <label>Total Marks</label>
                <input type="number" id="total_marks" readonly style="background: #f3f4f6;" value="0">
                <small id="marks_info">Select assessment type to see max marks</small>
            </div>
            <div class="form-group">
                <label>Assessment File (PDF, DOC, DOCX, ZIP) *</label>
                <input type="file" name="assignment_file" required accept=".pdf,.doc,.docx,.zip">
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success">Upload</button>
                <button type="button" onclick="closeModal('assignmentModal')" class="btn btn-danger">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Manual Marks Modal -->
<div id="manualMarksModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h3>📝 Add Manual Marks (Physical Submissions)</h3>
        <form method="POST" action="">
            <input type="hidden" name="save_manual_marks" value="1">
            <div class="form-group">
                <label>Select Student *</label>
                <select name="student_reg" required>
                    <option value="">-- Select Student --</option>
                    <?php 
                    $students_list = mysqli_query($conn, "SELECT id, full_name, reg_number FROM users WHERE role='student' AND department='$lecturer_dept' ORDER BY full_name");
                    while($student = mysqli_fetch_assoc($students_list)): ?>
                        <option value="<?php echo $student['reg_number']; ?>">
                            <?php echo $student['full_name'] . ' (' . $student['reg_number'] . ')'; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Select Unit *</label>
                <select name="unit_code" required>
                    <option value="">-- Select Unit --</option>
                    <?php foreach($lecturer_units as $code => $name): ?>
                        <option value="<?php echo $code; ?>"><?php echo $code . ' - ' . $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Assessment Type *</label>
                <select name="assessment_type" required id="manual_assessment_type" onchange="updateManualMaxMarks()">
                    <option value="">-- Select Type --</option>
                    <option value="CAT">📝 CAT (Max 20 marks)</option>
                    <option value="Assignment">📚 Assignment (Max 10 marks)</option>
                    <option value="Exam">📖 Main Exam (Max 70 marks)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Assessment Name *</label>
                <input type="text" name="assessment_name" required placeholder="e.g., CAT 1, Assignment 2, Final Exam">
            </div>
            
            <div class="form-group">
                <label>Total Marks</label>
                <input type="number" id="manual_total_marks" readonly style="background: #f3f4f6;" value="0">
            </div>
            
            <div class="form-group">
                <label>Obtained Marks *</label>
                <input type="number" name="obtained_marks" id="manual_obtained_marks" required min="0" step="0.5">
            </div>
            
            <div class="form-group">
                <label>Feedback (Optional)</label>
                <textarea name="feedback" rows="3" placeholder="Add comments or feedback..."></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">Save Marks</button>
                <button type="button" onclick="closeModal('manualMarksModal')" class="btn btn-danger">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Grade Modal -->
<div id="gradeModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h3>🎯 Award Marks</h3>
        <form method="POST" action="">
            <input type="hidden" name="submission_id" id="grade_submission_id">
            <input type="hidden" name="grade_submission" value="1">
            
            <div class="form-group">
                <label>Student Name</label>
                <input type="text" id="grade_student_name" readonly style="background: #f3f4f6;">
            </div>
            
            <div class="form-group">
                <label>Assessment</label>
                <input type="text" id="grade_assignment_title" readonly style="background: #f3f4f6;">
            </div>
            
            <div class="form-group">
                <label>Assessment Type</label>
                <input type="text" id="grade_assessment_type" readonly style="background: #f3f4f6;">
            </div>
            
            <div class="form-group">
                <label>Total Marks</label>
                <input type="text" id="grade_total_marks" readonly style="background: #f3f4f6;">
            </div>
            
            <div class="form-group">
                <label>Obtained Marks *</label>
                <input type="number" name="obtained_marks" id="grade_obtained_marks" required min="0" step="0.5" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                <small id="marks_warning" style="color: #f59e0b;"></small>
            </div>
            
            <div class="form-group">
                <label>Feedback (Optional)</label>
                <textarea name="feedback" id="grade_feedback" rows="4" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;" placeholder="Provide feedback to the student..."></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">Save Marks</button>
                <button type="button" onclick="closeGradeModal()" class="btn btn-danger">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Text Submission Modal -->
<div id="textSubmissionModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <h3>📝 Student's Answer</h3>
        <div id="text_submission_content" style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 15px 0; max-height: 400px; overflow-y: auto; white-space: pre-wrap; font-family: inherit;"></div>
        <div style="display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeTextSubmissionModal()" class="btn btn-danger">Close</button>
        </div>
    </div>
</div>

<!-- Password Change Modal -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <h3>🔐 Change Your Password</h3>
        <form method="POST">
            <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
            <div class="form-group"><label>New Password (min 6 characters)</label><input type="password" name="new_password" required minlength="6"></div>
            <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="change_password" class="btn btn-success">Change Password</button>
                <button type="button" onclick="closePasswordModal()" class="btn btn-danger">Cancel</button>
            </div>
        </form>
    </div>
</div>

<footer>
    &copy; 2026 Portal Assistant AI | Lecturer Management System
</footer>

<script>
// Modal Functions - Make sure these are globally accessible
function openModal(modalId) { 
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex'; 
    } else {
        console.error('Modal not found: ' + modalId);
    }
}

function closeModal(modalId) { 
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none'; 
    }
}

function openPasswordModal() { 
    var modal = document.getElementById('passwordModal');
    if (modal) modal.style.display = 'flex'; 
}

function closePasswordModal() { 
    var modal = document.getElementById('passwordModal');
    if (modal) modal.style.display = 'none'; 
}

function closeGradeModal() {
    var modal = document.getElementById('gradeModal');
    if (modal) modal.style.display = 'none';
}

function closeTextSubmissionModal() {
    var modal = document.getElementById('textSubmissionModal');
    if (modal) modal.style.display = 'none';
}

function updateMaxMarks() {
    const type = document.getElementById('assessment_type').value;
    const marksField = document.getElementById('total_marks');
    const infoSpan = document.getElementById('marks_info');
    
    if (type === 'CAT') {
        marksField.value = 20;
        infoSpan.innerHTML = '📝 CATs are marked out of 20 marks';
        marksField.style.background = '#d1fae5';
    } else if (type === 'Assignment') {
        marksField.value = 10;
        infoSpan.innerHTML = '📚 Assignments are marked out of 10 marks';
        marksField.style.background = '#d1fae5';
    } else if (type === 'Exam') {
        marksField.value = 70;
        infoSpan.innerHTML = '📖 Main Exams are marked out of 70 marks';
        marksField.style.background = '#d1fae5';
    } else {
        marksField.value = '';
        infoSpan.innerHTML = 'Select assessment type to see max marks';
        marksField.style.background = '#f3f4f6';
    }
}

function openGradeModal(id, studentName, assignmentTitle, totalMarks, obtainedMarks, feedback, assessmentType) {
    document.getElementById('grade_submission_id').value = id;
    document.getElementById('grade_student_name').value = studentName;
    document.getElementById('grade_assignment_title').value = assignmentTitle;
    document.getElementById('grade_assessment_type').value = assessmentType;
    document.getElementById('grade_total_marks').value = totalMarks;
    document.getElementById('grade_obtained_marks').value = obtainedMarks !== null ? obtainedMarks : '';
    document.getElementById('grade_feedback').value = feedback !== 'null' ? feedback : '';
    
    const marksInput = document.getElementById('grade_obtained_marks');
    const warningSpan = document.getElementById('marks_warning');
    
    marksInput.oninput = function() {
        const value = parseFloat(this.value);
        if (value > totalMarks) {
            warningSpan.innerHTML = '⚠️ Warning: Marks exceed maximum (' + totalMarks + ' marks)!';
            this.style.borderColor = '#ef4444';
        } else {
            warningSpan.innerHTML = '';
            this.style.borderColor = '#e2e8f0';
        }
    };
    
    marksInput.max = totalMarks;
    
    var modal = document.getElementById('gradeModal');
    if (modal) modal.style.display = 'flex';
}

function viewTextSubmission(text) {
    document.getElementById('text_submission_content').innerHTML = text.replace(/\n/g, '<br>');
    var modal = document.getElementById('textSubmissionModal');
    if (modal) modal.style.display = 'flex';
}

function updateManualMaxMarks() {
    const type = document.getElementById('manual_assessment_type').value;
    const marksField = document.getElementById('manual_total_marks');
    const obtainedField = document.getElementById('manual_obtained_marks');
    
    if (type === 'CAT') {
        marksField.value = 20;
        marksField.style.background = '#d1fae5';
        if (obtainedField.value > 20) obtainedField.value = 20;
        obtainedField.max = 20;
    } else if (type === 'Assignment') {
        marksField.value = 10;
        marksField.style.background = '#d1fae5';
        if (obtainedField.value > 10) obtainedField.value = 10;
        obtainedField.max = 10;
    } else if (type === 'Exam') {
        marksField.value = 70;
        marksField.style.background = '#d1fae5';
        if (obtainedField.value > 70) obtainedField.value = 70;
        obtainedField.max = 70;
    } else {
        marksField.value = '';
        marksField.style.background = '#f3f4f6';
        obtainedField.max = '';
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['pdfModal', 'youtubeModal', 'assignmentModal', 'passwordModal', 'gradeModal', 'textSubmissionModal', 'manualMarksModal'];
    modals.forEach(id => {
        const modal = document.getElementById(id);
        if (modal && event.target == modal) {
            modal.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const assignmentDueDate = document.querySelector('#assignmentModal input[name="due_date"]');
    if (assignmentDueDate) {
        assignmentDueDate.setAttribute('min', today);
        assignmentDueDate.setAttribute('title', 'Due date cannot be in the past');
    }
    
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                const newDueDate = document.querySelector('#assignmentModal input[name="due_date"]');
                if (newDueDate && !newDueDate.hasAttribute('min')) {
                    newDueDate.setAttribute('min', today);
                    newDueDate.setAttribute('title', 'Due date cannot be in the past');
                }
            }
        });
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
    
    const assignmentForm = document.querySelector('#assignmentModal form');
    if (assignmentForm) {
        assignmentForm.addEventListener('submit', function(e) {
            const dueDateInput = this.querySelector('input[name="due_date"]');
            if (dueDateInput) {
                const selectedDate = new Date(dueDateInput.value);
                const currentDate = new Date();
                currentDate.setHours(0, 0, 0, 0);
                
                if (selectedDate < currentDate) {
                    e.preventDefault();
                    alert('❌ Due date cannot be in the past! Please select a future date for the assignment/CAT.');
                    dueDateInput.focus();
                    return false;
                }
            }
        });
    }
});
</script>
</body>
</html>