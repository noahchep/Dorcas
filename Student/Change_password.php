<?php
session_start();

// 1. Check if user_id exists (Are they logged in?)
// 2. Check if the role is 'student' (Are they allowed here?)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    
    // Optional: If they are an admin trying to sneak in, send them to their own dashboard
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: ../admin/admin_dashboard.php?error=access_denied");
    } else {
        // Otherwise, send to login
        header("Location: ../login.php");
    }
    exit();
}

/* Rest of your database connection and logic... */
/* DB CONNECTION */
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

/* Initialize messages */
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $error = "All fields are required.";
    } elseif ($new !== $confirm) {
        $error = "New password and confirmation do not match.";
    } else {
        // Fetch current hashed password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($current, $hashed_password)) {
            $error = "Current password is incorrect.";
        } else {
            // Hash new password
            $new_hashed = password_hash($new, PASSWORD_DEFAULT);

            // Update in database
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_hashed, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to update password. Try again later.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password | Student Portal</title>
    <style>
        body { font-family: Verdana, sans-serif; background: #f2f2f2; padding: 20px; }
        #container { max-width: 500px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #ccc; }
        h2 { color: #0056b3; text-align: center; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        .password-field { position: relative; }
        input[type="password"], input[type="text"] { width: 100%; padding: 8px; margin-top: 5px; border-radius: 4px; border: 1px solid #ccc; }
        .toggle-eye { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 14px; color: #555; }
        .btn-submit { background: #0056b3; color: white; padding: 10px 20px; border: none; margin-top: 20px; cursor: pointer; border-radius: 5px; width: 100%; }
        .success { background: #d4edda; color: #155724; padding: 10px; margin-top: 10px; border-radius: 5px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; margin-top: 10px; border-radius: 5px; }
    </style>
</head>
<body>

<div id="container">
    <h2>Change Password</h2>

    <?php if($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="current_password">Current Password</label>
        <div class="password-field">
            <input type="password" name="current_password" id="current_password" required>
            <span class="toggle-eye" onclick="togglePassword('current_password')">👁️</span>
        </div>

        <label for="new_password">New Password</label>
        <div class="password-field">
            <input type="password" name="new_password" id="new_password" required>
            <span class="toggle-eye" onclick="togglePassword('new_password')">👁️</span>
        </div>

        <label for="confirm_password">Confirm New Password</label>
        <div class="password-field">
            <input type="password" name="confirm_password" id="confirm_password" required>
            <span class="toggle-eye" onclick="togglePassword('confirm_password')">👁️</span>
        </div>

        <button type="submit" class="btn-submit">Change Password</button>
    </form>
</div>

<script>
function togglePassword(fieldId) {
    var input = document.getElementById(fieldId);
    if (input.type === "password") {
        input.type = "text";
    } else {
        input.type = "password";
    }
}
</script>

</body>
</html>
