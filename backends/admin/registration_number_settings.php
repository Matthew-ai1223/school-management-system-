<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();

// Default settings
$settings = [
    'year_format' => 'Y',
    'kiddies_prefix' => 'KID',
    'college_prefix' => 'COL',
    'number_padding' => 4,
    'separator' => '',
    'custom_prefix' => '',
    'next_kid_number' => 1,
    'next_col_number' => 1,
    'suffix' => '',
    'manual_year' => date('Y'),
    'counter_position' => 'end', // Can be 'end' or 'after_prefix'
    'include_month' => 'no', // Can be 'no', 'numeric', 'roman'
    'include_day' => 'no',  // Can be 'no', 'yes'
];

// Check if settings table exists, if not create it
$checkTableQuery = "SHOW TABLES LIKE 'registration_number_settings'";
$tableExists = $mysqli->query($checkTableQuery)->num_rows > 0;

if (!$tableExists) {
    $createTableQuery = "CREATE TABLE registration_number_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_name VARCHAR(50) NOT NULL,
        setting_value VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $mysqli->query($createTableQuery);
    
    // Insert default settings
    foreach ($settings as $name => $value) {
        $query = "INSERT INTO registration_number_settings (setting_name, setting_value) VALUES (?, ?)";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('ss', $name, $value);
        $stmt->execute();
    }
} else {
    // Load existing settings
    $query = "SELECT setting_name, setting_value FROM registration_number_settings";
    $result = $mysqli->query($query);
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    
    // Check for new settings and add if they don't exist
    $existingSettings = [];
    $query = "SELECT setting_name FROM registration_number_settings";
    $result = $mysqli->query($query);
    while ($row = $result->fetch_assoc()) {
        $existingSettings[] = $row['setting_name'];
    }
    
    foreach ($settings as $name => $value) {
        if (!in_array($name, $existingSettings)) {
            $query = "INSERT INTO registration_number_settings (setting_name, setting_value) VALUES (?, ?)";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('ss', $name, $value);
            $stmt->execute();
        }
    }
}

// Get current registration numbers
$kidQuery = "SELECT registration_number FROM students WHERE registration_number LIKE '%KID%' OR registration_number LIKE '%".$settings['kiddies_prefix']."%' ORDER BY id DESC LIMIT 1";
$kidResult = $mysqli->query($kidQuery);
$lastKidNumber = "";
if ($kidResult && $kidResult->num_rows > 0) {
    $lastKidReg = $kidResult->fetch_assoc()['registration_number'];
    $lastKidNumber = $lastKidReg;
    
    // Try to extract the sequential number from the end
    preg_match('/(\d+)$/', $lastKidReg, $matches);
    if (isset($matches[1])) {
        $settings['next_kid_number'] = intval($matches[1]) + 1;
    }
}

$colQuery = "SELECT registration_number FROM students WHERE registration_number LIKE '%COL%' OR registration_number LIKE '%".$settings['college_prefix']."%' ORDER BY id DESC LIMIT 1";
$colResult = $mysqli->query($colQuery);
$lastColNumber = "";
if ($colResult && $colResult->num_rows > 0) {
    $lastColReg = $colResult->fetch_assoc()['registration_number'];
    $lastColNumber = $lastColReg;
    
    // Try to extract the sequential number from the end
    preg_match('/(\d+)$/', $lastColReg, $matches);
    if (isset($matches[1])) {
        $settings['next_col_number'] = intval($matches[1]) + 1;
    }
}

// Get total student counts
$kidCountQuery = "SELECT COUNT(*) as count FROM students WHERE registration_number LIKE '%KID%' OR registration_number LIKE '%".$settings['kiddies_prefix']."%'";
$kidCountResult = $mysqli->query($kidCountQuery);
$kiddiesCount = $kidCountResult ? $kidCountResult->fetch_assoc()['count'] : 0;

