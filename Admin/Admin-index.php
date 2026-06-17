<?php
session_start();

/* ==========================
    ACCESS CONTROL
========================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['user_name'] ?? 'Administrator';

/* ==========================
    DATABASE CONNECTION
========================== */
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

/* ==========================
    DASHBOARD METRICS
========================== */
$total_students = 0;
$q1 = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='student'");
if ($q1) { $total_students = mysqli_fetch_assoc($q1)['total']; }

$total_units = 0;
$q2 = mysqli_query($conn, "SELECT COUNT(*) AS total FROM academic_workload");
if ($q2) { $total_units = mysqli_fetch_assoc($q2)['total']; }

// Research Metric: Total Survey Responses
$total_surveys = 0;
$q3 = mysqli_query($conn, "SELECT COUNT(*) AS total FROM survey_responses");
if ($q3) { $total_surveys = mysqli_fetch_assoc($q3)['total']; }

// Get pending registrations count
$pending_registrations = 0;
$q4 = mysqli_query($conn, "SELECT COUNT(*) AS total FROM registered_courses WHERE status='pending'");
if ($q4) { $pending_registrations = mysqli_fetch_assoc($q4)['total']; }

// Get total lecturers count
$total_lecturers = 0;
$q5 = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='lecturer'");
if ($q5) { $total_lecturers = mysqli_fetch_assoc($q5)['total']; }

// Determine current section
$section = $_GET['section'] ?? 'dashboard';

