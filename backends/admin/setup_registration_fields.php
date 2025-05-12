<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

// Initialize authentication and database
$auth = new Auth();
$auth->requireRole('admin');
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // First, check if table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'registration_form_fields'")->num_rows > 0;
    
    if ($tableExists) {
        echo "<div style='color: blue; margin: 20px;'>Table 'registration_form_fields' already exists.</div>";
    } else {
        // Read and execute the SQL file
        $sql = file_get_contents(__DIR__ . '/sql/create_registration_form_fields.sql');
        if ($conn->multi_query($sql)) {
            do {
                // Consume all results
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
            
            echo "<div style='color: green; margin: 20px;'>Registration form fields table created successfully!</div>";
        } else {
            throw new Exception("Error creating table: " . $conn->error);
        }
    }

    // Check if default fields already exist
    $result = $conn->query("SELECT COUNT(*) as count FROM registration_form_fields");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Add default fields
        $default_fields = [
            [
                'field_label' => 'First Name',
                'field_type' => 'text',
                'field_order' => 1,
                'required' => 1,
                'registration_type' => 'kiddies'
            ],
            [
                'field_label' => 'Last Name',
                'field_type' => 'text',
                'field_order' => 2,
                'required' => 1,
                'registration_type' => 'kiddies'
            ],
            [
                'field_label' => 'Date of Birth',
                'field_type' => 'date',
                'field_order' => 3,
                'required' => 1,
                'registration_type' => 'kiddies'
            ],
            // Add same fields for college
            [
                'field_label' => 'First Name',
                'field_type' => 'text',
                'field_order' => 1,
                'required' => 1,
                'registration_type' => 'college'
            ],
            [
                'field_label' => 'Last Name',
                'field_type' => 'text',
                'field_order' => 2,
                'required' => 1,
                'registration_type' => 'college'
            ],
            [
                'field_label' => 'Date of Birth',
                'field_type' => 'date',
                'field_order' => 3,
                'required' => 1,
                'registration_type' => 'college'
            ]
        ];

        // Insert default fields
        foreach ($default_fields as $field) {
            $sql = "INSERT INTO registration_form_fields 
                    (field_label, field_type, field_order, required, registration_type) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiss", 
                $field['field_label'],
                $field['field_type'],
                $field['field_order'],
                $field['required'],
                $field['registration_type']
            );
            $stmt->execute();
        }
        
        echo "<div style='color: green; margin: 20px;'>Default fields added successfully!</div>";
    } else {
        echo "<div style='color: blue; margin: 20px;'>Default fields already exist.</div>";
    }

    echo "<div style='margin: 20px;'><a href='registration_form_filed_update.php' class='btn btn-primary'>Go to Form Field Management</a></div>";

} catch (Exception $e) {
    echo "<div style='color: red; margin: 20px;'>Error: " . $e->getMessage() . "</div>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup Registration Fields</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .message { margin: 20px; padding: 10px; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        .info { background-color: #cce5ff; color: #004085; }
    </style>
</head>
<body>
</body>
</html> 