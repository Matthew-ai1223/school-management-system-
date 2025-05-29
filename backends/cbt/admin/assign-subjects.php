<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$auth = new Auth();

// Check teacher authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$message = '';

try {
    // Get all teachers with proper user information
    $teacherQuery = "SELECT t.id, t.first_name, t.last_name, u.email 
                    FROM ace_school_system.teachers t 
                    JOIN ace_school_system.users u ON t.user_id = u.id 
                    WHERE u.role = 'teacher'
                    ORDER BY t.last_name, t.first_name";
    $teacherStmt = $db->query($teacherQuery);
    if (!$teacherStmt) {
        throw new PDOException("Failed to execute teacher query: " . $db->errorInfo()[2]);
    }
    $teachers = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($teachers)) {
        $message = '<div class="alert alert-warning">No teachers found in the system.</div>';
    }

    // Get all subjects from all_subjects table with correct column names
    $subjectQuery = "SELECT subject_name, category, subject_code, is_compulsory 
                    FROM ace_school_system.all_subjects 
                    ORDER BY category, subject_name";
    $subjectStmt = $db->query($subjectQuery);
    $subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['teacher_id']) && isset($_POST['subjects'])) {
            $db->beginTransaction();
            try {
                // Delete existing assignments for this teacher
                $deleteStmt = $db->prepare("DELETE FROM ace_school_system.teacher_subjects WHERE teacher_id = ?");
                if (!$deleteStmt->execute([$_POST['teacher_id']])) {
                    throw new PDOException("Delete failed: " . implode(" ", $deleteStmt->errorInfo()));
                }
                
                // Insert new assignments
                $insertStmt = $db->prepare("INSERT INTO ace_school_system.teacher_subjects (teacher_id, subject) VALUES (?, ?)");
                
                foreach ($_POST['subjects'] as $subject) {
                    if (!$insertStmt->execute([
                        $_POST['teacher_id'],
                        $subject
                    ])) {
                        throw new PDOException("Insert failed: " . implode(" ", $insertStmt->errorInfo()));
                    }
                }
                
                $db->commit();
                $_SESSION['success_message'] = "Subject assignments updated successfully.";
                header("Location: assign-subjects.php");
                exit();
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error in assign-subjects.php: " . $e->getMessage());
                $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Error: Missing teacher_id or subjects data</div>';
        }
    }

    // Get current assignments for all teachers
    $assignmentQuery = "SELECT teacher_id, subject FROM ace_school_system.teacher_subjects ORDER BY teacher_id";
    $assignmentStmt = $db->query($assignmentQuery);
    $assignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize assignments by teacher
    $teacherAssignments = [];
    foreach ($assignments as $assignment) {
        $teacherAssignments[$assignment['teacher_id']][] = $assignment['subject'];
    }

} catch (PDOException $e) {
    error_log("Database error in assign-subjects.php: " . $e->getMessage());
    $message = '<div class="alert alert-danger">Database error occurred. Please try again later.</div>';
}

