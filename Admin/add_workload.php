<?php
// 1. Only start session if one doesn't exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==========================
   DATABASE CONNECTION
========================== */
include_once('db_connect.php');
if (!$conn) {
    $conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
}

$message = "";

/* ==========================
   PROCESS FORM
========================== */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_workload'])) {
    
    // Sanitize inputs
    $code = mysqli_real_escape_string($conn, $_POST['unit_code']);
    $name = mysqli_real_escape_string($conn, $_POST['unit_name']);
    $dept = mysqli_real_escape_string($conn, $_POST['department']); // New Field
    $year = mysqli_real_escape_string($conn, $_POST['year_level']);
    $sem  = mysqli_real_escape_string($conn, $_POST['semester_level']);
    $time = mysqli_real_escape_string($conn, $_POST['offering_time']);

    // Insert into the table (Added department column)
    $sql = "INSERT INTO academic_workload (unit_code, unit_name, department, year_level, semester_level, offering_time) 
            VALUES ('$code', '$name', '$dept', '$year', '$sem', '$time')";
    
    if(mysqli_query($conn, $sql)) {
        $message = "success|Workload for $dept added to Master Plan successfully!";
    } else {
        $message = "error|Database Error: " . mysqli_error($conn);
    }
}
?>

<div class="section-box">
    <h2 style="margin-top: 0; color: var(--primary); font-size: 1.2rem; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
        Add Master Academic Path
    </h2>

    <?php if ($message): 
        $parts = explode('|', $message); ?>
        <div style="padding: 12px; margin-bottom: 20px; border-radius: 6px; font-weight: 600; 
             background: <?php echo $parts[0] == 'success' ? '#dcfce7' : '#fee2e2'; ?>; 
             color: <?php echo $parts[0] == 'success' ? '#166534' : '#991b1b'; ?>;
             border: 1px solid <?php echo $parts[0] == 'success' ? '#bbf7d0' : '#fecaca'; ?>;">
            <?php echo $parts[1]; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="Admin-index.php?section=add_workload" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        
        <div class="form-group">
            <label style="display:block; font-weight:700; margin-bottom:5px; color: var(--text-main);">Unit Code</label>
            <input type="text" name="unit_code" required placeholder="e.g. BIT 2102" 
                   style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; box-sizing: border-box;">
        </div>

        <div class="form-group">
            <label style="display:block; font-weight:700; margin-bottom:5px; color: var(--text-main);">Unit Name</label>
            <input type="text" name="unit_name" required placeholder="e.g. Data Structures" 
                   style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; box-sizing: border-box;">
        </div>

        <div class="form-group" style="grid-column: span 2;">
            <label style="display:block; font-weight:700; margin-bottom:5px; color: var(--text-main);">Department</label>
            <select name="department" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; background: #fff; cursor: pointer;">
                <option value="">-- Choose Department --</option>

                <optgroup label="Computing & Informatics">
                    <option value="Information Technology">Information Technology</option>
                    <option value="Information Science">Information Science & Knowledge Management</option>
                </optgroup>

                <optgroup label="Business & Economics">
                    <option value="Management">Management</option>
                    <option value="Economics">Economics</option>
                    <option value="Accounting and Finance">Accounting and Finance</option>
                </optgroup>

                <optgroup label="Health Sciences & Medicine">
                    <option value="Community Health">Community Health, Epidemiology & Biostatistics</option>
                    <option value="Environmental Health">Environmental Health & Health Systems Management</option>
                    <option value="Nursing">Nursing</option>
                    <option value="Pharmacy">Pharmacy</option>
                    <option value="Medical School">Medical School</option>
                    <option value="Clinical Medicine">Clinical Medicine</option>
                </optgroup>

                <optgroup label="Education">
                    <option value="Educational Management">Educational Management & Curriculum Studies</option>
                    <option value="Educational Psychology">Educational Psychology & Technology (EPT)</option>
                    <option value="Special Needs Education">Special Needs Education & Early Childhood</option>
                </optgroup>

                <optgroup label="Engineering & Built Environment">
                    <option value="Energy Engineering">Energy & Environmental Engineering</option>
                    <option value="Electrical Engineering">Electrical & Electronic Engineering</option>
                </optgroup>

                <optgroup label="Pure & Applied Sciences">
                    <option value="Natural Sciences">Natural Sciences</option>
                    <option value="Animal Health">Animal Health and Production</option>
                </optgroup>

                <optgroup label="Social Sciences & Humanities">
                    <option value="Psychology">Psychology, Humanities & Languages</option>
                    <option value="Law">Law</option>
                    <option value="Security Studies">Security Studies, Justice and Ethics</option>
                    <option value="Journalism">Journalism & Mass Communication</option>
                </optgroup>

                <optgroup label="Hospitality & Tourism">
                    <option value="Hospitality Management">Hospitality Management</option>
                    <option value="Travel and Tourism">Travel and Tourism Management</option>
                </optgroup>
            </select>
        </div>

        <div class="form-group">
            <label style="display:block; font-weight:700; margin-bottom:5px; color: var(--text-main);">Year Level</label>
            <select name="year_level" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                <option value="First Year">First Year</option>
                <option value="Second Year">Second Year</option>
                <option value="Third Year">Third Year</option>
                <option value="Fourth Year">Fourth Year</option>
            </select>
        </div>

        <div class="form-group">
            <label style="display:block; font-weight:700; margin-bottom:5px; color: var(--text-main);">Semester</label>
            <select name="semester_level" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                <option value="1st Semester">1st Semester</option>
                <option value="2nd Semester">2nd Semester</option>
            </select>
        </div>

        <div class="form-group" style="grid-column: span 2;">
            <label style="display:block; font-weight:700; margin-bottom:5px; color: var(--text-main);">Offering Schedule</label>
            <select name="offering_time" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                <option value="Every Semester">Available Every Semester</option>
                <option value="Once a Year">Available Once a Year Only</option>
                <option value="May-Aug">Available May-Aug 2026</option>       
                <option value="Sep-Dec">Available Sep-Dec 2026</option>  
            </select>
        </div>

        <div style="grid-column: span 2; margin-top: 10px;">
            <button type="submit" name="save_workload" 
                    style="background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: 700; width: 200px; transition: background 0.2s;">
                Save to Master Plan
            </button>
        </div>
    </form>
</div>