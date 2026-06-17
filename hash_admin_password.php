<?php
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) {
    die("DB connection failed");
}

// CHANGE THIS
$plainPassword = "admin123"; // current admin password
$adminReg      = "ADMIN/001"; // admin reg number

$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

$sql = "UPDATE users SET password = ? WHERE reg_number = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $hashedPassword, $adminReg);

if (mysqli_stmt_execute($stmt)) {
    echo "Admin password hashed successfully!";
} else {
    echo "Failed to update password";
}
