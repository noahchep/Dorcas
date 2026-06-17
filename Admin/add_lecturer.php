<?php
/* ==========================
   PHPMailer for Email Notifications
========================== */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Paths adjusted to your folder: Admin/phpmailer/src/
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

// Function to generate random password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    return substr(str_shuffle($chars), 0, $length);
}

// Function to generate staff number (starts with LEC/)
function generateStaffNumber($conn) {
    $year = date("Y");
    
    // Get the last staff number to increment sequence
    $query = "SELECT reg_number FROM users WHERE role = 'lecturer' ORDER BY id DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $last_reg = $row['reg_number'];
        // Extract last 5 digits and add 1
        if (preg_match('/LEC\/\d{4}\/(\d{5})$/', $last_reg, $matches)) {
            $last_sequence = (int)$matches[1];
            $new_sequence = str_pad($last_sequence + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $new_sequence = "00001";
        }
    } else {
        // First lecturer starting point
        $new_sequence = "00001";
    }
    
    return "LEC/$year/$new_sequence";
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_lecturer') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    // Auto-generate staff number (starts with LEC/) and password
    $reg_number = generateStaffNumber($conn);
    $plain_password = generateRandomPassword(8);
    $password = password_hash($plain_password, PASSWORD_DEFAULT);
    $role = 'lecturer';
    $password_changed = 0; // Set to 0 because password hasn't been changed yet
    
    // Check if email already exists
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
    if (mysqli_num_rows($check) > 0) {
        echo '<div class="alert alert-error">❌ Error: Email address already exists!</div>';
    } else {
        // Updated INSERT statement to include password_changed column
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, reg_number, password, role, department, phone, password_changed) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssi", $full_name, $email, $reg_number, $password, $role, $department, $phone, $password_changed);
        
        if ($stmt->execute()) {
            // Send Email Notification using PHPMailer
            $mail = new PHPMailer(true);
            try {
                // SMTP Settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                $mail->Username   = 'noahchepkonga1@gmail.com'; // YOUR GMAIL
                $mail->Password   = 'zltl hrka tjnr ezxl';    // YOUR 16-CHAR APP PASSWORD
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('no-reply@portal-assistant.ac.ke', 'Portal Admin');
                $mail->addAddress($email, $full_name);

                $mail->isHTML(true);
                $mail->Subject = 'Lecturer Account Created - Portal Assistant AI';
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; border: 1px solid #e5e7eb; padding: 25px; border-radius: 10px;'>
                        <h2 style='color: #4f46e5;'>Welcome, $full_name!</h2>
                        <p>Your lecturer account has been successfully created in the <strong>Portal Assistant AI</strong> system.</p>
                        <div style='background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                            <p><strong>📋 Login Credentials:</strong></p>
                            <p><b>Staff Number:</b> <span style='color: #d97706;'>$reg_number</span></p>
                            <p><b>Temporary Password:</b> <span style='color: #d97706;'>$plain_password</span></p>
                            <p><b>Department:</b> $department</p>
                            <p><b>Phone:</b> $phone</p>
                        </div>
                        <p><b>🔗 Login Portal:</b> <a href='http://localhost/Portal-Assistant-AI/login.php' style='color: #4f46e5;'>Click here to login</a></p>
                        <div style='background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;'>
                            <p style='margin: 0; color: #92400e;'><strong>⚠️ IMPORTANT:</strong> This is a temporary password. You will be required to change your password upon first login.</p>
                        </div>
                        <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='font-size: 0.85rem; color: #666;'>You can access your lecturer dashboard to manage student registrations and view your courses.</p>
                    </div>";
                
                $mail->AltBody = "Welcome $full_name!\n\nYour lecturer account has been created.\n\nStaff Number: $reg_number\nTemporary Password: $plain_password\nDepartment: $department\nPhone: $phone\n\nIMPORTANT: This is a temporary password. You will be required to change your password upon first login.\n\nLogin at: http://localhost/Portal-Assistant-AI/login.php";

                $mail->send();
                echo '<div class="alert alert-success">✅ Lecturer added successfully!<br>';
                echo '📧 Confirmation email sent to: ' . htmlspecialchars($email) . '<br>';
                echo '🔑 Staff Number: <strong style="color: #d97706;">' . htmlspecialchars($reg_number) . '</strong><br>';
                echo '🔐 Temporary Password: <strong style="color: #d97706;">' . htmlspecialchars($plain_password) . '</strong><br>';
                echo '📞 Phone: ' . htmlspecialchars($phone) . '<br>';
                echo '📚 Department: ' . htmlspecialchars($department) . '<br>';
                echo '🔒 Password change required on first login: <strong>Yes</strong></div>';
            } catch (Exception $e) {
                // Success in DB but Mail Failed
                echo '<div class="alert alert-success">✅ Lecturer added successfully!<br>';
                echo '⚠️ However, the email could not be sent. Error: ' . $mail->ErrorInfo . '<br>';
                echo '🔑 Staff Number: <strong style="color: #d97706;">' . htmlspecialchars($reg_number) . '</strong><br>';
                echo '🔐 Temporary Password: <strong style="color: #d97706;">' . htmlspecialchars($plain_password) . '</strong><br>';
                echo '📞 Phone: ' . htmlspecialchars($phone) . '<br>';
                echo '📚 Department: ' . htmlspecialchars($department) . '<br>';
                echo '🔒 Password change required on first login: <strong>Yes</strong><br>';
                echo '<strong style="color: #d97706;">⚠️ Please save these credentials and provide them to the lecturer manually!</strong></div>';
            }
        } else {
            echo '<div class="alert alert-error">❌ Error: ' . $conn->error . '</div>';
        }
        $stmt->close();
    }
}

