<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';

// Check if user is logged in and has class teacher role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'class_teacher') {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Check if student ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: students.php');
    exit;
}

$studentId = $_GET['id'];
$classTeacherId = $_SESSION['teacher_id'] ?? 0;

// Get student information
$studentQuery = "SELECT s.* 
                FROM students s
                WHERE s.id = ?";

$stmt = $conn->prepare($studentQuery);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$studentResult = $stmt->get_result();

if ($studentResult->num_rows === 0) {
    echo "Error: Student not found.";
    exit;
}

$student = $studentResult->fetch_assoc();

// Get recent activities for this student
$activitiesQuery = "SELECT cta.*, ct.user_id as teacher_user_id
                   FROM class_teacher_activities cta
                   JOIN class_teachers ct ON cta.class_teacher_id = ct.id
                   WHERE cta.student_id = ?
                   ORDER BY cta.activity_date DESC, cta.created_at DESC
                   LIMIT 10";

$stmt = $conn->prepare($activitiesQuery);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$activitiesResult = $stmt->get_result();
$activities = [];

while ($row = $activitiesResult->fetch_assoc()) {
    $activities[] = $row;
}

// Get comments for this student
$commentsQuery = "SELECT ctc.*, ct.user_id as teacher_user_id
                  FROM class_teacher_comments ctc
                  JOIN class_teachers ct ON ctc.class_teacher_id = ct.id
                  WHERE ctc.student_id = ?
                  ORDER BY ctc.created_at DESC
                  LIMIT 10";

$stmt = $conn->prepare($commentsQuery);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$commentsResult = $stmt->get_result();
$comments = [];

while ($row = $commentsResult->fetch_assoc()) {
    $comments[] = $row;
}

