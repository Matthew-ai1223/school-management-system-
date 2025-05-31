<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

// Check if admin is logged in
// $auth = new Auth();
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: login.php');
//     exit();
// }

$db = Database::getInstance()->getConnection();

// Handle message deletion
if (isset($_POST['delete_message'])) {
    $message_id = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
    if ($message_id) {
        try {
            $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = :id");
            $stmt->execute([':id' => $message_id]);
            $_SESSION['message'] = 'Message deleted successfully';
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Error deleting message';
            $_SESSION['message_type'] = 'danger';
        }
    }
}

// Handle marking message as read
if (isset($_POST['mark_read'])) {
    $message_id = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
    if ($message_id) {
        try {
            $stmt = $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id");
            $stmt->execute([':id' => $message_id]);
        } catch (PDOException $e) {
            error_log("Error marking message as read: " . $e->getMessage());
        }
    }
}

// Get messages with pagination
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    // Get total count
    $stmt = $db->query("SELECT COUNT(*) FROM contact_messages");
    $total_messages = $stmt->fetchColumn();
    $total_pages = ceil($total_messages / $per_page);

    // Get messages for current page
    $stmt = $db->prepare("SELECT * FROM contact_messages 
                         ORDER BY created_at DESC 
                         LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching messages: " . $e->getMessage());
    $messages = [];
    $total_pages = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        .message-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
        }
        .message-card.unread {
            border-left-color: #28a745;
            background-color: #f8f9fa;
        }
        .message-header {
            background-color: #f8f9fa;
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        .message-body {
            padding: 1rem;
        }
        .message-footer {
            padding: 0.5rem 1rem;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        .btn-group .btn {
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <h1 class="h2">Contact Messages</h1>
                </div>

                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show">
                        <?php 
                            echo $_SESSION['message'];
                            unset($_SESSION['message']);
                            unset($_SESSION['message_type']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($messages)): ?>
                    <div class="alert alert-info">No messages found.</div>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="card message-card <?php echo !$message['is_read'] ? 'unread' : ''; ?>">
                            <div class="message-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($message['subject']); ?></h5>
                                    <small class="text-muted">
                                        From: <?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?> 
                                        (<?php echo htmlspecialchars($message['email']); ?>)
                                    </small>
                                </div>
                                <div>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="message-body">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                            </div>
                            <div class="message-footer">
                                <div class="btn-group">
                                    <?php if (!$message['is_read']): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                            <button type="submit" name="mark_read" class="btn btn-sm btn-success">
                                                <i class='bx bx-check'></i> Mark as Read
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this message?');">
                                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                        <button type="submit" name="delete_message" class="btn btn-sm btn-danger">
                                            <i class='bx bx-trash'></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Message pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 