$colCountQuery = "SELECT COUNT(*) as count FROM students WHERE registration_number LIKE '%COL%' OR registration_number LIKE '%".$settings['college_prefix']."%'";
$colCountResult = $mysqli->query($colCountQuery);
$collegeCount = $colCountResult ? $colCountResult->fetch_assoc()['count'] : 0;

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        // Update settings
        $newSettings = [
            'year_format' => $_POST['year_format'],
            'kiddies_prefix' => $_POST['kiddies_prefix'],
            'college_prefix' => $_POST['college_prefix'],
            'number_padding' => intval($_POST['number_padding']),
            'separator' => $_POST['separator'],
            'custom_prefix' => $_POST['custom_prefix'],
            'suffix' => $_POST['suffix'] ?? '',
            'manual_year' => $_POST['manual_year'] ?? date('Y'),
            'counter_position' => $_POST['counter_position'] ?? 'end',
            'include_month' => $_POST['include_month'] ?? 'no',
            'include_day' => $_POST['include_day'] ?? 'no',
        ];
        
        foreach ($newSettings as $name => $value) {
            $query = "UPDATE registration_number_settings SET setting_value = ? WHERE setting_name = ?";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('ss', $value, $name);
            $stmt->execute();
        }
        
        $message = "Settings updated successfully!";
        $messageType = "success";
        
        // Reload settings
        foreach ($newSettings as $name => $value) {
            $settings[$name] = $value;
        }
    } elseif (isset($_POST['reset_counter'])) {
        // Reset counters
        $type = $_POST['counter_type'];
        $newValue = intval($_POST['new_counter_value']);
        
        $settingName = ($type === 'kiddies') ? 'next_kid_number' : 'next_col_number';
        
        $query = "UPDATE registration_number_settings SET setting_value = ? WHERE setting_name = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('ss', $newValue, $settingName);
        $stmt->execute();
        
        $settings[$settingName] = $newValue;
        
        $message = ucfirst($type) . " counter reset successfully!";
        $messageType = "success";
    } elseif (isset($_POST['test_generate'])) {
        // Generate test registration number
        $testType = $_POST['test_type'];
        require_once '../utils.php';
        
        $testRegNumber = generateRegistrationNumber($testType);
        
        $message = "Test registration number generated: <strong>" . $testRegNumber . "</strong>";
        $messageType = "info";
        
        // Reset the number back since this was just a test
        $settingName = ($testType === 'kiddies') ? 'next_kid_number' : 'next_col_number';
        $newValue = $settings[$settingName] - 1;
        
        $query = "UPDATE registration_number_settings SET setting_value = ? WHERE setting_name = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('ss', $newValue, $settingName);
        $stmt->execute();
        
        $settings[$settingName] = $newValue;
    } elseif (isset($_POST['batch_update'])) {
        // Batch update existing registration numbers
        $type = $_POST['batch_type'];
        $confirm = isset($_POST['confirm_batch']) && $_POST['confirm_batch'] === 'yes';
        
        if ($confirm) {
            // Get the pattern to search for
            $searchPattern = $type === 'kiddies' ? '%KID%' : '%COL%';
            
            // Get all students of this type
            $query = "SELECT id, registration_number FROM students WHERE registration_number LIKE ? ORDER BY id ASC";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('s', $searchPattern);
            $stmt->execute();
            $students = $stmt->get_result();
            
            $updatedCount = 0;
            $errors = [];
            
            // Start transaction
            $mysqli->begin_transaction();
            
            try {
                // Modified generateRegistrationNumber for batch processing
                require_once '../utils.php';
                
                $counter = 1; // Start counter at 1
                
                while ($student = $students->fetch_assoc()) {
                    // Generate a new registration number based on counter
                    $newNumber = generateRegistrationNumberWithCounter($type, $counter, $settings);
                    
                    // Update the student record
                    $updateQuery = "UPDATE students SET registration_number = ? WHERE id = ?";
                    $updateStmt = $mysqli->prepare($updateQuery);
                    $updateStmt->bind_param('si', $newNumber, $student['id']);
                    
                    if ($updateStmt->execute()) {
                        $updatedCount++;
                    } else {
                        $errors[] = "Failed to update student ID {$student['id']}: " . $mysqli->error;
                    }
                    
                    $counter++;
                }
                
                // Update next counter setting
                $settingName = ($type === 'kiddies') ? 'next_kid_number' : 'next_col_number';
                $newValue = $counter;
                
                $query = "UPDATE registration_number_settings SET setting_value = ? WHERE setting_name = ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param('ss', $newValue, $settingName);
                $stmt->execute();
                
                $settings[$settingName] = $newValue;
                
                // Commit transaction
                $mysqli->commit();
                
                if (!empty($errors)) {
                    $message = "Batch update completed with some errors. Updated $updatedCount records. Errors: " . implode(", ", $errors);
                    $messageType = "warning";
                } else {
                    $message = "Successfully updated $updatedCount student registration numbers.";
                    $messageType = "success";
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                $mysqli->rollback();
                $message = "Error performing batch update: " . $e->getMessage();
                $messageType = "danger";
            }
        } else {
            $message = "Please confirm the batch update by checking the confirmation box.";
            $messageType = "warning";
        }
    }
}

