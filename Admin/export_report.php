<?php
session_start();

/* ==========================
   ACCESS CONTROL
========================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* ==========================
   DATABASE CONNECTION
========================== */
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get the export type from URL parameter
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Set filename based on export type
$filename = date('Y-m-d') . '_';
switch($type) {
    case 'students':
        $filename .= 'students_list';
        break;
    case 'registrations':
        $filename .= 'registrations_report';
        break;
    case 'units':
        $filename .= 'units_master_list';
        break;
    case 'timetable':
        $filename .= 'timetable_schedule';
        break;
    default:
        die("Invalid export type");
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Export based on type
switch($type) {
    case 'students':
        // Export Student List
        fputcsv($output, ['ID', 'Full Name', 'Registration Number', 'Email', 'Department', 'Phone', 'Registration Date']);
        
        $query = "SELECT id, full_name, reg_number, email, department, phone, created_at 
                  FROM users 
                  WHERE role = 'student' 
                  ORDER BY full_name";
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            while($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['id'],
                    $row['full_name'],
                    $row['reg_number'],
                    $row['email'],
                    $row['department'],
                    $row['phone'] ?? 'Not provided',
                    date('Y-m-d', strtotime($row['created_at']))
                ]);
            }
        }
        break;
        
    case 'registrations':
        // Export Registration Report - FIXED: Using 'registered_at' column
        fputcsv($output, ['Student Name', 'Registration Number', 'Unit Code', 'Unit Name', 'Department', 'Exam Type', 'Class Group', 'Semester', 'Academic Year', 'Status', 'Registered At']);
        
        $query = "SELECT rc.*, u.full_name, u.reg_number, u.department, aw.unit_name 
                  FROM registered_courses rc
                  JOIN users u ON rc.student_reg_no = u.reg_number
                  JOIN academic_workload aw ON rc.unit_code = aw.unit_code
                  ORDER BY rc.registered_at DESC";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['full_name'],
                    $row['reg_number'],
                    $row['unit_code'],
                    $row['unit_name'],
                    $row['department'],
                    $row['exam_type'] ?? 'N/A',
                    $row['class_group'] ?? 'N/A',
                    $row['semester'] ?? 'N/A',
                    $row['academic_year'] ?? 'N/A',
                    $row['status'],
                    date('Y-m-d H:i:s', strtotime($row['registered_at']))
                ]);
            }
        } else {
            fputcsv($output, ['No registration records found']);
        }
        break;
        
    case 'units':
        // Export Unit Master List
        fputcsv($output, ['Unit Code', 'Unit Name', 'Department', 'Year Level', 'Semester', 'Offering Time']);
        
        $query = "SELECT unit_code, unit_name, department, year_level, semester_level, offering_time 
                  FROM academic_workload 
                  ORDER BY department, year_level, semester_level, unit_code";
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            while($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['unit_code'],
                    $row['unit_name'],
                    $row['department'],
                    $row['year_level'],
                    $row['semester_level'],
                    $row['offering_time']
                ]);
            }
        }
        break;
        
    case 'timetable':
        // Export Timetable Schedule
        fputcsv($output, ['Unit Code', 'Course Title', 'Day', 'Time From', 'Time To', 'Venue', 'Lecturer', 'Year Level', 'Semester', 'Academic Year']);
        
        $query = "SELECT t.*, aw.unit_name 
                  FROM timetable t 
                  JOIN academic_workload aw ON t.unit_code = aw.unit_code 
                  ORDER BY t.year_level, t.semester, FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), t.time_from";
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            while($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['unit_code'],
                    $row['unit_name'],
                    $row['day_of_week'],
                    $row['time_from'],
                    $row['time_to'],
                    $row['venue'],
                    $row['lecturer'] ?? 'TBA',
                    $row['year_level'],
                    $row['semester'],
                    $row['academic_year'] ?? date('Y')
                ]);
            }
        }
        break;
}

// Close the database connection
mysqli_close($conn);
?>