// Handle PDF generation if requested
if (isset($_GET['pdf'])) {
    // Start output buffering to prevent any accidental output
    ob_start();
    
    require_once __DIR__ . '/../utils.php';
    require_once __DIR__ . '/../fpdf_temp/fpdf.php';
    
    // Create PDF with styling matching the template image
    $pdf = new FPDF();
    $pdf->AliasNbPages(); // Enable page numbering
    $pdf->SetAuthor('ACE COLLEGE');
    $pdf->SetTitle('Student Details - ' . ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
    
    // Set margins
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();
    
    // Blue header
    $pdf->SetFillColor(0, 51, 102);
    $pdf->Rect(0, 0, 210, 25, 'F');
    
    // Logo placeholder (would be replaced with school logo)
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect(10, 5, 15, 15, 'F');
    
    // School name
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(30, 7);
    $pdf->Cell(150, 8, 'ACE COLLEGE', 0, 1);
    
    // School motto
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetXY(30, 15);
    $pdf->Cell(150, 5, 'Excellence with Integrity', 0, 1);
    
    $pdf->SetY(30); // Start content after header
    
    // MAIN TITLE
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 10, 'COMPLETE STUDENT PROFILE', 1, 1, 'C', false);
    
    // STUDENT INFORMATION SECTION
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 7, 'STUDENT INFORMATION', 1, 1, 'L', true);
    
    // Add student passport photo if available
    $studentImage = '';
    $hasImage = false;
    
    // Re-use the same logic to find the student's image
    if (!empty($student['profile_image'])) {
        $imagePath = '../uploads/students/' . $student['profile_image'];
        if (file_exists($imagePath)) {
            $studentImage = $imagePath;
            $hasImage = true;
        }
    }
    
    // If no profile image found, try the passport photo approach
    if (!$hasImage && isset($student['registration_number'])) {
        $safe_registration = str_replace(['/', ' '], '_', $student['registration_number']);
        
        $possiblePaths = [
            '../uploads/student_passports/' . $safe_registration . '.jpg',
            '../uploads/student_passports/' . $safe_registration . '.png',
            '../../uploads/student_passports/' . $safe_registration . '.jpg',
            '../../uploads/student_passports/' . $safe_registration . '.png'
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $studentImage = $path;
                $hasImage = true;
                break;
            }
        }
    }
    
    // Create a 2-column layout with image on the left and basic info on the right
    $startY = $pdf->GetY();
    
    // Display student image if found
    if ($hasImage) {
        // Save current position
        $currentY = $pdf->GetY();
        
        try {
            // Add image in a nice frame
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Rect(10, $currentY + 3, 45, 45, 'F');
            $pdf->Image($studentImage, 12, $currentY + 5, 40, 40);
            
            // Add a border around the image
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->Rect(12, $currentY + 5, 40, 40, 'D');
        } catch (Exception $e) {
            // If there's an error loading the image (e.g., invalid format), display placeholder
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Rect(12, $currentY + 5, 40, 40, 'F');
            $pdf->SetTextColor(150, 150, 150);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetXY(12, $currentY + 25);
            $pdf->Cell(40, 5, 'No Image Available', 0, 1, 'C');
        }
        
        // Position for the student table info (moved to right)
        $pdf->SetY($currentY);
        $pdf->SetX(60);
    }
    
    // Function for table rows with alternating colors - Modified for conditional two-column layout
    function addTableRow($pdf, $label, $value, $fill = false, $twoColumn = false) {
        $pdf->SetTextColor(0, 0, 0);
        
        // Width for single or two-column layout
        $width = $twoColumn ? 70 : 95;
        
        // Label column
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell($width, 6, $label, 1, 0, 'L', $fill);
        
        // Value column
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell($width, 6, $value, 1, 1, 'L', $fill);
        
        // Reset X position if in two-column mode
        if ($twoColumn) {
            $pdf->SetX(60);
        }
    }
    
    // Student basic details with alternating rows - Use two-column layout if image exists
    $pdf->SetFillColor(240, 240, 240);
    
    if ($hasImage) {
        // Two-column layout with narrower tables
        addTableRow($pdf, 'Registration Number', $student['registration_number'] ?? $student['admission_number'] ?? 'N/A', true, true);
        addTableRow($pdf, 'Student Name', ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''), false, true);
        addTableRow($pdf, 'Class', $student['class'] ?? 'N/A', true, true);
        addTableRow($pdf, 'Date of Birth', isset($student['date_of_birth']) ? date('M d, Y', strtotime($student['date_of_birth'])) : 'N/A', false, true);
        addTableRow($pdf, 'Gender', isset($student['gender']) ? ucfirst(strtolower($student['gender'])) : 'N/A', true, true);
        
        // Reset position after two-column layout
        $pdf->SetY(max($pdf->GetY(), $startY + 50));
    } else {
        // Standard full-width layout
        addTableRow($pdf, 'Registration Number', $student['registration_number'] ?? $student['admission_number'] ?? 'N/A', true);
        addTableRow($pdf, 'Student Name', ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
        addTableRow($pdf, 'Class', $student['class'] ?? 'N/A', true);
        addTableRow($pdf, 'Date of Birth', isset($student['date_of_birth']) ? date('M d, Y', strtotime($student['date_of_birth'])) : 'N/A');
        addTableRow($pdf, 'Gender', isset($student['gender']) ? ucfirst(strtolower($student['gender'])) : 'N/A', true);
    }
    
    $pdf->Ln(5);
    
    // PARENT INFORMATION SECTION
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 7, 'PARENT INFORMATION', 1, 1, 'L', true);
    
    // Parent details
    $pdf->SetFillColor(240, 240, 240);
    addTableRow($pdf, 'Father\'s Name', $student['father_s_name'] ?? 'N/A', true);
    addTableRow($pdf, 'Father\'s Contact', $student['father_s_contact_phone_number_s_'] ?? 'N/A');
    addTableRow($pdf, 'Father\'s Occupation', $student['father_s_occupation'] ?? 'N/A', true);
    addTableRow($pdf, 'Mother\'s Name', $student['mother_s_name'] ?? 'N/A');
    addTableRow($pdf, 'Mother\'s Contact', $student['mother_s_contact_phone_number_s_'] ?? 'N/A', true);
    addTableRow($pdf, 'Mother\'s Occupation', $student['mother_s_occupation'] ?? 'N/A');
    
    // Check if we need a new page
    if($pdf->GetY() > 220) {
        $pdf->AddPage();
    } else {
        $pdf->Ln(5);
    }
    
    // GUARDIAN INFORMATION SECTION (if available)
    if (isset($student['guardian_name']) || isset($student['child_lives_with'])) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(0, 51, 102);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 7, 'GUARDIAN INFORMATION', 1, 1, 'L', true);
        
        $pdf->SetFillColor(240, 240, 240);
        addTableRow($pdf, 'Guardian Name', $student['guardian_name'] ?? 'N/A', true);
        addTableRow($pdf, 'Guardian Contact', $student['guardian_contact_phone_number'] ?? 'N/A');
        addTableRow($pdf, 'Guardian Occupation', $student['guardian_occupation'] ?? 'N/A', true);
        addTableRow($pdf, 'Child Lives With', $student['child_lives_with'] ?? 'N/A');
        
        $pdf->Ln(5);
    }
    
    // MEDICAL INFORMATION SECTION (if available)
    if (isset($student['blood_group']) || isset($student['medical_conditions'])) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(0, 51, 102);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 7, 'MEDICAL INFORMATION', 1, 1, 'L', true);
        
        $pdf->SetFillColor(240, 240, 240);
        addTableRow($pdf, 'Blood Group', $student['blood_group'] ?? 'Not provided', true);
        addTableRow($pdf, 'Medical Conditions', $student['medical_conditions'] ?? 'None reported');
        
        $pdf->Ln(5);
    }
    
    // Check content type and add additional sections based on request
    switch ($_GET['pdf']) {
        case 'activities':
            // Add activities section
            if (count($activities) > 0) {
                // Check if we need a new page
                if($pdf->GetY() > 200) {
                    $pdf->AddPage();
                }
                
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetFillColor(0, 51, 102);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell(0, 7, 'STUDENT ACTIVITIES RECORD', 1, 1, 'L', true);
                
                // Table headers
                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(30, 6, 'Date', 1, 0, 'C', true);
                $pdf->Cell(40, 6, 'Type', 1, 0, 'C', true);
                $pdf->Cell(120, 6, 'Description', 1, 1, 'C', true);
                
                // Table content
                $pdf->SetFont('Arial', '', 9);
                $fill = false;
                foreach ($activities as $activity) {
                    $pdf->Cell(30, 6, date('M d, Y', strtotime($activity['activity_date'])), 1, 0, 'L', $fill);
                    $pdf->Cell(40, 6, ucfirst($activity['activity_type']), 1, 0, 'L', $fill);
                    
                    // Handle multiline descriptions
                    $x = $pdf->GetX();
                    $y = $pdf->GetY();
                    $pdf->MultiCell(120, 6, $activity['description'], 1, 'L', $fill);
                    
                    // Set position for next row
                    $pdf->SetXY($pdf->GetX(), $y + 6);
                    $fill = !$fill;
                }
            }
            break;
            
        case 'comments':
            // Add comments section
            if (count($comments) > 0) {
                // Check if we need a new page
                if($pdf->GetY() > 200) {
                    $pdf->AddPage();
                }
                
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetFillColor(0, 51, 102);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell(0, 7, 'TEACHER COMMENTS RECORD', 1, 1, 'L', true);
                
                $fill = false;
                foreach ($comments as $comment) {
                    // Comment header
                    $pdf->SetFillColor(240, 240, 240);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('Arial', 'B', 9);
                    
                    $commentHeader = date('M d, Y', strtotime($comment['created_at'])) . ' - ' . ucfirst($comment['comment_type']);
                    if (!empty($comment['term'])) {
                        $commentHeader .= ' (Term: ' . $comment['term'] . ', Session: ' . $comment['session'] . ')';
                    }
                    
                    $pdf->Cell(0, 6, $commentHeader, 1, 1, 'L', $fill);
                    
                    // Comment body
                    $pdf->SetFont('Arial', '', 9);
                    $pdf->MultiCell(0, 6, $comment['comment'], 1, 'L', $fill);
                    $pdf->Ln(3);
                    
                    $fill = !$fill;
                }
            }
            break;
            
        case 'full_profile':
            // Add both activities and comments if available
            if (count($activities) > 0) {
                // Check if we need a new page
                if($pdf->GetY() > 180) {
                    $pdf->AddPage();
                } else {
                    $pdf->Ln(5);
                }
                
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetFillColor(0, 51, 102);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell(0, 7, 'RECENT ACTIVITIES', 1, 1, 'L', true);
                
                // Table headers for activities
                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(30, 6, 'Date', 1, 0, 'C', true);
                $pdf->Cell(40, 6, 'Type', 1, 0, 'C', true);
                $pdf->Cell(120, 6, 'Description', 1, 1, 'C', true);
                
                // Show top 3 activities
                $pdf->SetFont('Arial', '', 9);
                $counter = 0;
                $fill = false;
                foreach ($activities as $activity) {
                    if ($counter >= 3) break;
                    
                    $pdf->Cell(30, 6, date('M d, Y', strtotime($activity['activity_date'])), 1, 0, 'L', $fill);
                    $pdf->Cell(40, 6, ucfirst($activity['activity_type']), 1, 0, 'L', $fill);
                    
                    // Handle long descriptions
                    $description = $activity['description'];
                    if (strlen($description) > 50) {
                        $description = substr($description, 0, 47) . '...';
                    }
                    
                    $pdf->Cell(120, 6, $description, 1, 1, 'L', $fill);
                    $fill = !$fill;
                    $counter++;
                }
                
                $pdf->Ln(5);
            }
            
            if (count($comments) > 0) {
                // Check if we need a new page
                if($pdf->GetY() > 180) {
                    $pdf->AddPage();
                }
                
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetFillColor(0, 51, 102);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell(0, 7, 'RECENT COMMENTS', 1, 1, 'L', true);
                
                // Show top 2 comments
                $counter = 0;
                $fill = false;
                foreach ($comments as $comment) {
                    if ($counter >= 2) break;
                    
                    // Comment header
                    $pdf->SetFillColor(240, 240, 240);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('Arial', 'B', 9);
                    
                    $commentHeader = date('M d, Y', strtotime($comment['created_at'])) . ' - ' . ucfirst($comment['comment_type']);
                    $pdf->Cell(0, 6, $commentHeader, 1, 1, 'L', $fill);
                    
                    // Comment body (shortened)
                    $pdf->SetFont('Arial', '', 9);
                    $commentText = $comment['comment'];
                    if (strlen($commentText) > 150) {
                        $commentText = substr($commentText, 0, 147) . '...';
                    }
                    
                    $pdf->MultiCell(0, 6, $commentText, 1, 'L', $fill);
                    $pdf->Ln(3);
                    
                    $fill = !$fill;
                    $counter++;
                }
            }
            break;
    }
    
    // Add blue footer
    $pdf->SetFillColor(0, 51, 102);
    $pdf->Rect(0, 270, 210, 27, 'F');
    
    // Footer text
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(10, 275);
    $pdf->Cell(100, 4, 'ACE COLLEGE', 0, 1);
    
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(10, 280);
    $pdf->Cell(100, 4, 'Generated on: ' . date('F j, Y'), 0, 0);
    
    // Page number in footer
    $pdf->SetXY(170, 280);
    $pdf->Cell(30, 4, 'Page ' . $pdf->PageNo() . ' of {nb}', 0, 0, 'R');
    
    // Clean any buffered output
    ob_end_clean();
    
    // Output PDF
    $pdf->Output();
    exit;
}

