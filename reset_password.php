<?php
// Set timezone to Kenya time
date_default_timezone_set('Africa/Nairobi');

session_start();

$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
$error = "";
$success = "";
$valid_token = false;
$user_id = null;
$user_name = "";

// Get token from URL
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    $error = "Invalid password reset link.";
} else {
    // Verify token and get the user
    $query = "SELECT id, full_name, email, reg_number FROM users WHERE reset_token = ? AND reset_expires > NOW()";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        $valid_token = true;
        $user_id = $user['id'];
        $user_name = $user['full_name'];
        $user_reg = $user['reg_number'];
    } else {
        $error = "Invalid or expired password reset link. Please request a new one.";
    }
}

// Handle password reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password']) && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token for THIS SPECIFIC USER
        $update_sql = "UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL, password_changed = 1 WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $user_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success = "Password has been reset successfully! You can now login with your new password.";
            // Redirect after 3 seconds
            header("refresh:3;url=login.php");
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Portal Assistant AI</title>
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
        .reset-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            width: 400px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }
        .reset-container h2 { color: #003366; margin-bottom: 10px; text-align: center; }
        .reset-container p { color: #666; font-size: 14px; margin-bottom: 25px; text-align: center; }
        .user-info {
            background: #e8f0fe;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .user-info strong {
            color: #003366;
        }
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
        .password-requirements {
            font-size: 11px;
            color: #888;
            margin-top: 5px;
        }
    </style>
</head>
<body>
<div class="reset-container">
    <div style="text-align: center; margin-bottom: 20px;">
        <img src="Images/logo.jpg" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
    </div>
    <h2>🔐 Reset Password</h2>
    
    <?php if($error != "") { echo '<div class="error-msg">'.$error.'</div>'; } ?>
    <?php if($success != "") { echo '<div class="success-msg">'.$success.'</div>'; } ?>
    
    <?php if($valid_token && empty($success)): ?>
        <div class="user-info">
            <strong><?php echo htmlspecialchars($user_name); ?></strong><br>
            <small>Registration: <?php echo htmlspecialchars($user_reg); ?></small>
        </div>
        <p>Enter your new password below.</p>
        <form method="post">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required minlength="6">
                <div class="password-requirements">Password must be at least 6 characters</div>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" name="reset_password" class="btn-reset">Reset Password</button>
        </form>
    <?php elseif(empty($success)): ?>
        <div class="back-link">
            <a href="forgot_password.php">← Request New Reset Link</a>
        </div>
    <?php endif; ?>
    
    <div class="back-link">
        <a href="login.php">← Back to Login</a>
    </div>
</div>
</body>
</html>