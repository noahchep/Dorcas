<?php
// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    // Check if this is not the main admin
    $check_admin = mysqli_query($conn, "SELECT email FROM users WHERE id = $delete_id");
    $admin_data = mysqli_fetch_assoc($check_admin);
    
    if ($admin_data['email'] == 'admin@example.com') {
        echo '<div class="alert alert-error">❌ Cannot delete the main admin account!</div>';
    } else {
        $del_query = "DELETE FROM users WHERE id = $delete_id";
        if (mysqli_query($conn, $del_query)) {
            echo '<div class="alert alert-success">✅ User deleted successfully!</div>';
        } else {
            echo '<div class="alert alert-error">❌ Error deleting user!</div>';
        }
    }
}

// Handle lecturer assignment to unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_lecturer'])) {
    $unit_code = mysqli_real_escape_string($conn, $_POST['unit_code']);
    $lecturer_id = intval($_POST['lecturer_id']);
    
    // Get lecturer details
    $lec_query = mysqli_query($conn, "SELECT full_name, department FROM users WHERE id = $lecturer_id");
    $lecturer = mysqli_fetch_assoc($lec_query);
    $lecturer_name = $lecturer['full_name'];
    $lecturer_dept = $lecturer['department'];
    
    // Check if the unit belongs to the lecturer's department
    $check_unit = mysqli_query($conn, "SELECT department FROM academic_workload WHERE unit_code = '$unit_code'");
    $unit_data = mysqli_fetch_assoc($check_unit);
    
    if ($unit_data['department'] != $lecturer_dept) {
        echo '<div class="alert alert-error">❌ Error: This unit belongs to ' . $unit_data['department'] . ' department. Lecturer is from ' . $lecturer_dept . ' department. Cannot assign!</div>';
    } else {
        // Update timetable with lecturer
        $update_query = "UPDATE timetable SET lecturer = '$lecturer_name' WHERE unit_code = '$unit_code'";
        if (mysqli_query($conn, $update_query)) {
            echo '<div class="alert alert-success">✅ Lecturer assigned to unit ' . htmlspecialchars($unit_code) . ' successfully!</div>';
        } else {
            echo '<div class="alert alert-error">❌ Error assigning lecturer: ' . $conn->error . '</div>';
        }
    }
}

