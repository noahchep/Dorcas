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
   DELETE UNIT
========================== */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM workload_units WHERE id=$id");
    header("Location: manage_units.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Units | Admin</title>

<style>
/* SAME STYLING AS YOUR DASHBOARD */
body {
    font-family: Verdana, sans-serif;
    font-size: 12px;
    margin: 0;
    background-size: cover;
}
#content {
    width: 1000px;
    margin: 20px auto;
    background: #fff;
    border: 1px solid #aaa;
    box-shadow: 0 0 20px rgba(0,0,0,0.4);
}
#top_info {
    padding: 15px;
    border-bottom: 3px solid #0056b3;
}
.logoimg { max-height: 70px; }
h1 { margin: 0; font-size: 22px; color: #0056b3; }

#navigation { background: #0056b3; }
.ult-section {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
}
.ult-section li a {
    display: block;
    padding: 10px 15px;
    font-weight: bold;
    color: #fff;
    text-decoration: none;
    border-right: 1px solid #003366;
}
.ult-section li a:hover,
.active { background: #004080; }

.admin-bar {
    background: #f9f9f9;
    padding: 10px;
    border-bottom: 1px solid #ddd;
    text-align: center;
    font-weight: bold;
}

.left_articles { padding: 20px; }

.section-title {
    background: #f2f2f2;
    font-weight: bold;
    padding: 8px 10px;
    margin-top: 15px;
    border: 1px solid #ccc;
    color: #0056b3;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
th, td {
    border: 1px solid #ddd;
    padding: 6px;
    font-size: 11px;
}
th { background: #eee; }

.action-btn {
    padding: 4px 8px;
    font-size: 11px;
    text-decoration: none;
    border-radius: 3px;
}
.edit { background: #28a745; color: #fff; }
.delete { background: #dc3545; color: #fff; }

#footer {
    text-align: right;
    padding: 10px;
    font-size: 11px;
    border-top: 1px solid #ccc;
    background: #f8f8f8;
}
</style>
</head>

<body>

<div id="content">

<!-- HEADER -->
<div id="top_info">
    <table width="100%">
        <tr>
            <td width="80"><img src="../Images/logo.jpg" class="logoimg"></td>
            <td>
                <h1>Student Support Agent – Admin</h1>
                <small>Academic Administration Portal</small>
            </td>
        </tr>
    </table>
</div>

<!-- NAVIGATION -->
<div id="navigation">
<ul class="ult-section">
    <li><a href="Admin-index.php">Dashboard</a></li>
    <li><a href="add_unit.php">Add Unit to Timetable</a></li>
    <li><a href="add_workload.php">Add Workload</a></li>
    <li class="active"><a href="manage_units.php">Manage Units</a></li>
    <li><a href="add_student.php">Manage Students</a></li>
    <li><a href="registrations.php">Registrations</a></li>
    <li><a href="reports.php">Reports</a></li>
    <li><a href="../logout.php">Sign Out</a></li>
</ul>
</div>

<div class="left_articles">

<div class="admin-bar">
    Logged in as: <?php echo htmlspecialchars($admin_name); ?> |
    Department: Registrar (Academic Affairs)
</div>

<div class="section-title">Manage Units / Workload</div>

<table>
<tr>
    <th>#</th>
    <th>Unit Code</th>
    <th>Course Title</th>
    <th>From</th>
    <th>To</th>
    <th>Venue</th>
    <th>Group</th>
    <th>Lecturer</th>
    <th>Exam Date</th>
    <th>Actions</th>
</tr>

<?php
$i = 1;
$result = mysqli_query($conn, "SELECT * FROM workload_units ORDER BY id DESC");

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>
        <td align='center'>{$i}</td>
        <td>{$row['unit_code']}</td>
        <td>{$row['course_title']}</td>
        <td>{$row['time_from']}</td>
        <td>{$row['time_to']}</td>
        <td>{$row['venue']}</td>
        <td>{$row['group_name']}</td>
        <td>{$row['lecturer']}</td>
        <td>{$row['exam_date']}</td>
        <td align='center'>
            <a class='action-btn edit' href='edit_unit.php?id={$row['id']}'>Edit</a>
            <a class='action-btn delete' 
               onclick=\"return confirm('Delete this unit?')\"
               href='manage_units.php?delete={$row['id']}'>Delete</a>
        </td>
    </tr>";
    $i++;
}
?>
</table>

</div>

<div id="footer">
    &copy; Portal Assistant AI | Admin Module
</div>

</div>
</body>
</html>
