<?php
session_start();

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

/* --- DB CONNECTION --- */
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

/* --- FETCH STUDENT DATA --- */
$user_id = $_SESSION['user_id'];
$student_reg = $_SESSION['reg_number'];
$student_dept = $_SESSION['department'];

$sql = "SELECT full_name, reg_number, department, created_at FROM users WHERE id = ? AND role = 'student'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) !== 1) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$student = mysqli_fetch_assoc($result);

// Determine student year level
function getStudentYearLevel($created_at) {
    $created_year = date('Y', strtotime($created_at));
    $current_year = date('Y');
    $year_diff = $current_year - $created_year;
    
    if ($year_diff == 0) return 'First Year';
    if ($year_diff == 1) return 'Second Year';
    if ($year_diff == 2) return 'Third Year';
    return 'Fourth Year';
}

// FIXED: Determine the correct semester for the student
function getStudentCurrentSemester($student_reg, $created_at) {
    // Check if student is new (admitted in current year or current month)
    $admission_year = null;
    if (preg_match('/\/(\d{4})\//', $student_reg, $matches)) {
        $admission_year = intval($matches[1]);
    }
    
    $current_year = date('Y');
    $current_month = date('n');
    $created_month = date('n', strtotime($created_at));
    $created_year = date('Y', strtotime($created_at));
    
    // NEW STUDENTS (admitted this year) ALWAYS start with Semester 1
    if ($admission_year == $current_year || $created_year == $current_year) {
        return 1; // Semester 1
    }
    
    // For returning students, follow the academic calendar
    // Kenyan Academic Calendar:
    // 1st Semester: September - December (months 9-12)
    // 2nd Semester: January - April (months 1-4)
    // Holiday: May - August (months 5-8)
    
    if ($current_month >= 9 && $current_month <= 12) {
        return 1; // 1st Semester
    } elseif ($current_month >= 1 && $current_month <= 4) {
        return 2; // 2nd Semester
    } else {
        // Holiday period (May-August)
        return 1; // Default to 1st Semester for the next academic year
    }
}

$student_year_level = getStudentYearLevel($student['created_at']);
$current_year = date('Y');
$current_semester = getStudentCurrentSemester($student_reg, $student['created_at']);

// Handle Payment Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['process_payment'])) {
        $fee_type = mysqli_real_escape_string($conn, $_POST['fee_type']);
        $amount = floatval($_POST['payment_amount']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $reference = 'PAY-' . date('Ymd') . '-' . strtoupper(substr($student_reg, 0, 6)) . '-' . rand(1000, 9999);
        
        if ($amount > 0) {
            $insert_payment = "INSERT INTO fee_payments (student_reg, fee_type, amount, payment_method, reference, payment_date, status) 
                               VALUES ('$student_reg', '$fee_type', $amount, '$payment_method', '$reference', NOW(), 'completed')";
            if (mysqli_query($conn, $insert_payment)) {
                $success_message = "✅ Payment of KSh " . number_format($amount, 2) . " for " . ucfirst(str_replace('_', ' ', $fee_type)) . " was successful!<br>Reference: " . $reference;
                echo "<meta http-equiv='refresh' content='0'>";
            } else {
                $error_message = "❌ Error processing payment: " . $conn->error;
            }
        } else {
            $error_message = "❌ Please enter a valid amount.";
        }
    }
}

// --- Get fee structure from fees_structure table ---
// Using 'year' column as department
$fee_structure_query = "SELECT 
                            year, 
                            semester, 
                            year_level, 
                            category as fee_type, 
                            amount, 
                            created_at,
                            id
                        FROM fees_structure 
                        WHERE year = '$student_dept' 
                        AND semester = '$current_semester'
                        AND year_level = '$student_year_level'
                        ORDER BY id ASC";

$fee_structure_result = mysqli_query($conn, $fee_structure_query);

// Get all payments made by this student
$payments_query = "SELECT fee_type, SUM(amount) as paid_amount 
                   FROM fee_payments 
                   WHERE student_reg = '$student_reg' 
                   AND status = 'completed'
                   GROUP BY fee_type";
