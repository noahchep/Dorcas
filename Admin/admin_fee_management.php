<?php
session_start();

/* ==========================
    ACCESS CONTROL
========================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['user_name'] ?? 'Administrator';

/* ==========================
    DATABASE CONNECTION
========================== */
$conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

/* ==========================
    HANDLE POST ACTIONS
========================== */
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'add_fee':
                $department = mysqli_real_escape_string($conn, $_POST['department']);
                $semester = intval($_POST['semester']);
                $year_level = mysqli_real_escape_string($conn, $_POST['year_level']);
                $category = mysqli_real_escape_string($conn, $_POST['category']);
                $amount = floatval($_POST['amount']);
                
                $insert = "INSERT INTO fees_structure (year, semester, year_level, category, amount, created_at) 
                           VALUES ('$department', $semester, '$year_level', '$category', $amount, NOW())";
                if (mysqli_query($conn, $insert)) {
                    $message = "✅ Fee added successfully!";
                    $message_type = "success";
                } else {
                    $message = "❌ Error adding fee: " . $conn->error;
                    $message_type = "error";
                }
                break;
                
            case 'update_fee':
                $fee_id = intval($_POST['fee_id']);
                $department = mysqli_real_escape_string($conn, $_POST['department']);
                $semester = intval($_POST['semester']);
                $year_level = mysqli_real_escape_string($conn, $_POST['year_level']);
                $category = mysqli_real_escape_string($conn, $_POST['category']);
                $amount = floatval($_POST['amount']);
                
                $update = "UPDATE fees_structure SET 
                           year='$department', 
                           semester=$semester, 
                           year_level='$year_level', 
                           category='$category', 
                           amount=$amount 
                           WHERE id=$fee_id";
                if (mysqli_query($conn, $update)) {
                    $message = "✅ Fee updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "❌ Error updating fee: " . $conn->error;
                    $message_type = "error";
                }
                break;
                
            case 'delete_fee':
                $fee_id = intval($_POST['fee_id']);
                $delete = "DELETE FROM fees_structure WHERE id=$fee_id";
                if (mysqli_query($conn, $delete)) {
                    $message = "✅ Fee deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "❌ Error deleting fee: " . $conn->error;
                    $message_type = "error";
                }
                break;
        }
    }
}

/* ==========================
    FETCH DATA
========================== */
// Get departments
$departments_query = mysqli_query($conn, "SELECT DISTINCT department FROM users WHERE role='student' AND department IS NOT NULL");
$departments = [];
if ($departments_query) {
    while($row = mysqli_fetch_assoc($departments_query)) {
        $departments[] = $row['department'];
    }
}

// Get fee structure - Grouped by Department, Year Level, Semester
$fee_structure_query = "SELECT 
                            year as department,
                            semester,
                            year_level,
                            category,
                            amount,
                            id
                        FROM fees_structure 
                        ORDER BY year, semester, year_level, category";
$fee_structure_result = mysqli_query($conn, $fee_structure_query);

