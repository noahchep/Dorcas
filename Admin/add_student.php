<?php
/* ==========================
   PHPMailer & CORE LOGIC
========================== */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Paths adjusted to your folder: Admin/phpmailer/src/
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==========================
   DATABASE CONNECTION
========================== */
include_once('db_connect.php');
if (!$conn) {
    $conn = mysqli_connect("localhost", "root", "", "Portal-Assistant-AI");
}

$message = "";

/* ==========================
   STUDENT REGISTRATION LOGIC
========================== */
if (isset($_POST['add_student'])) {
    $full_name  = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $dept       = mysqli_real_escape_string($conn, $_POST['department']);
    $phone      = mysqli_real_escape_string($conn, $_POST['phone']); // ADDED: Phone number
    
    // 1. Check if Email Already Exists (Prevents Duplicate Entry Fatal Error)
    $email_check_query = "SELECT email FROM users WHERE email = '$email' LIMIT 1";
    $email_check_result = mysqli_query($conn, $email_check_query);

    if (mysqli_num_rows($email_check_result) > 0) {
        $message = "error|The email address '$email' is already registered to another user.";
    } else {
        // 2. Generate Registration Number: [DEPT]/[YEAR]/[5-DIGIT-ID]
        $dept_codes = [
            // Computing & Informatics
            "Information Technology" => "BIT",
            "Information Science"    => "BIS",
            
            // Business & Economics
            "Management"             => "BBM",
            "Economics"              => "BEC",
            "Accounting and Finance" => "BAF",

            // Health Sciences & Medicine
            "Community Health"       => "BCH",
            "Environmental Health"   => "BEH",
            "Nursing"                => "BSN",
            "Pharmacy"               => "BPH",
            "Medical School"         => "MBB",
            "Clinical Medicine"      => "BCM",

            // Education
            "Educational Management"  => "BED",
            "Educational Psychology"  => "BEP",
            "Special Needs Education" => "BSN",

            // Engineering & Built Environment
            "Energy Engineering"      => "BEE",
            "Electrical Engineering"  => "BEL",

            // Pure & Applied Sciences
            "Natural Sciences"        => "BNS",
            "Animal Health"           => "BAH",

            // Social Sciences & Humanities
            "Psychology"              => "BPS",
            "Law"                     => "LLB",
            "Security Studies"        => "BSS",
            "Journalism"              => "BJM",

            // Hospitality & Tourism
            "Hospitality Management"  => "BHM",
            "Travel and Tourism"      => "BTT"
        ];

        $prefix = isset($dept_codes[$dept]) ? $dept_codes[$dept] : "STU";
        $year = date("Y");

        // Get the last registration number to increment sequence
        $query = "SELECT reg_number FROM users WHERE role = 'student' ORDER BY id DESC LIMIT 1";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $last_reg = $row['reg_number'];
            // Extract last 5 digits and add 1
            $last_sequence = (int)substr($last_reg, -5);
            $new_sequence = str_pad($last_sequence + 1, 5, '0', STR_PAD_LEFT);
        } else {
            // First student starting point
            $new_sequence = "43255"; 
        }
        
        $reg_number = "$prefix/$year/$new_sequence";
        $password_hashed = password_hash($reg_number, PASSWORD_DEFAULT);

        // 3. Insert Student into Database (ADDED: phone column)
        $sql = "INSERT INTO users (full_name, reg_number, email, department, role, password, phone) 
                VALUES ('$full_name', '$reg_number', '$email', '$dept', 'student', '$password_hashed', '$phone')";

        if (mysqli_query($conn, $sql)) {
            
            // 4. Send Email using PHPMailer
            $mail = new PHPMailer(true);
            try {
                // SMTP Settings - Replace with your real credentials
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
                $mail->Subject = 'Registration Successful - Portal-Assistant-AI';
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; border: 1px solid #e5e7eb; padding: 25px; border-radius: 10px;'>
                        <h2 style='color: #2563eb;'>Welcome, $full_name!</h2>
                        <p>Your account has been successfully created in the <strong>Portal-Assistant-AI</strong> system.</p>
                        <p><b>Registration Number:</b> <span style='color: #d97706;'>$reg_number</span></p>
                        <p><b>Temporary Password:</b> $reg_number</p>
                        <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='font-size: 0.85rem; color: #666;'>Log in and update your password immediately for account security.</p>
                    </div>";

                $mail->send();
                $message = "success|Student registered! Reg No: $reg_number. Confirmation email sent.";
            } catch (Exception $e) {
                // Success in DB but Mail Failed
                $message = "success|Student registered (Reg No: $reg_number), but the email could not be sent. Error: {$mail->ErrorInfo}";
            }
        } else {
            $message = "error|Database Error: " . mysqli_error($conn);
        }
    }
}
?>

