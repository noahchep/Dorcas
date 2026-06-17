<?php
/* ==========================
   1. DATABASE & POST LOGIC
   ========================== */
include_once('db_connect.php');
if (!$conn) {
    // Fallback connection if db_connect.php isn't used
    $conn = mysqli_connect("localhost", "root", "", "portal-asisstant-ai");
}

$message = ""; 

// Handle the Timetable Modal Submission
if (isset($_POST['save_to_timetable'])) {
    $u_code = mysqli_real_escape_string($conn, $_POST['unit_code']);
    $u_title = mysqli_real_escape_string($conn, $_POST['course_title']);
    $u_sem = mysqli_real_escape_string($conn, $_POST['semester']);
    $time_from = mysqli_real_escape_string($conn, $_POST['time_from']);
    $time_to = mysqli_real_escape_string($conn, $_POST['time_to']);
    $venue = mysqli_real_escape_string($conn, $_POST['venue']);
    $lecturer = mysqli_real_escape_string($conn, $_POST['lecturer']);
    $u_group = mysqli_real_escape_string($conn, $_POST['unit_group']); 
    $academic_year = date("Y");

    $insert_sql = "INSERT INTO timetable (unit_code, course_title, time_from, time_to, venue, unit_group, lecturer, semester, academic_year) 
                    VALUES ('$u_code', '$u_title', '$time_from', '$time_to', '$venue', '$u_group', '$lecturer', '$u_sem', '$academic_year')";
    
    if(mysqli_query($conn, $insert_sql)) {
        echo "<script>alert('Unit successfully scheduled for group $u_group!'); window.location='Admin-index.php?section=units';</script>";
    } else {
        echo "<script>alert('Error: " . mysqli_error($conn) . "');</script>";
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

<form class="search-container" method="GET" action="Admin-index.php" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 25px; display: flex; gap: 15px; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); flex-wrap: wrap;">
    <input type="hidden" name="section" value="units">
    
    <div style="flex-grow: 1; min-width: 200px;">
        <input type="text" name="search" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;" 
               placeholder="Search by Code or Unit Name..." value="<?php echo htmlspecialchars($search); ?>">
    </div>

    <select name="department" style="padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: white; max-width: 250px;">
        <option value="all">All Departments</option>

        <optgroup label="Computing & Informatics">
            <option value="Information Technology" <?php if($dept_filter == 'Information Technology') echo 'selected'; ?>>Information Technology</option>
            <option value="Information Science" <?php if($dept_filter == 'Information Science') echo 'selected'; ?>>Information Science & Knowledge Management</option>
        </optgroup>

        <optgroup label="Business & Economics">
            <option value="Management" <?php if($dept_filter == 'Management') echo 'selected'; ?>>Management</option>
            <option value="Economics" <?php if($dept_filter == 'Economics') echo 'selected'; ?>>Economics</option>
            <option value="Accounting and Finance" <?php if($dept_filter == 'Accounting and Finance') echo 'selected'; ?>>Accounting and Finance</option>
        </optgroup>

        <optgroup label="Health Sciences & Medicine">
            <option value="Community Health" <?php if($dept_filter == 'Community Health') echo 'selected'; ?>>Community Health, Epidemiology & Biostatistics</option>
            <option value="Environmental Health" <?php if($dept_filter == 'Environmental Health') echo 'selected'; ?>>Environmental Health & Health Systems Management</option>
            <option value="Nursing" <?php if($dept_filter == 'Nursing') echo 'selected'; ?>>Nursing</option>
            <option value="Pharmacy" <?php if($dept_filter == 'Pharmacy') echo 'selected'; ?>>Pharmacy</option>
            <option value="Medical School" <?php if($dept_filter == 'Medical School') echo 'selected'; ?>>Medical School</option>
            <option value="Clinical Medicine" <?php if($dept_filter == 'Clinical Medicine') echo 'selected'; ?>>Clinical Medicine</option>
        </optgroup>

        <optgroup label="Education">
            <option value="Educational Management" <?php if($dept_filter == 'Educational Management') echo 'selected'; ?>>Educational Management & Curriculum Studies</option>
            <option value="Educational Psychology" <?php if($dept_filter == 'Educational Psychology') echo 'selected'; ?>>Educational Psychology & Technology (EPT)</option>
            <option value="Special Needs Education" <?php if($dept_filter == 'Special Needs Education') echo 'selected'; ?>>Special Needs Education & Early Childhood</option>
        </optgroup>

        <optgroup label="Engineering & Built Environment">
            <option value="Energy Engineering" <?php if($dept_filter == 'Energy Engineering') echo 'selected'; ?>>Energy & Environmental Engineering</option>
            <option value="Electrical Engineering" <?php if($dept_filter == 'Electrical Engineering') echo 'selected'; ?>>Electrical & Electronic Engineering</option>
        </optgroup>

        <optgroup label="Pure & Applied Sciences">
            <option value="Natural Sciences" <?php if($dept_filter == 'Natural Sciences') echo 'selected'; ?>>Natural Sciences</option>
            <option value="Animal Health" <?php if($dept_filter == 'Animal Health') echo 'selected'; ?>>Animal Health and Production</option>
        </optgroup>

        <optgroup label="Social Sciences & Humanities">
            <option value="Psychology" <?php if($dept_filter == 'Psychology') echo 'selected'; ?>>Psychology, Humanities & Languages</option>
            <option value="Law" <?php if($dept_filter == 'Law') echo 'selected'; ?>>Law</option>
            <option value="Security Studies" <?php if($dept_filter == 'Security Studies') echo 'selected'; ?>>Security Studies, Justice and Ethics</option>
            <option value="Journalism" <?php if($dept_filter == 'Journalism') echo 'selected'; ?>>Journalism & Mass Communication</option>
        </optgroup>

        <optgroup label="Hospitality & Tourism">
            <option value="Hospitality Management" <?php if($dept_filter == 'Hospitality Management') echo 'selected'; ?>>Hospitality Management</option>
            <option value="Travel and Tourism Management" <?php if($dept_filter == 'Travel and Tourism Management') echo 'selected'; ?>>Travel and Tourism Management</option>
        </optgroup>
    </select>

    <select name="year_level" style="padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: white;">
        <option value="all">All Year Levels</option>
        <option value="First Year" <?php if($year_filter == 'First Year') echo 'selected'; ?>>First Year</option>
        <option value="Second Year" <?php if($year_filter == 'Second Year') echo 'selected'; ?>>Second Year</option>
        <option value="Third Year" <?php if($year_filter == 'Third Year') echo 'selected'; ?>>Third Year</option>
        <option value="Fourth Year" <?php if($year_filter == 'Fourth Year') echo 'selected'; ?>>Fourth Year</option>
    </select>
    
    <button type="submit" style="background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600;">Filter Results</button>
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
                    <th style="padding: 12px;">Department</th>
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
            $badgeStyle = ($offering == 'Every Semester') ? "background: #dcfce7; color: #166534;" : "background: #fee2e2; color: #991b1b;";
            $semVal = ($row['semester_level'] == '1st Semester') ? '1' : (($row['semester_level'] == '2nd Semester') ? '2' : '3');
            
            // Reduced font-size to 0.85rem and added strict nowrap
            echo "<tr style='border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;'>
                    <td style='padding: 12px; color: var(--text-light); vertical-align: middle;'>$i</td>
                    
                    <td style='padding: 12px; vertical-align: middle; white-space: nowrap;'>
                        <span style='background: var(--accent); color: var(--primary-dark); padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;'>{$row['unit_code']}</span>
                    </td>
                    
                    <td style='padding: 12px; vertical-align: middle; white-space: nowrap; min-width: 250px;'>{$row['unit_name']}</td>
                    
                    <td style='padding: 12px; vertical-align: middle; white-space: nowrap;'>{$row['department']}</td>
                    
                    <td style='padding: 12px; vertical-align: middle; white-space: nowrap;'>{$row['year_level']}</td>
                    
                    <td style='padding: 12px; vertical-align: middle; white-space: nowrap;'>{$row['semester_level']}</td>
                    
                    <td style='padding: 12px; vertical-align: middle; white-space: nowrap;'>
                        <span style='padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: bold; $badgeStyle'>$offering</span>
                    </td>
                    
                    <td style='padding: 12px; text-align: center; vertical-align: middle;'>
                        <button onclick=\"openScheduleModal('{$row['unit_code']}', '" . addslashes($row['unit_name']) . "', '$semVal')\" 
                                style='background: #4f46e5; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 0.7rem; font-weight: bold; white-space: nowrap;'>
                            + Schedule
                        </button>
                    </td>
                  </tr>";
            $i++; 
        }
    } else {
        echo "<tr><td colspan='8' style='padding: 30px; text-align: center; color: var(--text-light);'>No workload units found.</td></tr>";
    }
    ?>
