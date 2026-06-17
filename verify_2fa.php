<?php
// Set timezone to Kenya time
date_default_timezone_set('Africa/Nairobi');

session_start();

// ========== PHPMailer USE STATEMENTS MUST BE AT THE TOP ==========
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is in 2FA pending state
if (!isset($_SESSION['2fa_pending']) || !isset($_SESSION['2fa_user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
$error = "";
$success = "";

// Handle resend code request
if (isset($_GET['resend'])) {
    // Get user info
    $user_id = $_SESSION['2fa_user_id'];
    $user_query = mysqli_query($conn, "SELECT email, full_name FROM users WHERE id = $user_id");
    $user = mysqli_fetch_assoc($user_query);
    
    // Generate new code - 30 minutes expiration
    $verification_code = sprintf("%06d", mt_rand(1, 999999));
    $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    // Update database
    $update_sql = "UPDATE users SET two_factor_code = ?, two_factor_expires = ? WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "ssi", $verification_code, $expires, $user_id);
    mysqli_stmt_execute($update_stmt);
    
    // ========== Send email using PHPMailer ==========
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
        
        $mail->isHTML(true);
        $mail->Subject = 'Your New 2FA Verification Code';
        $mail->Body    = "
        <div style='font-family: Arial, sans-serif; border: 1px solid #e5e7eb; padding: 25px; border-radius: 10px;'>
            <h2 style='color: #2563eb;'>New Verification Code</h2>
            <p>Hello <strong>{$user['full_name']}</strong>,</p>
            <p>You requested a new verification code. Please use the code below:</p>
            <div style='background: #f0f4f8; padding: 20px; text-align: center; border-radius: 8px; margin: 25px 0;'>
                <h1 style='color: #003366; font-size: 42px; letter-spacing: 8px; margin: 0;'>{$verification_code}</h1>
            </div>
            <p>This code expires in <strong>30 minutes</strong>.</p>
            <hr>
            <small>Portal Assistant AI - Secure Login System</small>
        </div>";
        
        $mail->send();
        $success = "A new verification code has been sent to your email!";
    } catch (Exception $e) {
        $error = "Unable to send verification code. Please try again.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_code'])) {
    $code = $_POST['code'];
    $user_id = $_SESSION['2fa_user_id'];
    
    // FIXED: Use PHP time instead of MySQL NOW()
    $current_php_time = date('Y-m-d H:i:s');
    $query = "SELECT * FROM users WHERE id = ? AND two_factor_code = ? AND two_factor_expires > '$current_php_time'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "is", $user_id, $code);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 1) {
        // Clear 2FA code from database
        mysqli_query($conn, "UPDATE users SET two_factor_code = NULL, two_factor_expires = NULL WHERE id = $user_id");
        
        // Preserve user data from session before clearing
        $user_id_saved = $_SESSION['2fa_user_id'];
        $user_name_saved = $_SESSION['2fa_user_name'];
        $user_role_saved = $_SESSION['2fa_role'];
        $user_email_saved = $_SESSION['2fa_email'];
        $user_department_saved = $_SESSION['2fa_department'];
        $user_reg_number_saved = $_SESSION['2fa_reg_number'];
        
        // Clear 2FA session variables
        unset($_SESSION['2fa_pending']);
        unset($_SESSION['2fa_user_id']);
        unset($_SESSION['2fa_user_name']);
        unset($_SESSION['2fa_role']);
        unset($_SESSION['2fa_email']);
        unset($_SESSION['2fa_department']);
        unset($_SESSION['2fa_reg_number']);
        
        // Set final session variables
        $_SESSION['user_id'] = $user_id_saved;
        $_SESSION['user_name'] = $user_name_saved;
        $_SESSION['role'] = $user_role_saved;
        $_SESSION['email'] = $user_email_saved;
        $_SESSION['department'] = $user_department_saved;
        $_SESSION['reg_number'] = $user_reg_number_saved;
        $_SESSION['login_time'] = time();
        $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'];
        
        // Set role-specific session variables
        if ($user_role_saved == 'admin') {
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_name'] = $user_name_saved;
            header("Location: Admin/Admin-index.php");
        } elseif ($user_role_saved == 'lecturer') {
            $_SESSION['is_lecturer'] = true;
            $_SESSION['lecturer_name'] = $user_name_saved;
            $_SESSION['lecturer_department'] = $user_department_saved;
            header("Location: Lecturer/lecturer_dashboard.php");
        } else {
            $_SESSION['is_student'] = true;
            $_SESSION['student_name'] = $user_name_saved;
            $_SESSION['student_department'] = $user_department_saved;
            header("Location: Student/home.php");
        }
        exit();
    } else {
        $error = "Invalid or expired verification code! Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify 2FA | Portal Assistant AI</title>
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
        .verify-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            width: 400px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }
        .verify-container h2 { color: #003366; margin-bottom: 10px; }
        .verify-container p { color: #666; font-size: 14px; margin-bottom: 20px; }
        .code-input {
            width: 200px;
            padding: 15px;
            font-size: 24px;
            text-align: center;
            letter-spacing: 5px;
            border: 2px solid #ddd;
            border-radius: 10px;
            margin: 20px auto;
            display: block;
            font-weight: bold;
        }
        .code-input:focus {
            border-color: #003366;
            outline: none;
        }
        .btn-verify {
            background: #003366;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        .btn-verify:hover { background: #002244; }
        .error-msg {
            color: #dc3545;
            background: #fdecea;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        .success-msg {
            color: #155724;
            background: #d4edda;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        .resend-link {
            margin-top: 20px;
            font-size: 13px;
        }
        .resend-link a { color: #003366; text-decoration: none; }
        .resend-link a:hover { text-decoration: underline; }
        .back-link {
            margin-top: 15px;
            font-size: 12px;
        }
        .back-link a { color: #888; text-decoration: none; }
    </style>
</head>
<body>
<div class="verify-container">
    <div style="margin-bottom: 20px;">
        <img src="Images/logo.jpg" class="logo-circle" alt="MKU Logo" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
    </div>
    <h2>🔐 Two-Factor Authentication</h2>
    <p>We've sent a verification code to <strong><?php echo htmlspecialchars($_SESSION['2fa_email'] ?? 'your email'); ?></strong></p>
    
    <?php if($error != "") { echo '<div class="error-msg">'.$error.'</div>'; } ?>
    <?php if($success != "") { echo '<div class="success-msg">'.$success.'</div>'; } ?>
    
    <form method="post">
        <input type="text" name="code" class="code-input" placeholder="000000" maxlength="6" autofocus required>
        <button type="submit" name="verify_code" class="btn-verify">Verify & Login</button>
    </form>
    
    <div class="resend-link">
        <a href="?resend=1">📧 Didn't receive code? Resend</a>
    </div>
    
    <div class="back-link">
        <a href="login.php">← Back to Login</a>
    </div>
</div>
</body>
</html>