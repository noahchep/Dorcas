<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = mysqli_connect("localhost", "root", "", "portal-asisstant-ai");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$current_file = $_SERVER['PHP_SELF'];
$conv_id = $_REQUEST['conv_id'] ?? '21';
$status_msg = '';

if(isset($_POST['submit'])) {
    $admin_reply = mysqli_real_escape_string($conn, $_POST['message'] ?? '');
    
    if(!empty($admin_reply)) {
        /* NEW TRAINING LOGIC: 
           Find the student's most recent query in this conversation 
           so the AI learns what this specific answer is for.
        */
        $student_q_res = mysqli_query($conn, "SELECT message FROM chat_messages 
                                              WHERE conversation_id = '$conv_id' 
                                              AND sender_type = 'student' 
                                              ORDER BY created_at DESC LIMIT 1");
        
        $student_query = "Unknown Student Query";
        if($row = mysqli_fetch_assoc($student_q_res)) {
            $student_query = mysqli_real_escape_string($conn, $row['message']);
        }

        // 1. Log the REAL pair to the knowledge base for AI training
        mysqli_query($conn, "INSERT INTO ai_knowledge_base (student_query, verified_answer) 
                             VALUES ('$student_query', '$admin_reply')");
        
        // 2. Post the reply to the chat
        $sql_chat = "INSERT INTO chat_messages (conversation_id, message, sender_type) 
                     VALUES ('$conv_id', '$admin_reply', 'admin')";
        
        if(mysqli_query($conn, $sql_chat)) {
            // Use header redirect to prevent form resubmission and "3rd interface" issues
            header("Location: " . $current_file . "?conv_id=" . $conv_id . "&status=sent");
            exit();
        }
    }
}

if(isset($_GET['status']) && $_GET['status'] == 'sent') {
    $status_msg = "Replied & AI Trained!";
}

$chat_result = mysqli_query($conn, "SELECT * FROM chat_messages WHERE conversation_id = '$conv_id' ORDER BY created_at ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; font-family: 'Segoe UI', sans-serif; }
        .escalation-box { display: flex; flex-direction: column; height: 100vh; width: 100%; background: white; }
        .viewing-area { flex: 1; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; background: #f9f9fb; }
        .bubble { max-width: 85%; padding: 10px 14px; border-radius: 15px; font-size: 13px; line-height: 1.4; }
        .student { align-self: flex-end; background: #5d46e2; color: white; border-bottom-right-radius: 2px; }
        .admin { align-self: flex-start; background: #f1f3f9; color: #333; border-bottom-left-radius: 2px; border-left: 3px solid #5d46e2; }
        .input-area { padding: 10px; border-top: 1px solid #eee; display: flex; align-items: center; gap: 8px; background: white; }
        .input-area input { flex: 1; padding: 10px 15px; border: 1px solid #ddd; border-radius: 20px; outline: none; font-size: 13px; }
        .send-btn { background: #5d46e2; color: white; border: none; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .status { font-size: 11px; color: #10b981; text-align: center; margin-bottom: 5px; font-weight: bold; }
    </style>
</head>
<body>

<div class="escalation-box">
    <div class="viewing-area" id="chatScroll">
        <?php while($row = mysqli_fetch_assoc($chat_result)): ?>
            <div class="bubble <?php echo ($row['sender_type'] == 'student') ? 'student' : 'admin'; ?>">
                <?php echo htmlspecialchars($row['message']); ?>
            </div>
        <?php endwhile; ?>
    </div>

    <?php if($status_msg): ?> <div class="status"><?php echo $status_msg; ?></div> <?php endif; ?>

    <form class="input-area" method="POST" action="<?php echo $current_file; ?>?conv_id=<?php echo $conv_id; ?>">
        <input type="text" name="message" placeholder="Type official response..." required autocomplete="off">
        <button type="submit" name="submit" class="send-btn">
            <span class="material-icons" style="font-size: 18px;">send</span>
        </button>
    </form>
</div>

<script>
    const objDiv = document.getElementById("chatScroll");
    objDiv.scrollTop = objDiv.scrollHeight;
</script>

</body>
</html>