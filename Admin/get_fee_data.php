<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit();
}

$query = mysqli_query($conn, "SELECT * FROM fee_structure WHERE id = $id");
if ($fee = mysqli_fetch_assoc($query)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'id' => $fee['id'],
        'department' => $fee['department'],
        'semester' => $fee['semester'],
        'year_level' => $fee['year_level'],
        'fee_type' => $fee['fee_type'],
        'amount' => $fee['amount'],
        'due_date' => $fee['due_date'],
        'description' => $fee['description']
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Fee not found']);
}
?>