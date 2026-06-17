<?php
/* ===========================================
    PORTAL-ASSISTANT-AI: STUDENT DIRECTORY
   =========================================== */
include_once('db_connect.php');

if (!$conn) {
    $conn = mysqli_connect("localhost", "root", "", "portal-asisstant-ai");
}

// 1. Drop Student Logic
if (isset($_GET['drop_id'])) {
    $drop_id = mysqli_real_escape_string($conn, $_GET['drop_id']);
    
    mysqli_query($conn, "DELETE FROM survey_responses WHERE user_id = '$drop_id'");
    
    $reg_query = mysqli_query($conn, "SELECT reg_number FROM users WHERE id = '$drop_id'");
    $reg_data = mysqli_fetch_assoc($reg_query);
    if($reg_data) {
        $reg_no = $reg_data['reg_number'];
        mysqli_query($conn, "DELETE FROM registered_courses WHERE student_reg_no = '$reg_no'");
    }

    $delete_user = "DELETE FROM users WHERE id = '$drop_id' AND role = 'student'";
    
    if (mysqli_query($conn, $delete_user)) {
        echo "<script>alert('Student and all records dropped successfully'); window.location='Admin-index.php?section=students';</script>";
    }
}

// 2. Search & Filter Logic
$search = $_GET['search'] ?? '';
$dept_filter = $_GET['dept'] ?? 'all';

$query = "SELECT * FROM users WHERE role='student'";
if ($dept_filter !== 'all') {
    $query .= " AND department = '" . mysqli_real_escape_string($conn, $dept_filter) . "'";
}
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $query .= " AND (full_name LIKE '%$s%' OR reg_number LIKE '%$s%')";
}
$query .= " ORDER BY id DESC";
$result = mysqli_query($conn, $query);
$current_view_count = ($result) ? mysqli_num_rows($result) : 0;
?>

<style>
    .master-plan-container {
        background: #ffffff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .plan-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 10px;
        border-bottom: 1px solid #f1f5f9;
    }

    .plan-header h2 {
        color: #4f46e5;
        font-size: 1.1rem;
        font-weight: 700;
        margin: 0;
    }

    .search-box {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .search-box input, .search-box select {
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.85rem;
    }

    .workload-table {
        width: 100%;
        border-collapse: collapse;
    }

    .workload-table th {
        text-align: left;
        padding: 12px 15px;
        color: #334155;
        font-weight: 700;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.85rem;
    }

    .workload-table td {
        padding: 15px;
        border-bottom: 1px solid #f8fafc;
        color: #475569;
        font-size: 0.85rem;
        vertical-align: middle;
    }

    /* Badge matching the Unit Code style */
    .reg-badge {
        background: #eef2ff;
        color: #4338ca;
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        border: 1px solid #e0e7ff;
    }

    /* Department Badge */
    .dept-tag {
        background: #f1f5f9;
        color: #475569;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    /* Buttons matching your Master Plan style */
    .btn-action {
        background: #4f46e5;
        color: white;
        text-decoration: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: 0.2s;
    }

    .btn-drop {
        background: #ef4444;
    }

    .btn-drop:hover {
        background: #dc2626;
    }

    .btn-search {
        background: #4f46e5;
    }

    .btn-search:hover {
        background: #3730a3;
    }
</style>

<div class="master-plan-container">
    <div class="plan-header">
        <h2>Student Directory Master List</h2>
        <form class="search-box" method="GET" action="Admin-index.php">
            <input type="hidden" name="section" value="students">
            
            <input type="text" name="search" placeholder="Name or Reg No..." value="<?php echo htmlspecialchars($search); ?>">
            
            <select name="dept">
                <option value="all">All Departments</option>
                <option value="Information Technology" <?php if($dept_filter == 'Information Technology') echo 'selected'; ?>>Information Technology</option>
                <option value="Computer Science" <?php if($dept_filter == 'Computer Science') echo 'selected'; ?>>Computer Science</option>
                <option value="Enterprise Computing" <?php if($dept_filter == 'Enterprise Computing') echo 'selected'; ?>>Enterprise Computing</option>
                <option value="Information Science & Knowledge Management" <?php if($dept_filter == 'Information Science & Knowledge Management') echo 'selected'; ?>>Information Science</option>
            </select>
            
            <button type="submit" class="btn-action btn-search">Filter Records</button>
            <a href="Admin-index.php?section=students" style="font-size: 0.75rem; color: #64748b; text-decoration: none;">Reset</a>
        </form>
    </div>

    <table class="workload-table">
        <thead>
            <tr>
                <th>Reg Number</th>
                <th>Full Name</th>
                <th>Department</th>
                <th>Email Address</th>
                <th>Administrative Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if($current_view_count > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><span class="reg-badge"><?php echo htmlspecialchars($row['reg_number']); ?></span></td>
                    <td style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><span class="dept-tag"><?php echo htmlspecialchars($row['department']); ?></span></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td>
                        <a href="Admin-index.php?section=students&drop_id=<?php echo $row['id']; ?>" 
                           class="btn-action btn-drop"
                           onclick="return confirm('WARNING: Are you sure you want to drop this student? All their registrations and survey data will be permanently deleted.');">
                            × Drop Student
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding: 40px; color: #94a3b8;">
                        No student records found matching your current search criteria.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>