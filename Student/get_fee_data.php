<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in - with better handling
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = "SELECT * FROM fees_structure WHERE id = $id";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
        echo json_encode([
            'success' => true,
            'id' => $data['id'],
            'year' => $data['year'],
            'semester' => $data['semester'],
            'year_level' => $data['year_level'],
            'category' => $data['category'],
            'amount' => $data['amount']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fee not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
}

mysqli_close($conn);
?>