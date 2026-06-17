<?php
session_start();

/* ==========================
    ACCESS CONTROL
========================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

/* ==========================
    DATABASE CONNECTION
========================== */
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

/* ==========================
    EXPORT LOGIC (JOINED QUERY)
========================== */
$filename = "OrientaCore_Survey_Analysis_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Professional Headers for your BIT Project
fputcsv($output, array(
    'Response ID', 
    'Reg Number',      // Pulling from users table
    'Student Name',    // Pulling from users table
    'Challenge Type', 
    'UI Experience', 
    'AI Helpfulness', 
    'Ease Rating (1-5)', 
    'Comments'
));

// This query JOINS the survey with the users table to get real names/reg numbers
$query = "SELECT s.id, u.reg_no, u.full_name, s.challenge_type, 
                 s.ui_experience, s.chatbot_help, s.ease_rating, s.student_comments 
          FROM survey_responses s 
          LEFT JOIN users u ON s.user_id = u.id 
          ORDER BY s.id DESC";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit();
?>