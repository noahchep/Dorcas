<?php
// Set timezone to Kenya time
date_default_timezone_set('Africa/Nairobi');

session_start();

// ========== PHPMailer USE STATEMENTS MUST BE AT THE TOP ==========
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Database connection
$host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "Portal-Asisstant-AI";

$conn = mysqli_connect($host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$error = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signin'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password']; 

    $sql = "SELECT * FROM `users` WHERE `reg_number` = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);

        if (password_verify($password, $row['password'])) {
            
            // Check if 2FA should be applied (Admin or Lecturer only)
            $requires_2fa = false;
            if (($row['role'] == 'admin' || $row['role'] == 'lecturer') && isset($row['two_factor_enabled']) && $row['two_factor_enabled'] == 1) {
                $requires_2fa = true;
            }
            
            if ($requires_2fa) {
                // Generate 6-digit verification code
                $verification_code = sprintf("%06d", mt_rand(1, 999999));
                $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                
                // Save code to database
                $update_sql = "UPDATE users SET two_factor_code = ?, two_factor_expires = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "ssi", $verification_code, $expires, $row['id']);
                mysqli_stmt_execute($update_stmt);
                
                // ========== Send email using PHPMailer ==========
                require_once 'Admin/phpmailer/src/Exception.php';
                require_once 'Admin/phpmailer/src/PHPMailer.php';
                require_once 'Admin/phpmailer/src/SMTP.php';
                
                $mail = new PHPMailer(true);
                $email_sent = false;
                
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com'; 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'noahchepkonga1@gmail.com';
                    $mail->Password   = 'zltl hrka tjnr ezxl';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    
                    $mail->setFrom('no-reply@portal-assistant.ac.ke', 'Portal Assistant AI');
                    $mail->addAddress($row['email'], $row['full_name']);
                    
                    $mail->isHTML(true);
                    $mail->Subject = 'Your 2FA Verification Code - Portal Assistant AI';
                    $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; border: 1px solid #e5e7eb; padding: 25px; border-radius: 10px;'>
                        <div style='text-align: center; margin-bottom: 20px;'>
                            <img src='https://via.placeholder.com/80?text=AI' alt='Logo' style='width: 80px; height: 80px; border-radius: 50%;'>
                        </div>
                        <h2 style='color: #2563eb;'>Two-Factor Authentication</h2>
                        <p>Hello <strong>{$row['full_name']}</strong>,</p>
                        <p>You have requested to log in to <strong>Portal Assistant AI</strong>. Please use the verification code below:</p>
                        <div style='background: #f0f4f8; padding: 20px; text-align: center; border-radius: 8px; margin: 25px 0;'>
                            <h1 style='color: #003366; font-size: 42px; letter-spacing: 8px; margin: 0; font-family: monospace;'>{$verification_code}</h1>
                        </div>
                        <p>This code will expire in <strong>30 minutes</strong>.</p>
                        <p style='color: #dc2626; font-size: 0.85rem;'>If you did not attempt to log in, please ignore this email.</p>
                        <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='font-size: 0.75rem; color: #6b7280; text-align: center;'>Portal Assistant AI - Secure Login System</p>
                    </div>";
                    
                    $mail->send();
                    $email_sent = true;
                } catch (Exception $e) {
                    error_log("2FA Email failed: " . $mail->ErrorInfo);
                    $error = "Unable to send verification code. Please try again.";
                }
                
                if ($email_sent) {
                    $_SESSION['2fa_pending'] = true;
                    $_SESSION['2fa_user_id'] = $row['id'];
                    $_SESSION['2fa_user_name'] = $row['full_name'];
                    $_SESSION['2fa_role'] = $row['role'];
                    $_SESSION['2fa_email'] = $row['email'];
                    $_SESSION['2fa_department'] = $row['department'];
                    $_SESSION['2fa_reg_number'] = $row['reg_number'];
                    
                    header("Location: verify_2fa.php");
                    exit();
                }
                
            } else {
                session_regenerate_id(true);
                $_SESSION = array();

                $_SESSION['user_id']       = $row['id'];
                $_SESSION['user_name']     = $row['full_name'];
                $_SESSION['reg_number']    = $row['reg_number'];
                $_SESSION['role']          = $row['role'];
                $_SESSION['email']         = $row['email'];
                $_SESSION['department']    = $row['department'];
                $_SESSION['login_time']    = time();
                $_SESSION['login_ip']      = $_SERVER['REMOTE_ADDR'];

                if ($row['role'] == 'admin') {
                    $_SESSION['is_admin'] = true;
                    $_SESSION['admin_name'] = $row['full_name'];
                    header("Location: Admin/Admin-index.php");
                    exit();
                } 
                elseif ($row['role'] == 'lecturer') {
                    $_SESSION['is_lecturer'] = true;
                    $_SESSION['lecturer_name'] = $row['full_name'];
                    $_SESSION['lecturer_department'] = $row['department'];
                    header("Location: Lecturer/lecturer_dashboard.php");
                    exit();
                }
                else {
                    $_SESSION['is_student'] = true;
                    $_SESSION['student_name'] = $row['full_name'];
                    $_SESSION['student_department'] = $row['department'];
                    $_SESSION['student_year'] = determineStudentYear($conn, $row['reg_number']);
                    header("Location: Student/home.php");
                    exit();
                }
            }
        } else {
            $error = "Invalid Registration Number or Password!";
        }
    } else {
        $error = "Invalid Registration Number or Password!";
    }
}