// Get all fee payments summary
$payment_summary_query = "SELECT 
    COUNT(*) as total_payments,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_collected,
    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
    COUNT(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
    COUNT(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
    FROM fee_payments";
$payment_summary_result = mysqli_query($conn, $payment_summary_query);
$payment_summary = mysqli_fetch_assoc($payment_summary_result);

// Get recent payments
$recent_payments_query = "SELECT fp.*, u.full_name 
                         FROM fee_payments fp 
                         LEFT JOIN users u ON fp.student_reg = u.reg_number 
                         ORDER BY fp.payment_date DESC LIMIT 10";
$recent_payments_result = mysqli_query($conn, $recent_payments_query);

// Get summary for fees_structure
$summary_query = "SELECT 
    COUNT(*) as total_fees,
    SUM(amount) as total_amount,
    COUNT(DISTINCT year) as total_departments
    FROM fees_structure";
$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);

// Group fees by department, year level, and semester for summary view
$grouped_fees = [];
while($fee = mysqli_fetch_assoc($fee_structure_result)) {
    $key = $fee['department'] . '|' . $fee['year_level'] . '|' . $fee['semester'];
    if (!isset($grouped_fees[$key])) {
        $grouped_fees[$key] = [
            'department' => $fee['department'],
            'year_level' => $fee['year_level'],
            'semester' => $fee['semester'],
            'total' => 0,
            'items' => []
        ];
    }
    $grouped_fees[$key]['total'] += floatval($fee['amount']);
    $grouped_fees[$key]['items'][] = [
        'id' => $fee['id'],
        'category' => $fee['category'],
        'amount' => $fee['amount']
    ];
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'structure';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Fee Management | Portal Assistant AI</title>
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

        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .admin-strip { background: var(--accent); padding: 12px 20px; border-radius: 10px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; color: var(--primary-dark); font-weight: 700; font-size: 0.85rem; flex-wrap: wrap; gap: 10px; }

        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .tabs { display: flex; gap: 5px; margin-bottom: 25px; border-bottom: 2px solid var(--border); flex-wrap: wrap; }
        .tab { padding: 12px 24px; cursor: pointer; border: none; background: none; font-weight: 600; color: var(--text-light); transition: 0.3s; border-bottom: 3px solid transparent; font-size: 0.95rem; }
        .tab:hover { color: var(--primary); }
        .tab.active { color: var(--fee-primary); border-bottom-color: var(--fee-primary); }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--white); padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-card h3 { margin: 0; font-size: 2rem; color: var(--fee-primary); font-weight: 800; }
        .stat-card p { margin: 5px 0 0 0; color: var(--text-light); font-weight: 600; font-size: 0.85rem; }
        .stat-card .sub { font-size: 0.75rem; color: var(--text-light); font-weight: 400; }
        
        .section-box { background: var(--white); border-radius: 12px; border: 1px solid var(--border); padding: 25px; margin-bottom: 30px; overflow-x: auto; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-main); }
        .form-group input, .form-group select { width: 100%; max-width: 500px; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; }
        .form-group input:focus, .form-group select:focus { border-color: var(--fee-primary); outline: none; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .btn { padding: 10px 24px; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: 0.3s; display: inline-block; text-decoration: none; }
        .btn-primary { background: var(--fee-gradient); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-2px); }
        .btn-sm { padding: 5px 12px; font-size: 0.8rem; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        .data-table th { background: var(--bg); font-weight: 700; color: var(--text-main); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .data-table tr:hover { background: #f8fafc; }
        .data-table .clickable { cursor: pointer; transition: background 0.3s; }
        .data-table .clickable:hover { background: #e0e7ff; }
        
        /* Expanded row styles */
        .detail-row { display: none; background: #f8fafc; }
        .detail-row.show { display: table-row; }
        .detail-row td { padding: 0; }
        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table td { padding: 10px 20px; border-bottom: 1px solid #e8ecf0; }
        .detail-table tr:last-child td { border-bottom: none; }
        .detail-table .label { font-weight: 600; color: var(--text-light); width: 200px; }
        .detail-table .value { font-weight: 600; }
        .expand-icon { font-size: 1.2rem; display: inline-block; transition: transform 0.3s; }
        .expand-icon.open { transform: rotate(90deg); }
        
        .status-badge { padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .status-badge.pending { background: #fed7aa; color: #92400e; }
        .status-badge.completed { background: #d1fae5; color: #065f46; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 90%; max-width: 700px; max-height: 85vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { margin: 0; }
        .modal-close { cursor: pointer; font-size: 1.5rem; color: var(--text-light); }

        .no-data { text-align: center; padding: 60px 20px; color: #666; background: #f9fafb; border-radius: 8px; }
        .no-data .big-icon { font-size: 4rem; display: block; margin-bottom: 15px; }

        footer { text-align: center; padding: 40px; color: var(--text-light); font-size: 0.85rem; border-top: 1px solid var(--border); margin-top: 40px; }

        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .dashboard-grid { grid-template-columns: 1fr; }
            .data-table { font-size: 0.8rem; }
            .data-table th, .data-table td { padding: 8px; }
            .container { padding: 0 10px; }
            .detail-table td { padding: 8px 12px; }
        }
    </style>
</head>
<body>

<header>
    <div class="branding">
        <img src="../Images/logo.jpg" class="logoimg" alt="Logo">
        <div>
            <h1>Student Support Agent – Admin</h1>
            <small>Fee Management Module</small>
        </div>
    </div>
    <a href="Admin-index.php" class="back-btn">← Back to Dashboard</a>
</header>

<div class="container">
    <div class="admin-strip">
        <span>Active Admin: <strong><?php echo htmlspecialchars($admin_name); ?></strong></span>
        <span>Fee Management System</span>
        <span style="background: var(--fee-primary); color: white; padding: 4px 12px; border-radius: 20px;">💳 Admin Access</span>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="dashboard-grid">
        <div class="stat-card">
            <h3>KSh <?php echo number_format($payment_summary['total_collected'] ?? 0, 2); ?></h3>
            <p>Total Collected</p>
            <div class="sub">From <?php echo $payment_summary['completed_count'] ?? 0; ?> payments</div>
        </div>
        <div class="stat-card">
            <h3 style="color: var(--warning);">KSh <?php echo number_format($payment_summary['total_pending'] ?? 0, 2); ?></h3>
            <p>Pending Payments</p>
            <div class="sub"><?php echo $payment_summary['pending_count'] ?? 0; ?> students</div>
        </div>
        <div class="stat-card">
            <h3 style="color: var(--fee-primary);"><?php echo $summary['total_fees'] ?? 0; ?></h3>
            <p>Active Fee Structures</p>
            <div class="sub">Across all departments</div>
        </div>
        <div class="stat-card">
            <h3 style="color: var(--success);"><?php echo $payment_summary['total_payments'] ?? 0; ?></h3>
            <p>Total Transactions</p>
            <div class="sub">All time</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab <?php echo $tab == 'structure' ? 'active' : ''; ?>" onclick="location.href='admin_fee_management.php?tab=structure'">📋 Fee Structure</button>
        <button class="tab <?php echo $tab == 'add' ? 'active' : ''; ?>" onclick="location.href='admin_fee_management.php?tab=add'">➕ Add Fee</button>
        <button class="tab <?php echo $tab == 'payments' ? 'active' : ''; ?>" onclick="location.href='admin_fee_management.php?tab=payments'">📊 Payments</button>
    </div>

    <?php switch($tab):
        case 'add': ?>
            <!-- Add Fee Form -->
            <div class="section-box">
                <h3>➕ Add New Fee</h3>
                <p style="color: var(--text-light); margin-bottom: 20px;">Add a fee for a department, semester, and year level</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_fee">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Department *</label>
                            <select name="department" required>
                                <option value="">Select Department</option>
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Business Management">Business Management</option>
                                <option value="Accounting and Finance">Accounting and Finance</option>
                                <option value="Economics">Economics</option>
                                <option value="Hospitality Management">Hospitality Management</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester *</label>
                            <select name="semester" required>
                                <option value="">Select Semester</option>
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Year Level *</label>
                            <select name="year_level" required>
                                <option value="">Select Year Level</option>
                                <option value="First Year">First Year</option>
                                <option value="Second Year">Second Year</option>
                                <option value="Third Year">Third Year</option>
                                <option value="Fourth Year">Fourth Year</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Fee Category *</label>
                            <select name="category" required>
                                <option value="">Select Category</option>
                                <option value="Tuition Fee">Tuition Fee</option>
                                <option value="Library Fee">Library Fee</option>
                                <option value="Lab Fee">Lab Fee</option>
                                <option value="Examination Fee">Examination Fee</option>
                                <option value="Registration Fee">Registration Fee</option>
                                <option value="Sports Fee">Sports Fee</option>
                                <option value="Medical Fee">Medical Fee</option>
                                <option value="Development Fee">Development Fee</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Amount (KSh) *</label>
                        <input type="number" name="amount" placeholder="0.00" min="0" step="0.01" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">💰 Add Fee</button>
                </form>
            </div>
        <?php break;
        
        case 'payments': ?>
            <!-- Payment History -->
            <div class="section-box">
                <h3>📊 Recent Payments</h3>
                <p style="color: var(--text-light); margin-bottom: 15px;">View all payment transactions</p>
                
                <?php if ($recent_payments_result && mysqli_num_rows($recent_payments_result) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Reg Number</th>
                                <th>Fee Type</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($payment = mysqli_fetch_assoc($recent_payments_result)): ?>
                                <tr>
                                    <td><?php echo date('d M Y H:i', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['full_name'] ?? 'N/A'); ?></td>
                                    <td><code><?php echo $payment['student_reg']; ?></code></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $payment['fee_type'])); ?></td>
                                    <td><strong>KSh <?php echo number_format($payment['amount'], 2); ?></strong></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? 'N/A')); ?></td>
                                    <td><code style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;"><?php echo $payment['reference']; ?></code></td>
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
                        <p>No payments recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php break;
        
        default: ?>
            <!-- Fee Structure View -->
            <div class="section-box">
                <h3>📋 Fee Structure</h3>
                <p style="color: var(--text-light); margin-bottom: 15px;">
                    <strong>💡 Click on any row to expand and view detailed fee breakdown</strong>
                </p>
                
                <?php if (count($grouped_fees) > 0): ?>
                    <table class="data-table" id="feeTable">
                        <thead>
                            <tr>
                                <th style="width: 30px;"></th>
                                <th>Department</th>
                                <th>Year Level</th>
                                <th>Semester</th>
                                <th>Total Fees (KSh)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; foreach($grouped_fees as $key => $group): ?>
                                <?php 
                                    $row_id = 'row_' . md5($key);
                                    $detail_id = 'detail_' . md5($key);
                                ?>
                                <tr class="clickable" onclick="toggleRow('<?php echo $detail_id; ?>', '<?php echo $row_id; ?>')" id="<?php echo $row_id; ?>">
                                    <td>
                                        <span class="expand-icon" id="icon_<?php echo $detail_id; ?>">▶</span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($group['department']); ?></strong></td>
                                    <td><?php echo $group['year_level']; ?></td>
                                    <td>Semester <?php echo $group['semester']; ?></td>
                                    <td><strong style="color: var(--fee-primary);"><?php echo number_format($group['total'], 2); ?></strong></td>
                                    <td>
                                        <button onclick="event.stopPropagation(); editGroup('<?php echo htmlspecialchars($group['department']); ?>', <?php echo $group['semester']; ?>, '<?php echo htmlspecialchars($group['year_level']); ?>')" class="btn btn-primary btn-sm">✏️</button>
                                        <button onclick="event.stopPropagation(); deleteGroup('<?php echo htmlspecialchars($group['department']); ?>', <?php echo $group['semester']; ?>, '<?php echo htmlspecialchars($group['year_level']); ?>')" class="btn btn-danger btn-sm">🗑️</button>
                                    </td>
                                </tr>
                                <tr class="detail-row" id="<?php echo $detail_id; ?>">
                                    <td colspan="6">
                                        <table class="detail-table">
                                            <tr>
                                                <td colspan="2" style="font-weight: 700; font-size: 1.1rem; background: #e8ecf0;">
                                                    📋 Fee Breakdown: <?php echo htmlspecialchars($group['department']); ?> - <?php echo $group['year_level']; ?> (Semester <?php echo $group['semester']; ?>)
                                                </td>
                                            </tr>
                                            <?php foreach($group['items'] as $item): ?>
                                                <tr>
                                                    <td class="label"><?php echo htmlspecialchars($item['category']); ?></td>
                                                    <td class="value">KSh <?php echo number_format($item['amount'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr style="background: #e8ecf0; font-weight: 700;">
                                                <td style="padding: 10px 20px;">TOTAL</td>
                                                <td style="padding: 10px 20px; color: var(--fee-primary);">KSh <?php echo number_format($group['total'], 2); ?></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <span class="big-icon">📭</span>
                        <p>No fee structures found.</p>
                        <p style="font-size: 0.9rem; color: #94a3b8;">Click on "Add Fee" to create a new fee.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php break;
    endswitch; ?>
</div>

<!-- Edit Fee Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>✏️ Edit Fee</h3>
            <span class="modal-close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_fee">
            <input type="hidden" id="edit_fee_id" name="fee_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Department *</label>
                    <select id="edit_department" name="department" required>
                        <?php foreach($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Business Management">Business Management</option>
                        <option value="Accounting and Finance">Accounting and Finance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Semester *</label>
                    <select id="edit_semester" name="semester" required>
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Year Level *</label>
                    <select id="edit_year_level" name="year_level" required>
                        <option value="First Year">First Year</option>
                        <option value="Second Year">Second Year</option>
                        <option value="Third Year">Third Year</option>
                        <option value="Fourth Year">Fourth Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fee Category *</label>
                    <select id="edit_category" name="category" required>
                        <option value="Tuition Fee">Tuition Fee</option>
                        <option value="Library Fee">Library Fee</option>
                        <option value="Lab Fee">Lab Fee</option>
                        <option value="Examination Fee">Examination Fee</option>
                        <option value="Registration Fee">Registration Fee</option>
                        <option value="Sports Fee">Sports Fee</option>
                        <option value="Medical Fee">Medical Fee</option>
                        <option value="Development Fee">Development Fee</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Amount (KSh) *</label>
                <input type="number" id="edit_amount" name="amount" min="0" step="0.01" required>
            </div>
            
            <button type="submit" class="btn btn-primary">💾 Update Fee</button>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>⚠️ Confirm Delete</h3>
            <span class="modal-close" onclick="closeModal('deleteModal')">&times;</span>
        </div>
        <p id="deleteMessage">Are you sure you want to delete this fee group? This action cannot be undone.</p>
        <form method="POST" action="" id="deleteForm">
            <input type="hidden" name="action" value="delete_fee">
            <input type="hidden" id="delete_fee_id" name="fee_id">
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
                <button type="button" onclick="closeModal('deleteModal')" class="btn btn-primary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<footer>
    &copy; <?php echo date('Y'); ?> Portal Assistant AI. All rights reserved.
</footer>

<script>
function toggleRow(detailId, rowId) {
    var detailRow = document.getElementById(detailId);
    var icon = document.getElementById('icon_' + detailId);
    
    if (detailRow.classList.contains('show')) {
        detailRow.classList.remove('show');
        icon.classList.remove('open');
        icon.textContent = '▶';
    } else {
        // Close all other open rows
        var allDetails = document.querySelectorAll('.detail-row');
        allDetails.forEach(function(row) {
            row.classList.remove('show');
        });
        var allIcons = document.querySelectorAll('.expand-icon');
        allIcons.forEach(function(icon) {
            icon.classList.remove('open');
            icon.textContent = '▶';
        });
        
        detailRow.classList.add('show');
        icon.classList.add('open');
        icon.textContent = '▼';
    }
}

function editGroup(department, semester, yearLevel) {
    // Find the first fee in this group to edit
    var table = document.getElementById('feeTable');
    var rows = table.getElementsByTagName('tr');
    
    for (var i = 0; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName('td');
        if (cells.length > 1) {
            // Check if this is a detail row
            if (rows[i].classList.contains('detail-row')) {
                continue;
            }
            // Check if this row matches the group
            var rowDept = cells[1] ? cells[1].textContent.trim() : '';
            var rowYear = cells[2] ? cells[2].textContent.trim() : '';
            var rowSem = cells[3] ? cells[3].textContent.trim().replace('Semester ', '') : '';
            
            if (rowDept === department && rowYear === yearLevel && parseInt(rowSem) === semester) {
                // Find the edit button in this row
                var editBtn = rows[i].querySelector('.btn-primary');
                if (editBtn) {
                    editBtn.click();
                    return;
                }
            }
        }
    }
    alert('Please edit individual fee items by clicking the edit button on the expanded row.');
}

function deleteGroup(department, semester, yearLevel) {
    document.getElementById('deleteMessage').innerHTML = 
        'Are you sure you want to delete all fees for <strong>' + department + '</strong> - ' + 
        yearLevel + ' (Semester ' + semester + ')? This action cannot be undone.';
    
    // Find all fee IDs for this group
    var table = document.getElementById('feeTable');
    var rows = table.getElementsByTagName('tr');
    var feeIds = [];
    
    for (var i = 0; i < rows.length; i++) {
        if (rows[i].classList.contains('detail-row')) {
            var detailCells = rows[i].querySelectorAll('.detail-table tr');
            // Find the fee IDs from the detail row
            // For now, we'll use a simpler approach - just delete one fee
            var deleteBtn = rows[i].querySelector('.btn-danger');
            if (deleteBtn) {
                // Get the fee ID from the delete button
                var onclickAttr = deleteBtn.getAttribute('onclick');
                var match = onclickAttr.match(/deleteFee\((\d+)\)/);
                if (match) {
                    feeIds.push(parseInt(match[1]));
                }
            }
        }
    }
    
    if (feeIds.length > 0) {
        // Just delete the first fee for now
        document.getElementById('delete_fee_id').value = feeIds[0];
    } else {
        alert('No fees found to delete.');
        return;
    }
    
    document.getElementById('deleteModal').style.display = 'flex';
}

function editFee(id) {
    fetch('get_fee_data.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_fee_id').value = data.id;
                document.getElementById('edit_department').value = data.year;
                document.getElementById('edit_semester').value = data.semester;
                document.getElementById('edit_year_level').value = data.year_level;
                document.getElementById('edit_category').value = data.category;
                document.getElementById('edit_amount').value = data.amount;
                document.getElementById('editModal').style.display = 'flex';
            } else {
                alert('Error loading fee data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading fee data');
        });
}

function deleteFee(id) {
    document.getElementById('delete_fee_id').value = id;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = 'none';
    }
}
</script>

</body>
</html>