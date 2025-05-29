<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

$message = '';
$certificate = null;

if (isset($_GET['code'])) {
    $code = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING);
    
    $db = Database::getInstance()->getConnection();
    $query = "SELECT c.*, u.name as student_name, e.title as exam_title,
              ea.score, e.passing_score
              FROM certificates c
              JOIN users u ON c.user_id = u.id
              JOIN exams e ON c.exam_id = e.id
              JOIN exam_attempts ea ON ea.user_id = u.id AND ea.exam_id = e.id
              WHERE c.certificate_number = :code
              AND ea.status = 'completed'
              ORDER BY ea.score DESC LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':code' => $code]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$certificate) {
        $message = '<div class="alert alert-danger">Invalid certificate number.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h3 class="text-center">Certificate Verification</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="mb-4">
                            <div class="input-group">
                                <input type="text" class="form-control" name="code" 
                                       placeholder="Enter certificate number" 
                                       value="<?php echo isset($_GET['code']) ? htmlspecialchars($_GET['code']) : ''; ?>"
                                       required>
                                <button type="submit" class="btn btn-primary">Verify</button>
                            </div>
                        </form>

                        <?php echo $message; ?>

                        <?php if ($certificate): ?>
                        <div class="alert alert-success">
                            <h4 class="alert-heading">Valid Certificate!</h4>
                            <hr>
                            <p><strong>Student Name:</strong> <?php echo htmlspecialchars($certificate['student_name']); ?></p>
                            <p><strong>Exam:</strong> <?php echo htmlspecialchars($certificate['exam_title']); ?></p>
                            <p><strong>Score:</strong> <?php echo $certificate['score']; ?>%</p>
                            <p><strong>Issue Date:</strong> <?php echo date('F d, Y', strtotime($certificate['issue_date'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 