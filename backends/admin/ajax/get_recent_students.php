<?php
require_once '../../auth.php';
require_once '../../config.php';
require_once '../../database.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();

// Get recently registered students
$recent_students = [];

// Simple query to get the most recent students
$query = "SELECT * FROM students ORDER BY id DESC LIMIT 5";
$result = $mysqli->query($query);

// Check for query errors
if (!$result) {
    echo "<!-- Error: " . htmlspecialchars($mysqli->error) . " -->";
}

// Process students if found
if ($result && $result->num_rows > 0) {
    echo "<!-- Found " . $result->num_rows . " students -->";
    
    while ($row = $result->fetch_assoc()) {
        // Add student data to array
        $recent_students[] = [
            'id' => $row['id'],
            'student_name' => !empty($row['full_name']) ? $row['full_name'] : 
                             (!empty($row['first_name']) && !empty($row['last_name']) ? 
                              $row['first_name'] . ' ' . $row['last_name'] : 
                              (!empty($row['name']) ? $row['name'] : 'Student #' . $row['id'])),
            'registration_number' => !empty($row['registration_number']) ? 
                                    $row['registration_number'] : 
                                    'ACE-' . date('Y') . '-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
            'class' => !empty($row['class']) ? $row['class'] : 
                      (!empty($row['grade']) ? $row['grade'] : 'N/A')
        ];
    }
} else {
    echo "<!-- No students found in the database -->";
}

// Output the HTML
?>
<?php if (!empty($recent_students)): ?>
    <?php foreach ($recent_students as $student): ?>
        <div class="student-item">
            <div class="student-avatar">
                <?php 
                $initials = '';
                $student_name = isset($student['student_name']) ? trim($student['student_name']) : '';
                
                if (!empty($student_name)) {
                    $name_parts = explode(' ', $student_name);
                    if (count($name_parts) >= 2) {
                        $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                    } else {
                        $initials = strtoupper(substr($student_name, 0, 1));
                    }
                }
                
                echo !empty($initials) ? htmlspecialchars($initials) : 'S';
                ?>
            </div>
            <div class="student-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="student-name">
                        <?php echo htmlspecialchars($student['student_name']); ?>
                    </div>
                    <span class="student-class">
                        <?php echo htmlspecialchars($student['class']); ?>
                    </span>
                </div>
                <div class="student-reg">
                    <strong>Reg. No:</strong> <?php echo htmlspecialchars($student['registration_number']); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="text-center py-4">
        <i class="bi bi-info-circle text-primary" style="font-size: 2rem;"></i>
        <p class="mt-2 mb-0">No registered students found</p>
    </div>
<?php endif; ?> 