// Handle bulk assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_assign'])) {
    $lecturer_id = intval($_POST['bulk_lecturer_id']);
    $selected_units = $_POST['selected_units'] ?? [];
    
    if (!empty($selected_units)) {
        // Get lecturer details
        $lec_query = mysqli_query($conn, "SELECT full_name, department FROM users WHERE id = $lecturer_id");
        $lecturer = mysqli_fetch_assoc($lec_query);
        $lecturer_name = $lecturer['full_name'];
        $lecturer_dept = $lecturer['department'];
        
        $success_count = 0;
        $error_count = 0;
        $wrong_dept_units = [];
        
        foreach ($selected_units as $unit_code) {
            $unit_code = mysqli_real_escape_string($conn, $unit_code);
            
            // Check if unit belongs to lecturer's department
            $check_unit = mysqli_query($conn, "SELECT department FROM academic_workload WHERE unit_code = '$unit_code'");
            $unit_data = mysqli_fetch_assoc($check_unit);
            
            if ($unit_data['department'] == $lecturer_dept) {
                $update_query = "UPDATE timetable SET lecturer = '$lecturer_name' WHERE unit_code = '$unit_code'";
                if (mysqli_query($conn, $update_query)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                $wrong_dept_units[] = $unit_code;
                $error_count++;
            }
        }
        
        echo '<div class="alert alert-success">✅ Assigned ' . $success_count . ' units to ' . htmlspecialchars($lecturer_name) . '!</div>';
        if (!empty($wrong_dept_units)) {
            echo '<div class="alert alert-error">⚠️ Skipped ' . count($wrong_dept_units) . ' units from other departments: ' . implode(', ', $wrong_dept_units) . '</div>';
        }
    } else {
        echo '<div class="alert alert-error">❌ Please select at least one unit to assign!</div>';
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$department_filter = isset($_GET['department']) ? mysqli_real_escape_string($conn, $_GET['department']) : '';

// Build query with filters
$query = "SELECT id, full_name, email, reg_number, department, phone, role, created_at 
          FROM users 
          WHERE role = 'lecturer'";

if (!empty($search)) {
    $query .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%' OR reg_number LIKE '%$search%')";
}
if (!empty($department_filter)) {
    $query .= " AND department = '$department_filter'";
}

$query .= " ORDER BY full_name";
$result = mysqli_query($conn, $query);

// Get departments for filter dropdown
$dept_query = "SELECT DISTINCT department FROM academic_workload WHERE department IS NOT NULL UNION SELECT DISTINCT department FROM users WHERE role='lecturer' AND department IS NOT NULL";
$dept_result = mysqli_query($conn, $dept_query);
$departments = [];
while($dept = mysqli_fetch_assoc($dept_result)) {
    if(!empty($dept['department'])) {
        $departments[] = $dept['department'];
    }
}
?>

<style>
.filter-bar {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
}
.filter-group {
    display: inline-block;
    margin-right: 15px;
    margin-bottom: 10px;
}
.filter-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 5px;
    color: #64748b;
}
.filter-group input, .filter-group select {
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.85rem;
    min-width: 200px;
}
.btn-filter {
    background: #4f46e5;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    margin-top: 22px;
}
.btn-clear {
    background: #64748b;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    margin-top: 22px;
}
.assignment-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: center;
}
.assignment-modal-content {
    background: white;
    padding: 30px;
    border-radius: 16px;
    width: 90%;
    max-width: 700px;
    max-height: 80vh;
    overflow-y: auto;
}
.unit-checkbox {
    margin: 8px 0;
    padding: 8px;
    border-bottom: 1px solid #e2e8f0;
}
.unit-checkbox input {
    margin-right: 10px;
}
.select-all {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #4f46e5;
}
.batch-actions {
    margin-top: 20px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f1f5f9;
    border-radius: 8px;
}
.unit-search {
    margin-bottom: 15px;
    padding: 10px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.unit-search input {
    width: 100%;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.9rem;
}
.units-container {
    max-height: 350px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px;
}
.unit-item {
    display: none;
}
.unit-item.show {
    display: block;
}
.selected-count {
    background: #4f46e5;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    margin-left: 10px;
}
</style>

<h2>👨‍🏫 Lecturer/Staff Management</h2>

<div style="margin-bottom: 20px;">
    <a href="Admin-index.php?section=add_lecturer" class="btn btn-primary">+ Add New Staff Member</a>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" action="Admin-index.php">
        <input type="hidden" name="section" value="lecturers">
        
        <div class="filter-group">
            <label>🔍 Search</label>
            <input type="text" name="search" placeholder="Name, Email, Staff No..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <div class="filter-group">
            <label>📚 Department</label>
            <select name="department">
                <option value="">All Departments</option>
                <?php foreach($departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($department_filter == $dept) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <button type="submit" class="btn-filter">Apply Filters</button>
            <a href="Admin-index.php?section=lecturers" class="btn-clear">Clear All</a>
        </div>
    </form>
</div>

<!-- Batch Assignment Section -->
<div class="batch-actions">
    <strong>📋 Batch Lecturer Assignment:</strong>
    <button onclick="openAssignmentModal()" class="btn btn-primary" style="margin-left: 15px;">Assign Lecturer to Multiple Units</button>
</div>

<?php if (mysqli_num_rows($result) == 0): ?>
    <div class="alert" style="background: #fef3c7; color: #92400e; padding: 20px; border-radius: 8px; border-left: 4px solid #f59e0b;">
        <strong>⚠️ No lecturers found!</strong><br><br>
        <p>You haven't added any lecturers yet. Click the <strong>"Add New Staff Member"</strong> button above to add your first lecturer.</p>
    </div>
<?php else: ?>
    <p><strong>Total Lecturers:</strong> <?php echo mysqli_num_rows($result); ?></p>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Staff Number</th>
                <th>Department</th>
                <th>Role</th>
                <th>Registered On</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
            <?php
            // Get units for this lecturer's department only
            $dept_units_query = "SELECT COUNT(*) as count FROM academic_workload WHERE department = '{$row['department']}'";
            $dept_units_result = mysqli_query($conn, $dept_units_query);
            $dept_units = mysqli_fetch_assoc($dept_units_result);
            ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td>
                    <?php if(!empty($row['phone'])): ?>
                        <a href="tel:<?php echo $row['phone']; ?>" style="text-decoration: none; color: #4f46e5;">
                            📞 <?php echo htmlspecialchars($row['phone']); ?>
                        </a>
                    <?php else: ?>
                        <span style="color: #999;">Not provided</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($row['reg_number']); ?></td>
                <td>
                    <span class="status-badge" style="background: #e0e7ff; color: #3730a3;">
                        <?php echo htmlspecialchars($row['department'] ?? 'Not Assigned'); ?>
                    </span>
                    <small style="display: block; color: #666;">(<?php echo $dept_units['count']; ?> units)</small>
                </td>
                <td>
                    <span class="status-badge" style="background: #d1fae5; color: #065f46;">
                        <?php echo ucfirst($row['role']); ?>
                    </span>
                </td>
                <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                <td>
                    <button class="btn btn-warning btn-sm" onclick="editUser(<?php echo $row['id']; ?>)">Edit</button>
                    <button class="btn btn-primary btn-sm" onclick="assignUnitToLecturer(<?php echo $row['id']; ?>, '<?php echo addslashes($row['full_name']); ?>', '<?php echo addslashes($row['department']); ?>')">Assign Unit</button>
                    <a href="?section=lecturers&delete_id=<?php echo $row['id']; ?>" 
                       class="btn btn-danger btn-sm" 
                       onclick="return confirm('Are you sure you want to delete <?php echo addslashes($row['full_name']); ?>?')">Delete</a>
                 </td>
             </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Assignment Modal (Single Unit) -->
<div id="assignModal" class="assignment-modal">
    <div class="assignment-modal-content">
        <h3>📚 Assign Lecturer to Unit</h3>
        <form method="POST" action="">
            <input type="hidden" name="assign_lecturer" value="1">
            <input type="hidden" name="lecturer_id" id="assign_lecturer_id">
            <input type="hidden" name="lecturer_dept" id="assign_lecturer_dept">
            
            <div class="form-group">
                <label>Lecturer</label>
                <input type="text" id="assign_lecturer_name" readonly style="background: #f3f4f6; padding: 10px; border-radius: 6px; width: 100%;">
            </div>
            
            <div class="form-group">
                <label>Department</label>
                <input type="text" id="assign_lecturer_dept_display" readonly style="background: #f3f4f6; padding: 10px; border-radius: 6px; width: 100%;">
            </div>
            
            <div class="form-group">
                <label>Select Unit to Assign (Only showing <?php echo htmlspecialchars($row['department'] ?? ''); ?> department units)</label>
                <div class="unit-search">
                    <input type="text" id="unitSearch" placeholder="🔍 Search by Unit Code or Name..." onkeyup="filterUnits()">
                </div>
                <select name="unit_code" id="unitSelect" required style="width: 100%; padding: 10px; border-radius: 6px; min-height: 200px;">
                    <option value="">-- Select Unit --</option>
                </select>
            </div>
            
            <div class="modal-buttons" style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">Assign Lecturer</button>
                <button type="button" onclick="closeAssignmentModal()" class="btn" style="background: #64748b; color: white;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Assignment Modal -->
<div id="bulkAssignModal" class="assignment-modal">
    <div class="assignment-modal-content">
        <h3>📚 Bulk Assign Lecturer to Multiple Units</h3>
        <form method="POST" action="" id="bulkAssignForm">
            <input type="hidden" name="bulk_assign" value="1">
            
            <div class="form-group">
                <label>Select Lecturer</label>
                <select name="bulk_lecturer_id" id="bulk_lecturer_id" required style="width: 100%; padding: 10px; border-radius: 6px;" onchange="loadUnitsForLecturer()">
                    <option value="">-- Select Lecturer --</option>
                    <?php 
                    mysqli_data_seek($result, 0);
                    while($lec = mysqli_fetch_assoc($result)): 
                    ?>
                        <option value="<?php echo $lec['id']; ?>" data-dept="<?php echo htmlspecialchars($lec['department']); ?>">
                            <?php echo $lec['full_name'] . ' (' . $lec['department'] . ')'; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Search Units</label>
                <div class="unit-search">
                    <input type="text" id="bulkUnitSearch" placeholder="🔍 Search by Unit Code or Name..." onkeyup="filterBulkUnits()">
                </div>
            </div>
            
            <div class="form-group">
                <label>Select Units to Assign (Only showing selected lecturer's department units)</label>
                <div class="select-all">
                    <label>
                        <input type="checkbox" id="selectAllUnits" onclick="toggleAllUnits()"> 
                        <strong>Select All Units</strong>
                        <span id="selectedCount" class="selected-count">0 selected</span>
                    </label>
                </div>
                <div id="unitsList" class="units-container">
                    <div class="loading-units" style="text-align: center; padding: 20px; color: #666;">
                        Select a lecturer first to see available units
                    </div>
                </div>
            </div>
            
            <div class="modal-buttons" style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">Assign Selected Units</button>
                <button type="button" onclick="closeBulkAssignmentModal()" class="btn" style="background: #64748b; color: white;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
let allUnitsData = [];

function editUser(id) {
    window.location.href = 'Admin-index.php?section=edit_user&id=' + id;
}

function assignUnitToLecturer(lecturerId, lecturerName, lecturerDept) {
    document.getElementById('assign_lecturer_id').value = lecturerId;
    document.getElementById('assign_lecturer_name').value = lecturerName;
    document.getElementById('assign_lecturer_dept').value = lecturerDept;
    document.getElementById('assign_lecturer_dept_display').value = lecturerDept;
    
    // Load units for this department
    loadUnitsForDepartment(lecturerDept);
    
    document.getElementById('assignModal').style.display = 'flex';
}

function loadUnitsForDepartment(department) {
    fetch(`get_units_by_department.php?dept=${encodeURIComponent(department)}`)
        .then(response => response.json())
        .then(data => {
            allUnitsData = data;
            const unitSelect = document.getElementById('unitSelect');
            unitSelect.innerHTML = '<option value="">-- Select Unit --</option>';
            
            data.forEach(unit => {
                const option = document.createElement('option');
                option.value = unit.unit_code;
                option.textContent = `${unit.unit_code} - ${unit.unit_name} (Current: ${unit.lecturer || 'Not Assigned'})`;
                option.setAttribute('data-name', unit.unit_name.toLowerCase());
                option.setAttribute('data-code', unit.unit_code.toLowerCase());
                unitSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading units:', error));
}

function filterUnits() {
    const searchTerm = document.getElementById('unitSearch').value.toLowerCase();
    const options = document.getElementById('unitSelect').options;
    
    for (let i = 0; i < options.length; i++) {
        const option = options[i];
        if (option.value === '') continue;
        
        const text = option.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    }
}

function openAssignmentModal() {
    document.getElementById('bulkAssignModal').style.display = 'flex';
}

function closeAssignmentModal() {
    document.getElementById('assignModal').style.display = 'none';
    document.getElementById('unitSearch').value = '';
}

function closeBulkAssignmentModal() {
    document.getElementById('bulkAssignModal').style.display = 'none';
    document.getElementById('bulkUnitSearch').value = '';
}

function loadUnitsForLecturer() {
    const select = document.getElementById('bulk_lecturer_id');
    const selectedOption = select.options[select.selectedIndex];
    const department = selectedOption.getAttribute('data-dept');
    const lecturerId = select.value;
    
    if (!lecturerId) {
        document.getElementById('unitsList').innerHTML = '<div class="loading-units" style="text-align: center; padding: 20px; color: #666;">Select a lecturer first to see available units</div>';
        return;
    }
    
    document.getElementById('unitsList').innerHTML = '<div class="loading-units" style="text-align: center; padding: 20px;">Loading units...</div>';
    
    fetch(`get_units_by_department.php?dept=${encodeURIComponent(department)}`)
        .then(response => response.json())
        .then(data => {
            allUnitsData = data;
            const unitsList = document.getElementById('unitsList');
            unitsList.innerHTML = '';
            
            data.forEach(unit => {
                const div = document.createElement('div');
                div.className = 'unit-checkbox unit-item show';
                div.setAttribute('data-code', unit.unit_code.toLowerCase());
                div.setAttribute('data-name', unit.unit_name.toLowerCase());
                div.innerHTML = `
                    <label>
                        <input type="checkbox" name="selected_units[]" value="${unit.unit_code}" onchange="updateSelectedCount()">
                        <strong>${unit.unit_code}</strong> - ${unit.unit_name}
                        <span style="color: #666; font-size: 0.8rem;">(Current: ${unit.lecturer || 'Not Assigned'})</span>
                    </label>
                `;
                unitsList.appendChild(div);
            });
            
            updateSelectedCount();
        })
        .catch(error => {
            console.error('Error loading units:', error);
            document.getElementById('unitsList').innerHTML = '<div class="loading-units" style="text-align: center; padding: 20px; color: red;">Error loading units</div>';
        });
}

function filterBulkUnits() {
    const searchTerm = document.getElementById('bulkUnitSearch').value.toLowerCase();
    const items = document.querySelectorAll('.unit-item');
    
    items.forEach(item => {
        const code = item.getAttribute('data-code');
        const name = item.getAttribute('data-name');
        
        if (code.includes(searchTerm) || name.includes(searchTerm)) {
            item.classList.add('show');
        } else {
            item.classList.remove('show');
        }
    });
}

function toggleAllUnits() {
    const selectAll = document.getElementById('selectAllUnits');
    const checkboxes = document.querySelectorAll('input[name="selected_units[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('input[name="selected_units[]"]:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').innerHTML = `${count} selected`;
}

// Close modals when clicking outside
window.onclick = function(event) {
    const assignModal = document.getElementById('assignModal');
    const bulkModal = document.getElementById('bulkAssignModal');
    if (event.target == assignModal) {
        assignModal.style.display = 'none';
    }
    if (event.target == bulkModal) {
        bulkModal.style.display = 'none';
    }
}
</script>