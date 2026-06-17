<?php
// Set Kenya timezone
date_default_timezone_set('Africa/Nairobi');

echo "<h2>🔍 Time Synchronization Test</h2>";

// PHP Time
$php_time = date('Y-m-d H:i:s');
echo "📅 PHP Time (Kenya): <strong>$php_time</strong><br>";

// MySQL Time
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
$result = mysqli_query($conn, "SELECT NOW() as mysql_time");
$row = mysqli_fetch_assoc($result);
$mysql_time = $row['mysql_time'];
echo "🗄️ MySQL Time: <strong>$mysql_time</strong><br>";

// Calculate difference
$php_timestamp = strtotime($php_time);
$mysql_timestamp = strtotime($mysql_time);
$diff_seconds = abs($php_timestamp - $mysql_timestamp);
$diff_minutes = round($diff_seconds / 60, 2);

echo "<br>⏱️ Time Difference: <strong>$diff_minutes minutes</strong><br>";

if ($diff_seconds > 60) {
    echo "<span style='color: red;'>❌ Time difference is too large ($diff_seconds seconds)! 2FA will fail.</span><br>";
    echo "<br><strong>Solutions:</strong><br>";
    echo "1. Restart MySQL in XAMPP<br>";
    echo "2. Run: SET GLOBAL time_zone = '+03:00';<br>";
    echo "3. Or add date_default_timezone_set('Africa/Nairobi'); to your PHP files<br>";
} else {
    echo "<span style='color: green;'>✅ Time is synchronized! 2FA should work.</span><br>";
}

// Check admin 2FA status
echo "<hr>";
echo "<h3>👤 Admin 2FA Status</h3>";
$admin_check = mysqli_query($conn, "SELECT id, full_name, email, two_factor_enabled, two_factor_code, two_factor_expires FROM users WHERE role = 'admin'");
$admin = mysqli_fetch_assoc($admin_check);

echo "Name: " . $admin['full_name'] . "<br>";
echo "Email: " . $admin['email'] . "<br>";
echo "2FA Enabled: " . ($admin['two_factor_enabled'] ? "✅ Yes" : "❌ No") . "<br>";
echo "Current Code: " . ($admin['two_factor_code'] ?? "None") . "<br>";
echo "Code Expires: " . ($admin['two_factor_expires'] ?? "N/A") . "<br>";

// If code exists, check if expired
if ($admin['two_factor_expires']) {
    $expires_time = strtotime($admin['two_factor_expires']);
    $current_time = time();
    if ($expires_time > $current_time) {
        $remaining = round(($expires_time - $current_time) / 60, 1);
        echo "Code Status: <span style='color: green;'>✅ Valid (expires in $remaining minutes)</span><br>";
    } else {
        echo "Code Status: <span style='color: red;'>❌ EXPIRED</span><br>";
    }
}
?>