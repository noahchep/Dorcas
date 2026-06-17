<?php
session_start();

if (!isset($_SESSION['2fa_pending']) || !isset($_SESSION['2fa_user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");

// Generate new code
$verification_code = sprintf("%06d", mt_rand(1, 999999));
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Update database
$update_sql = "UPDATE users SET two_factor_code = ?, two_factor_expires = ? WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "ssi", $verification_code, $expires, $_SESSION['2fa_user_id']);
mysqli_stmt_execute($update_stmt);

// Get user info
$user_query = mysqli_query($conn, "SELECT email, full_name FROM users WHERE id = {$_SESSION['2fa_user_id']}");
$user = mysqli_fetch_assoc($user_query);

// Send email
$to = $user['email'];
$subject = "Your New 2FA Verification Code - Portal Assistant AI";
$message = "
<html>
<body style='font-family: Arial, sans-serif;'>
    <div style='max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
        <h2 style='color: #003366;'>Your New Verification Code</h2>
        <p>Hello <strong>{$user['full_name']}</strong>,</p>
        <p>You requested a new verification code. Please use the code below:</p>
        <div style='background: #f0f4f8; padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0;'>
            <h1 style='color: #003366; font-size: 36px; letter-spacing: 5px; margin: 0;'>{$verification_code}</h1>
        </div>
        <p>This code expires in <strong>10 minutes</strong>.</p>
        <hr>
        <small>Portal Assistant AI - Secure Login System</small>
    </div>
</body>
</html>
";

$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: noreply@portalassistant.com" . "\r\n";

mail($to, $subject, $message, $headers);

header("Location: verify_2fa.php?resent=1");
exit();
?>