// Notification Logic
$notif_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM admin_referrals WHERE status='pending'");
$notif_count = mysqli_fetch_assoc($notif_q)['count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Student Support Agent</title>
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
            --research: #8b5cf6;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text-main); margin: 0; line-height: 1.5; }

        header { background: var(--white); border-bottom: 1px solid var(--border); padding: 1rem 5%; display: flex; align-items: center; justify-content: space-between; }
        .branding { display: flex; align-items: center; gap: 15px; }
        .logoimg { height: 50px; border-radius: 8px; }
        .branding h1 { margin: 0; font-size: 1.4rem; color: var(--primary); font-weight: 800; }

        nav { background: var(--primary); padding: 0 5%; }
        .nav-top { display: flex; gap: 10px; flex-wrap: wrap; }
        nav a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 14px 20px; font-size: 0.9rem; font-weight: 600; transition: 0.3s; border-bottom: 3px solid transparent; }
        nav a:hover { color: white; background: rgba(255,255,255,0.1); }
        nav a.active { color: white; background: rgba(255,255,255,0.15); border-bottom: 3px solid white; }
        
        .nav-research { background: rgba(255,255,255,0.1); border-radius: 5px; margin: 5px 0; }

        .nav-sub { background: #f1f5f9; display: flex; gap: 10px; padding: 0 5%; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
        .nav-sub a { color: var(--text-light); font-size: 0.8rem; padding: 10px 15px; text-decoration: none; font-weight: 600; }
        .nav-sub a:hover { color: var(--primary); }
        .nav-sub a.active { color: var(--primary); font-weight: 700; border-bottom: 2px solid var(--primary); }

        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .admin-strip { background: var(--accent); padding: 12px 20px; border-radius: 10px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; color: var(--primary-dark); font-weight: 700; font-size: 0.85rem; flex-wrap: wrap; gap: 10px; }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--white); padding: 25px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: 0.3s; }
        .stat-card.actionable:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: var(--primary); cursor: pointer; }
        .stat-card h3 { margin: 0; font-size: 2rem; color: var(--primary); font-weight: 800; }
        .stat-card p { margin: 5px 0 0 0; color: var(--text-light); font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; }

        .section-box { background: var(--white); border-radius: 12px; border: 1px solid var(--border); padding: 25px; margin-bottom: 30px; overflow-x: auto; }
        .ai-note { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 4px; font-size: 0.85rem; color: #92400e; }
        footer { text-align: center; padding: 40px; color: var(--text-light); font-size: 0.85rem; }

        .chat-fab { position: fixed; bottom: 30px; right: 30px; background: var(--primary); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); z-index: 1000; text-decoration: none; border: none; cursor: pointer; }
        .badge { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        
        /* Table Styles */
        .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        .data-table th { background: var(--bg); font-weight: 700; color: var(--text-main); }
        .data-table tr:hover { background: var(--accent); }
        
        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: 0.3s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #0b9e6e; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
        
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fed7aa; color: #92400e; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-main); }
        .form-group input, .form-group select, .form-group textarea { width: 100%; max-width: 500px; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; }
        .form-group textarea { min-height: 100px; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 1px solid var(--border); }
        .tab { padding: 10px 20px; cursor: pointer; border: none; background: none; font-weight: 600; color: var(--text-light); }
        .tab.active { color: var(--primary); border-bottom: 2px solid var(--primary); }
    </style>
</head>
<body>

<header>
    <div class="branding">
        <img src="../Images/logo.jpg" class="logoimg" alt="Logo">
        <div>
            <h1>Student Support Agent – Admin</h1>
            <small>Academic Administration Portal</small>
        </div>
    </div>
</header>

<nav>
    <div class="nav-top">
        <a href="Admin-index.php?section=dashboard" class="<?php echo ($section === 'dashboard') ? 'active' : ''; ?>">Dashboard</a>
        <a href="Admin-index.php?section=students" class="<?php echo ($section === 'students' || $section === 'add_student' || $section === 'edit_student') ? 'active' : ''; ?>">Manage Students</a>
        <a href="Admin-index.php?section=lecturers" class="<?php echo ($section === 'lecturers' || $section === 'add_lecturer') ? 'active' : ''; ?>">Manage Lecturers</a>
        <a href="Admin-index.php?section=units" class="<?php echo ($section === 'units' || $section === 'add_workload' || $section === 'add_unit' || $section === 'edit_unit') ? 'active' : ''; ?>">Manage Units</a>
        <a href="Admin-index.php?section=timetable" class="<?php echo ($section === 'timetable') ? 'active' : ''; ?>">Timetable</a>
        <a href="Admin-index.php?section=registrations" class="<?php echo ($section === 'registrations') ? 'active' : ''; ?>">Registrations</a>
        <a href="Admin-index.php?section=escalations" class="<?php echo ($section === 'escalations') ? 'active' : ''; ?>">AI Escalations</a>
        <a href="Admin-index.php?section=analytics" class="nav-research <?php echo ($section === 'analytics') ? 'active' : ''; ?>">📊 Research Analytics</a>
        <a href="Admin-index.php?section=reports" class="<?php echo ($section === 'reports') ? 'active' : ''; ?>">Reports</a>
        <a href="Admin-index.php?section=kb" class="<?php echo ($section === 'kb') ? 'active' : ''; ?>">Knowledge Base</a>
        <a href="Admin-index.php?section=settings" class="<?php echo ($section === 'settings') ? 'active' : ''; ?>">Settings</a>
        <a href="../logout.php">Sign Out</a>
    </div>
</nav>

<?php if ($section !== 'dashboard' && $section !== 'analytics' && $section !== 'reports' && $section !== 'settings' && $section !== 'kb'): ?>
<div class="nav-sub">
    <?php if ($section === 'units' || $section === 'add_workload' || $section === 'add_unit' || $section === 'edit_unit'): ?>
        <a href="Admin-index.php?section=add_unit" class="<?php echo ($section === 'add_unit') ? 'active' : ''; ?>">Add Unit</a>
        <a href="Admin-index.php?section=add_workload" class="<?php echo ($section === 'add_workload') ? 'active' : ''; ?>">Add Workload</a>
        <a href="Admin-index.php?section=units" class="<?php echo ($section === 'units') ? 'active' : ''; ?>">View Workload</a>
    <?php elseif ($section === 'students' || $section === 'add_student' || $section === 'edit_student'): ?>
        <a href="Admin-index.php?section=add_student" class="<?php echo ($section === 'add_student') ? 'active' : ''; ?>">Add Student</a>
        <a href="Admin-index.php?section=students" class="<?php echo ($section === 'students') ? 'active' : ''; ?>">Student Directory</a>
    <?php elseif ($section === 'lecturers' || $section === 'add_lecturer'): ?>
        <a href="Admin-index.php?section=add_lecturer" class="<?php echo ($section === 'add_lecturer') ? 'active' : ''; ?>">Add Lecturer</a>
        <a href="Admin-index.php?section=lecturers" class="<?php echo ($section === 'lecturers') ? 'active' : ''; ?>">Lecturer Directory</a>
    <?php elseif ($section === 'timetable'): ?>
        <a href="Admin-index.php?section=timetable&action=view" class="active">View Timetable</a>
        <a href="Admin-index.php?section=timetable&action=add">Add Schedule</a>
        <a href="Admin-index.php?section=timetable&action=manage">Manage Venues</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="container">
    <div class="admin-strip">
        <span>Active Admin: <strong><?php echo htmlspecialchars($admin_name); ?></strong></span>
        <span>Department of Registrar (Academic)</span>
        <?php if($pending_registrations > 0): ?>
            <span style="background: var(--warning); padding: 4px 12px; border-radius: 20px;">⚠️ <?php echo $pending_registrations; ?> Pending Approvals</span>
        <?php endif; ?>
    </div>

    <?php 
    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch($_POST['action']) {
                case 'approve_registration':
                    $reg_id = $_POST['reg_id'];
                    mysqli_query($conn, "UPDATE registered_courses SET status='Confirmed' WHERE id='$reg_id'");
                    echo '<div class="alert alert-success">✅ Registration approved successfully!</div>';
                    break;
                case 'reject_registration':
                    $reg_id = $_POST['reg_id'];
                    mysqli_query($conn, "UPDATE registered_courses SET status='Rejected' WHERE id='$reg_id'");
                    echo '<div class="alert alert-error">❌ Registration rejected!</div>';
                    break;
                case 'delete_student':
                    $student_id = $_POST['student_id'];
                    mysqli_query($conn, "DELETE FROM users WHERE id='$student_id' AND role='student'");
                    echo '<div class="alert alert-success">✅ Student deleted successfully!</div>';
                    break;
                case 'delete_unit':
                    $unit_id = $_POST['unit_id'];
                    mysqli_query($conn, "DELETE FROM academic_workload WHERE id='$unit_id'");
                    echo '<div class="alert alert-success">✅ Unit deleted successfully!</div>';
                    break;
            }
        }
    }

    switch($section) {
        case 'dashboard': ?>
            <div class="dashboard-grid">
                <div class="stat-card">
                    <h3><?php echo $total_students; ?></h3>
                    <p>Total Students Registered</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $total_lecturers; ?></h3>
                    <p>Total Lecturers</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $total_units; ?></h3>
                    <p>Units in Master Plan</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: var(--warning);"><?php echo $pending_registrations; ?></h3>
                    <p>Pending Approvals</p>
                </div>
                <div class="stat-card" style="border-left: 4px solid var(--research);">
                    <h3 style="color: var(--research);"><?php echo $total_surveys; ?></h3>
                    <p>Research Survey Responses</p>
                </div>
            </div>

            <h2 style="font-size: 1.1rem; margin-bottom: 20px;">Administrative Quick Links</h2>
            <div class="dashboard-grid">
                <a href="Admin-index.php?section=analytics" style="text-decoration: none;">
                    <div class="stat-card actionable" style="background: #fdf2ff;">
                        <div style="font-size: 1.5rem; margin-bottom: 10px;">📊</div>
                        <p style="color: var(--research);">System Analysis & Conclusion</p>
                    </div>
                </a>
                <a href="Admin-index.php?section=students" style="text-decoration: none;">
                    <div class="stat-card actionable">
                        <div style="font-size: 1.5rem; margin-bottom: 10px;">👥</div>
                        <p style="color: var(--primary);">Student Records</p>
                    </div>
                </a>
                <a href="Admin-index.php?section=registrations" style="text-decoration: none;">
                    <div class="stat-card actionable">
                        <div style="font-size: 1.5rem; margin-bottom: 10px;">✍️</div>
                        <p style="color: var(--primary);">Registration Logs</p>
                    </div>
                </a>
                <a href="Admin-index.php?section=timetable" style="text-decoration: none;">
                    <div class="stat-card actionable">
                        <div style="font-size: 1.5rem; margin-bottom: 10px;">📅</div>
                        <p style="color: var(--primary);">Timetable Manager</p>
                    </div>
                </a>
                <a href="Admin-index.php?section=reports" style="text-decoration: none;">
                    <div class="stat-card actionable">
                        <div style="font-size: 1.5rem; margin-bottom: 10px;">📈</div>
                        <p style="color: var(--primary);">Generate Reports</p>
                    </div>
                </a>
            </div>

            <div class="section-box">
                <div class="ai-note">
                    <strong>Research Status:</strong> Data collection for Objective 1.5.2 (iv) is active. The system is currently aggregating student feedback to evaluate AI effectiveness.
                </div>
            </div>
        <?php break;

        case 'analytics':
            include('admin_survey_analysis.php');
            break;

        case 'units':
            include('view_workload.php');
            break;

        case 'add_workload':
            include('add_workload.php');
            break;

        case 'add_unit':
            include('add_unit.php');
            break;
            
        case 'edit_unit':
            include('edit_unit.php');
            break;

        case 'students':
            include('view_students.php');
            break;

        case 'add_student':
            include('add_student.php');
            break;
            
        case 'edit_student':
            include('edit_student.php');
            break;
            
        case 'lecturers':
            include('view_lecturers.php');
            break;
            
        case 'add_lecturer':
            include('add_lecturer.php');
            break;
            
        case 'registrations':
            include('view_registrations.php');
            break;
            
        case 'timetable':
            include('manage_timetable.php');
            break;

        case 'escalations':
            echo '<div style="height:600px; border:1px solid #ccc; border-radius:8px;">
                    <iframe src="view_escalations.php" style="width:100%; height:100%; border:none;"></iframe>
                    </div>';
            break;
            
        case 'reports':
            include('generate_reports.php');
            break;
            
        case 'kb':
            include('manage_knowledge_base.php');
            break;
            
        case 'settings':
            include('system_settings.php');
            break;
    } 
    ?>
