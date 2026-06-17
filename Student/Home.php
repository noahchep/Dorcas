<?php
session_start();

// 1. Check if user_id exists (Are they logged in?)
// 2. Check if the role is 'student' (Are they allowed here?)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    
    // Optional: If they are an admin trying to sneak in, send them to their own dashboard
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: ../admin/admin_dashboard.php?error=access_denied");
    } else {
        // Otherwise, send to login
        header("Location: ../login.php");
    }
    exit();
}

/* --- 2. DB CONNECTION --- */
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

/* --- 3. FETCH STUDENT DATA --- */
$user_id = $_SESSION['user_id'];
$student_reg = $_SESSION['reg_number'];
$student_dept = $_SESSION['department'];

$sql = "SELECT full_name, reg_number, password, department, created_at FROM users WHERE id = ? AND role = 'student'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) !== 1) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$student = mysqli_fetch_assoc($result);

// Determine student year level based on registration number or creation date
function getStudentYearLevel($reg_number, $created_at) {
    if (preg_match('/\/(\d{4})\//', $reg_number, $matches)) {
        $admission_year = intval($matches[1]);
        $current_year = date('Y');
        $year_diff = $current_year - $admission_year;
        
        if ($year_diff == 0) return 'First Year';
        if ($year_diff == 1) return 'Second Year';
        if ($year_diff == 2) return 'Third Year';
        if ($year_diff >= 3) return 'Fourth Year';
    }
    
    $created_year = date('Y', strtotime($created_at));
    $current_year = date('Y');
    $year_diff = $current_year - $created_year;
    
    if ($year_diff == 0) return 'First Year';
    if ($year_diff == 1) return 'Second Year';
    if ($year_diff == 2) return 'Third Year';
    return 'Fourth Year';
}

$student_year_level = getStudentYearLevel($student['reg_number'], $student['created_at']);

// Security Check: Is the student still using the default password?
$is_default_password = password_verify($student['reg_number'], $student['password']);

$name_parts = explode(" ", $student['full_name']);
$fname = $name_parts[0] ?? 'Student';

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_assignment'])) {
        $assignment_id = intval($_POST['assignment_id']);
        $submission_text = mysqli_real_escape_string($conn, $_POST['submission_text']);
        
        $file_path = null;
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == 0) {
            $target_dir = "../uploads/submissions/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $safe_reg = str_replace('/', '_', $student_reg);
            $file_ext = pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION);
            $file_name = time() . '_' . $safe_reg . '_' . $assignment_id . '.' . $file_ext;
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $target_file)) {
                $file_path = $file_name;
            } else {
                echo '<div class="alert-error">❌ Error uploading file.</div>';
            }
        }
        
        $insert = "INSERT INTO assignment_submissions (assignment_id, student_reg, submission_text, file_path, submitted_at, status) 
                   VALUES ($assignment_id, '$student_reg', '$submission_text', " . ($file_path ? "'$file_path'" : "NULL") . ", NOW(), 'submitted')";
        if (mysqli_query($conn, $insert)) {
            echo '<div class="alert-success">✅ Assignment submitted successfully!</div>';
        } else {
            echo '<div class="alert-error">❌ Error submitting assignment: ' . $conn->error . '</div>';
        }
    }
}

// Get current page
$page = $_GET['page'] ?? 'dashboard';

// Get filter and sort parameters
$materials_sort = $_GET['materials_sort'] ?? 'date_desc';
$materials_unit_filter = $_GET['materials_unit'] ?? '';
$materials_type_filter = $_GET['materials_type'] ?? '';

$assignments_sort = $_GET['assignments_sort'] ?? 'due_asc';
$assignments_unit_filter = $_GET['assignments_unit'] ?? '';
$assignments_status_filter = $_GET['assignments_status'] ?? '';

$submissions_sort = $_GET['submissions_sort'] ?? 'date_desc';
$submissions_unit_filter = $_GET['submissions_unit'] ?? '';
$submissions_status_filter = $_GET['submissions_status'] ?? '';

$results_sort = $_GET['results_sort'] ?? 'unit_asc';

// Get student's enrolled units (from registered_courses)
$student_units_query = "SELECT DISTINCT unit_code FROM registered_courses WHERE student_reg_no = '$student_reg' AND status = 'Confirmed'";
$student_units_result = mysqli_query($conn, $student_units_query);
$student_units = [];
$unit_options = [];
while($unit = mysqli_fetch_assoc($student_units_result)) {
    $student_units[] = $unit['unit_code'];
    // Get unit name for display
    $unit_name_query = mysqli_query($conn, "SELECT unit_name FROM academic_workload WHERE unit_code = '{$unit['unit_code']}'");
    $unit_name = mysqli_fetch_assoc($unit_name_query);
    $unit_options[$unit['unit_code']] = $unit_name['unit_name'] ?? $unit['unit_code'];
}

