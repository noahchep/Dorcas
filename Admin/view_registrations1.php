<?php
$query = "SELECT rc.*, u.full_name, u.reg_number, aw.unit_name 
          FROM registered_courses rc
          JOIN users u ON rc.student_reg_no = u.reg_number
          JOIN academic_workload aw ON rc.unit_code = aw.unit_code
          WHERE rc.status = 'pending'
          ORDER BY rc.registration_date DESC";
$result = mysqli_query($conn, $query);
?>

<h2>✍️ Registration Management</h2>

<div class="tabs">
    <button class="tab active" onclick="showTab('pending')">Pending Approvals</button>
    <button class="tab" onclick="showTab('all')">All Registrations</button>
</div>

<div id="pendingTab">
    <table class="data-table">
        <thead>
            <tr><th>Student</th><th>Reg Number</th><th>Unit Code</th><th>Unit Name</th><th>Date</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo $row['student_reg_no']; ?></td>
                <td><?php echo $row['unit_code']; ?></td>
                <td><?php echo htmlspecialchars($row['unit_name']); ?></td>
                <td><?php echo $row['registration_date']; ?></td>
                <td>
                    <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="action" value="approve_registration">
                        <input type="hidden" name="reg_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="btn btn-success btn-sm">✅ Approve</button>
                    </form>
                    <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="action" value="reject_registration">
                        <input type="hidden" name="reg_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm">❌ Reject</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>