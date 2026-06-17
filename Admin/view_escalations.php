<?php
$conn = mysqli_connect("localhost", "root", "", "portal-asisstant-ai");
if (!$conn) { die("Database error."); }

/**
 * We fetch unique pending referrals.
 * We JOIN chat_messages to show the VERY LAST message sent, 
 * giving the admin a "live preview" of the current problem.
 */
$sql = "SELECT r.conversation_id, r.sender_name, r.status, 
        (SELECT message FROM chat_messages WHERE conversation_id = r.conversation_id ORDER BY created_at DESC LIMIT 1) as last_msg,
        (SELECT created_at FROM chat_messages WHERE conversation_id = r.conversation_id ORDER BY created_at DESC LIMIT 1) as last_time
        FROM admin_referrals r
        WHERE r.status = 'pending'
        ORDER BY last_time DESC";

$chats = mysqli_query($conn, $sql);
?>

<style>
    body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #fff; }
    .msg-item { 
        padding: 15px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: 0.2s; 
        border-left: 4px solid transparent; display: block; text-decoration: none;
    }
    .msg-item:hover { background: #f8fafc; border-left: 4px solid #4f46e5; }
    .msg-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
    .student-name { font-weight: 700; color: #1e293b; font-size: 0.85rem; }
    .time-stamp { font-size: 0.7rem; color: #94a3b8; }
    .query-preview { 
        font-size: 0.8rem; color: #64748b; white-space: nowrap; 
        overflow: hidden; text-overflow: ellipsis; display: block;
    }
    .empty-state { padding: 50px 20px; text-align: center; color: #cbd5e1; }
</style>

<div class="list-wrapper">
    <?php if($chats && mysqli_num_rows($chats) > 0): ?>
        <?php while($row = mysqli_fetch_assoc($chats)): ?>
            <div class="msg-item" onclick="window.parent.loadMessage('<?php echo $row['conversation_id']; ?>')">
                <div class="msg-header">
                    <span class="student-name"><?php echo htmlspecialchars($row['sender_name']); ?></span>
                    <span class="time-stamp"><?php echo date('H:i', strtotime($row['last_time'])); ?></span>
                </div>
                <span class="query-preview">
                    <?php echo htmlspecialchars($row['last_msg']); ?>
                </span>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">🌟</div>
            <p>All clear! No pending escalations.</p>
        </div>
    <?php endif; ?>
</div>