$payments_result = mysqli_query($conn, $payments_query);

// Create an array of paid amounts by fee type
$paid_amounts = [];
while($p = mysqli_fetch_assoc($payments_result)) {
    $paid_amounts[$p['fee_type']] = floatval($p['paid_amount']);
}

// Calculate totals and build fee breakdown
$fee_breakdown = [];
$total_fees = 0;
$paid_amount = 0;

while($fee = mysqli_fetch_assoc($fee_structure_result)) {
    $fee_type = $fee['fee_type'];
    $fee_amount = floatval($fee['amount']);
    $paid_this_fee = $paid_amounts[$fee_type] ?? 0;
    $remaining = $fee_amount - $paid_this_fee;
    
    $fee_breakdown[] = [
        'fee_type' => $fee_type,
        'amount' => $fee_amount,
        'paid' => $paid_this_fee,
        'remaining' => $remaining,
        'status' => ($remaining <= 0) ? 'completed' : 'pending'
    ];
    
    $total_fees += $fee_amount;
    $paid_amount += $paid_this_fee;
}

$outstanding_balance = $total_fees - $paid_amount;
$payment_percentage = ($total_fees > 0) ? ($paid_amount / $total_fees) * 100 : 0;

// Get payment history
$payment_history_query = "SELECT * FROM fee_payments 
                          WHERE student_reg = '$student_reg' 
                          ORDER BY payment_date DESC LIMIT 10";
$payment_history_result = mysqli_query($conn, $payment_history_query);

$name_parts = explode(" ", $student['full_name']);
$fname = $name_parts[0] ?? 'Student';

// Check if there are fees in the table
$check_fees_query = "SELECT COUNT(*) as total FROM fees_structure WHERE year = '$student_dept' AND semester = '$current_semester'";
$check_result = mysqli_query($conn, $check_fees_query);
$fee_count = mysqli_fetch_assoc($check_result);
$has_fees = $fee_count['total'] > 0;