$units_list = !empty($student_units) ? "'" . implode("','", $student_units) . "'" : "''";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Portal Assistant AI</title>
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
            --warning-bg: #fff7ed;
            --warning-text: #9a3412;
            --warning-border: #fdba74;
            --success: #10b981;
            --danger: #ef4444;
        }

        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text-main); margin: 0; line-height: 1.5; }

        header { background: var(--white); border-bottom: 1px solid var(--border); padding: 1rem 5%; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .branding { display: flex; align-items: center; gap: 15px; }
        .logoimg { height: 50px; border-radius: 8px; }
        .branding h1 { margin: 0; font-size: 1.4rem; color: var(--primary); font-weight: 800; }
        .branding small { color: var(--text-light); display: block; font-size: 0.85rem; }

        nav { background: var(--primary); padding: 0 5%; display: flex; gap: 10px; flex-wrap: wrap; }
        nav a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 14px 20px; font-size: 0.9rem; font-weight: 600; transition: 0.3s; border-bottom: 3px solid transparent; }
        nav a:hover { color: white; background: rgba(255,255,255,0.1); }
        nav a.active { color: white; border-bottom: 3px solid white; background: rgba(255,255,255,0.15); }

        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .student-strip { background: var(--accent); padding: 15px 25px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; color: var(--primary-dark); font-weight: 700; flex-wrap: wrap; gap: 10px; }

        .security-alert { background: var(--warning-bg); border: 1px solid var(--warning-border); color: var(--warning-text); padding: 15px 25px; border-radius: 12px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .security-alert button { background: var(--warning-text); color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: 600; cursor: pointer; }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px; margin-bottom: 30px; }
        
        .card { background: var(--white); border-radius: 12px; border: 1px solid var(--border); overflow: hidden; }
        .card-header { background: var(--primary); color: white; padding: 15px 20px; font-weight: 700; }
        .card-body { padding: 20px; max-height: 500px; overflow-y: auto; }
        
        .material-item, .assignment-item { border-bottom: 1px solid var(--border); padding: 15px 0; }
        .material-item:last-child, .assignment-item:last-child { border-bottom: none; }
        .material-title, .assignment-title { font-weight: 700; color: var(--primary); margin-bottom: 5px; }
        .material-meta, .assignment-meta { font-size: 0.75rem; color: var(--text-light); margin-bottom: 8px; }
        
        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
        .btn-clear { background: #64748b; color: white; }

        .alert-success { background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 90%; max-width: 800px; max-height: 85vh; overflow-y: auto; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-family: inherit; }
        .form-group textarea { resize: vertical; }

        .submission-status { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
        .status-submitted { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fed7aa; color: #92400e; }
        .status-graded { background: #e0e7ff; color: #3730a3; }
        .status-late { background: #fee2e2; color: #991b1b; }

        .filter-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap; padding: 15px; background: #f1f5f9; border-radius: 8px; }
        .filter-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .filter-group label { font-size: 0.75rem; font-weight: 600; color: var(--text-light); }
        .filter-group select, .filter-group input { padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.8rem; background: white; cursor: pointer; }
        .filter-group .clear-btn { background: #64748b; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.75rem; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        .data-table th { background: var(--bg); font-weight: 700; }

        .results-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; padding: 20px; background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); border-radius: 12px; }
        .result-stat { text-align: center; padding: 10px; background: white; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .result-stat .label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .result-stat .value { font-size: 1.8rem; font-weight: 800; color: #4f46e5; }
        .result-stat .total { font-size: 0.8rem; color: #94a3b8; }

        .grade-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 20px; border-left: 4px solid var(--primary); }
        .grade-title { font-weight: 700; color: var(--primary); margin-bottom: 10px; }
        .grade-details { display: flex; gap: 20px; flex-wrap: wrap; }
        .grade-item { flex: 1; min-width: 120px; }
        .grade-item .label { font-size: 0.7rem; color: #64748b; }
        .grade-item .score { font-size: 1.2rem; font-weight: 700; }

        footer { text-align: center; padding: 40px; color: var(--text-light); font-size: 0.85rem; }
        
        .no-data { text-align: center; padding: 40px; color: #666; background: #f9fafb; border-radius: 8px; }
        
        /* Chatbot Styles */
        #chat-fab { position: fixed; bottom: 30px; right: 30px; background: var(--primary); color: white; width: 55px; height: 55px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 10px 15px rgba(79, 70, 229, 0.4); z-index: 100; font-size: 1.5rem; border: none; }
        #chat-window { position: fixed; bottom: 100px; right: 30px; width: 350px; height: 500px; background: white; border-radius: 16px; display: none; flex-direction: column; box-shadow: 0 20px 25px rgba(0,0,0,0.1); border: 1px solid var(--border); z-index: 101; overflow: hidden; }
        #chat-content { flex: 1; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; background: #f9fafb; }
        .msg { max-width: 85%; padding: 10px 14px; border-radius: 12px; font-size: 0.85rem; }
        .msg-bot { align-self: flex-start; background: #f1f5f9; border-left: 3px solid var(--primary); }
        .msg-user { align-self: flex-end; background: var(--primary); color: white; }
        .chat-input-area { padding: 12px; border-top: 1px solid var(--border); display: flex; gap: 8px; background: white; }
        .chat-input-area input { flex: 1; border-radius: 20px; padding: 8px 15px; border: 1px solid var(--border); outline: none; }
        .chat-input-area button { background: var(--primary); color: white; border-radius: 50%; border: none; width: 35px; height: 35px; cursor: pointer; }
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
</header>

<nav>
    <a href="home.php?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
    <a href="home.php?page=materials" class="<?php echo $page === 'materials' ? 'active' : ''; ?>">📚 Course Materials</a>
    <a href="home.php?page=assignments" class="<?php echo $page === 'assignments' ? 'active' : ''; ?>">📝 Assignments</a>
    <a href="home.php?page=my_submissions" class="<?php echo $page === 'my_submissions' ? 'active' : ''; ?>">📋 My Submissions</a>
    <a href="home.php?page=my_results" class="<?php echo $page === 'my_results' ? 'active' : ''; ?>">📊 My Results</a>
    <a href="personal_information.php">👤 Information Update</a>
    <a href="teaching_timetable.php">📅 Timetables</a>
    <a href="registration.php">📖 Course Registration</a>        
    <a href="../logout.php">🚪 Sign Out</a>
</nav>

<div class="container">
    <div class="student-strip">
        <span>Welcome back, <?php echo htmlspecialchars($fname); ?></span>
        <span><?php echo htmlspecialchars($student['reg_number']); ?> | <?php echo htmlspecialchars($student_dept); ?> Department | <?php echo $student_year_level; ?></span>
    </div>

    <?php if ($is_default_password): ?>
    <div id="securityAlertBox" class="security-alert">
        <span>⚠️ <strong>Security Notice:</strong> You are still using your registration number as your password. Change it now to protect your personal data.</span>
        <button onclick="window.location.href='change_password.php'">Change Password</button>
    </div>
    <?php endif; ?>

    <?php
    switch($page) {
        case 'dashboard': ?>
            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">📚 Recent Learning Materials</div>
                    <div class="card-body">
                        <?php
                        if (!empty($student_units)) {
                            $recent_materials = mysqli_query($conn, "SELECT * FROM course_materials WHERE unit_code IN ($units_list) ORDER BY uploaded_at DESC LIMIT 5");
                            if (mysqli_num_rows($recent_materials) > 0):
                                while($material = mysqli_fetch_assoc($recent_materials)):
                        ?>
                            <div class="material-item">
                                <div class="material-title"><?php echo htmlspecialchars($material['title']); ?></div>
                                <div class="material-meta">Unit: <?php echo $material['unit_code']; ?> | Uploaded: <?php echo date('d M Y', strtotime($material['uploaded_at'])); ?></div>
                                <?php if ($material['material_type'] == 'youtube'): ?>
                                    <a href="<?php echo $material['youtube_url']; ?>" target="_blank" class="btn btn-primary btn-sm">▶️ Watch Tutorial</a>
                                <?php else: ?>
                                    <a href="../uploads/materials/<?php echo $material['file_path']; ?>" target="_blank" class="btn btn-primary btn-sm">📥 Download Material</a>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; else: ?>
                            <p>No materials uploaded yet.</p>
                        <?php endif;
                        } else { echo '<p>You are not registered for any units yet. Please complete course registration.</p>'; } ?>
                        <div style="margin-top: 15px;"><a href="home.php?page=materials" class="btn btn-primary">View All Materials →</a></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">📝 Pending Assignments</div>
                    <div class="card-body">
                        <?php
                        if (!empty($student_units)) {
                            $pending_assignments = mysqli_query($conn, "SELECT a.* FROM assignments a WHERE a.unit_code IN ($units_list) AND a.due_date >= CURDATE() AND NOT EXISTS (SELECT 1 FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_reg = '$student_reg') ORDER BY a.due_date ASC LIMIT 5");
                            if (mysqli_num_rows($pending_assignments) > 0):
                                while($assignment = mysqli_fetch_assoc($pending_assignments)):
                        ?>
                            <div class="assignment-item">
                                <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                <div class="assignment-meta">Due: <?php echo date('d M Y', strtotime($assignment['due_date'])); ?> | Marks: <?php echo $assignment['total_marks']; ?></div>
                                <button onclick="openAssignmentModal(<?php echo $assignment['id']; ?>, '<?php echo addslashes($assignment['title']); ?>')" class="btn btn-success btn-sm">Submit Assignment</button>
                            </div>
                        <?php endwhile; else: ?>
                            <p>No pending assignments. Great job!</p>
                        <?php endif;
                        } else { echo '<p>Complete course registration to see assignments.</p>'; } ?>
                        <div style="margin-top: 15px;"><a href="home.php?page=assignments" class="btn btn-primary">View All Assignments →</a></div>
                    </div>
                </div>
            </div>
        <?php break;

        case 'materials':
            // Build WHERE clause with filters
            $where_conditions = ["unit_code IN ($units_list)"];
            if (!empty($materials_unit_filter)) {
                $where_conditions[] = "unit_code = '$materials_unit_filter'";
            }
            if (!empty($materials_type_filter)) {
                $where_conditions[] = "material_type = '$materials_type_filter'";
            }
            $where_clause = implode(" AND ", $where_conditions);
            
            // Apply sorting
            $order_by = "";
            switch($materials_sort) {
                case 'date_asc': $order_by = "uploaded_at ASC"; break;
                case 'title_asc': $order_by = "title ASC"; break;
                case 'title_desc': $order_by = "title DESC"; break;
                case 'unit_asc': $order_by = "unit_code ASC"; break;
                default: $order_by = "uploaded_at DESC";
            }
            
            $materials_query = "SELECT * FROM course_materials WHERE $where_clause ORDER BY $order_by";
            $materials_result = mysqli_query($conn, $materials_query);
            ?>
            <div class="card">
                <div class="card-header">📚 All Course Materials</div>
                <div class="card-body">
                    <?php if (empty($student_units)): ?>
                        <p>You are not registered for any units yet. Please complete <a href="registration.php">course registration</a> first.</p>
                    <?php else: ?>
                        <!-- Enhanced Filter Bar -->
                        <div class="filter-bar">
                            <div class="filter-group">
                                <label>📂 Filter by Unit:</label>
                                <select onchange="location.href='home.php?page=materials&materials_sort=<?php echo $materials_sort; ?>&materials_unit='+this.value+'&materials_type=<?php echo $materials_type_filter; ?>'">
                                    <option value="">All Units</option>
                                    <?php foreach($unit_options as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo $materials_unit_filter == $code ? 'selected' : ''; ?>>
                                            <?php echo $code . ' - ' . htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>📄 Filter by Type:</label>
                                <select onchange="location.href='home.php?page=materials&materials_sort=<?php echo $materials_sort; ?>&materials_unit=<?php echo $materials_unit_filter; ?>&materials_type='+this.value">
                                    <option value="">All Types</option>
                                    <option value="youtube" <?php echo $materials_type_filter == 'youtube' ? 'selected' : ''; ?>>▶️ YouTube Videos</option>
                                    <option value="pdf" <?php echo $materials_type_filter == 'pdf' ? 'selected' : ''; ?>>📄 PDF Documents</option>
                                    <option value="doc" <?php echo $materials_type_filter == 'doc' ? 'selected' : ''; ?>>📝 Word Documents</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>🔽 Sort by:</label>
                                <select onchange="location.href='home.php?page=materials&materials_sort='+this.value+'&materials_unit=<?php echo $materials_unit_filter; ?>&materials_type=<?php echo $materials_type_filter; ?>'">
                                    <option value="date_desc" <?php echo $materials_sort == 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="date_asc" <?php echo $materials_sort == 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="title_asc" <?php echo $materials_sort == 'title_asc' ? 'selected' : ''; ?>>Title (A-Z)</option>
                                    <option value="title_desc" <?php echo $materials_sort == 'title_desc' ? 'selected' : ''; ?>>Title (Z-A)</option>
                                    <option value="unit_asc" <?php echo $materials_sort == 'unit_asc' ? 'selected' : ''; ?>>Unit Code (A-Z)</option>
                                </select>
                            </div>
                            
                            <?php if(!empty($materials_unit_filter) || !empty($materials_type_filter)): ?>
                                <div class="filter-group">
                                    <a href="home.php?page=materials" class="clear-btn">🗑️ Clear Filters</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (mysqli_num_rows($materials_result) == 0): ?>
                            <div class="no-data">
                                <p>📭 No course materials found with the selected filters.</p>
                                <p>Try adjusting your filters or view all materials.</p>
                            </div>
                        <?php else: ?>
                            <?php while($material = mysqli_fetch_assoc($materials_result)): ?>
                                <div class="material-item">
                                    <div class="material-title"><?php echo htmlspecialchars($material['title']); ?></div>
                                    <div class="material-meta">
                                        Unit: <?php echo $material['unit_code']; ?> | 
                                        Type: <?php echo strtoupper($material['material_type'] ?? 'FILE'); ?> | 
                                        Uploaded: <?php echo date('d M Y', strtotime($material['uploaded_at'])); ?>
                                    </div>
                                    <div><?php echo nl2br(htmlspecialchars($material['description'])); ?></div>
                                    <?php if ($material['material_type'] == 'youtube'): ?>
                                        <div style="margin-top: 10px;">
                                            <iframe width="100%" height="200" src="https://www.youtube.com/embed/<?php echo $material['video_id']; ?>" frameborder="0" allowfullscreen></iframe>
                                        </div>
                                        <a href="<?php echo $material['youtube_url']; ?>" target="_blank" class="btn btn-primary btn-sm">🔗 Open on YouTube</a>
                                    <?php else: ?>
                                        <a href="../uploads/materials/<?php echo $material['file_path']; ?>" target="_blank" class="btn btn-primary btn-sm">📥 Download Material</a>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; 
                    endif; ?>
                </div>
            </div>
        <?php break;

        case 'assignments':
            // Build WHERE clause with filters
            $where_conditions = ["a.unit_code IN ($units_list)"];
            if (!empty($assignments_unit_filter)) {
                $where_conditions[] = "a.unit_code = '$assignments_unit_filter'";
            }
            
            // Apply status filter
            if ($assignments_status_filter == 'pending') {
                $where_conditions[] = "NOT EXISTS (SELECT 1 FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_reg = '$student_reg')";
                $where_conditions[] = "a.due_date >= CURDATE()";
            } elseif ($assignments_status_filter == 'submitted') {
                $where_conditions[] = "EXISTS (SELECT 1 FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_reg = '$student_reg')";
            } elseif ($assignments_status_filter == 'late') {
                $where_conditions[] = "NOT EXISTS (SELECT 1 FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_reg = '$student_reg')";
                $where_conditions[] = "a.due_date < CURDATE()";
            }
            
            $where_clause = implode(" AND ", $where_conditions);
            
            // Apply sorting
            $order_by = "";
            switch($assignments_sort) {
                case 'due_desc': $order_by = "due_date DESC"; break;
                case 'title_asc': $order_by = "title ASC"; break;
                case 'title_desc': $order_by = "title DESC"; break;
                case 'unit_asc': $order_by = "unit_code ASC"; break;
                case 'marks_asc': $order_by = "total_marks ASC"; break;
                case 'marks_desc': $order_by = "total_marks DESC"; break;
                default: $order_by = "due_date ASC";
            }
            
            $assignments_query = "SELECT a.*, (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_reg = '$student_reg') as submitted 
                                 FROM assignments a 
                                 WHERE $where_clause 
                                 ORDER BY $order_by";
            $assignments_result = mysqli_query($conn, $assignments_query);
            ?>
            <div class="card">
                <div class="card-header">📝 All Assignments</div>
                <div class="card-body">
                    <?php if (empty($student_units)): ?>
                        <p>You are not registered for any units yet. Please complete <a href="registration.php">course registration</a> first.</p>
                    <?php else: ?>
                        <!-- Enhanced Filter Bar -->
                        <div class="filter-bar">
                            <div class="filter-group">
                                <label>📂 Filter by Unit:</label>
                                <select onchange="location.href='home.php?page=assignments&assignments_sort=<?php echo $assignments_sort; ?>&assignments_unit='+this.value+'&assignments_status=<?php echo $assignments_status_filter; ?>'">
                                    <option value="">All Units</option>
                                    <?php foreach($unit_options as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo $assignments_unit_filter == $code ? 'selected' : ''; ?>>
                                            <?php echo $code . ' - ' . htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>📋 Filter by Status:</label>
                                <select onchange="location.href='home.php?page=assignments&assignments_sort=<?php echo $assignments_sort; ?>&assignments_unit=<?php echo $assignments_unit_filter; ?>&assignments_status='+this.value">
                                    <option value="">All Assignments</option>
                                    <option value="pending" <?php echo $assignments_status_filter == 'pending' ? 'selected' : ''; ?>>⏳ Pending (Not Submitted)</option>
                                    <option value="submitted" <?php echo $assignments_status_filter == 'submitted' ? 'selected' : ''; ?>>✅ Already Submitted</option>
                                    <option value="late" <?php echo $assignments_status_filter == 'late' ? 'selected' : ''; ?>>⚠️ Late (Past Due Date)</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>🔽 Sort by:</label>
                                <select onchange="location.href='home.php?page=assignments&assignments_sort='+this.value+'&assignments_unit=<?php echo $assignments_unit_filter; ?>&assignments_status=<?php echo $assignments_status_filter; ?>'">
                                    <option value="due_asc" <?php echo $assignments_sort == 'due_asc' ? 'selected' : ''; ?>>Due Date (Earliest First)</option>
                                    <option value="due_desc" <?php echo $assignments_sort == 'due_desc' ? 'selected' : ''; ?>>Due Date (Latest First)</option>
                                    <option value="title_asc" <?php echo $assignments_sort == 'title_asc' ? 'selected' : ''; ?>>Title (A-Z)</option>
                                    <option value="title_desc" <?php echo $assignments_sort == 'title_desc' ? 'selected' : ''; ?>>Title (Z-A)</option>
                                    <option value="unit_asc" <?php echo $assignments_sort == 'unit_asc' ? 'selected' : ''; ?>>Unit Code (A-Z)</option>
                                    <option value="marks_asc" <?php echo $assignments_sort == 'marks_asc' ? 'selected' : ''; ?>>Marks (Low to High)</option>
                                    <option value="marks_desc" <?php echo $assignments_sort == 'marks_desc' ? 'selected' : ''; ?>>Marks (High to Low)</option>
                                </select>
                            </div>
                            
                            <?php if(!empty($assignments_unit_filter) || !empty($assignments_status_filter)): ?>
                                <div class="filter-group">
                                    <a href="home.php?page=assignments" class="clear-btn">🗑️ Clear Filters</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (mysqli_num_rows($assignments_result) == 0): ?>
                            <div class="no-data">
                                <p>📭 No assignments found with the selected filters.</p>
                                <p>Try adjusting your filters or check back later.</p>
                            </div>
                        <?php else: ?>
                            <?php while($assignment = mysqli_fetch_assoc($assignments_result)): ?>
                                <div class="assignment-item">
                                    <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                    <div class="assignment-meta">
                                        Unit: <?php echo $assignment['unit_code']; ?> | 
                                        Due: <?php echo date('d M Y', strtotime($assignment['due_date'])); ?> | 
                                        Total Marks: <?php echo $assignment['total_marks']; ?>
                                        <?php if($assignment['submitted'] > 0): ?>
                                            <span class="submission-status status-submitted">✅ Submitted</span>
                                        <?php elseif(strtotime($assignment['due_date']) < time()): ?>
                                            <span class="submission-status status-late">⏰ Late</span>
                                        <?php else: ?>
                                            <span class="submission-status status-pending">⏳ Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <div><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></div>
                                    <div style="margin-top: 10px;">
                                        <a href="../uploads/assignments/<?php echo $assignment['file_path']; ?>" target="_blank" class="btn btn-primary btn-sm">📥 Download Assignment</a>
                                        <?php if($assignment['submitted'] == 0): ?>
                                            <button onclick="openAssignmentModal(<?php echo $assignment['id']; ?>, '<?php echo addslashes($assignment['title']); ?>')" class="btn btn-success btn-sm">Submit Answer</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif;
                    endif; ?>
                </div>
            </div>
        <?php break;

        case 'my_submissions':
            // Build WHERE clause with filters
            $where_conditions = ["s.student_reg = '$student_reg'"];
            if (!empty($submissions_unit_filter)) {
                $where_conditions[] = "a.unit_code = '$submissions_unit_filter'";
            }
            if (!empty($submissions_status_filter)) {
                $where_conditions[] = "s.status = '$submissions_status_filter'";
            }
            $where_clause = implode(" AND ", $where_conditions);
            
            // Apply sorting
            $order_by = "";
            switch($submissions_sort) {
                case 'date_asc': $order_by = "s.submitted_at ASC"; break;
                case 'marks_desc': $order_by = "s.obtained_marks DESC"; break;
                case 'marks_asc': $order_by = "s.obtained_marks ASC"; break;
                case 'unit_asc': $order_by = "a.unit_code ASC"; break;
                default: $order_by = "s.submitted_at DESC";
            }
            
            $submissions_query = "SELECT s.*, a.title as assignment_title, a.unit_code, a.total_marks 
                                 FROM assignment_submissions s 
                                 LEFT JOIN assignments a ON s.assignment_id = a.id 
                                 WHERE $where_clause 
                                 ORDER BY $order_by";
            $submissions_result = mysqli_query($conn, $submissions_query);
            
            // Get submission statistics
            $stats_query = "SELECT 
                                COUNT(*) as total_submissions,
                                SUM(CASE WHEN status = 'graded' THEN 1 ELSE 0 END) as graded_count,
                                SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as pending_count
                            FROM assignment_submissions s 
                            WHERE s.student_reg = '$student_reg'";
            $stats_result = mysqli_query($conn, $stats_query);
            $stats = mysqli_fetch_assoc($stats_result);
            ?>
            <div class="card">
                <div class="card-header">📋 My Assignment Submissions</div>
                <div class="card-body">
                    <!-- Submission Statistics -->
                    <div style="display: flex; gap: 15px; margin-bottom: 20px; padding: 15px; background: #f1f5f9; border-radius: 8px;">
                        <div><strong>📊 Total Submissions:</strong> <?php echo $stats['total_submissions']; ?></div>
                        <div><strong>✅ Graded:</strong> <?php echo $stats['graded_count']; ?></div>
                        <div><strong>⏳ Pending:</strong> <?php echo $stats['pending_count']; ?></div>
                    </div>
                    
                    <!-- Enhanced Filter Bar -->
                    <div class="filter-bar">
                        <div class="filter-group">
                            <label>📂 Filter by Unit:</label>
                            <select onchange="location.href='home.php?page=my_submissions&submissions_sort=<?php echo $submissions_sort; ?>&submissions_unit='+this.value+'&submissions_status=<?php echo $submissions_status_filter; ?>'">
                                <option value="">All Units</option>
                                <?php foreach($unit_options as $code => $name): ?>
                                    <option value="<?php echo $code; ?>" <?php echo $submissions_unit_filter == $code ? 'selected' : ''; ?>>
                                        <?php echo $code . ' - ' . htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>📋 Filter by Status:</label>
                            <select onchange="location.href='home.php?page=my_submissions&submissions_sort=<?php echo $submissions_sort; ?>&submissions_unit=<?php echo $submissions_unit_filter; ?>&submissions_status='+this.value">
                                <option value="">All Status</option>
                                <option value="submitted" <?php echo $submissions_status_filter == 'submitted' ? 'selected' : ''; ?>>⏳ Pending Grading</option>
                                <option value="graded" <?php echo $submissions_status_filter == 'graded' ? 'selected' : ''; ?>>✅ Graded</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>🔽 Sort by:</label>
                            <select onchange="location.href='home.php?page=my_submissions&submissions_sort='+this.value+'&submissions_unit=<?php echo $submissions_unit_filter; ?>&submissions_status=<?php echo $submissions_status_filter; ?>'">
                                <option value="date_desc" <?php echo $submissions_sort == 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="date_asc" <?php echo $submissions_sort == 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="marks_desc" <?php echo $submissions_sort == 'marks_desc' ? 'selected' : ''; ?>>Highest Marks First</option>
                                <option value="marks_asc" <?php echo $submissions_sort == 'marks_asc' ? 'selected' : ''; ?>>Lowest Marks First</option>
                                <option value="unit_asc" <?php echo $submissions_sort == 'unit_asc' ? 'selected' : ''; ?>>Unit Code (A-Z)</option>
                            </select>
                        </div>
                        
                        <?php if(!empty($submissions_unit_filter) || !empty($submissions_status_filter)): ?>
                            <div class="filter-group">
                                <a href="home.php?page=my_submissions" class="clear-btn">🗑️ Clear Filters</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (mysqli_num_rows($submissions_result) == 0): ?>
                        <div class="no-data">
                            <p>📭 No submissions found with the selected filters.</p>
                            <p>You haven't submitted any assignments yet or try adjusting your filters.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Assignment</th>
                                        <th>Unit</th>
                                        <th>Submitted On</th>
                                        <th>Marks</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($sub = mysqli_fetch_assoc($submissions_result)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sub['assignment_title'] ?? 'Manual Entry'); ?></td>
                                            <td><?php echo $sub['unit_code'] ?? 'N/A'; ?></td>
                                            <td><?php echo date('d M Y H:i', strtotime($sub['submitted_at'])); ?></td>
                                            <td>
                                                <?php if($sub['obtained_marks'] !== null): ?>
                                                    <strong style="color: #10b981;"><?php echo $sub['obtained_marks']; ?></strong> / <?php echo $sub['total_marks'] ?? 'N/A'; ?>
                                                <?php else: ?>
                                                    <span class="marks-pending">Not graded yet</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo ($sub['status'] == 'graded') ? '<span class="submission-status status-submitted">✅ Graded</span>' : '<span class="submission-status status-pending">⏳ Pending</span>'; ?></td>
                                            <td>
                                                <?php if($sub['file_path']): ?>
                                                    <a href="../uploads/submissions/<?php echo $sub['file_path']; ?>" target="_blank" class="btn btn-primary btn-sm">📄 View</a>
                                                <?php endif; ?>
                                                <?php if($sub['submission_text'] && !empty(trim($sub['submission_text']))): ?>
                                                    <button onclick="viewTextSubmission('<?php echo addslashes($sub['submission_text']); ?>')" class="btn btn-info btn-sm">📝 View Answer</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php break;
        
        case 'my_results':
            // Get ALL graded submissions (online + manual)
            $results_query = "SELECT s.*, a.title as assignment_title, a.unit_code as assignment_unit_code, a.total_marks as assignment_total_marks, a.due_date, a.assessment_type
                             FROM assignment_submissions s 
                             LEFT JOIN assignments a ON s.assignment_id = a.id 
                             WHERE s.student_reg = '$student_reg' 
                             AND (s.status = 'graded' OR s.assignment_id = 0)
                             ORDER BY COALESCE(a.unit_code, s.unit_code), s.submitted_at DESC";
            $results_result = mysqli_query($conn, $results_query);
            
            // Organize results by unit
            $unit_results = [];
            while($row = mysqli_fetch_assoc($results_result)) {
                // Get unit code
                $unit = $row['assignment_unit_code'] ?? $row['unit_code'];
                if (empty($unit) || $unit == 'Unknown') {
                    // Try to extract from submission text
                    if (preg_match('/\[Manual Entry:.*?- ([A-Z0-9]+)\]/', $row['submission_text'], $matches)) {
                        $unit = $matches[1];
                    } else {
                        continue; // Skip entries without unit code
                    }
                }
                
                if (!isset($unit_results[$unit])) {
                    $unit_results[$unit] = [
                        'unit_code' => $unit,
                        'cat_total' => 0,
                        'assignment_total' => 0,
                        'exam_score' => 0,
                        'cat_count' => 0,
                        'assignment_count' => 0
                    ];
                }
                
                // Determine assessment type
                $assessment_type = $row['assessment_type'] ?? 'Assignment';
                $obtained = floatval($row['obtained_marks'] ?? 0);
                
                // For manual entries, determine type by total_marks
                if ($row['assignment_id'] == 0) {
                    if ($row['total_marks'] == 20) {
                        $assessment_type = 'CAT';
                    } elseif ($row['total_marks'] == 70) {
                        $assessment_type = 'Exam';
                    } else {
                        $assessment_type = 'Assignment';
                    }
                }
                
                // Add marks (not capped yet - will cap at the end)
                if ($assessment_type == 'CAT') {
                    $unit_results[$unit]['cat_total'] += $obtained;
                    $unit_results[$unit]['cat_count']++;
                } elseif ($assessment_type == 'Exam') {
                    $unit_results[$unit]['exam_score'] += $obtained;
                } else {
                    $unit_results[$unit]['assignment_total'] += $obtained;
                    $unit_results[$unit]['assignment_count']++;
                }
            }
            
            // Cap totals per unit (CAT max 20, Assignment max 10, Exam max 70)
            foreach($unit_results as $unit => $data) {
                $unit_results[$unit]['cat_total'] = min($data['cat_total'], 20);
                $unit_results[$unit]['assignment_total'] = min($data['assignment_total'], 10);
                $unit_results[$unit]['exam_score'] = min($data['exam_score'], 70);
                $unit_results[$unit]['total_score'] = $unit_results[$unit]['cat_total'] + $unit_results[$unit]['assignment_total'] + $unit_results[$unit]['exam_score'];
            }
            
            // Sort unit results
            $unit_results_array = array_values($unit_results);
            usort($unit_results_array, function($a, $b) use ($results_sort) {
                if ($results_sort == 'cat_desc') return $b['cat_total'] <=> $a['cat_total'];
                if ($results_sort == 'assignment_desc') return $b['assignment_total'] <=> $a['assignment_total'];
                if ($results_sort == 'total_desc') return $b['total_score'] <=> $a['total_score'];
                if ($results_sort == 'total_asc') return $a['total_score'] <=> $b['total_score'];
                return strcmp($a['unit_code'], $b['unit_code']);
            });
            
            // Calculate overall totals (each unit contributes max 20+10+70=100)
            $total_cats = 0;
            $total_assignments = 0;
            $total_exam = 0;
            $total_overall = 0;
            
            foreach($unit_results_array as $unit) {
                $total_cats += $unit['cat_total'];
                $total_assignments += $unit['assignment_total'];
                $total_exam += $unit['exam_score'];
                $total_overall += $unit['total_score'];
            }
            
            $unit_count = count($unit_results_array);
            $max_cats = $unit_count * 20;
            $max_assignments = $unit_count * 10;
            $max_exam = $unit_count * 70;
            $max_total = $unit_count * 100;
            
            $overall_percentage = ($max_total > 0) ? ($total_overall / $max_total) * 100 : 0;
            $grade_color = $overall_percentage >= 70 ? '#10b981' : ($overall_percentage >= 50 ? '#f59e0b' : '#ef4444');
            ?>
            <div class="card">
                <div class="card-header">📊 My Academic Results</div>
                <div class="card-body">
                    <div class="filter-bar">
                        <div class="filter-group">
                            <label>🔽 Sort Units by:</label>
                            <select onchange="location.href='home.php?page=my_results&results_sort='+this.value">
                                <option value="unit_asc" <?php echo $results_sort == 'unit_asc' ? 'selected' : ''; ?>>Unit Code (A-Z)</option>
                                <option value="cat_desc" <?php echo $results_sort == 'cat_desc' ? 'selected' : ''; ?>>Highest CATs First</option>
                                <option value="assignment_desc" <?php echo $results_sort == 'assignment_desc' ? 'selected' : ''; ?>>Highest Assignments First</option>
                                <option value="total_desc" <?php echo $results_sort == 'total_desc' ? 'selected' : ''; ?>>Highest Total First</option>
                                <option value="total_asc" <?php echo $results_sort == 'total_asc' ? 'selected' : ''; ?>>Lowest Total First</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Overall Summary Stats -->
                    <div class="results-summary">
                        <div class="result-stat">
                            <div class="label">CATs Total</div>
                            <div class="value"><?php echo $total_cats; ?></div>
                            <div class="total">out of <?php echo $max_cats; ?></div>
                        </div>
                        <div class="result-stat">
                            <div class="label">Assignments Total</div>
                            <div class="value"><?php echo $total_assignments; ?></div>
                            <div class="total">out of <?php echo $max_assignments; ?></div>
                        </div>
                        <div class="result-stat">
                            <div class="label">Main Exam</div>
                            <div class="value"><?php echo $total_exam; ?></div>
                            <div class="total">out of <?php echo $max_exam; ?></div>
                        </div>
                        <div class="result-stat">
                            <div class="label">Combined Total</div>
                            <div class="value" style="color: <?php echo $grade_color; ?>;"><?php echo $total_overall; ?></div>
                            <div class="total">out of <?php echo $max_total; ?></div>
                        </div>
                        <div class="result-stat">
                            <div class="label">Overall Percentage</div>
                            <div class="value" style="color: <?php echo $grade_color; ?>;"><?php echo round($overall_percentage, 1); ?>%</div>
                            <div class="total"><?php echo $overall_percentage >= 70 ? '🌟 Excellent' : ($overall_percentage >= 50 ? '📘 Good' : '📚 Need Improvement'); ?></div>
                        </div>
                    </div>
                    
                    <?php if (count($unit_results_array) == 0): ?>
                        <div class="no-data">
                            <p>📭 No results available yet.</p>
                            <p>Once your assignments and exams are graded, they will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Unit Code</th>
                                        <th>CATs (out of 20)</th>
                                        <th>Assignments (out of 10)</th>
                                        <th>Main Exam (out of 70)</th>
                                        <th>Total (out of 100)</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($unit_results_array as $unit): 
                                        $total_display = $unit['cat_total'] + $unit['assignment_total'] + $unit['exam_score'];
                                        $unit_percentage = ($total_display / 100) * 100;
                                        $unit_grade = $unit_percentage >= 70 ? 'A' : ($unit_percentage >= 60 ? 'B' : ($unit_percentage >= 50 ? 'C' : ($unit_percentage >= 40 ? 'D' : 'F')));
                                        $unit_grade_color = $unit_percentage >= 70 ? '#10b981' : ($unit_percentage >= 50 ? '#f59e0b' : '#ef4444');
                                    ?>
                                        <tr>
                                            <td><strong><?php echo $unit['unit_code']; ?></strong></td>
                                            <td>
                                                <?php echo $unit['cat_total']; ?> / 20
                                                <?php if($unit['cat_count'] > 0): ?>
                                                    <br><small style="color:#666;">(<?php echo $unit['cat_count']; ?> CAT(s))</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $unit['assignment_total']; ?> / 10
                                                <?php if($unit['assignment_count'] > 0): ?>
                                                    <br><small style="color:#666;">(<?php echo $unit['assignment_count']; ?> assignment(s))</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $unit['exam_score']; ?> / 70</td>
                                            <td><strong style="color: <?php echo $unit_grade_color; ?>;"><?php echo $total_display; ?> / 100</strong></td>
                                            <td><span style="background: <?php echo $unit_grade_color; ?>; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem;"><?php echo $unit_grade; ?> (<?php echo round($unit_percentage, 1); ?>%)</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php break;
        
        default:
            header("Location: home.php?page=dashboard");
            break;
    }
    ?>
</div>

<!-- Assignment Submission Modal -->
<div id="assignmentModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">📝 Submit Assignment</h3>
        <form method="POST" enctype="multipart/form-data" id="submissionForm">
            <input type="hidden" name="assignment_id" id="assignment_id">
            <input type="hidden" name="submit_assignment" value="1">
            
            <div class="form-group">
                <label>Your Answer</label>
                <textarea name="submission_text" id="submission_text" rows="12" style="width:100%; padding:12px; border:1px solid #e2e8f0; border-radius:8px; font-family: inherit; resize: vertical;" placeholder="Type your assignment answer here..."></textarea>
                <small>Write your answer directly in the box above.</small>
            </div>
            
            <div class="form-group">
                <label>Or Upload File (PDF, DOC, DOCX, ZIP)</label>
                <input type="file" name="submission_file" accept=".pdf,.doc,.docx,.zip">
                <small>Maximum file size: 10MB</small>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">Submit Assignment</button>
                <button type="button" onclick="closeAssignmentModal()" class="btn btn-danger">Cancel</button>
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

<!-- Chatbot Interface -->
<div id="chat-fab" onclick="toggleChat()">💬</div>
<div id="chat-window">
    <div style="background: var(--primary); color:white; padding: 15px; font-weight:bold; display: flex; justify-content: space-between; align-items: center;">
        <span>🤖 Portal Assistant</span>
        <span onclick="toggleChat()" style="cursor:pointer; font-size: 1.2rem;">&times;</span>
    </div>
    <div id="chat-content">
        <div class="msg msg-bot">
            Hello <?php echo htmlspecialchars($fname); ?>! I'm your AI assistant. How can I help you today?
        </div>
    </div>
    <div class="chat-input-area">
        <input type="text" id="chat-input" placeholder="Ask me anything...">
        <button onclick="sendChatMessage()">➤</button>
    </div>
</div>

<footer>
    &copy; Portal Assistant AI
</footer>

<script>
function openAssignmentModal(assignmentId, assignmentTitle) {
    document.getElementById('assignment_id').value = assignmentId;
    document.getElementById('modalTitle').innerHTML = '📝 Submit: ' + assignmentTitle;
    document.getElementById('assignmentModal').style.display = 'flex';
    document.getElementById('submission_text').value = '';
}

function closeAssignmentModal() {
    document.getElementById('assignmentModal').style.display = 'none';
}

function viewTextSubmission(text) {
    document.getElementById('text_submission_content').innerHTML = text.replace(/\n/g, '<br>');
    document.getElementById('textSubmissionModal').style.display = 'flex';
}

function closeTextSubmissionModal() {
    document.getElementById('textSubmissionModal').style.display = 'none';
}

// Chatbot Functions
function toggleChat() {
    const win = document.getElementById('chat-window');
    win.style.display = (win.style.display === 'flex') ? 'none' : 'flex';
    if (win.style.display === 'flex') {
        document.getElementById('chat-input').focus();
    }
}

async function sendChatMessage() {
    const input = document.getElementById('chat-input');
    const box = document.getElementById('chat-content');
    const msg = input.value.trim();
    if(!msg) return;

    box.innerHTML += `<div class="msg msg-user">${escapeHtml(msg)}</div>`;
    input.value = '';
    box.scrollTop = box.scrollHeight;

    try {
        const response = await fetch('chat_process_ml.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'message=' + encodeURIComponent(msg)
        });
        const text = await response.text();
        box.innerHTML += `<div class="msg msg-bot">${text}</div>`;
        box.scrollTop = box.scrollHeight;
    } catch (e) {
        box.innerHTML += `<div class="msg msg-bot" style="color:red">⚠️ Connection error. Please try again.</div>`;
        box.scrollTop = box.scrollHeight;
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.getElementById('chat-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        sendChatMessage();
    }
});

window.onclick = function(event) {
    const modal = document.getElementById('assignmentModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
    const textModal = document.getElementById('textSubmissionModal');
    if (event.target == textModal) {
        textModal.style.display = 'none';
    }
}
</script>
</body>
</html>