</div>

<div id="chatModal" style="display:none; position:fixed; z-index:9999; bottom:90px; right:20px; width:400px; height:500px; background:white; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2); overflow:hidden; flex-direction:column;">
    <div style="background:#4f46e5; color:white; padding:15px; display:flex; justify-content:space-between; align-items:center;">
        <strong>AI Escalations</strong>
        <span onclick="document.getElementById('chatModal').style.display='none'" style="cursor:pointer; font-size:20px;">&times;</span>
    </div>
    <div id="modalContent" style="flex:1; overflow-y:auto; background:#f9f9f9;">
        <p style="padding:20px;">Select an escalation...</p>
    </div>
</div>

<?php if($notif_count > 0): ?>
    <button onclick="openEscalations()" class="chat-fab">💬<span class="badge"><?php echo $notif_count; ?></span></button>
<?php endif; ?>

<script>
function openEscalations() {
    document.getElementById('chatModal').style.display = 'flex';
    document.getElementById('modalContent').innerHTML = '<p style="padding:20px;">Loading list...</p>';
    fetch('view_escalations.php').then(response => response.text()).then(data => {
        document.getElementById('modalContent').innerHTML = data;
    });
}
function loadMessage(conv_id) {
    document.getElementById('chatModal').style.display = 'flex';
    const content = document.getElementById('modalContent');
    content.innerHTML = `<iframe src="view_conversation.php?conv_id=${conv_id}" style="width:100%; height:100%; border:none; display:block;"></iframe>`;
}
</script>
 
<footer>
    &copy; 2026 Portal Assistant AI
</footer>
</body>
</html>