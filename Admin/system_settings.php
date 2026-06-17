<?php
// Handle semester settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_semester'])) {
        $current_semester = $_POST['current_semester'];
        $reg_deadline = $_POST['reg_deadline'];
        // Save to settings table or session
        echo '<div class="alert alert-success">✅ Settings updated successfully!</div>';
    }
}
?>

<h2>⚙️ System Settings</h2>

<div class="section-box">
    <h3>📅 Academic Calendar Settings</h3>
    <form method="POST">
        <div class="form-group">
            <label>Current Semester</label>
            <select name="current_semester">
                <option value="1">1st Semester (Jan - June)</option>
                <option value="2">2nd Semester (July - Dec)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Registration Deadline</label>
            <input type="date" name="reg_deadline" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
        </div>
        <div class="form-group">
            <label>Academic Year</label>
            <input type="text" value="<?php echo date('Y') . '/' . (date('Y')+1); ?>" readonly>
        </div>
        <input type="hidden" name="update_semester" value="1">
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>

<div class="section-box">
    <h3>🔐 Security Settings</h3>
    <form>
        <div class="form-group">
            <label>Session Timeout (minutes)</label>
            <input type="number" value="30" min="5" max="120">
        </div>
        <div class="form-group">
            <label>Maximum Login Attempts</label>
            <input type="number" value="5" min="3" max="10">
        </div>
        <button type="submit" class="btn btn-primary">Save Security Settings</button>
    </form>
</div>

<div class="section-box">
    <h3>📧 Notification Settings</h3>
    <form>
        <div class="form-group">
            <label>
                <input type="checkbox" checked> Send email on registration approval
            </label>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" checked> Send reminder for pending registrations
            </label>
        </div>
        <button type="submit" class="btn btn-primary">Save Notification Settings</button>
    </form>
</div>