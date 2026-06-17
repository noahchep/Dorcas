<?php
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $query = mysqli_query($conn, "SELECT * FROM admin_referrals WHERE id = $id");
    $message = mysqli_fetch_assoc($query);

    if ($message) {
        echo "<div style='background: white; padding: 10px; border-radius: 8px; margin-bottom: 10px;'>";
        echo "<strong>" . htmlspecialchars($message['sender_name']) . "</strong><br>";
        echo "<p>" . htmlspecialchars($message['student_query']) . "</p>";
        echo "<small>" . $message['created_at'] . "</small>";
        echo "</div>";
    }
}
?>