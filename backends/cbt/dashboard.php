<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("=== Dashboard Access Attempt ===");
error_log("Session student_id: " . (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 'Not set'));

if (!isset($_SESSION['student_id'])) {
    error_log("No student_id in session - redirecting to login");
    header('Location: login.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Get student details
    $stmt = $db->prepare("SELECT * FROM students WHERE id = :student_id AND status IN ('active', 'registered')");
    $stmt->execute([':student_id' => $_SESSION['student_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Student lookup result: " . ($student ? "Found" : "Not found"));
    if (!$student) {
        error_log("Student not found in database or not active - redirecting to login");
        session_destroy();
        header('Location: login.php?error=not_found');
        exit();
    }

    // Get available exams for student's class
    $query = "SELECT e.*, 
              (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.id AND ea.student_id = :student_id) as attempts_taken
              FROM exams e 
              WHERE e.is_active = true 
              AND (e.class = :class OR e.class = 'all')
              ORDER BY e.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':class' => $student['class'],
        ':student_id' => $_SESSION['student_id']
    ]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get student's exam history
    $history_query = "SELECT ea.*, e.title as exam_title, e.passing_score
                     FROM exam_attempts ea
                     JOIN exams e ON ea.exam_id = e.id
                     WHERE ea.student_id = :student_id
                     ORDER BY ea.start_time DESC
                     LIMIT 5";
    $stmt = $db->prepare($history_query);
    $stmt->execute([':student_id' => $_SESSION['student_id']]);
    $exam_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Database error in dashboard.php: " . $e->getMessage());
    session_destroy();
    header('Location: login.php?error=database_error');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1a73e8;
            --secondary-blue: #4285f4;
            --light-blue: #e8f0fe;
            --hover-blue: #1557b0;
            --accent-blue: #8ab4f8;
            --deep-blue: #174ea6;
            --pale-blue: #f8fbff;
            --nav-blue: #f3f8ff;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--pale-blue);
            color: #2c3e50;
            min-height: 100vh;
        }

        /* Navbar Styling */
        .navbar {
            background: linear-gradient(to right, var(--primary-blue), var(--secondary-blue)) !important;
            box-shadow: 0 2px 4px rgba(26, 115, 232, 0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 600;
            color: white !important;
        }

        .nav-link {
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9) !important;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-link.active {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.2);
        }

        /* Profile Button */
        .profile-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background-color: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            padding: 0.5rem 1rem;
        }

        .profile-button:hover {
            background-color: rgba(255, 255, 255, 0.25);
        }

        .profile-button i {
            font-size: 1.2rem;
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, var(--light-blue) 0%, var(--pale-blue) 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(26, 115, 232, 0.08);
            border: 1px solid rgba(26, 115, 232, 0.1);
        }

        .welcome-title {
            font-size: 1.75rem;
            color: var(--deep-blue);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        /* Exam Cards */
        .exam-section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--deep-blue);
            margin-bottom: 1.5rem;
            padding-left: 0.5rem;
            border-left: 4px solid var(--primary-blue);
        }

        .exam-card {
            background: white;
            border-radius: 12px;
            border: 1px solid rgba(26, 115, 232, 0.1);
            transition: all 0.3s ease;
            height: 100%;
            overflow: hidden;
        }

        .exam-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(26, 115, 232, 0.15);
            border-color: var(--accent-blue);
        }

        .exam-card .card-body {
            padding: 1.5rem;
            background: linear-gradient(to bottom, white, var(--pale-blue));
        }

        .exam-card .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 1rem;
        }

        .exam-card .card-text {
            color: #5a7184;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .exam-duration {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--secondary-blue);
            font-size: 0.9rem;
            margin-bottom: 1.25rem;
            padding: 0.5rem;
            background-color: var(--light-blue);
            border-radius: 6px;
        }

        .exam-duration i {
            color: var(--primary-blue);
        }

        .btn-start-exam {
            width: 100%;
            padding: 0.75rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
            background-color: var(--primary-blue);
            border: none;
            color: white;
        }

        .btn-start-exam:hover {
            transform: scale(1.02);
            background-color: var(--hover-blue);
            box-shadow: 0 4px 8px rgba(26, 115, 232, 0.2);
        }

        .btn-start-exam i {
            margin-left: 0.5rem;
            transition: transform 0.3s ease;
        }

        .btn-start-exam:hover i {
            transform: translateX(4px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .welcome-section {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .exam-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class='bx bxs-dashboard'></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link profile-button" href="profile.php">
                            <i class='bx bx-user'></i> My Profile
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class='bx bx-log-out'></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="welcome-section">
            <h2 class="welcome-title">Welcome back, <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>!</h2>
            <p class="text-muted">Class: <?php echo htmlspecialchars($student['class']); ?></p>
            <p class="text-muted">Ready to test your knowledge? Choose an exam below to get started.</p>
        </div>
        
        <div class="row">
            <div class="col-12">
                <h3 class="exam-section-title">Available Exams</h3>
                <div class="row g-4">
                    <?php foreach ($exams as $exam): 
                        $can_take_exam = $exam['attempts_taken'] < $exam['max_attempts'];
                    ?>
                    <div class="col-md-4">
                        <div class="exam-card card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($exam['title']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($exam['description']); ?></p>
                                <div class="exam-duration">
                                    <i class='bx bx-time'></i>
                                    <span><?php echo $exam['duration']; ?> minutes</span>
                                </div>
                                <?php if ($can_take_exam): ?>
                                    <a href="start-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-start-exam">
                                        Start Exam <i class='bx bx-right-arrow-alt'></i>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-start-exam" disabled>
                                        Maximum attempts reached
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 