function determineStudentYear($conn, $reg_number) {
    if (preg_match('/\/(\d{4})\//', $reg_number, $matches)) {
        $admission_year = intval($matches[1]);
        $current_year = date('Y');
        $year_diff = $current_year - $admission_year;
        
        if ($year_diff == 0) return 'First Year';
        if ($year_diff == 1) return 'Second Year';
        if ($year_diff == 2) return 'Third Year';
        if ($year_diff >= 3) return 'Fourth Year';
    }
    return 'First Year';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Assistant AI | Login</title>
    
    <link rel="icon" type="image/jpeg" href="Images/logo.jpg">
    <link rel="shortcut icon" href="Images/logo.jpg">
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            background: url("../Images/logback.jpg") no-repeat center center;
            background-size: cover;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            width: 900px;
            display: flex;
            background: #fff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #003366, #0056b3);
            color: #fff;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .logo-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 3px solid white;
            margin-bottom: 20px;
            padding: 5px;
            background: white;
            object-fit: cover;
        }

        .login-left h2 { margin: 10px 0; font-size: 24px; }
        .login-left p { font-size: 14px; opacity: 0.9; font-style: italic; }

        .login-right {
            flex: 1.2;
            padding: 50px;
            background: white;
        }

        .login-title {
            color: #003366;
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .slogan {
            font-size: 12px;
            color: #666;
            margin-bottom: 30px;
            display: block;
        }

        .form-group { margin-bottom: 20px; }

        .control-label {
            display: block;
            font-weight: 600;
            color: #003366;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-family: 'Poppins';
            outline: none;
            transition: border 0.3s;
        }

        .form-control:focus { border-color: #0056b3; }

        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper input {
            width: 100%;
            padding-right: 40px;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 16px;
            color: #666;
            user-select: none;
        }
        
        .toggle-password:hover {
            color: #003366;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: #003366;
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0, 51, 102, 0.3);
            transition: 0.3s;
        }

        .btn-login:hover { background: #002244; transform: translateY(-2px); }

        .forgot-link {
            text-align: right;
            margin-top: 15px;
            font-size: 12px;
        }

        .forgot-link a { color: #dc3545; text-decoration: none; font-weight: bold; }

        .forgot-link a:hover { text-decoration: underline; }

        .twofa-info {
            margin-top: 15px;
            padding: 8px;
            background: #e8f0fe;
            border-radius: 5px;
            font-size: 10px;
            text-align: center;
            color: #003366;
        }
        .twofa-info a {
            color: #003366;
            text-decoration: none;
            font-weight: bold;
        }

        marquee {
            margin-top: 30px;
            background: linear-gradient(135deg, #e8f0fe, #f0f4f8);
            padding: 10px 8px;
            border-radius: 8px;
            color: #003366;
            font-size: 12px;
            border: 1px solid #d0dae5;
            font-weight: 500;
        }

        .error-msg {
            color: #dc3545;
            background: #fdecea;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #fabeb6;
        }
        
        footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 15px;
            font-size: 10px;
            color: #888;
            border-top: 1px solid #eee;
        }
        
        @media (max-width: 768px) {
            .login-container {
                width: 95%;
                flex-direction: column;
                margin: 20px;
            }
            .login-left {
                padding: 30px;
            }
            .login-right {
                padding: 30px;
            }
            .logo-circle {
                width: 80px;
                height: 80px;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-left">
        <img src="Images/logo.jpg" class="logo-circle" alt="MKU Logo">
        <h2>Portal Assistant AI</h2>
        <p>Infinite support for infinite possibilities.</p>
        <div style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.3); padding-top: 20px;">
            <small>System Version 2026.1</small>
        </div>
    </div>

    <div class="login-right">
        <div class="login-title">Portal Assistant AI</div>
        <span class="slogan">Infinite support for infinite possibilities.</span>

        <?php if($error != "") { echo '<div class="error-msg">'.$error.'</div>'; } ?>

        <form action="" method="post">
            <div class="form-group">
                <label class="control-label">User ID / Reg Number</label>
                <input type="text" name="username" class="form-control" placeholder="e.g. BIT/2024/43255" required>
            </div>

            <div class="form-group">
                <label class="control-label">Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                    <span class="toggle-password" onclick="togglePassword()">👁️</span>
                </div>
            </div>

            <input type="submit" name="signin" class="btn-login" value="SIGN IN">
            
            <div class="forgot-link">
                <a href="forgot_password.php">Forgot Password?</a>
            </div>

            

            <marquee behavior="scroll" direction="left" scrollamount="3" onmouseover="this.stop()" onmouseout="this.start()">
                🎓 <strong>Welcome to Portal Assistant AI!</strong> — Your intelligent academic companion for success! | 
                📚 Access courses, submit assignments, and track your progress | 
                🤖 AI-powered support available 24/7 | 
                ✅ New semester registration is now open! | 
                🔐 Admins & Lecturers: 2FA is enabled for your account security!
            </marquee>
        </form>
        
        <footer>
            &copy; 2026 Portal Assistant AI. All rights reserved.
        </footer>
    </div>
</div>

<script>
function togglePassword() {
    var passwordField = document.getElementById("password");
    var toggleIcon = document.querySelector(".toggle-password");
    
    if (passwordField.type === "password") {
        passwordField.type = "text";
        toggleIcon.textContent = "🙈";
    } else {
        passwordField.type = "password";
        toggleIcon.textContent = "👁️";
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var inputs = document.querySelectorAll('input');
    inputs.forEach(function(input) {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.querySelector('input[name="signin"]').click();
            }
        });
    });
});
</script>

</body>
</html>