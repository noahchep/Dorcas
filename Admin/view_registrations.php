<?php
/* ===========================================
    PORTAL-ASSISTANT-AI: ENHANCED REGISTRATIONS
   =========================================== */

// 1. Handle Status Confirmation & Deletion
if (isset($_GET['action']) && isset($_GET['reg_id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['reg_id']);
    if ($_GET['action'] == 'confirm') {
        mysqli_query($conn, "UPDATE registered_courses SET status = 'Confirmed' WHERE id = '$id'");
    } elseif ($_GET['action'] == 'drop') {
        mysqli_query($conn, "DELETE FROM registered_courses WHERE id = '$id'");
    }
    echo "<script>window.location.href='Admin-index.php?section=registrations';</script>";
}

// 2. Advanced Filter Logic
$where_clause = "WHERE 1=1";
$selected_dept = isset($_POST['dept_filter']) ? mysqli_real_escape_string($conn, $_POST['dept_filter']) : "";
$selected_unit = isset($_POST['unit_filter']) ? mysqli_real_escape_string($conn, $_POST['unit_filter']) : "";

if (isset($_POST['filter_reg'])) {
    // Filter by Department
    if (!empty($selected_dept)) {
        $where_clause .= " AND department = '$selected_dept'";
    }
    // Filter by specific Unit Code (Exact match for better counting)
    if (!empty($selected_unit)) {
        $where_clause .= " AND unit_code LIKE '%$selected_unit%'";
    }
}

$query = "SELECT * FROM registered_courses $where_clause ORDER BY registered_at DESC";
$result = mysqli_query($conn, $query);

// 3. Count the results for the admin summary
$total_students = mysqli_num_rows($result);
?>

<style>
    .master-plan-container { background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); font-family: 'Segoe UI', sans-serif; }
    .plan-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
    .plan-header h2 { color: #4f46e5; font-size: 1.1rem; font-weight: 700; margin: 0; }
    
    /* Summary Badge */
    .summary-counter { background: #4f46e5; color: white; padding: 10px 20px; border-radius: 8px; margin-bottom: 20px; display: inline-block; font-weight: 600; font-size: 0.9rem; }

    .filter-bar { display: flex; gap: 10px; background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
    .filter-input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem; min-width: 180px; }

    .workload-table { width: 100%; border-collapse: collapse; }
    .workload-table th { text-align: left; padding: 12px 15px; color: #334155; font-weight: 700; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem; }
    .workload-table td { padding: 15px; border-bottom: 1px solid #f8fafc; color: #475569; font-size: 0.85rem; vertical-align: middle; }
    
    .unit-badge { background: #eef2ff; color: #4338ca; font-weight: 700; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; border: 1px solid #e0e7ff; }
    .status-tag { padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
    .confirmed { background: #dcfce7; color: #166534; }
    .provisional { background: #fee2e2; color: #991b1b; }

    .btn-action { text-decoration: none; padding: 8px 14px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; display: inline-block; transition: 0.2s; border: none; cursor: pointer; }
    .btn-confirm { background: #4f46e5; color: white; }
    .btn-drop { background: #ef4444; color: white; }
</style>

<div class="master-plan-container">
    <div class="plan-header">
        <h2>Unit Registration Analysis</h2>
        <a href="Admin-index.php?section=registrations" class="btn-action" style="background: #f1f5f9; color: #475569;">Clear All Filters</a>
    </div>

    <div class="summary-counter">
        <?php 
            if(!empty($selected_dept) || !empty($selected_unit)){
                echo "Found " . $total_students . " student(s) matching your filters.";
            } else {
                echo "Total Registered Students: " . $total_students;
            }
        ?>
    </div>

    <form method="POST" class="filter-bar">
        <div style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-size: 0.75rem; font-weight:700; color:#64748b;">Department</label>
            <select name="dept_filter" class="filter-input">
                <option value="">-- All Departments --</option>
                <optgroup label="Computing & Informatics">
                    <option value="Information Technology" <?php if($selected_dept == 'Information Technology') echo 'selected'; ?>>Information Technology</option>
                    <option value="Computer Science" <?php if($selected_dept == 'Computer Science') echo 'selected'; ?>>Computer Science</option>
                </optgroup>
                <optgroup label="Health Sciences">
                    <option value="Nursing" <?php if($selected_dept == 'Nursing') echo 'selected'; ?>>Nursing</option>
                    <option value="Public Health" <?php if($selected_dept == 'Public Health') echo 'selected'; ?>>Public Health</option>
                    <option value="Pharmacy" <?php if($selected_dept == 'Pharmacy') echo 'selected'; ?>>Pharmacy</option>
                </optgroup>
                <optgroup label="Business">
                    <option value="Economics" <?php if($selected_dept == 'Economics') echo 'selected'; ?>>Economics</option>
                    <option value="Management" <?php if($selected_dept == 'Management') echo 'selected'; ?>>Management</option>
                </optgroup>
            </select>
        </div>

        <div style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-size: 0.75rem; font-weight:700; color:#64748b;">Specific Unit Code</label>
            <input type="text" name="unit_filter" placeholder="e.g. BIT 1101" class="filter-input" value="<?php echo htmlspecialchars($selected_unit); ?>">
        </div>

        <button type="submit" name="filter_reg" class="btn-action btn-confirm" style="margin-top: 18px;">Generate Report</button>
    </form>

    <table class="workload-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Unit</th>
                <th>Registration No.</th>
                <th>Level / Dept</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if($total_students > 0): $count = 1; ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo $count++; ?></td>
                    <td><span class="unit-badge"><?php echo htmlspecialchars($row['unit_code']); ?></span></td>
                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['student_reg_no']); ?></td>
                    <td>
                        <span style="display:block; font-weight: 500;">Year <?php echo $row['academic_year']; ?></span>
                        <small style="color:#64748b;"><?php echo htmlspecialchars($row['department']); ?></small>
                    </td>
                    <td><span class="status-tag <?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span></td>
                    <td>
                        <?php if($row['status'] == 'Provisional'): ?>
                            <a href="Admin-index.php?section=registrations&action=confirm&reg_id=<?php echo $row['id']; ?>" class="btn-action btn-confirm">Confirm</a>
                        <?php endif; ?>
                        <a href="Admin-index.php?section=registrations&action=drop&reg_id=<?php echo $row['id']; ?>" class="btn-action btn-drop" onclick="return confirm('Drop student?')">Drop</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center; padding: 40px; color: #94a3b8;">No students found for this department/unit combination.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>