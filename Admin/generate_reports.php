
<?php
// Get statistics for reports
$dept_stats = mysqli_query($conn, "SELECT department, COUNT(*) as count FROM users WHERE role='student' GROUP BY department");
$reg_stats = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM registered_courses GROUP BY status");
$unit_stats = mysqli_query($conn, "SELECT year_level, COUNT(*) as count FROM academic_workload GROUP BY year_level");
?>

<h2>📈 Report Generation</h2>

<div class="dashboard-grid">
    <div class="stat-card actionable" onclick="exportReport('students')">
        <div style="font-size: 1.5rem;">👥</div>
        <p>Export Student List</p>
        <small>CSV / Excel format</small>
    </div>
    <div class="stat-card actionable" onclick="exportReport('registrations')">
        <div style="font-size: 1.5rem;">📋</div>
        <p>Registration Report</p>
        <small>All registrations summary</small>
    </div>
    <div class="stat-card actionable" onclick="exportReport('units')">
        <div style="font-size: 1.5rem;">📚</div>
        <p>Unit Master List</p>
        <small>All units by department</small>
    </div>
    <div class="stat-card actionable" onclick="exportReport('timetable')">
        <div style="font-size: 1.5rem;">📅</div>
        <p>Timetable Export</p>
        <small>Full schedule</small>
    </div>
</div>

<h3>Student Distribution by Department</h3>
<table class="data-table">
    <tr><th>Department</th><th>Number of Students</th><th>Percentage</th></tr>
    <?php 
    $total = array_sum(array_column(mysqli_fetch_all($dept_stats, MYSQLI_ASSOC), 'count'));
    mysqli_data_seek($dept_stats, 0);
    while($row = mysqli_fetch_assoc($dept_stats)): 
        $percent = ($row['count'] / $total) * 100;
    ?>
    <tr><td><?php echo $row['department']; ?></td><td><?php echo $row['count']; ?></td><td><?php echo round($percent, 1); ?>%</td></tr>
    <?php endwhile; ?>
</table>

<h3>Registration Status Overview</h3>
<table class="data-table">
    <tr><th>Status</th><th>Count</th></tr>
    <?php while($row = mysqli_fetch_assoc($reg_stats)): ?>
    <tr><td><?php echo $row['status']; ?></td><td><?php echo $row['count']; ?></td></tr>
    <?php endwhile; ?>
</table>

<script>
function exportReport(type, format) {
    // If format not specified, default to CSV
    if (!format) format = 'csv';
    window.location.href = 'export_report.php?type=' + type + '&format=' + format;
}
</script>

<!-- Add format buttons next to each export option -->
<div class="dashboard-grid">
    <div class="stat-card actionable">
        <div style="font-size: 1.5rem;">👥</div>
        <p>Export Student List</p>
        <div style="margin-top: 10px;">
            <button onclick="exportReport('students', 'csv')" style="background: #4f46e5; color: white; border: none; padding: 5px 10px; border-radius: 5px; margin-right: 5px;">CSV</button>
            <button onclick="exportReport('students', 'excel')" style="background: #10b981; color: white; border: none; padding: 5px 10px; border-radius: 5px;">Excel</button>
        </div>
    </div>
    <div class="stat-card actionable">
        <div style="font-size: 1.5rem;">📋</div>
        <p>Registration Report</p>
        <div style="margin-top: 10px;">
            <button onclick="exportReport('registrations', 'csv')" style="background: #4f46e5; color: white; border: none; padding: 5px 10px; border-radius: 5px; margin-right: 5px;">CSV</button>
            <button onclick="exportReport('registrations', 'excel')" style="background: #10b981; color: white; border: none; padding: 5px 10px; border-radius: 5px;">Excel</button>
        </div>
    </div>
    <div class="stat-card actionable">
        <div style="font-size: 1.5rem;">📚</div>
        <p>Unit Master List</p>
        <div style="margin-top: 10px;">
            <button onclick="exportReport('units', 'csv')" style="background: #4f46e5; color: white; border: none; padding: 5px 10px; border-radius: 5px; margin-right: 5px;">CSV</button>
            <button onclick="exportReport('units', 'excel')" style="background: #10b981; color: white; border: none; padding: 5px 10px; border-radius: 5px;">Excel</button>
        </div>
    </div>
    <div class="stat-card actionable">
        <div style="font-size: 1.5rem;">📅</div>
        <p>Timetable Export</p>
        <div style="margin-top: 10px;">
            <button onclick="exportReport('timetable', 'csv')" style="background: #4f46e5; color: white; border: none; padding: 5px 10px; border-radius: 5px; margin-right: 5px;">CSV</button>
            <button onclick="exportReport('timetable', 'excel')" style="background: #10b981; color: white; border: none; padding: 5px 10px; border-radius: 5px;">Excel</button>
        </div>
    </div>
</div>