// Helper function for batch updates
function generateRegistrationNumberWithCounter($registrationType, $counter, $settings) {
    // Generate year portion
    $year = '';
    if ($settings['year_format'] === 'Y') {
        $year = date('Y');
    } elseif ($settings['year_format'] === 'y') {
        $year = date('y');
    } elseif ($settings['year_format'] === 'manual') {
        $year = $settings['manual_year'];
    }
    
    // Month formatting
    $month = '';
    if ($settings['include_month'] !== 'no') {
        if ($settings['include_month'] === 'numeric') {
            $month = date('m');
        } elseif ($settings['include_month'] === 'roman') {
            $monthNum = intval(date('m'));
            $romanMonths = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
            $month = $romanMonths[$monthNum - 1];
        }
    }
    
    // Day formatting
    $day = $settings['include_day'] === 'yes' ? date('d') : '';
    
    // Select prefix based on registration type
    $type_prefix = ($registrationType === 'kiddies') ? $settings['kiddies_prefix'] : $settings['college_prefix'];
    
    // Get the separator
    $separator = $settings['separator'];
    
    // Add custom prefix if exists
    $custom_prefix = empty($settings['custom_prefix']) ? '' : $settings['custom_prefix'] . $separator;
    
    // Add suffix if exists
    $suffix = empty($settings['suffix']) ? '' : $separator . $settings['suffix'];
    
    // Pad the number portion
    $padded_number = str_pad($counter, intval($settings['number_padding']), '0', STR_PAD_LEFT);
    
    // Build the registration number based on counter position
    $parts = [];
    
    if (!empty($custom_prefix)) {
        $parts[] = rtrim($custom_prefix, $separator);
    }
    
    if (!empty($year)) {
        $parts[] = $year;
    }
    
    if (!empty($month)) {
        $parts[] = $month;
    }
    
    if (!empty($day)) {
        $parts[] = $day;
    }
    
    if ($settings['counter_position'] === 'after_prefix') {
        $parts[] = $type_prefix;
        $parts[] = $padded_number;
    } else {
        $parts[] = $type_prefix;
        // Add any other parts here if needed
        $parts[] = $padded_number;
    }
    
    if (!empty($suffix)) {
        $parts[] = ltrim($suffix, $separator);
    }
    
    // Join with separator
    $registration_number = empty($separator) 
        ? implode('', $parts) 
        : implode($separator, $parts);
    
    return $registration_number;
}

// Generate sample registration numbers
$year = '';
if ($settings['year_format'] === 'Y') {
    $year = date('Y');
} elseif ($settings['year_format'] === 'y') {
    $year = date('y');
} elseif ($settings['year_format'] === 'manual') {
    $year = $settings['manual_year'];
}
$separator = $settings['separator'];
$customPrefix = empty($settings['custom_prefix']) ? '' : $settings['custom_prefix'] . $separator;
$suffix = empty($settings['suffix']) ? '' : $separator . $settings['suffix'];

$month = '';
if ($settings['include_month'] !== 'no') {
    if ($settings['include_month'] === 'numeric') {
        $month = date('m');
    } elseif ($settings['include_month'] === 'roman') {
        $monthNum = intval(date('m'));
        $romanMonths = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        $month = $romanMonths[$monthNum - 1];
    }
}

$day = $settings['include_day'] === 'yes' ? date('d') : '';

$sampleKidNumber = str_pad($settings['next_kid_number'], intval($settings['number_padding']), '0', STR_PAD_LEFT);
$sampleColNumber = str_pad($settings['next_col_number'], intval($settings['number_padding']), '0', STR_PAD_LEFT);

// Build sample registration numbers
$kidParts = [];
$colParts = [];

if (!empty($customPrefix)) {
    $kidParts[] = rtrim($customPrefix, $separator);
    $colParts[] = rtrim($customPrefix, $separator);
}

if (!empty($year)) {
    $kidParts[] = $year;
    $colParts[] = $year;
}

if (!empty($month)) {
    $kidParts[] = $month;
    $colParts[] = $month;
}

if (!empty($day)) {
    $kidParts[] = $day;
    $colParts[] = $day;
}

if ($settings['counter_position'] === 'after_prefix') {
    $kidParts[] = $settings['kiddies_prefix'];
    $kidParts[] = $sampleKidNumber;
    
    $colParts[] = $settings['college_prefix'];
    $colParts[] = $sampleColNumber;
} else {
    $kidParts[] = $settings['kiddies_prefix'];
    $kidParts[] = $sampleKidNumber;
    
    $colParts[] = $settings['college_prefix'];
    $colParts[] = $sampleColNumber;
}