// Get semester name
function getSemesterName($semester) {
    return ($semester == 1) ? '1st Semester' : '2nd Semester';
}
$semester_name = getSemesterName($current_semester);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Management | Portal Assistant AI</title>
    <link rel="icon" type="image/jpeg" href="../Images/logo.jpg">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --bg: #f8fafc;
            --white: #ffffff;
            --text-main: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --accent: #e0e7ff;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --fee-primary: #8b5cf6;
            --fee-gradient: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
        }

        * { box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text-main); margin: 0; line-height: 1.6; }

        header { background: var(--white); border-bottom: 1px solid var(--border); padding: 1rem 5%; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .branding { display: flex; align-items: center; gap: 15px; }
        .logoimg { height: 50px; border-radius: 8px; }
        .branding h1 { margin: 0; font-size: 1.4rem; color: var(--primary); font-weight: 800; }
        .branding small { color: var(--text-light); display: block; font-size: 0.85rem; }

        .back-btn { background: var(--primary); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .back-btn:hover { background: var(--primary-dark); transform: translateY(-2px); }

        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .student-strip { background: var(--accent); padding: 15px 25px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; color: var(--primary-dark); font-weight: 700; flex-wrap: wrap; gap: 10px; }

        .fee-module { background: white; padding: 2rem; margin-bottom: 2rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .fee-module h2 { color: var(--fee-primary); margin-top: 0; display: flex; align-items: center; gap: 10px; }

        .fee-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin: 1.5rem 0; }
        
        .fee-card { background: #f8f9fa; padding: 1.5rem; border-radius: 12px; border-left: 4px solid var(--fee-primary); transition: transform 0.3s; }
        .fee-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        .fee-card h3 { margin: 0; font-size: 0.85rem; color: var(--text-light); font-weight: 600; text-transform: uppercase; }
        .fee-card .amount { font-size: 2rem; font-weight: 800; color: var(--text-main); margin: 0.5rem 0; }
        .fee-card .sub-text { font-size: 0.85rem; color: var(--text-light); }
        .fee-card.paid { border-left-color: var(--success); }
        .fee-card.outstanding { border-left-color: var(--danger); }

        .progress-bar-fee { background: #e9ecef; border-radius: 10px; height: 30px; margin: 1rem 0; overflow: hidden; }
        .progress-fee { background: linear-gradient(90deg, var(--success), #34d399); height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; border-radius: 10px; transition: width 0.8s ease; font-size: 0.85rem; }
        
        .alert-success-fee { background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #a7f3d0; }
        .alert-error-fee { background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca; }
        .alert-info-fee { background: #dbeafe; color: #1e40af; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bfdbfe; }

        .payment-methods { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin: 15px 0; }
        .payment-method-btn { padding: 12px; border: 2px solid var(--border); border-radius: 10px; background: white; cursor: pointer; transition: all 0.3s; text-align: center; }
        .payment-method-btn:hover { border-color: var(--fee-primary); background: #f5f3ff; }
        .payment-method-btn.selected { border-color: var(--fee-primary); background: #f5f3ff; }
        .payment-method-btn .method-icon { font-size: 1.5rem; display: block; }
        .payment-method-btn .method-name { font-size: 0.75rem; font-weight: 600; margin-top: 5px; }
        
        .fee-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .fee-table th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 700; color: var(--text-light); text-transform: uppercase; font-size: 0.8rem; }
        .fee-table td { padding: 12px; border-bottom: 1px solid #dee2e6; }
        .fee-table tr:hover { background: #f8fafc; }
        .fee-table .paid-row { background: #f0fdf4; }
        
        .status-badge { padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .status-badge.paid { background: #d1fae5; color: #065f46; }
        .status-badge.completed { background: #d1fae5; color: #065f46; }
        .status-badge.pending { background: #fed7aa; color: #92400e; }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; max-width: 400px; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 1rem; }
        .form-group input:focus, .form-group select:focus { border-color: var(--fee-primary); outline: none; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        
        .btn { padding: 12px 30px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .btn-fee { background: var(--fee-gradient); color: white; }
        .btn-fee:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3); }

        .no-data { text-align: center; padding: 60px 20px; color: #666; background: #f9fafb; border-radius: 8px; }
        .no-data .big-icon { font-size: 4rem; display: block; margin-bottom: 15px; }

        footer { text-align: center; padding: 40px; color: var(--text-light); font-size: 0.85rem; border-top: 1px solid var(--border); margin-top: 40px; }

        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .fee-summary-grid { grid-template-columns: 1fr; }
            .container { padding: 0 10px; }
            .fee-module { padding: 1rem; }
        }
    </style>
</head>
<body>

<header>
    <div class="branding">
        <img src="../Images/logo.jpg" class="logoimg" alt="Logo">
        <div>
            <h1>Student Support Agent</h1>
            <small>Infinite support for infinite possibilities.</small>
        </div>
    </div>
    <a href="home.php" class="back-btn">← Back to Dashboard</a>
</header>

<div class="container">
    <div class="student-strip">
        <span>Welcome back, <?php echo htmlspecialchars($fname); ?></span>
        <span><?php echo htmlspecialchars($student['reg_number']); ?> | <?php echo htmlspecialchars($student_dept); ?></span>
        <span><?php echo $student_year_level; ?> | <?php echo $semester_name; ?></span>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert-success-fee"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="alert-error-fee"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (!$has_fees): ?>
        <div class="alert-info-fee">
            <strong>📢 No fees configured for <?php echo $semester_name; ?> yet.</strong><br>
            The administration hasn't set up fee structures for your department and year level. 
            Please contact the finance office or check back later.
        </div>
    <?php endif; ?>

    <div class="fee-module">
        <h2>💰 Fee Management - <?php echo $semester_name; ?></h2>
        
        <!-- Fee Summary Cards -->
        <div class="fee-summary-grid">
            <div class="fee-card">
                <h3>Total Fees</h3>
                <div class="amount">KSh <?php echo number_format($total_fees, 2); ?></div>
                <div class="sub-text">Academic Year <?php echo $current_year; ?></div>
            </div>
            
            <div class="fee-card paid">
                <h3>Paid Amount</h3>
                <div class="amount">KSh <?php echo number_format($paid_amount, 2); ?></div>
                <div class="sub-text"><?php echo number_format($payment_percentage, 1); ?>% Completed</div>
            </div>
            
            <div class="fee-card <?php echo $outstanding_balance > 0 ? 'outstanding' : 'paid'; ?>">
                <h3>Outstanding Balance</h3>
                <div class="amount" style="color: <?php echo $outstanding_balance > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
                    KSh <?php echo number_format($outstanding_balance, 2); ?>
                </div>
                <div class="sub-text"><?php echo $outstanding_balance > 0 ? 'Pending payment' : 'All fees cleared! 🎉'; ?></div>
            </div>
        </div>

        <!-- Payment Progress -->
        <div style="margin: 20px 0;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <span>Payment Progress</span>
                <span><strong><?php echo number_format($payment_percentage, 1); ?>%</strong> paid</span>
            </div>
            <div class="progress-bar-fee">
                <div class="progress-fee" style="width: <?php echo min($payment_percentage, 100); ?>%;">
                    <?php if ($payment_percentage > 30): ?>
                        <?php echo number_format($payment_percentage, 1); ?>%
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Fee Breakdown Table -->
        <h3 style="margin-top: 30px;">📋 Fee Breakdown - <?php echo $semester_name; ?></h3>
        
        <?php if (count($fee_breakdown) > 0): ?>
            <table class="fee-table">
                <thead>
                    <tr>
                        <th>Fee Type</th>
                        <th>Total Amount (KSh)</th>
                        <th>Paid (KSh)</th>
                        <th>Remaining (KSh)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($fee_breakdown as $fee): ?>
                        <tr class="<?php echo $fee['status'] == 'completed' ? 'paid-row' : ''; ?>">
                            <td><strong><?php echo ucfirst(str_replace('_', ' ', $fee['fee_type'])); ?></strong></td>
                            <td><?php echo number_format($fee['amount'], 2); ?></td>
                            <td><?php echo number_format($fee['paid'], 2); ?></td>
                            <td>
                                <?php if ($fee['remaining'] > 0): ?>
                                    <strong style="color: var(--danger);"><?php echo number_format($fee['remaining'], 2); ?></strong>
                                <?php else: ?>
                                    <span style="color: var(--success);">0.00</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($fee['status'] == 'completed'): ?>
                                    <span class="status-badge completed">✅ Paid</span>
                                <?php else: ?>
                                    <span class="status-badge pending">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f1f5f9; font-weight: 700;">
                        <td><strong>TOTAL</strong></td>
                        <td><strong><?php echo number_format($total_fees, 2); ?></strong></td>
                        <td><strong><?php echo number_format($paid_amount, 2); ?></strong></td>
                        <td><strong style="color: <?php echo $outstanding_balance > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
                            <?php echo number_format($outstanding_balance, 2); ?>
                        </strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <div class="no-data">
                <span class="big-icon">📭</span>
                <p>No fee records found for <?php echo $semester_name; ?>.</p>
                <p style="font-size: 0.9rem; color: #94a3b8;">
                    Department: <strong><?php echo $student_dept; ?></strong><br>
                    Year Level: <strong><?php echo $student_year_level; ?></strong><br>
                    Semester: <strong><?php echo $semester_name; ?></strong>
                </p>
                <p style="font-size: 0.9rem; color: #94a3b8;">Your fee structure will appear here once it's set up by the administration.</p>
            </div>
        <?php endif; ?>

        <!-- Payment Section -->
        <?php if ($outstanding_balance > 0 && count($fee_breakdown) > 0): ?>
        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid var(--border);">
            <h3>💳 Make a Payment</h3>
            
            <form method="POST" action="" id="paymentForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_amount">Amount to Pay (KSh)</label>
                        <input type="number" id="payment_amount" name="payment_amount" placeholder="Enter amount in KSh" min="1" step="0.01" required>
                        <small style="color: var(--text-light);">Outstanding: KSh <?php echo number_format($outstanding_balance, 2); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="fee_type">Select Fee Type</label>
                        <select id="fee_type" name="fee_type" required>
                            <option value="">Select fee type...</option>
                            <?php foreach($fee_breakdown as $fee): ?>
                                <?php if ($fee['remaining'] > 0): ?>
                                    <option value="<?php echo $fee['fee_type']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $fee['fee_type'])); ?> 
                                        (Remaining: KSh <?php echo number_format($fee['remaining'], 2); ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Payment Method</label>
                    <div class="payment-methods">
                        <div class="payment-method-btn" data-method="credit_card" onclick="selectPaymentMethod(this)">
                            <span class="method-icon">💳</span>
                            <span class="method-name">Credit Card</span>
                        </div>
                        <div class="payment-method-btn" data-method="bank_transfer" onclick="selectPaymentMethod(this)">
                            <span class="method-icon">🏛️</span>
                            <span class="method-name">Bank Transfer</span>
                        </div>
                        <div class="payment-method-btn" data-method="mobile_money" onclick="selectPaymentMethod(this)">
                            <span class="method-icon">📱</span>
                            <span class="method-name">Mobile Money</span>
                        </div>
                        <div class="payment-method-btn" data-method="m_pesa" onclick="selectPaymentMethod(this)">
                            <span class="method-icon">📲</span>
                            <span class="method-name">M-Pesa</span>
                        </div>
                    </div>
                    <input type="hidden" id="payment_method" name="payment_method" value="">
                </div>
                
                <button type="submit" name="process_payment" class="btn btn-fee" style="margin-top: 10px;">
                    💳 Process Payment
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Payment History -->
        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid var(--border);">
            <h3>📜 Payment History</h3>
            
            <?php if (mysqli_num_rows($payment_history_result) > 0): ?>
                <table class="fee-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Fee Type</th>
                            <th>Amount (KSh)</th>
                            <th>Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($payment = mysqli_fetch_assoc($payment_history_result)): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                <td><code style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;"><?php echo $payment['reference']; ?></code></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['fee_type'])); ?></td>
                                <td><strong><?php echo number_format($payment['amount'], 2); ?></strong></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <span class="big-icon">📭</span>
                    <p>No payment history found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer>
    &copy; <?php echo date('Y'); ?> Portal Assistant AI. All rights reserved.
</footer>

<script>
function selectPaymentMethod(element) {
    document.querySelectorAll('.payment-method-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    element.classList.add('selected');
    document.getElementById('payment_method').value = element.dataset.method;
}

document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
    const amount = document.getElementById('payment_amount').value;
    const feeType = document.getElementById('fee_type').value;
    const paymentMethod = document.getElementById('payment_method').value;
    
    if (!amount || parseFloat(amount) <= 0) {
        e.preventDefault();
        alert('Please enter a valid payment amount.');
        return false;
    }
    
    if (!feeType) {
        e.preventDefault();
        alert('Please select a fee type.');
        return false;
    }
    
    if (!paymentMethod) {
        e.preventDefault();
        alert('Please select a payment method.');
        return false;
    }
    
    const outstanding = <?php echo $outstanding_balance; ?>;
    if (parseFloat(amount) > outstanding) {
        if (!confirm(`⚠️ You are paying KSh ${amount} which is more than your outstanding balance of KSh ${outstanding.toFixed(2)}. Do you want to continue?`)) {
            e.preventDefault();
            return false;
        }
    }
    
    if (!confirm(`Confirm payment of KSh ${amount} for ${feeType.replace('_', ' ')} using ${paymentMethod.replace('_', ' ')}?`)) {
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>

</body>
</html>