// Check if student has a profile image
$profileImagePath = '';
$defaultImagePath = '../assets/img/default_student.png';
$debugImageInfo = [];

// First check for profile_image in the student record
if (!empty($student['profile_image'])) {
    $imagePath = '../uploads/students/' . $student['profile_image'];
    if (file_exists($imagePath)) {
        $profileImagePath = $imagePath;
        $debugImageInfo[] = "Found profile image at: $imagePath";
    } else {
        $debugImageInfo[] = "Profile image not found at: $imagePath";
    }
}

// If no profile image found, try the passport photo approach from student dashboard
if (empty($profileImagePath) && isset($student['registration_number'])) {
    // Sanitize registration number for file path
    $safe_registration = str_replace(['/', ' '], '_', $student['registration_number']);
    
    // Try multiple possible paths for the passport photo
    $possiblePaths = [
        // Relative paths
        '../uploads/student_passports/' . $safe_registration . '.jpg',
        '../uploads/student_passports/' . $safe_registration . '.png',
        '../../uploads/student_passports/' . $safe_registration . '.jpg',
        '../../uploads/student_passports/' . $safe_registration . '.png',
        // Absolute paths from document root
        $_SERVER['DOCUMENT_ROOT'] . '/uploads/student_passports/' . $safe_registration . '.jpg',
        $_SERVER['DOCUMENT_ROOT'] . '/uploads/student_passports/' . $safe_registration . '.png',
        // Alternative directories
        '../../../uploads/student_passports/' . $safe_registration . '.jpg',
        '../../../uploads/student_passports/' . $safe_registration . '.png'
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $profileImagePath = $path;
            $debugImageInfo[] = "Found passport at: $path";
            break;
        } else {
            $debugImageInfo[] = "Passport not found at: $path";
        }
    }
}

