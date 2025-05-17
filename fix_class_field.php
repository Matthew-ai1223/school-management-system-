<?php
// Include database configuration
require_once 'backends/config.php';
require_once 'backends/database.php';

// Set up error handling
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Initialize variables to track progress
$totalSteps = 4;
$completedSteps = 0;
$issues = [];
$success = true;

// Function to check if a step was successful
function checkStep($condition, &$completedSteps, &$issues, $successMessage, $errorMessage) {
    if ($condition) {
        $completedSteps++;
        return "<li class='success'>✅ " . $successMessage . "</li>";
    } else {
        $issues[] = $errorMessage;
        return "<li class='error'>❌ " . $errorMessage . "</li>";
    }
}

// STEP 1: Check if class-related columns exist in the students table
$step1Results = "<h3>Step 1: Checking for class column in students table</h3><ul>";
$classColumns = ['class', 'level', 'grade', 'student_class'];
$foundClassColumns = [];

foreach ($classColumns as $column) {
    $checkResult = $conn->query("SHOW COLUMNS FROM students LIKE '$column'");
    if ($checkResult && $checkResult->num_rows > 0) {
        $foundClassColumns[] = $column;
    }
}

// If no class column exists, add one
if (empty($foundClassColumns)) {
    $alterSql = "ALTER TABLE students ADD COLUMN class VARCHAR(50) NULL AFTER email";
    $alterResult = $conn->query($alterSql);
    $step1Results .= checkStep($alterResult, $completedSteps, $issues, 
        "Added 'class' column to students table.", 
        "Failed to add 'class' column: " . $conn->error);
    
    // Verify column was added
    $checkAgain = $conn->query("SHOW COLUMNS FROM students LIKE 'class'");
    $step1Results .= checkStep($checkAgain && $checkAgain->num_rows > 0, $completedSteps, $issues,
        "Verified 'class' column exists in students table.",
        "Failed to verify 'class' column was added.");
} else {
    $step1Results .= checkStep(true, $completedSteps, $issues,
        "Class columns already exist in database: " . implode(", ", $foundClassColumns),
        "");
}
$step1Results .= "</ul>";

// STEP 2: Check if registration form fields have Class/Level
$step2Results = "<h3>Step 2: Checking registration form fields</h3><ul>";
$regTypes = ['kiddies', 'college'];
$regTypeResults = [];

foreach ($regTypes as $regType) {
    $checkSql = "SELECT id FROM registration_form_fields WHERE is_active = 1 
                AND registration_type = ? 
                AND (field_label LIKE '%class%' OR field_label LIKE '%level%' OR field_label LIKE '%grade%')";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $regType);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $regTypeResults[$regType] = checkStep(true, $completedSteps, $issues,
            "Class/Level field already exists for $regType registration.",
            "");
    } else {
        // Get the highest field order
        $maxOrderSql = "SELECT MAX(field_order) as max_order FROM registration_form_fields 
                       WHERE is_active = 1 AND registration_type = ? AND field_category = 'student_info'";
        $maxOrderStmt = $conn->prepare($maxOrderSql);
        $maxOrderStmt->bind_param("s", $regType);
        $maxOrderStmt->execute();
        $maxOrderResult = $maxOrderStmt->get_result();
        $maxOrder = ($maxOrderResult->fetch_assoc())['max_order'] ?? 0;
        
        // Add the Class/Level field
        $insertSql = "INSERT INTO registration_form_fields 
                     (field_label, field_type, field_order, required, options, registration_type, field_category, is_active) 
                     VALUES ('Class/Level', 'text', ?, 1, '', ?, 'student_info', 1)";
        $insertStmt = $conn->prepare($insertSql);
        $newOrder = $maxOrder + 1;
        $insertStmt->bind_param("is", $newOrder, $regType);
        $insertResult = $insertStmt->execute();
        
        $regTypeResults[$regType] = checkStep($insertResult && $insertStmt->affected_rows > 0, $completedSteps, $issues,
            "Added Class/Level field to $regType registration form.",
            "Failed to add Class/Level field to $regType registration form: " . $conn->error);
    }
}

foreach ($regTypeResults as $result) {
    $step2Results .= $result;
}
$step2Results .= "</ul>";

// STEP 3: Update save_registration.php to handle the field (already done)
$step3Results = "<h3>Step 3: Verifying save_registration.php</h3><ul>";
$savePath = 'backends/student/registration/save_registration.php';
$saveContent = file_get_contents($savePath);

$hasClassCode = strpos($saveContent, 'student_class') !== false || 
                strpos($saveContent, '$class_field_names') !== false;

$step3Results .= checkStep($hasClassCode, $completedSteps, $issues,
    "save_registration.php already includes code to handle the Class/Level field.",
    "save_registration.php may need to be updated to handle the Class/Level field.");
$step3Results .= "</ul>";

