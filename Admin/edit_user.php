<?php
$user_id = intval($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $user_id = intval($_POST['user_id']);
    
    $update = "UPDATE users SET full_name='$full_name', email='$email', department='$department', phone='$phone', role='$role' WHERE id=$user_id";
    if (mysqli_query($conn, $update)) {
        echo '<div class="alert alert-success">✅ User updated successfully!</div>';
    } else {
        echo '<div class="alert alert-error">❌ Error: ' . $conn->error . '</div>';
    }
}

// Get user data
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
$user = mysqli_fetch_assoc($user_query);
if (!$user) {
    echo '<div class="alert alert-error">❌ User not found!</div>';
    return;
}
?>

<h2>✏️ Edit User</h2>

<form method="POST">
    <input type="hidden" name="action" value="edit_user">
    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
    
    <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
    </div>
    
    <div class="form-group">
        <label>Email *</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
    </div>
    
    <div class="form-group">
        <label>Phone Number</label>
        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="e.g., 0712345678">
    </div>
    
    <div class="form-group">
        <label>Department</label>
        <select name="department">
            <option value="Management" <?php echo ($user['department'] == 'Management') ? 'selected' : ''; ?>>Management</option>
            <option value="Nursing" <?php echo ($user['department'] == 'Nursing') ? 'selected' : ''; ?>>Nursing</option>
            <option value="Economics" <?php echo ($user['department'] == 'Economics') ? 'selected' : ''; ?>>Economics</option>
            <option value="Information Technology" <?php echo ($user['department'] == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
        </select>
    </div>
    
    <div class="form-group">
        <label>Role</label>
        <select name="role">
            <option value="student" <?php echo ($user['role'] == 'student') ? 'selected' : ''; ?>>Student</option>
            <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
        </select>
    </div>
    
    <div class="form-group">
        <label>Registration Number (Read Only)</label>
        <input type="text" value="<?php echo htmlspecialchars($user['reg_number']); ?>" readonly disabled style="background: #f0f0f0;">
    </div>
    
    <button type="submit" class="btn btn-primary">Save Changes</button>
    <a href="Admin-index.php?section=lecturers" class="btn" style="background: #64748b; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none;">Cancel</a>
</form>