// Include header/dashboard template
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                                <div class="col-sm-6">                    <h1 class="m-0">Student Details</h1>                </div>                <div class="col-sm-6">                    <ol class="breadcrumb float-sm-right">                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>                        <li class="breadcrumb-item active">Student Details</li>                    </ol>                </div>                <div class="col-12 mt-3">                    <div class="dropdown d-inline-block float-right">                        <button class="btn btn-success dropdown-toggle" type="button" id="pdfDropdown" data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false">                            <i class="fas fa-file-pdf"></i> Download PDF                        </button>                        <div class="dropdown-menu" aria-labelledby="pdfDropdown">                            <a class="dropdown-item" href="student_details.php?id=<?php echo $studentId; ?>&pdf=full_profile">Complete Student Profile</a>                            <a class="dropdown-item" href="student_details.php?id=<?php echo $studentId; ?>&pdf=activities">Student Activities</a>                            <a class="dropdown-item" href="student_details.php?id=<?php echo $studentId; ?>&pdf=comments">Teacher Comments</a>                        </div>                    </div>                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Student Profile Card -->
                <div class="col-md-4">
                    <div class="card card-primary card-outline">
                        <div class="card-body box-profile">
                            <div class="text-center">
                                <div class="position-relative d-inline-block">
                                    <img class="profile-user-img img-fluid img-circle" 
                                        src="<?php echo !empty($profileImagePath) ? $profileImagePath : $defaultImagePath; ?>" 
                                        alt="Student profile picture"
                                        style="width: 150px; height: 150px; object-fit: cover; border: 4px solid #e3f2fd; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                </div>
                                <?php if(empty($profileImagePath)): ?>
                                <small class="text-muted d-block mt-2">No image found for student</small>
                                <?php endif; ?>
                                
                                <?php if($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1'): ?>
                                <!-- Debug information - only visible on localhost -->
                                <small class="d-none">
                                    <strong>Image Debug:</strong><br>
                                    <?php echo implode('<br>', $debugImageInfo); ?>
                                </small>
                                <?php endif; ?>
                            </div>

                            <h3 class="profile-username text-center">
                                <?php echo ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''); ?>
                            </h3>

                            <p class="text-muted text-center">
                                <?php echo $student['class'] ?? 'N/A'; ?>
                            </p>

                            <ul class="list-group list-group-unbordered mb-3">
                                <li class="list-group-item">                                    <b>Admission #</b> <a class="float-right"><?php echo $student['admission_number'] ?? $student['registration_number'] ?? 'N/A'; ?></a>                                </li>
                                <li class="list-group-item">
                                    <b>Registration #</b> <a class="float-right"><?php echo $student['registration_number'] ?? 'N/A'; ?></a>
                                </li>
                                <li class="list-group-item">
                                    <b>Gender</b> <a class="float-right"><?php echo isset($student['gender']) ? ucfirst(strtolower($student['gender'])) : 'N/A'; ?></a>
                                </li>
                                <li class="list-group-item">
                                    <b>Date of Birth</b> <a class="float-right"><?php echo isset($student['date_of_birth']) ? date('M d, Y', strtotime($student['date_of_birth'])) : 'N/A'; ?></a>
                                </li>
                            </ul>

                            <a href="record_activity.php?student_id=<?php echo $studentId; ?>" class="btn btn-warning btn-block">
                                <i class="fas fa-clipboard"></i> Record Activity
                            </a>
                            <a href="add_comment.php?student_id=<?php echo $studentId; ?>" class="btn btn-primary btn-block">
                                <i class="fas fa-comment"></i> Add Comment
                            </a>
                        </div>
                    </div>

                    <!-- Contact Information Box -->
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Student Information</h3>
                        </div>
                        <div class="card-body">
                            <!-- Father's Information -->
                            <h5 class="text-bold"><i class="fas fa-user mr-1"></i> Father's Information</h5>
                            <div class="pl-3 mb-3">
                                <p><strong>Name:</strong> <?php echo $student['father_s_name'] ?? 'N/A'; ?></p>
                                <p><strong>Occupation:</strong> <?php echo $student['father_s_occupation'] ?? 'N/A'; ?></p>
                                <p><strong>Phone:</strong> <?php echo $student['father_s_contact_phone_number_s_'] ?? 'N/A'; ?></p>
                                <p><strong>Office Address:</strong> <?php echo $student['father_s_office_address'] ?? 'N/A'; ?></p>
                            </div>

                            <!-- Mother's Information -->
                            <h5 class="text-bold"><i class="fas fa-user mr-1"></i> Mother's Information</h5>
                            <div class="pl-3 mb-3">
                                <p><strong>Name:</strong> <?php echo $student['mother_s_name'] ?? 'N/A'; ?></p>
                                <p><strong>Occupation:</strong> <?php echo $student['mother_s_occupation'] ?? 'N/A'; ?></p>
                                <p><strong>Phone:</strong> <?php echo $student['mother_s_contact_phone_number_s_'] ?? 'N/A'; ?></p>
                                <p><strong>Office Address:</strong> <?php echo $student['mother_s_office_address'] ?? 'N/A'; ?></p>
                            </div>

                            <!-- Guardian Information (if available) -->
                            <?php if(!empty($student['guardian_name'])): ?>
                            <h5 class="text-bold"><i class="fas fa-user mr-1"></i> Guardian's Information</h5>
                            <div class="pl-3 mb-3">
                                <p><strong>Name:</strong> <?php echo $student['guardian_name'] ?? 'N/A'; ?></p>
                                <p><strong>Occupation:</strong> <?php echo $student['guardian_occupation'] ?? 'N/A'; ?></p>
                                <p><strong>Phone:</strong> <?php echo $student['guardian_contact_phone_number'] ?? 'N/A'; ?></p>
                                <p><strong>Office Address:</strong> <?php echo $student['guardian_office_address'] ?? 'N/A'; ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- General Parent Information -->
                            <h5 class="text-bold"><i class="fas fa-home mr-1"></i> General Contact</h5>
                            <div class="pl-3 mb-3">
                                <p><strong>Parent/Guardian:</strong> <?php echo $student['parent_name'] ?? 'N/A'; ?></p>
                                <p><strong>Phone:</strong> <?php echo $student['parent_phone'] ?? 'N/A'; ?></p>
                                <p><strong>Email:</strong> <?php echo $student['parent_email'] ?? 'N/A'; ?></p>
                                <p><strong>Home Address:</strong> <?php echo $student['address'] ?? 'N/A'; ?></p>
                                <?php if(!empty($student['child_lives_with'])): ?>
                                <p><strong>Child Lives With:</strong> <?php echo $student['child_lives_with']; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Details Column -->
                <div class="col-md-8">
                    <!-- Academic Information Card -->
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Academic Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-info"><i class="fas fa-graduation-cap"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Class</span>
                                            <span class="info-box-number"><?php echo $student['class'] ?? 'N/A'; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-success"><i class="fas fa-calendar-alt"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Admission Date</span>
                                            <span class="info-box-number"><?php echo isset($student['admission_date']) ? date('M d, Y', strtotime($student['admission_date'])) : 'N/A'; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (isset($student['blood_group']) || isset($student['medical_conditions'])): ?>
                            <div class="callout callout-info">
                                <h5>Medical Information</h5>
                                <p><strong>Blood Group:</strong> <?php echo $student['blood_group'] ?? 'Not provided'; ?></p>
                                <p><strong>Medical Conditions:</strong> <?php echo $student['medical_conditions'] ?? 'None reported'; ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($student['additional_info']) && !empty($student['additional_info'])): ?>
                            <div class="callout callout-warning">
                                <h5>Additional Information</h5>
                                <p><?php echo nl2br($student['additional_info']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Uploaded Documents -->
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title">Uploaded Documents</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php 
                                // Check for documents in uploads directory
                                $uploadsDir = '../uploads/students/' . $studentId;
                                $hasDocuments = false;
                                
                                if (is_dir($uploadsDir)) {
                                    $files = scandir($uploadsDir);
                                    foreach ($files as $file) {
                                        if ($file != '.' && $file != '..' && $file != $student['profile_image']) {
                                            $hasDocuments = true;
                                            $extension = pathinfo($file, PATHINFO_EXTENSION);
                                            $icon = 'fa-file';
                                            
                                            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                $icon = 'fa-file-image';
                                            } elseif (in_array($extension, ['pdf'])) {
                                                $icon = 'fa-file-pdf';
                                            } elseif (in_array($extension, ['doc', 'docx'])) {
                                                $icon = 'fa-file-word';
                                            } elseif (in_array($extension, ['xls', 'xlsx'])) {
                                                $icon = 'fa-file-excel';
                                            }
                                            
                                            echo '<div class="col-md-3 col-sm-6">';
                                            echo '<a href="' . $uploadsDir . '/' . $file . '" target="_blank" class="btn btn-app">';
                                            echo '<i class="fas ' . $icon . '"></i> ' . $file;
                                            echo '</a>';
                                            echo '</div>';
                                        }
                                    }
                                }
                                
                                if (!$hasDocuments) {
                                    echo '<div class="col-12 text-center">';
                                    echo '<p class="text-muted">No documents uploaded</p>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="card card-warning">
                        <div class="card-header">
                            <h3 class="card-title">Recent Activities</h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($activities) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th style="width: 15%">Date</th>
                                            <th style="width: 15%">Type</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activities as $activity): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($activity['activity_date'])); ?></td>
                                            <td>
                                                <?php 
                                                $typeClass = '';
                                                switch ($activity['activity_type']) {
                                                    case 'attendance': $typeClass = 'badge-info'; break;
                                                    case 'behavioral': $typeClass = 'badge-warning'; break;
                                                    case 'academic': $typeClass = 'badge-success'; break;
                                                    case 'health': $typeClass = 'badge-danger'; break;
                                                    default: $typeClass = 'badge-secondary';
                                                }
                                                ?>
                                                <span class="badge <?php echo $typeClass; ?>">
                                                    <?php echo ucfirst($activity['activity_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $activity['description']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="p-3">
                                <p class="text-center">No activities recorded for this student.</p>
                            </div>
                            <?php endif; ?>
                            <div class="card-footer">
                                <a href="student_activities.php?student_id=<?php echo $studentId; ?>" class="btn btn-sm btn-info">
                                    View All Activities
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Teacher Comments -->
                    <div class="card card-secondary">
                        <div class="card-header">
                            <h3 class="card-title">Teacher Comments</h3>
                        </div>
                        <div class="card-body">
                            <?php if (count($comments) > 0): ?>
                            <div class="direct-chat-messages" style="height: auto;">
                                <?php foreach ($comments as $comment): ?>
                                <div class="direct-chat-msg">
                                    <div class="direct-chat-infos clearfix">
                                        <span class="direct-chat-name float-left">
                                            <?php echo ($comment['teacher_user_id'] == $_SESSION['user_id']) ? 'You' : 'Another Teacher'; ?>
                                        </span>
                                        <span class="direct-chat-timestamp float-right">
                                            <?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="direct-chat-img">
                                        <i class="fas fa-user-circle fa-2x"></i>
                                    </div>
                                    <div class="direct-chat-text">
                                        <strong><?php echo ucfirst($comment['comment_type']); ?>:</strong>
                                        <?php echo nl2br($comment['comment']); ?>
                                        <?php if (!empty($comment['term'])): ?>
                                        <small class="text-muted d-block mt-1">
                                            Term: <?php echo $comment['term']; ?>, 
                                            Session: <?php echo $comment['session']; ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-center">No comments recorded for this student.</p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="add_comment.php?student_id=<?php echo $studentId; ?>" class="btn btn-primary">
                                <i class="fas fa-comment"></i> Add New Comment
                            </a>
                        </div>
                    </div>
                    
                    <!-- Announcement Management Section -->
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Manage Announcements</h3>
                        </div>
                        <div class="card-body">
                            <!-- Existing Announcements -->
                            <?php
                            // Get announcements for this student
                            $announcementsQuery = "SELECT * FROM announcements WHERE student_id = ? OR student_id = 0 ORDER BY created_at DESC";
                            $announcementStmt = $conn->prepare($announcementsQuery);
                            $announcementStmt->bind_param("i", $studentId);
                            $announcementStmt->execute();
                            $announcementsResult = $announcementStmt->get_result();
                            
                            if ($announcementsResult->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Content</th>
                                                <th>Date</th>
                                                <th>Target</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($announcement = $announcementsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($announcement['content'], 0, 50) . (strlen($announcement['content']) > 50 ? '...' : '')); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></td>
                                                <td><?php echo $announcement['student_id'] == 0 ? '<span class="badge badge-info">All Students</span>' : '<span class="badge badge-warning">This Student</span>'; ?></td>
                                                <td>
                                                    <a href="edit_announcement.php?id=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete_announcement.php?id=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No announcements found for this student.</p>
                            <?php endif; ?>
                            
                            <!-- Add New Announcement Button -->
                            <div class="mt-3">
                                <a href="add_announcement.php?student_id=<?php echo $studentId; ?>" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add New Announcement
                                </a>
                                <a href="manage_announcements.php" class="btn btn-outline-secondary ml-2">
                                    <i class="fas fa-bullhorn"></i> Manage All Announcements
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Fix for PDF download dropdown menu
    document.addEventListener('DOMContentLoaded', function() {
        // Check if Bootstrap 5 is loaded (using data-bs-toggle)
        if (typeof bootstrap !== 'undefined') {
            // Bootstrap 5 approach
            var dropdownElementList = [].slice.call(document.querySelectorAll('#pdfDropdown'))
            dropdownElementList.map(function(element) {
                return new bootstrap.Dropdown(element)
            });
        } else {
            // Fallback for Bootstrap 4 or jQuery based approach
            $('#pdfDropdown').on('click', function() {
                $(this).next('.dropdown-menu').toggleClass('show');
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.dropdown').length) {
                    $('.dropdown-menu').removeClass('show');
                }
            });
        }
    });
</script>

<?php include '../admin/include/footer.php'; ?> 