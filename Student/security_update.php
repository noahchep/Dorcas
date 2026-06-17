<?php
/* --- 1. DB CONNECTION CHECK --- */
// Ensures $conn is available when included in home.php
if (!isset($conn)) {
    $conn = mysqli_connect("localhost", "root", "", "portal-asisstant-ai");
}

$pass_message = "";

if (isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass     = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    $user_id      = $_SESSION['user_id'];

    $query = "SELECT password FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($res);

    if (!password_verify($current_pass, $user_data['password'])) {
        $pass_message = "error|Incorrect current password.";
    } elseif ($new_pass !== $confirm_pass) {
        $pass_message = "error|Passwords do not match.";
    } else {
        $new_hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password = ? WHERE id = ?";
        $up_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($up_stmt, "si", $new_hashed, $user_id);
        
        if (mysqli_stmt_execute($up_stmt)) {
            $pass_message = "success|Security update successful! Refreshing...";
            // Automatically hides the form and alert after success
            echo "<script>
                setTimeout(() => { 
                    document.getElementById('passwordInterface').style.display = 'none';
                    document.getElementById('securityAlertBox').style.display = 'none';
                }, 3000);
            </script>";
        } else {
            $pass_message = "error|System Error: Could not update.";
        }
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .security-card { background: #fff; padding: 30px; border-radius: 15px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .password-wrapper { position: relative; display: flex; align-items: center; margin-bottom: 18px; }
    .password-input { width: 100%; padding: 12px 45px 12px 12px; border: 1px solid #cbd5e1; border-radius: 10px; outline: none; }
    .toggle-password { position: absolute; right: 15px; cursor: pointer; color: #94a3b8; }
    .req-list { list-style: none; padding: 0; margin: 15px 0; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 0.8rem; }
    .req-list li { color: #94a3b8; display: flex; align-items: center; gap: 8px; }
    .req-list li.valid { color: #166534; font-weight: 600; }
</style>

<div class="security-card">
    <h3 style="margin-top: 0; color: #1e293b;"><i class="fa-solid fa-shield-halved"></i> Security Settings</h3>
    
    <?php if ($pass_message): 
        $m = explode('|', $pass_message); ?>
        <div style="padding: 12px; margin-bottom: 20px; border-radius: 8px; background: <?php echo $m[0] == 'success' ? '#f0fdf4' : '#fef2f2'; ?>; color: <?php echo $m[0] == 'success' ? '#166534' : '#991b1b'; ?>;">
            <?php echo $m[1]; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <label style="font-weight: 600;">Current Password</label>
        <div class="password-wrapper">
            <input type="password" name="current_password" id="cp" class="password-input" required>
            <i class="fa-solid fa-eye toggle-password" onclick="toggleVis('cp', this)"></i>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <label style="font-weight: 600;">New Password</label>
                <div class="password-wrapper">
                    <input type="password" name="new_password" id="np" class="password-input" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="toggleVis('np', this)"></i>
                </div>
            </div>
            <div>
                <label style="font-weight: 600;">Confirm New</label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="cnp" class="password-input" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="toggleVis('cnp', this)"></i>
                </div>
            </div>
        </div>

        <ul class="req-list">
            <li id="r1"><i class="fa-solid fa-circle-check"></i> 8+ characters</li>
            <li id="r2"><i class="fa-solid fa-circle-check"></i> 1 Uppercase</li>
            <li id="r3"><i class="fa-solid fa-circle-check"></i> 1 Number</li>
            <li id="r4"><i class="fa-solid fa-circle-check"></i> Match</li>
        </ul>

        <button type="submit" name="change_password" id="sbtn" disabled style="width: 100%; background: #94a3b8; color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 700;">Save Changes</button>
    </form>
</div>

<script>
    function toggleVis(id, icon) {
        const i = document.getElementById(id);
        i.type = (i.type === "password") ? "text" : "password";
        icon.classList.toggle("fa-eye");
        icon.classList.toggle("fa-eye-slash");
    }

    const n = document.getElementById('np'), c = document.getElementById('cnp'), b = document.getElementById('sbtn');
    const r = { l: document.getElementById('r1'), u: document.getElementById('r2'), n: document.getElementById('r3'), m: document.getElementById('r4') };

    function val() {
        const v = n.value, cv = c.value;
        const isL = v.length >= 8, isU = /[A-Z]/.test(v), isN = /[0-9]/.test(v), isM = v === cv && v !== "";
        r.l.className = isL ? 'valid' : ''; r.u.className = isU ? 'valid' : ''; r.n.className = isN ? 'valid' : ''; r.m.className = isM ? 'valid' : '';
        b.disabled = !(isL && isU && isN && isM);
        b.style.background = b.disabled ? "#94a3b8" : "#4f46e5";
    }
    n.addEventListener('input', val); c.addEventListener('input', val);
</script>