<div class="section-box" style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
    <h2 style="margin-top: 0; color: #2563eb; font-size: 1.4rem; border-bottom: 2px solid #f3f4f6; padding-bottom: 15px; margin-bottom: 20px;">
        Add New Student
    </h2>

    <?php if ($message): 
        $parts = explode('|', $message); ?>
        <div style="padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 600; 
             background: <?php echo $parts[0] == 'success' ? '#dcfce7' : '#fee2e2'; ?>; 
             color: <?php echo $parts[0] == 'success' ? '#166534' : '#991b1b'; ?>; border: 1px solid <?php echo $parts[0] == 'success' ? '#bbf7d0' : '#fecaca'; ?>;">
            <?php echo $parts[1]; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="Admin-index.php?section=add_student" style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
        
        <div class="form-group">
            <label style="display:block; font-weight:700; margin-bottom:8px; color: #374151;">Full Name</label>
            <input type="text" name="full_name" required placeholder="Enter student's full name" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; outline: none;">
        </div>

        <div class="form-group">
            <label style="display:block; font-weight:700; margin-bottom:8px; color: #374151;">Email Address</label>
            <input type="email" name="email" required placeholder="student@example.com" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; outline: none;">
        </div>

        <!-- ADDED: Phone Number Field -->
        <div class="form-group">
            <label style="display:block; font-weight:700; margin-bottom:8px; color: #374151;">Phone Number</label>
            <input type="tel" name="phone" placeholder="e.g., 0712345678" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; outline: none;">
            <small style="color: #6b7280; font-size: 0.75rem;">Optional: Student's mobile number</small>
        </div>

        <div class="form-group">
    <label style="display:block; font-weight:700; margin-bottom:8px; color: #374151;">Department</label>
    <select name="department" required style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; background: #fff; cursor: pointer;">
        <option value="">-- Choose Department --</option>

        <optgroup label="Computing & Informatics">
            <option value="Information Technology">Information Technology</option>
            <option value="Information Science">Information Science & Knowledge Management</option>
        </optgroup>

        <optgroup label="Business & Economics">
            <option value="Management">Management</option>
            <option value="Economics">Economics</option>
            <option value="Accounting and Finance">Accounting and Finance</option>
        </optgroup>

        <optgroup label="Health Sciences & Medicine">
            <option value="Community Health">Community Health, Epidemiology & Biostatistics</option>
            <option value="Environmental Health">Environmental Health & Health Systems Management</option>
            <option value="Nursing">Nursing</option>
            <option value="Pharmacy">Pharmacy</option>
            <option value="Medical School">Medical School</option>
            <option value="Clinical Medicine">Clinical Medicine</option>
        </optgroup>

        <optgroup label="Education">
            <option value="Educational Management">Educational Management & Curriculum Studies</option>
            <option value="Educational Psychology">Educational Psychology & Technology (EPT)</option>
            <option value="Special Needs Education">Special Needs Education & Early Childhood</option>
        </optgroup>

        <optgroup label="Engineering & Built Environment">
            <option value="Energy Engineering">Energy & Environmental Engineering</option>
            <option value="Electrical Engineering">Electrical & Electronic Engineering</option>
        </optgroup>

        <optgroup label="Pure & Applied Sciences">
            <option value="Natural Sciences">Natural Sciences</option>
            <option value="Animal Health">Animal Health and Production</option>
        </optgroup>

        <optgroup label="Social Sciences & Humanities">
            <option value="Psychology">Psychology, Humanities & Languages</option>
            <option value="Law">Law</option>
            <option value="Security Studies">Security Studies, Justice and Ethics</option>
            <option value="Journalism">Journalism & Mass Communication</option>
        </optgroup>

        <optgroup label="Hospitality & Tourism">
            <option value="Hospitality Management">Hospitality Management</option>
            <option value="Travel and Tourism">Travel and Tourism Management</option>
        </optgroup>
    </select>
</div>
        <div style="grid-column: span 2; background: #f9fafb; padding: 15px; border-radius: 8px; border-left: 4px solid #2563eb;">
            <p style="margin: 0; font-size: 0.85rem; color: #4b5563;">
                <strong>Automatic System:</strong> A unique ID (e.g., BIT/<?php echo date('Y'); ?>/43255) and a default password will be generated and emailed to the student upon confirmation.
            </p>
        </div>

        <div style="grid-column: span 2; text-align: right; margin-top: 10px;">
            <button type="submit" name="add_student" style="background: #2563eb; color: white; border: none; padding: 14px 30px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 1rem; transition: background 0.3s;">
                Register Student & Send Email
            </button>
        </div>
    </form>
</div>