</tbody>
        </table>
    </div>
</div>

<div id="scheduleModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:white; padding:30px; border-radius:15px; width:480px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <h3 id="modalTitle" style="margin-top:0; color:#4f46e5; border-bottom: 1px solid #eee; padding-bottom: 10px;">Schedule Unit</h3>
        <form method="POST">
            <input type="hidden" name="unit_code" id="form_unit_code">
            <input type="hidden" name="course_title" id="form_course_title">
            <input type="hidden" name="semester" id="form_semester">
            
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Lecturer Name</label>
                <input type="text" name="lecturer" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Student Group (e.g. BIT/S/JAN26)</label>
                <input type="text" name="unit_group" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
            </div>
            
            <div style="display:flex; gap:10px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">From</label>
                    <input type="time" name="time_from" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">To</label>
                    <input type="time" name="time_to" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Venue / Room No.</label>
                <input type="text" name="venue" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeModal()" style="background:#f1f5f9; border:none; padding:10px 20px; border-radius:8px; cursor:pointer;">Cancel</button>
                <button type="submit" name="save_to_timetable" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:bold;">Push to Timetable</button>
            </div>
        </form>
    </div>
</div>

<script>
function openScheduleModal(code, title, sem) {
    document.getElementById('scheduleModal').style.display = 'flex';
    document.getElementById('modalTitle').innerText = "Schedule: " + code;
    document.getElementById('form_unit_code').value = code;
    document.getElementById('form_course_title').value = title;
    document.getElementById('form_semester').value = sem;
}

function closeModal() {
    document.getElementById('scheduleModal').style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    let modal = document.getElementById('scheduleModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>