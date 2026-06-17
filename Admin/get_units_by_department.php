<?php
header('Content-Type: application/json');

$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$department = isset($_GET['dept']) ? mysqli_real_escape_string($conn, $_GET['dept']) : '';

if (empty($department)) {
    echo json_encode([]);
    exit();
}

$query = "SELECT DISTINCT t.unit_code, aw.unit_name, t.lecturer 
          FROM timetable t 
          JOIN academic_workload aw ON t.unit_code = aw.unit_code 
          WHERE aw.department = '$department'
          ORDER BY t.unit_code";

$result = mysqli_query($conn, $query);
$units = [];

while ($row = mysqli_fetch_assoc($result)) {
    $units[] = [
        'unit_code' => $row['unit_code'],
        'unit_name' => $row['unit_name'],
        'lecturer' => $row['lecturer']
    ];
}

echo json_encode($units);
?>