if (!empty($suffix)) {
    $kidParts[] = ltrim($suffix, $separator);
    $colParts[] = ltrim($suffix, $separator);
}

$sampleKidReg = empty($separator) ? implode('', $kidParts) : implode($separator, $kidParts);
$sampleColReg = empty($separator) ? implode('', $colParts) : implode($separator, $colParts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Number Settings - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
        }
        .sidebar a:hover {
            color: #f8f9fa;
        }
        .main-content {
            padding: 20px;
        }
        .sample-reg {
            font-family: monospace;
            font-size: 1.2rem;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .explanation {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .format-preview {
            background-color: #f8f9fa;
            border-left: 4px solid #5bc0de;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
        }
        .badge-info {
            font-size: 0.8em;
            vertical-align: middle;
            background-color: #17a2b8;
            color: white;
            padding: 0.3em 0.5em;
            border-radius: 0.25rem;
        }
        .text-format {
            font-family: monospace;
            background-color: #eee;
            padding: 0.2em 0.4em;
            border-radius: 0.25rem;
        }
        .format-section {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'include/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Registration Number Settings</h2>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Settings Form -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="mb-0">Registration Number Format</h4>
                                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#formatHelpModal">
                                    <i class="bi bi-question-circle"></i> Format Help
                                </button>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="format-section">
                                        <h5>Basic Format</h5>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="custom_prefix" class="form-label">School Prefix</label>
                                                <input type="text" class="form-control" id="custom_prefix" name="custom_prefix" value="<?php echo htmlspecialchars($settings['custom_prefix']); ?>" maxlength="10">
                                                <div class="form-text explanation">Add school identifier (e.g., "ACE")</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="suffix" class="form-label">Suffix (Optional)</label>
                                                <input type="text" class="form-control" id="suffix" name="suffix" value="<?php echo htmlspecialchars($settings['suffix']); ?>" maxlength="10">
                                                <div class="form-text explanation">Add ending text (e.g., "STU")</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="format-section">
                                        <h5>Date Components</h5>
                                        <div class="mb-3">
                                            <label for="year_format" class="form-label">Year Format</label>
                                            <select class="form-select" id="year_format" name="year_format">
                                                <option value="Y" <?php echo $settings['year_format'] === 'Y' ? 'selected' : ''; ?>>Full Year (e.g., 2024)</option>
                                                <option value="y" <?php echo $settings['year_format'] === 'y' ? 'selected' : ''; ?>>Short Year (e.g., 24)</option>
                                                <option value="manual" <?php echo $settings['year_format'] === 'manual' ? 'selected' : ''; ?>>Manual Year Entry</option>
                                                <option value="none" <?php echo $settings['year_format'] === 'none' ? 'selected' : ''; ?>>Don't Include Year</option>
                                            </select>
                                            <div class="form-text explanation">How the year appears in the registration number</div>
                                        </div>

                                        <div class="mb-3" id="manual_year_container" style="display: <?php echo $settings['year_format'] === 'manual' ? 'block' : 'none'; ?>">
                                            <label for="manual_year" class="form-label">Year Value</label>
                                            <input type="text" class="form-control" id="manual_year" name="manual_year" value="<?php echo $settings['manual_year'] ?? date('Y'); ?>" maxlength="4">
                                            <div class="form-text explanation">Enter the specific year to use in registration numbers</div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="include_month" class="form-label">Include Month</label>
                                                <select class="form-select" id="include_month" name="include_month">
                                                    <option value="no" <?php echo $settings['include_month'] === 'no' ? 'selected' : ''; ?>>No</option>
                                                    <option value="numeric" <?php echo $settings['include_month'] === 'numeric' ? 'selected' : ''; ?>>Numeric (01-12)</option>
                                                    <option value="roman" <?php echo $settings['include_month'] === 'roman' ? 'selected' : ''; ?>>Roman Numerals (I-XII)</option>
                                                </select>
                                                <div class="form-text explanation">Add month to registration number</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="include_day" class="form-label">Include Day</label>
                                                <select class="form-select" id="include_day" name="include_day">
                                                    <option value="no" <?php echo $settings['include_day'] === 'no' ? 'selected' : ''; ?>>No</option>
                                                    <option value="yes" <?php echo $settings['include_day'] === 'yes' ? 'selected' : ''; ?>>Yes (01-31)</option>
                                                </select>
                                                <div class="form-text explanation">Add day to registration number</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="format-section">
                                        <h5>Student Type</h5>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="kiddies_prefix" class="form-label">Kiddies Prefix</label>
                                                <input type="text" class="form-control" id="kiddies_prefix" name="kiddies_prefix" value="<?php echo htmlspecialchars($settings['kiddies_prefix']); ?>" required maxlength="5">
                                                <div class="form-text explanation">Identifier for Kiddies students</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="college_prefix" class="form-label">College Prefix</label>
                                                <input type="text" class="form-control" id="college_prefix" name="college_prefix" value="<?php echo htmlspecialchars($settings['college_prefix']); ?>" required maxlength="5">
                                                <div class="form-text explanation">Identifier for College students</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="format-section">
                                        <h5>Numbering Format</h5>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="number_padding" class="form-label">Number Padding</label>
                                                <select class="form-select" id="number_padding" name="number_padding">
                                                    <option value="2" <?php echo $settings['number_padding'] == 2 ? 'selected' : ''; ?>>2 digits (01-99)</option>
                                                    <option value="3" <?php echo $settings['number_padding'] == 3 ? 'selected' : ''; ?>>3 digits (001-999)</option>
                                                    <option value="4" <?php echo $settings['number_padding'] == 4 ? 'selected' : ''; ?>>4 digits (0001-9999)</option>
                                                    <option value="5" <?php echo $settings['number_padding'] == 5 ? 'selected' : ''; ?>>5 digits (00001-99999)</option>
                                                </select>
                                                <div class="form-text explanation">How many digits in the sequential number</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="counter_position" class="form-label">Counter Position</label>
                                                <select class="form-select" id="counter_position" name="counter_position">
                                                    <option value="end" <?php echo $settings['counter_position'] === 'end' ? 'selected' : ''; ?>>At End (KID0001)</option>
                                                    <option value="after_prefix" <?php echo $settings['counter_position'] === 'after_prefix' ? 'selected' : ''; ?>>After Prefix (KID0001-2024)</option>
                                                </select>
                                                <div class="form-text explanation">Where to place the sequential number</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="separator" class="form-label">Separator</label>
                                        <select class="form-select" id="separator" name="separator">
                                            <option value="" <?php echo $settings['separator'] === '' ? 'selected' : ''; ?>>None (e.g., 2024KID0001)</option>
                                            <option value="-" <?php echo $settings['separator'] === '-' ? 'selected' : ''; ?>>Hyphen (e.g., 2024-KID-0001)</option>
                                            <option value="/" <?php echo $settings['separator'] === '/' ? 'selected' : ''; ?>>Slash (e.g., 2024/KID/0001)</option>
                                            <option value="_" <?php echo $settings['separator'] === '_' ? 'selected' : ''; ?>>Underscore (e.g., 2024_KID_0001)</option>
                                            <option value="." <?php echo $settings['separator'] === '.' ? 'selected' : ''; ?>>Dot (e.g., 2024.KID.0001)</option>
                                        </select>
                                        <div class="form-text explanation">Character to separate parts of the registration number</div>
                                    </div>

                                    <button type="submit" name="update_settings" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Preview and Counter Reset -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4 class="mb-0">Registration Number Preview</h4>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="format-preview">
                                        <div class="d-flex justify-content-between mb-2">
                                            <h5 class="mb-0">Kiddies Format <span class="badge-info"><?php echo $kiddiesCount; ?> students</span></h5>
                                        </div>
                                        <div class="sample-reg mb-2"><?php echo htmlspecialchars($sampleKidReg); ?></div>
                                        <?php if (!empty($lastKidNumber)): ?>
                                            <div class="text-muted">Last issued: <span class="text-format"><?php echo htmlspecialchars($lastKidNumber); ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="format-preview">
                                        <div class="d-flex justify-content-between mb-2">
                                            <h5 class="mb-0">College Format <span class="badge-info"><?php echo $collegeCount; ?> students</span></h5>
                                        </div>
                                        <div class="sample-reg mb-2"><?php echo htmlspecialchars($sampleColReg); ?></div>
                                        <?php if (!empty($lastColNumber)): ?>
                                            <div class="text-muted">Last issued: <span class="text-format"><?php echo htmlspecialchars($lastColNumber); ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h4 class="mb-0">Test Generation</h4>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Generate a test registration number using the current settings without affecting existing numbers.</p>
                                <form method="POST" class="mb-0">
                                    <div class="mb-3">
                                        <label for="test_type" class="form-label">Registration Type</label>
                                        <select class="form-select" id="test_type" name="test_type" required>
                                            <option value="kiddies">Kiddies</option>
                                            <option value="college">College</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="test_generate" class="btn btn-info">
                                        <i class="bi bi-lightning"></i> Generate Test Number
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h4 class="mb-0">Reset Counter</h4>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> Warning: Changing these counters may result in duplicate registration numbers if not done carefully.
                                </div>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="counter_type" class="form-label">Registration Type</label>
                                        <select class="form-select" id="counter_type" name="counter_type" required>
                                            <option value="kiddies">Kiddies</option>
                                            <option value="college">College</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_counter_value" class="form-label">New Counter Value</label>
                                        <input type="number" class="form-control" id="new_counter_value" name="new_counter_value" min="1" value="1" required>
                                        <div class="form-text explanation">The next registration number will start from this value</div>
                                    </div>
                                    <button type="submit" name="reset_counter" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset this counter? This may lead to duplicate registration numbers if not done carefully.');">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset Counter
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Batch Update Tool -->
                <div class="card mt-4 mb-4">
                    <div class="card-header">
                        <h4 class="mb-0">Batch Update Registration Numbers</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i> <strong>Caution:</strong> This will update ALL existing registration numbers for the selected student type using the current format settings.
                            <ul class="mb-0 mt-2">
                                <li>This action cannot be undone</li>
                                <li>It will affect all reports, records, and references to these students</li>
                                <li>Consider backing up your database before proceeding</li>
                            </ul>
                        </div>
                        <form method="POST" class="mb-4">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="batch_type" class="form-label">Student Type</label>
                                    <select class="form-select" id="batch_type" name="batch_type" required>
                                        <option value="kiddies">Kiddies (<?php echo $kiddiesCount; ?> students)</option>
                                        <option value="college">College (<?php echo $collegeCount; ?> students)</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="confirm_batch" name="confirm_batch" value="yes" required>
                                        <label class="form-check-label" for="confirm_batch">
                                            I understand this will change all registration numbers
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="invisible">Submit</label>
                                    <button type="submit" name="batch_update" class="btn btn-warning w-100" onclick="return confirm('WARNING: You are about to update ALL registration numbers for the selected student type. This cannot be undone. Are you absolutely sure you want to proceed?');">
                                        <i class="bi bi-arrow-repeat"></i> Update All Registration Numbers
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Implementation Guide -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Implementation Guide</h4>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#implementationGuide" aria-expanded="false" aria-controls="implementationGuide">
                            <i class="bi bi-info-circle"></i> Toggle Guide
                        </button>
                    </div>
                    <div class="collapse" id="implementationGuide">
                        <div class="card-body">
                            <p>The registration number system is already fully integrated in your application. The <code>generateRegistrationNumber()</code> function in the <code>utils.php</code> file handles all the registration number generation.</p>
                            
                            <h5 class="mt-4 mb-3">How to Use the Function</h5>
                            <pre class="bg-light p-3 rounded">
// Example usage in your code:
$registration_number = generateRegistrationNumber($registrationType);

// Where $registrationType is either 'kiddies' or 'college'
                            </pre>
                            
                            <h5 class="mt-4 mb-3">Implementation Details</h5>
                            <ul>
                                <li>The settings are stored in the <code>registration_number_settings</code> database table</li>
                                <li>Each setting has a name and value</li>
                                <li>The system automatically creates this table if it doesn't exist</li>
                                <li>Default settings are used if specific settings are not found</li>
                                <li>Counter values for each student type are tracked separately</li>
                            </ul>
                            
                            <h5 class="mt-4 mb-3">Current Format Components</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Component</th>
                                            <th>Description</th>
                                            <th>Example</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>School Prefix</td>
                                            <td>Optional identifier for the school</td>
                                            <td><span class="text-format">ACE</span></td>
                                        </tr>
                                        <tr>
                                            <td>Year</td>
                                            <td>Full or short year</td>
                                            <td><span class="text-format">2024</span> or <span class="text-format">24</span></td>
                                        </tr>
                                        <tr>
                                            <td>Month</td>
                                            <td>Numeric (01-12) or Roman (I-XII)</td>
                                            <td><span class="text-format">05</span> or <span class="text-format">V</span></td>
                                        </tr>
                                        <tr>
                                            <td>Day</td>
                                            <td>Day of month (01-31)</td>
                                            <td><span class="text-format">15</span></td>
                                        </tr>
                                        <tr>
                                            <td>Type Prefix</td>
                                            <td>Identifier for student type</td>
                                            <td><span class="text-format">KID</span> or <span class="text-format">COL</span></td>
                                        </tr>
                                        <tr>
                                            <td>Counter</td>
                                            <td>Padded sequential number</td>
                                            <td><span class="text-format">0001</span></td>
                                        </tr>
                                        <tr>
                                            <td>Suffix</td>
                                            <td>Optional identifier at the end</td>
                                            <td><span class="text-format">STU</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Format Help Modal -->
    <div class="modal fade" id="formatHelpModal" tabindex="-1" aria-labelledby="formatHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="formatHelpModalLabel">Registration Number Format Guide</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h5>Format Examples</h5>
                    <p>Here are some common registration number formats that you can create:</p>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Format Style</th>
                                    <th>Example</th>
                                    <th>Settings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Basic</td>
                                    <td><code>KID0001</code>, <code>COL0001</code></td>
                                    <td>
                                        <ul class="mb-0">
                                            <li>Year: None</li>
                                            <li>Prefix: KID/COL</li>
                                            <li>Separator: None</li>
                                        </ul>
                                    </td>
                                </tr>
                                <tr>
                                    <td>With Year</td>
                                    <td><code>2024KID0001</code>, <code>2024COL0001</code></td>
                                    <td>
                                        <ul class="mb-0">
                                            <li>Year: Full (YYYY)</li>
                                            <li>Prefix: KID/COL</li>
                                            <li>Separator: None</li>
                                        </ul>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Separated Parts</td>
                                    <td><code>2024-KID-0001</code>, <code>2024-COL-0001</code></td>
                                    <td>
                                        <ul class="mb-0">
                                            <li>Year: Full (YYYY)</li>
                                            <li>Prefix: KID/COL</li>
                                            <li>Separator: Hyphen (-)</li>
                                        </ul>
                                    </td>
                                </tr>
                                <tr>
                                    <td>School Identifier</td>
                                    <td><code>ACE-2024-KID-0001</code>, <code>ACE-2024-COL-0001</code></td>
                                    <td>
                                        <ul class="mb-0">
                                            <li>School Prefix: ACE</li>
                                            <li>Year: Full (YYYY)</li>
                                            <li>Prefix: KID/COL</li>
                                            <li>Separator: Hyphen (-)</li>
                                        </ul>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Full Date Format</td>
                                    <td><code>2024-05-15-KID-0001</code>, <code>24/05/15/COL/0001</code></td>
                                    <td>
                                        <ul class="mb-0">
                                            <li>Year: Full/Short</li>
                                            <li>Include Month: Yes (Numeric)</li>
                                            <li>Include Day: Yes</li>
                                            <li>Prefix: KID/COL</li>
                                            <li>Separator: Hyphen/Slash</li>
                                        </ul>
                                    </td>
                                </tr>
                                <tr>
                                    <td>With Suffix</td>
                                    <td><code>2024KID0001STU</code>, <code>2024-COL-0001-STU</code></td>
                                    <td>
                                        <ul class="mb-0">
                                            <li>Year: Full (YYYY)</li>
                                            <li>Prefix: KID/COL</li>
                                            <li>Suffix: STU</li>
                                            <li>Separator: None/Hyphen</li>
                                        </ul>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <h5 class="mt-4">Best Practices</h5>
                    <ul>
                        <li>Include the year in registration numbers to avoid number conflicts across years</li>
                        <li>Use a consistent format for all student types</li>
                        <li>Consider using separators for better readability</li>
                        <li>Ensure the format is compatible with your other systems that might use registration numbers</li>
                        <li>Keep the length reasonable - very long registration numbers might be difficult to use</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview registration numbers when settings change
        document.addEventListener('DOMContentLoaded', function() {
            const yearFormatEl = document.getElementById('year_format');
            const kiddiePrefixEl = document.getElementById('kiddies_prefix');
            const collegePrefixEl = document.getElementById('college_prefix');
            const numberPaddingEl = document.getElementById('number_padding');
            const separatorEl = document.getElementById('separator');
            const customPrefixEl = document.getElementById('custom_prefix');
            const suffixEl = document.getElementById('suffix');
            const counterPositionEl = document.getElementById('counter_position');
            const includeMonthEl = document.getElementById('include_month');
            const includeDayEl = document.getElementById('include_day');
            const manualYearEl = document.getElementById('manual_year');
            const manualYearContainer = document.getElementById('manual_year_container');
            
            const sampleKidReg = document.querySelectorAll('.sample-reg')[0];
            const sampleColReg = document.querySelectorAll('.sample-reg')[1];
            
            const formElements = [
                yearFormatEl, kiddiePrefixEl, collegePrefixEl, numberPaddingEl, 
                separatorEl, customPrefixEl, suffixEl, counterPositionEl,
                includeMonthEl, includeDayEl, manualYearEl
            ];
            
            // Toggle manual year field visibility
            if (yearFormatEl) {
                yearFormatEl.addEventListener('change', function() {
                    if (this.value === 'manual') {
                        manualYearContainer.style.display = 'block';
                    } else {
                        manualYearContainer.style.display = 'none';
                    }
                });
            }
            
            formElements.forEach(el => {
                el && el.addEventListener('change', updatePreview);
                el && el.addEventListener('keyup', updatePreview);
            });
            
            function updatePreview() {
                // Get year format
                let year = '';
                if (yearFormatEl && yearFormatEl.value === 'Y') {
                    year = '<?php echo date('Y'); ?>';
                } else if (yearFormatEl && yearFormatEl.value === 'y') {
                    year = '<?php echo date('y'); ?>';
                } else if (yearFormatEl && yearFormatEl.value === 'manual') {
                    year = document.getElementById('manual_year').value;
                }
                
                // Get month format if enabled
                let month = '';
                if (includeMonthEl && includeMonthEl.value !== 'no') {
                    if (includeMonthEl.value === 'numeric') {
                        month = '<?php echo date('m'); ?>';
                    } else if (includeMonthEl.value === 'roman') {
                        month = '<?php 
                            $monthNum = intval(date('m'));
                            $romanMonths = ["I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
                            echo $romanMonths[$monthNum - 1];
                        ?>';
                    }
                }
                
                // Get day format if enabled
                let day = '';
                if (includeDayEl && includeDayEl.value === 'yes') {
                    day = '<?php echo date('d'); ?>';
                }
                
                const kiddiesPrefix = kiddiePrefixEl ? kiddiePrefixEl.value : '<?php echo $settings['kiddies_prefix']; ?>';
                const collegePrefix = collegePrefixEl ? collegePrefixEl.value : '<?php echo $settings['college_prefix']; ?>';
                const separator = separatorEl ? separatorEl.value : '<?php echo $settings['separator']; ?>';
                const customPrefix = customPrefixEl ? customPrefixEl.value : '<?php echo $settings['custom_prefix']; ?>';
                const suffix = suffixEl ? suffixEl.value : '<?php echo $settings['suffix']; ?>';
                const padding = parseInt(numberPaddingEl ? numberPaddingEl.value : '<?php echo $settings['number_padding']; ?>');
                const counterPosition = counterPositionEl ? counterPositionEl.value : '<?php echo $settings['counter_position']; ?>';
                
                const kidNumber = '<?php echo $settings['next_kid_number']; ?>';
                const colNumber = '<?php echo $settings['next_col_number']; ?>';
                
                const paddedKidNumber = kidNumber.toString().padStart(padding, '0');
                const paddedColNumber = colNumber.toString().padStart(padding, '0');
                
                // Build the registration numbers
                let kidParts = [];
                let colParts = [];
                
                if (customPrefix) {
                    kidParts.push(customPrefix);
                    colParts.push(customPrefix);
                }
                
                if (year) {
                    kidParts.push(year);
                    colParts.push(year);
                }
                
                if (month) {
                    kidParts.push(month);
                    colParts.push(month);
                }
                
                if (day) {
                    kidParts.push(day);
                    colParts.push(day);
                }
                
                if (counterPosition === 'after_prefix') {
                    kidParts.push(kiddiesPrefix);
                    kidParts.push(paddedKidNumber);
                    
                    colParts.push(collegePrefix);
                    colParts.push(paddedColNumber);
                } else {
                    kidParts.push(kiddiesPrefix);
                    kidParts.push(paddedKidNumber);
                    
                    colParts.push(collegePrefix);
                    colParts.push(paddedColNumber);
                }
                
                if (suffix) {
                    kidParts.push(suffix);
                    colParts.push(suffix);
                }
                
                if (sampleKidReg) {
                    sampleKidReg.textContent = separator ? kidParts.join(separator) : kidParts.join('');
                }
                
                if (sampleColReg) {
                    sampleColReg.textContent = separator ? colParts.join(separator) : colParts.join('');
                }
            }
        });
    </script>
</body>
</html>