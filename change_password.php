<?php
session_start();

// Check if user is logged in and needs password change
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Set a flag in session to indicate password change is required
$force_password_change = $_SESSION['force_password_change'] ?? false;

/* ==========================
   DATABASE CONNECTION
========================== */
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get user's current password from database
    $user_id = $_SESSION['user_id'];
    $query = "SELECT password FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $user_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $success = "Password changed successfully!";
                    
                    // Remove force password change flag
                    unset($_SESSION['force_password_change']);
                    
                    // Redirect based on role after 2 seconds
                    $role = $_SESSION['role'];
                    echo '<meta http-equiv="refresh" content="2;url=' . 
                         ($role == 'lecturer' ? 'Lecturer/lecturer_dashboard.php' : 
                         ($role == 'admin' ? 'Admin/Admin-index.php' : 'Student/home.php')) . '">';
                } else {
                    $error = "Error updating password. Please try again.";
                }
            } else {
                $error = "New password must be at least 6 characters long!";
            }
        } else {
            $error = "New password and confirm password do not match!";
        }
    } else {
        $error = "Current password is incorrect!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | Portal Assistant AI</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .warning-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 10px;
        }
        
        .body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4f46e5;
        }
        
        .btn-change {
            width: 100%;
            padding: 12px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-change:hover {
            transform: translateY(-2px);
            background: #4338ca;
        }
        
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .password-requirements {
            background: #f3f4f6;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Change Password</h1>
            <?php if($force_password_change): ?>
                <div class="warning-badge">⚠️ You are required to change your password</div>
            <?php endif; ?>
        </div>
        
        <div class="body">
            <?php if($error): ?>
                <div class="error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="success">✅ <?php echo $success; ?> Redirecting...</div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                
                <div class="password-requirements">
                    <strong>Password Requirements:</strong>
                    <ul style="margin-left: 20px; margin-top: 5px;">
                        <li>Minimum 6 characters</li>
                        <li>Use a mix of letters and numbers</li>
                        <li>Don't use common words</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn-change">Change Password</button>
            </form>
        </div>
    </div>
</body>
</html>