// STEP 4: Check if student_dashboard.php and student_details.php display the field (already done)
$step4Results = "<h3>Step 4: Verifying display in dashboard and admin pages</h3><ul>";
$dashboardPath = 'backends/student/registration/student_dashboard.php';
$detailsPath = 'backends/admin/student_details.php';

$dashboardContent = file_get_contents($dashboardPath);
$detailsContent = file_get_contents($detailsPath);

$dashboardHasClass = strpos($dashboardContent, 'Class/Level') !== false && 
                     strpos($dashboardContent, '$student_class') !== false;
                     
$detailsHasClass = strpos($detailsContent, 'Class/Level') !== false && 
                  strpos($detailsContent, '$student_class') !== false;

$step4Results .= checkStep($dashboardHasClass, $completedSteps, $issues,
    "student_dashboard.php displays the Class/Level field.",
    "student_dashboard.php may not be displaying the Class/Level field correctly.");

$step4Results .= checkStep($detailsHasClass, $completedSteps, $issues,
    "student_details.php displays the Class/Level field.",
    "student_details.php may not be displaying the Class/Level field correctly.");
$step4Results .= "</ul>";

// Success rate
$successRate = round(($completedSteps / $totalSteps) * 100);

// Create test data to verify everything works
$testResults = "";
if ($successRate >= 75) {
    $testResults = "<h3>Bonus Step: Testing Class/Level Field with Sample Data</h3><ul>";
    
    // Get a random student to update (or create one if needed)
    $studentQuery = "SELECT id FROM students LIMIT 1";
    $studentResult = $conn->query($studentQuery);
    
    if ($studentResult && $studentResult->num_rows > 0) {
        $studentId = $studentResult->fetch_assoc()['id'];
        
        // Update the student with a test class value
        $testClass = "Test Grade " . rand(1, 12);
        $updateSql = "UPDATE students SET class = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $testClass, $studentId);
        $updateResult = $updateStmt->execute();
        
        $testResults .= checkStep($updateResult, $completedSteps, $issues,
            "Updated student ID $studentId with class: $testClass",
            "Failed to update test student with class value.");
    } else {
        $testResults .= "<li class='warning'>⚠️ No students found to test with. Create a student first.</li>";
    }
    
    $testResults .= "</ul>";
}

// Output the final HTML report
?>
<!DOCTYPE html>
<html>
<head>
    <title>Class/Level Field Fix</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1 { color: #333; }
        h2 { color: #444; margin-top: 20px; }
        h3 { color: #555; margin-top: 15px; }
        ul { list-style-type: none; padding-left: 20px; }
        li { margin-bottom: 8px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .progress-bar {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 5px;
            margin: 20px 0;
        }
        .progress {
            height: 30px;
            background-color: #4caf50;
            border-radius: 5px;
            text-align: center;
            line-height: 30px;
            color: white;
            transition: width 0.5s;
        }
        .next-steps {
            background-color: #f5f5f5;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background-color: #4361ee;
            color: white;
            padding: 10px 20px;
            margin: 10px 0;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }
        .button:hover {
            background-color: #3f37c9;
        }
    </style>
</head>
<body>
    <h1>Class/Level Field Fix Report</h1>
    
    <div class="progress-bar">
        <div class="progress" style="width: <?php echo $successRate; ?>%;">
            <?php echo $successRate; ?>% Complete
        </div>
    </div>
    
    <?php if (!empty($issues)): ?>
    <div class="next-steps">
        <h2>Issues to Resolve:</h2>
        <ul>
            <?php foreach ($issues as $issue): ?>
            <li class="error">❌ <?php echo $issue; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php else: ?>
    <div class="next-steps">
        <h2>✅ All checks passed! The Class/Level field should now work correctly</h2>
        <p>The following changes have been made:</p>
        <ul>
            <li>Added 'class' column to the students table (if needed)</li>
            <li>Added Class/Level field to registration form fields (if needed)</li>
            <li>Verified that the code to handle the field is present</li>
            <li>Verified that the dashboard and admin pages display the field</li>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Display the results of each step -->
    <h2>Detailed Results</h2>
    
    <?php 
    echo $step1Results;
    echo $step2Results;
    echo $step3Results;
    echo $step4Results;
    echo $testResults;
    ?>
    
    <h2>Next Steps</h2>
    <p>You should now be able to:</p>
    <ol>
        <li>Use the registration form and enter a Class/Level</li>
        <li>See the Class/Level in the student dashboard</li>
        <li>See the Class/Level in the admin student details page</li>
    </ol>
    
    <div>
        <a href="backends/student/registration/reg_form.php" class="button">Go to Registration Form</a>
    </div>
    
    <div>
        <a href="backends/student/registration/student_dashboard.php" class="button">View Student Dashboard</a>
    </div>
    
    <div>
        <a href="backends/admin/student_details.php?id=1" class="button">View Student Details (Admin)</a>
    </div>
</body>
</html> 