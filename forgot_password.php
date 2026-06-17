<?php
// Set timezone to Kenya time
date_default_timezone_set('Africa/Nairobi');

session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_reset'])) {
    $reg_number = mysqli_real_escape_string($conn, $_POST['reg_number']);
    
    // FIXED: Added reg_number to SELECT query
    $query = "SELECT id, full_name, email, role, reg_number FROM users WHERE reg_number = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $reg_number);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Generate reset token
        $reset_token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Save token to database for this specific user
        $update_sql = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ssi", $reset_token, $expires, $user['id']);
        mysqli_stmt_execute($update_stmt);
        
        // Send reset email using PHPMailer
        require_once 'Admin/phpmailer/src/Exception.php';
        require_once 'Admin/phpmailer/src/PHPMailer.php';
        require_once 'Admin/phpmailer/src/SMTP.php';
        
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'noahchepkonga1@gmail.com';
            $mail->Password   = 'zltl hrka tjnr ezxl';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            $mail->setFrom('no-reply@portal-assistant.ac.ke', 'Portal Assistant AI');
            $mail->addAddress($user['email'], $user['full_name']);
            
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $reset_token;
            
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - Portal Assistant AI';
            $mail->Body    = "
            <div style='font-family: Arial, sans-serif; border: 1px solid #e5e7eb; padding: 25px; border-radius: 10px; max-width: 500px; margin: 0 auto;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <img src='https://via.placeholder.com/80?text=AI' alt='Logo' style='width: 80px; height: 80px; border-radius: 50%;'>
                </div>
                <h2 style='color: #2563eb;'>Password Reset Request</h2>
                <p>Hello <strong>{$user['full_name']}</strong>,</p>
                <p>We received a request to reset your password for your <strong>Portal Assistant AI</strong> account.</p>
                <p><strong>Registration Number:</strong> {$user['reg_number']}</p>
                <p>Click the button below to reset your password:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$reset_link}' style='background: #003366; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                </div>
                <p>This link will expire in <strong>1 hour</strong>.</p>
                <p>If you did not request this, please ignore this email or contact support.</p>
                <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 0.75rem; color: #6b7280; text-align: center;'>Portal Assistant AI - Secure Password Recovery</p>
            </div>";
            
            $mail->send();
            $success = "A password reset link has been sent to the email address associated with this registration number.";
        } catch (Exception $e) {
            $error = "Unable to send reset email. Please try again later.";
            error_log("Password reset email failed: " . $mail->ErrorInfo);
        }
    } else {
        // Show same message for security
        $success = "If a user exists with that registration number, a password reset link has been sent to their email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Portal Assistant AI</title>
        <link rel="icon" type="image/jpeg" href="Images/logo.jpg">
    <link rel="shortcut icon" href="Images/logo.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            background: linear-gradient(135deg, #003366, #0056b3);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .forgot-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            width: 400px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }
        .forgot-container h2 { color: #003366; margin-bottom: 10px; text-align: center; }
        .forgot-container p { color: #666; font-size: 14px; margin-bottom: 25px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #003366;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-family: 'Poppins';
            outline: none;
        }
        .form-group input:focus { border-color: #0056b3; }
        .btn-reset {
            width: 100%;
            padding: 12px;
            background: #003366;
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-reset:hover { background: #002244; }
        .error-msg {
            color: #dc3545;
            background: #fdecea;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 13px;
            text-align: center;
        }
        .success-msg {
            color: #155724;
            background: #d4edda;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 13px;
            text-align: center;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a { color: #003366; text-decoration: none; font-size: 13px; }
        .back-link a:hover { text-decoration: underline; }
        .info-note {
            background: #e8f0fe;
            padding: 10px;
            border-radius: 5px;
            font-size: 11px;
            text-align: center;
            color: #003366;
            margin-top: 15px;
        }
    </style>
</head>
<body>
<div class="forgot-container">
    <div style="text-align: center; margin-bottom: 20px;">
        <img src="Images/logo.jpg" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
    </div>
    <h2>🔐 Forgot Password?</h2>
    <p>Enter your <strong>Registration Number</strong> to reset your password.</p>
    
    <?php if($error != "") { echo '<div class="error-msg">'.$error.'</div>'; } ?>
    <?php if($success != "") { echo '<div class="success-msg">'.$success.'</div>'; } ?>
    
    <form method="post">
        <div class="form-group">
            <label>Registration Number</label>
            <input type="text" name="reg_number" required placeholder="e.g., BIT/2024/43255">
            <div class="info-note">
                ℹ️ Enter your registration number (e.g., BIT/2024/43255). A reset link will be sent to your registered email.
            </div>
        </div>
        <button type="submit" name="send_reset" class="btn-reset">Send Reset Link</button>
    </form>
    
    <div class="back-link">
        <a href="login.php">← Back to Login</a>
    </div>
</div>
</body>
</html>