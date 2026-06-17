<?php
session_start();

/** * DYNAMIC DATABASE CONNECTION 
 * This looks for the file starting from the server root to avoid "File Not Found" errors
 */
$db_path = $_SERVER['DOCUMENT_ROOT'] . '/Portal-Assistant-AI/db_connect.php';

if (file_exists($db_path)) {
    include_once($db_path);
} else {
    // Fallback: If include fails, we connect directly so the student isn't stuck
    $conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
}

if (!$conn) {
    die("Database Connection Error: " . mysqli_connect_error());
}

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../login.php"); 
    exit(); 
}

$user_id = $_SESSION['user_id'];

// Fetch user info for branding
$user_info = mysqli_query($conn, "SELECT full_name FROM users WHERE id = '$user_id'");
$user_row = mysqli_fetch_assoc($user_info);
$student_name = $user_row['full_name'] ?? 'Student';

/* ====================================
   PROCESS SURVEY SUBMISSION
==================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $challenge = mysqli_real_escape_string($conn, $_POST['challenge']);
    $ui_experience = mysqli_real_escape_string($conn, $_POST['ui_experience']);
    $chatbot_help = mysqli_real_escape_string($conn, $_POST['chatbot_help']);
    $academic_value = mysqli_real_escape_string($conn, $_POST['academic_value']);
    $ease = mysqli_real_escape_string($conn, $_POST['ease']);
    $comments = mysqli_real_escape_string($conn, $_POST['comments']);

    $sql = "INSERT INTO survey_responses (user_id, challenge_type, ui_experience, chatbot_help, academic_value, ease_rating, student_comments) 
            VALUES ('$user_id', '$challenge', '$ui_experience', '$chatbot_help', '$academic_value', '$ease', '$comments')";
    
    if (mysqli_query($conn, $sql)) {
        // Mark survey as done so they can go back to registration.php
        mysqli_query($conn, "UPDATE users SET survey_done = 1 WHERE id = '$user_id'");
        header("Location: registration.php?survey_complete=1");
        exit();
    } else {
        echo "<script>alert('Error saving survey. Please try again.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Evaluation | OrientaCore AI</title>
    <style>
        :root { --primary: #4f46e5; --bg: #f8fafc; --text: #1e293b; --white: #ffffff; --border: #e2e8f0; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); padding: 20px; line-height: 1.6; }
        .survey-card { max-width: 800px; margin: 20px auto; background: var(--white); padding: 40px; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid var(--border); }
        .header-box { border-bottom: 3px solid var(--primary); margin-bottom: 30px; padding-bottom: 15px; }
        .header-box h2 { margin: 0; color: var(--primary); font-size: 1.8rem; }
        .q-group { margin-bottom: 25px; }
        .q-title { font-weight: 700; display: block; margin-bottom: 8px; color: var(--primary); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .grid-options { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 10px; }
        .option-label { border: 1px solid var(--border); padding: 14px; border-radius: 8px; display: block; cursor: pointer; transition: 0.2s; font-size: 0.9rem; background: #fff; }
        .option-label:hover { background: #f1f5f9; border-color: var(--primary); }
        .option-label input { margin-right: 10px; }
        select, textarea { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; background: #fff; }
        textarea { min-height: 110px; resize: vertical; margin-top: 5px; }
        .submit-btn { background: var(--primary); color: white; border: none; padding: 16px; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; font-size: 1.1rem; margin-top: 20px; transition: 0.3s; }
        .submit-btn:hover { background: #3730a3; transform: translateY(-1px); }
        .research-note { font-size: 0.8rem; color: #64748b; font-style: italic; margin-top: 20px; text-align: center; border-top: 1px solid var(--border); padding-top: 15px; }
    </style>
</head>
<body>

<div class="survey-card">
    <div class="header-box">
        <h2>System Evaluation Survey</h2>
        <p style="margin:5px 0; color:#64748b;">Investigator: <strong>Noah Chepkonga</strong> | BIT/2024/43255</p>
    </div>

    <form method="POST">
        <div class="q-group">
            <span class="q-title">1. Course Registration Challenges</span>
            <label>Which aspect of unit registration is most difficult for you?</label>
            <select name="challenge" required>
                <option value="" disabled selected>Select an option...</option>
                <option value="Unit Selection">Finding and selecting correct units</option>
                <option value="Prerequisite Info">Understanding unit prerequisites</option>
                <option value="Technical Error">System speed and loading issues</option>
                <option value="Information Gap">Lack of academic advising/guidance</option>
            </select>
        </div>

        <div class="q-group">
            <span class="q-title">2. User Experience (HCI)</span>
            <label>How would you rate the interface of the new AI Student Portal?</label>
            <div class="grid-options">
                <label class="option-label"><input type="radio" name="ui_experience" value="Very Intuitive" required> Very Intuitive</label>
                <label class="option-label"><input type="radio" name="ui_experience" value="User Friendly"> User Friendly</label>
                <label class="option-label"><input type="radio" name="ui_experience" value="Average"> Average</label>
                <label class="option-label"><input type="radio" name="ui_experience" value="Complex"> Complex/Difficult</label>
            </div>
        </div>

        <div class="q-group">
            <span class="q-title">3. AI Effectiveness</span>
            <label>Did the Chatbot provide accurate guidance for your queries?</label>
            <div class="grid-options">
                <label class="option-label"><input type="radio" name="chatbot_help" value="Highly Accurate" required> Highly Accurate</label>
                <label class="option-label"><input type="radio" name="chatbot_help" value="Mostly Accurate"> Mostly Accurate</label>
                <label class="option-label"><input type="radio" name="chatbot_help" value="Needs Improvement"> Needs Improvement</label>
                <label class="option-label"><input type="radio" name="chatbot_help" value="Inaccurate"> Inaccurate</label>
            </div>
        </div>

        <div class="q-group">
            <span class="q-title">4. Impact on Planning</span>
            <label>Did this system improve your confidence in academic planning?</label>
            <select name="academic_value" required>
                <option value="Significant">Significantly improved my planning</option>
                <option value="Moderate">Moderately improved my planning</option>
                <option value="No Change">No significant change</option>
            </select>
        </div>

        <div class="q-group">
            <span class="q-title">5. Overall Satisfaction</span>
            <label>Rate the system from 1 (Poor) to 5 (Excellent):</label>
            <select name="ease" required>
                <option value="5">5 - Excellent</option>
                <option value="4">4 - Very Good</option>
                <option value="3">3 - Good</option>
                <option value="2">2 - Fair</option>
                <option value="1">1 - Poor</option>
            </select>
        </div>

        <div class="q-group">
            <span class="q-title">6. Qualitative Feedback</span>
            <label>Additional comments or suggestions for the OrientaCore AI system:</label>
            <textarea name="comments" placeholder="Your feedback helps us improve..."></textarea>
        </div>

        <button type="submit" class="submit-btn">Submit Research Data & Complete Registration</button>
        
        <p class="research-note">
            This study is conducted in partial fulfillment of the requirements for the award of <br>
            <strong>Bachelor of Science in Information Technology</strong> at Mount Kenya University.
        </p>
    </form>
</div>

</body>
</html>