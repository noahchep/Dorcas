<?php
$action = $_GET['action'] ?? 'view';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit_code = $_POST['unit_code'];
    $day = $_POST['day'];
    $time_from = $_POST['time_from'];
    $time_to = $_POST['time_to'];
    $venue = $_POST['venue'];
    $lecturer = $_POST['lecturer'];
    $year_level = $_POST['year_level'];
    $semester = $_POST['semester'];
    $academic_year = date('Y');
    
    $stmt = $conn->prepare("INSERT INTO timetable (unit_code, course_title, day_of_week, time_from, time_to, venue, lecturer, year_level, semester, academic_year) 
                            SELECT ?, aw.unit_name, ?, ?, ?, ?, ?, ?, ?, ? 
                            FROM academic_workload aw WHERE aw.unit_code = ?");
    $stmt->bind_param("ssssssssss", $unit_code, $day, $time_from, $time_to, $venue, $lecturer, $year_level, $semester, $academic_year, $unit_code);
    $stmt->execute();
    echo '<div class="alert alert-success">✅ Schedule added successfully!</div>';
}

if ($action === 'view'):
?>
<h2>📅 Timetable Management</h2>

<div style="margin-bottom: 20px;">
    <a href="Admin-index.php?section=timetable&action=add" class="btn btn-primary">+ Add New Schedule</a>
</div>

<?php
$query = "SELECT t.*, aw.unit_name FROM timetable t 
          JOIN academic_workload aw ON t.unit_code = aw.unit_code 
          ORDER BY t.year_level, t.semester, FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), t.time_from";
$result = mysqli_query($conn, $query);
?>

<table class="data-table">
    <thead>
        <tr><th>Unit</th><th>Course Title</th><th>Day</th><th>Time</th><th>Venue</th><th>Lecturer</th><th>Year/Sem</th><th>Actions</th></tr>
    </thead>
    <tbody>
        <?php while($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td><?php echo $row['unit_code']; ?></td>
            <td><?php echo htmlspecialchars($row['unit_name']); ?></td>
            <td><?php echo $row['day_of_week']; ?></td>
            <td><?php echo $row['time_from'] . ' - ' . $row['time_to']; ?></td>
            <td><?php echo $row['venue']; ?></td>
            <td><?php echo $row['lecturer']; ?></td>
            <td><?php echo $row['year_level'] . ' / Sem ' . $row['semester']; ?></td>
            <td>
                <button class="btn btn-warning btn-sm">Edit</button>
                <button class="btn btn-danger btn-sm">Delete</button>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php elseif ($action === 'add'): ?>
<h2>➕ Add Timetable Schedule</h2>
<form method="POST">
    <div class="form-group">
        <label>Unit Code *</label>
        <select name="unit_code" required>
            <option value="">Select Unit</option>
            <?php
            $units = mysqli_query($conn, "SELECT unit_code, unit_name FROM academic_workload ORDER BY unit_code");
            while($u = mysqli_fetch_assoc($units)):
            ?>
            <option value="<?php echo $u['unit_code']; ?>"><?php echo $u['unit_code'] . ' - ' . $u['unit_name']; ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Day *</label>
        <select name="day" required>
            <option>Monday</option><option>Tuesday</option><option>Wednesday</option>
            <option>Thursday</option><option>Friday</option>
        </select>
    </div>
    <div class="form-group">
        <label>Time From *</label>
        <input type="time" name="time_from" required>
    </div>
    <div class="form-group">
        <label>Time To *</label>
        <input type="time" name="time_to" required>
    </div>
    <div class="form-group">
        <label>Venue *</label>
        <input type="text" name="venue" required>
    </div>
    <div class="form-group">
        <label>Lecturer</label>
        <input type="text" name="lecturer">
    </div>
    <div class="form-group">
        <label>Year Level *</label>
        <select name="year_level" required>
            <option>First Year</option><option>Second Year</option>
            <option>Third Year</option><option>Fourth Year</option>
        </select>
    </div>
    <div class="form-group">
        <label>Semester *</label>
        <select name="semester" required>
            <option value="1">1st Semester</option>
            <option value="2">2nd Semester</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Add Schedule</button>
    <a href="Admin-index.php?section=timetable" class="btn">Back</a>
</form>
<?php endif; ?>