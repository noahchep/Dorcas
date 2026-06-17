<?php
// Database configuration
$host = "localhost";
$user = "root";     // Default XAMPP username
$pass = "";         // Default XAMPP password (empty)
$dbname = "Portal-Asisstant-AI";

// Create connection
$conn = mysqli_connect($host, $user, $pass, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8 to avoid character issues
mysqli_set_charset($conn, "utf8");
?>