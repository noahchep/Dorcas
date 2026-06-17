<?php
// Handle adding new Q&A
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_qa'])) {
    $question = mysqli_real_escape_string($conn, $_POST['question']);
    $answer = mysqli_real_escape_string($conn, $_POST['answer']);
    
    $stmt = $conn->prepare("INSERT INTO ai_knowledge_base (student_query, verified_answer) VALUES (?, ?)");
    $stmt->bind_param("ss", $question, $answer);
    
    if ($stmt->execute()) {
        echo '<div class="alert alert-success">✅ Q&A added to knowledge base!</div>';
    } else {
        echo '<div class="alert alert-error">❌ Error: ' . $conn->error . '</div>';
    }
    $stmt->close();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if (mysqli_query($conn, "DELETE FROM ai_knowledge_base WHERE id='$id'")) {
        echo '<div class="alert alert-success">✅ Deleted successfully!</div>';
    } else {
        echo '<div class="alert alert-error">❌ Error deleting!</div>';
    }
}

// Handle edit
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_query = mysqli_query($conn, "SELECT * FROM ai_knowledge_base WHERE id = $edit_id");
    $edit_data = mysqli_fetch_assoc($edit_query);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qa'])) {
        $question = mysqli_real_escape_string($conn, $_POST['question']);
        $answer = mysqli_real_escape_string($conn, $_POST['answer']);
        $update = "UPDATE ai_knowledge_base SET student_query='$question', verified_answer='$answer' WHERE id=$edit_id";
        if (mysqli_query($conn, $update)) {
            echo '<div class="alert alert-success">✅ Updated successfully!</div>';
            $edit_data['student_query'] = $question;
            $edit_data['verified_answer'] = $answer;
        } else {
            echo '<div class="alert alert-error">❌ Error updating!</div>';
        }
    }
}

// Get all knowledge base entries
$kb_query = "SELECT * FROM ai_knowledge_base ORDER BY id DESC";
$kb_result = mysqli_query($conn, $kb_query);
?>

<style>
.edit-form {
    background: #f9fafb;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    border: 1px solid #e5e7eb;
}
</style>

<h2>🤖 AI Knowledge Base Management</h2>

<?php if (isset($edit_data)): ?>
<div class="edit-form">
    <h3>✏️ Edit Q&A Pair</h3>
    <form method="POST">
        <div class="form-group">
            <label>Student Question *</label>
            <input type="text" name="question" value="<?php echo htmlspecialchars($edit_data['student_query']); ?>" required style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px;">
        </div>
        <div class="form-group">
            <label>Verified Answer *</label>
            <textarea name="answer" required rows="4" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px;"><?php echo htmlspecialchars($edit_data['verified_answer']); ?></textarea>
        </div>
        <input type="hidden" name="update_qa" value="1">
        <button type="submit" class="btn btn-primary">Update</button>
        <a href="Admin-index.php?section=kb" class="btn" style="background: #64748b; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none;">Cancel</a>
    </form>
</div>
<?php endif; ?>

<div class="section-box">
    <h3>➕ Add New Q&A Pair</h3>
    <form method="POST">
        <div class="form-group">
            <label>Student Question *</label>
            <input type="text" name="question" required placeholder="e.g., How do I register for units?" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px;">
        </div>
        <div class="form-group">
            <label>Verified Answer *</label>
            <textarea name="answer" required placeholder="Provide the official response..." rows="4" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px;"></textarea>
        </div>
        <input type="hidden" name="add_qa" value="1">
        <button type="submit" class="btn btn-primary">Add to Knowledge Base</button>
    </form>
</div>

<h3>📚 Existing Knowledge Base (<?php echo mysqli_num_rows($kb_result); ?> entries)</h3>

<?php if (mysqli_num_rows($kb_result) == 0): ?>
    <div class="alert" style="background: #fef3c7; color: #92400e; padding: 15px; border-radius: 8px;">
        No entries in knowledge base yet. Add your first Q&A pair above!
    </div>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Question</th>
                <th>Answer</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($kb_result)): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td style="max-width: 300px;">
                    <strong><?php echo htmlspecialchars(substr($row['student_query'], 0, 60)); ?></strong>
                    <?php if(strlen($row['student_query']) > 60) echo '...'; ?>
                </td>
                <td style="max-width: 400px;">
                    <?php echo htmlspecialchars(substr($row['verified_answer'], 0, 100)); ?>
                    <?php if(strlen($row['verified_answer']) > 100) echo '...'; ?>
                </td>
                <td style="font-size: 0.8rem; color: #666;">
                    <?php echo date('d M Y H:i', strtotime($row['created_at'])); ?>
                </td>
                <td>
                    <a href="Admin-index.php?section=kb&edit=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                    <a href="Admin-index.php?section=kb&delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this entry?')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php endif; ?>