// Get departments for dropdown
$dept_query = "SELECT DISTINCT department FROM academic_workload WHERE department IS NOT NULL";
$dept_result = mysqli_query($conn, $dept_query);
$departments = [];
if ($dept_result && mysqli_num_rows($dept_result) > 0) {
    while($row = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $row['department'];
    }
}
// Add default departments if none found
if (empty($departments)) {
    $departments = ['Management', 'Nursing', 'Economics', 'Information Technology'];
}
?>

<style>
.alert-success {
    background: #d1fae5;
    color: #065f46;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #a7f3d0;
}
.alert-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #fecaca;
}
.info-box {
    background: #e0e7ff;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    color: #3730a3;
    font-size: 0.85rem;
}
.staff-number-preview {
    background: #f3f4f6;
    padding: 10px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 0.9rem;
}
</style>

<h2>➕ Add New Lecturer</h2>

<div class="info-box">
    <strong>ℹ️ Automatic Generation:</strong> 
    <ul style="margin-top: 8px; margin-left: 20px;">
        <li>Staff number will be auto-generated in format: <strong>LEC/YYYY/XXXXX</strong> (e.g., LEC/2026/00001)</li>
        <li>Temporary password will be auto-generated (8 characters)</li>
        <li>Credentials will be sent to the lecturer's email</li>
        <li>Lecturer will be required to change password upon first login</li>
    </ul>
</div>

<form method="POST" onsubmit="return validateForm()">
    <input type="hidden" name="action" value="add_lecturer">
    
    <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name" required placeholder="e.g., Dr. John Smith">
    </div>
    
    <div class="form-group">
        <label>Email Address *</label>
        <input type="email" name="email" required placeholder="lecturer@university.ac.ke">
        <small>Login credentials will be sent to this email</small>
    </div>
    
    <div class="form-group">
        <label>Phone Number *</label>
        <input type="tel" name="phone" required placeholder="e.g., 0712345678">
        <small>Format: 0712345678 or +254712345678</small>
    </div>
    
    <div class="form-group">
        <label>Department *</label>
        <select name="department" required>
            <option value="">Select Department</option>
            <?php foreach($departments as $dept): ?>
                <option value="<?php echo htmlspecialchars($dept); ?>">
                    <?php echo htmlspecialchars($dept); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label>Staff Number (Auto-generated)</label>
        <div class="staff-number-preview">
            Will be generated as: <strong>LEC/<?php echo date('Y'); ?>/XXXXX</strong>
        </div>
        <small>Format: LEC/Year/5-digit sequential number</small>
    </div>
    
    <div class="form-group">
        <label>Role</label>
        <input type="text" value="Lecturer (Auto-assigned)" disabled style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; background: #f3f4f6;">
    </div>
    
    <div class="form-group">
        <label>Password Status</label>
        <input type="text" value="⚠️ Password change required on first login" disabled style="width:100%; padding:12px; border:1px solid #f59e0b; border-radius:8px; background: #fef3c7; color: #92400e;">
    </div>
    
    <button type="submit" class="btn btn-primary">Add Lecturer & Send Email</button>
    <a href="Admin-index.php?section=lecturers" class="btn" style="background: #64748b; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none;">Cancel</a>
</form>

<script>
function validateForm() {
    let phone = document.querySelector('input[name="phone"]').value;
    let email = document.querySelector('input[name="email"]').value;
    let full_name = document.querySelector('input[name="full_name"]').value;
    
    if (full_name.trim() === '') {
        alert('Please enter the lecturer\'s full name!');
        return false;
    }
    
    if (!email.includes('@')) {
        alert('Please enter a valid email address!');
        return false;
    }
    
    // Basic phone validation
    let phoneRegex = /^[0-9+\-\s]{10,15}$/;
    if (!phoneRegex.test(phone)) {
        alert('Please enter a valid phone number (10-15 digits, may include +, -, spaces)');
        return false;
    }
    
    return confirm('The lecturer will receive an email with auto-generated credentials (Staff Number: LEC/<?php echo date('Y'); ?>/XXXXX). They will be required to change their password on first login. Continue?');
}
</script>