// Get any session messages
if (isset($_SESSION['success_message'])) {
    $message = '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Subjects - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #10b981;
            --background-color: #f9fafb;
            --card-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
        }

        body {
            background-color: var(--background-color);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-primary);
        }

        .main-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 3rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .page-header h1 {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.75rem;
            letter-spacing: -0.025em;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            max-width: 600px;
        }

        .teacher-card {
            background: white;
            border-radius: 16px;
            margin-bottom: 2rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
        }

        .teacher-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--hover-shadow);
        }

        .teacher-card .card-body {
            padding: 2rem;
        }

        .teacher-info {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .teacher-avatar {
            width: 56px;
            height: 56px;
            background: var(--primary-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
        }

        .teacher-details .card-title {
            color: var(--text-primary);
            font-size: 1.35rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .teacher-details .email {
            color: var(--text-secondary);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }

        .btn-save {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.75rem;
            border-radius: 10px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            font-size: 1rem;
        }

        .btn-save:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .select2-container--default .select2-selection--multiple {
            border: 2px solid var(--border-color) !important;
            border-radius: 12px !important;
            min-height: 54px !important;
            padding: 8px 12px !important;
            background: #fff;
            transition: all 0.2s ease;
        }

        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1) !important;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background: var(--primary-color) !important;
            border: none !important;
            color: white !important;
            border-radius: 20px !important;
            padding: 6px 12px !important;
            margin: 4px !important;
            font-size: 0.95rem !important;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2) !important;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: rgba(255, 255, 255, 0.8) !important;
            margin-right: 8px !important;
            font-size: 1.1rem !important;
            border: none !important;
            padding: 0 !important;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: white !important;
            background: transparent !important;
        }

        .select2-dropdown {
            border: 1px solid var(--border-color) !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
            border-radius: 12px !important;
            margin-top: 4px !important;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: var(--primary-color) !important;
        }

        .select2-container--default .select2-results__group {
            padding: 8px 12px;
            font-weight: 600;
            color: var(--text-primary);
            background: #f3f4f6;
            font-size: 0.95rem;
        }

        .select2-results__option {
            padding: 10px 16px !important;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            border: 1px solid var(--border-color);
        }

        .btn-action.select-all {
            color: var(--primary-color);
            background: rgba(79, 70, 229, 0.1);
            border-color: rgba(79, 70, 229, 0.2);
        }

        .btn-action.clear-all {
            color: var(--text-secondary);
            background: white;
        }

        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .alert {
            border-radius: 12px;
            border: 1px solid transparent;
            margin-bottom: 2rem;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert i {
            font-size: 1.25rem;
        }

        .alert-success {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }

        .alert-danger {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .teacher-card .card-body {
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="page-header">
                    <h1 class="h2">Assign Subjects to Teachers</h1>
                    <p class="text-muted mb-0">Manage and assign subjects to your teaching staff</p>
                </div>

                <?php echo $message; ?>

                <div class="row">
                    <?php foreach ($teachers as $teacher): ?>
                    <div class="col-md-6">
                        <div class="teacher-card">
                            <div class="card-body">
                                <div class="teacher-info">
                                    <div class="teacher-avatar">
                                        <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                                    </div>
                                    <div class="teacher-details">
                                        <h5 class="card-title"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h5>
                                        <p class="email">
                                            <i class='bx bx-envelope'></i>
                                            <?php echo htmlspecialchars($teacher['email']); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                    
                                    <div class="form-section">
                                        <label for="subjects_<?php echo $teacher['id']; ?>" class="form-label">
                                            <i class='bx bx-book-alt'></i> Assigned Subjects
                                        </label>
                                        <select class="form-select select2" 
                                                id="subjects_<?php echo $teacher['id']; ?>" 
                                                name="subjects[]" 
                                                multiple 
                                                required>
                                            <?php 
                                            // Group subjects by category
                                            $groupedSubjects = [];
                                            foreach ($subjects as $subject) {
                                                $groupedSubjects[$subject['category']][] = $subject;
                                            }
                                            
                                            // Display subjects grouped by category
                                            foreach ($groupedSubjects as $category => $categorySubjects):
                                            ?>
                                                <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                                    <?php foreach ($categorySubjects as $subject): ?>
                                                        <option value="<?php echo htmlspecialchars($subject['subject_name']); ?>"
                                                                <?php echo in_array($subject['subject_name'], $teacherAssignments[$teacher['id']] ?? []) ? 'selected' : ''; ?>>
                                                            <?php 
                                                            $displayText = htmlspecialchars($subject['subject_name']);
                                                            $displayText .= ' (' . htmlspecialchars($subject['subject_code']) . ')';
                                                            if ($subject['is_compulsory']) {
                                                                $displayText .= ' *';
                                                            }
                                                            echo $displayText;
                                                            ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">* Compulsory subject</small>
                                    </div>

                                    <div class="text-end">
                                        <button type="submit" class="btn btn-save">
                                            <i class='bx bx-save'></i>
                                            Save Assignments
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 with custom options
            $('.select2').select2({
                theme: 'default',
                placeholder: 'Select subjects...',
                allowClear: true,
                closeOnSelect: false,
                templateResult: formatSubjectOption,
                templateSelection: formatSubjectSelection
            });

            // Custom formatting for dropdown options
            function formatSubjectOption(subject) {
                if (!subject.id) return subject.text;
                
                const isCompulsory = subject.text.includes('*');
                const subjectCode = subject.text.match(/\((.*?)\)/);
                
                return $(`
                    <div class="subject-option">
                        <span class="subject-name">
                            ${subject.text.split('(')[0]}
                            ${isCompulsory ? '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.75rem;">Required</span>' : ''}
                        </span>
                        ${subjectCode ? `<span class="subject-code">${subjectCode[1]}</span>` : ''}
                    </div>
                `);
            }

            // Custom formatting for selected options
            function formatSubjectSelection(subject) {
                if (!subject.id) return subject.text;
                return subject.text.split('(')[0].trim();
            }

            // Add action buttons for each teacher's subject selection
            $('.teacher-card').each(function() {
                const selectElement = $(this).find('.select2');
                const buttonContainer = $('<div class="action-buttons"></div>');
                
                // Create Select All button with icon
                const selectAllBtn = $(`
                    <button type="button" class="btn-action select-all">
                        <i class="bx bx-check-double"></i>
                        Select All
                    </button>
                `);
                
                // Create Clear All button with icon
                const clearAllBtn = $(`
                    <button type="button" class="btn-action clear-all">
                        <i class="bx bx-x"></i>
                        Clear All
                    </button>
                `);

                // Select All click handler with animation
                selectAllBtn.click(function() {
                    $(this).addClass('active').css('transform', 'scale(0.95)');
                    setTimeout(() => $(this).css('transform', ''), 150);
                    
                    selectElement.find('option').prop('selected', true);
                    selectElement.trigger('change');
                });

                // Clear All click handler with animation
                clearAllBtn.click(function() {
                    $(this).addClass('active').css('transform', 'scale(0.95)');
                    setTimeout(() => $(this).css('transform', ''), 150);
                    
                    selectElement.val(null).trigger('change');
                });

                // Add buttons before the select element
                buttonContainer.append(selectAllBtn).append(clearAllBtn);
                selectElement.before(buttonContainer);
            });

            // Add loading state to save button
            $('form').on('submit', function() {
                const submitBtn = $(this).find('.btn-save');
                submitBtn.prop('disabled', true)
                    .html('<i class="bx bx-loader-alt bx-spin"></i> Saving...');
            });

            // Smooth scroll to message if present
            if ($('.alert').length) {
                $('html, body').animate({
                    scrollTop: $('.alert').offset().top - 20
                }, 500);
            }
        });
    </script>
</body>
</html> 