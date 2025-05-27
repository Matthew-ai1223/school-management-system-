<?php
// Database connection details
$host = 'localhost';
$username = 'root';  // Default XAMPP username
$password = '';      // Default XAMPP password
$dbname = 'ace_school_system';  // Your database name

echo "<h2>Payments Table Fix Tool</h2>";

try {
    // Create connection
    $conn = new mysqli($host, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("<p style='color: red;'>Connection failed: " . $conn->connect_error . "</p>");
    }
    
    echo "<p>Connected to database successfully.</p>";
    
    // First, check if the 'payments' table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'payments'";
    $tableResult = $conn->query($tableCheckQuery);
    
    if ($tableResult->num_rows == 0) {
        echo "<p style='color: red;'>Error: The payments table does not exist in the database.</p>";
        echo "<p>Would you like to create the payments table? <a href='?create_table=1'>Create Table</a></p>";
        
        // Create the table if requested
        if (isset($_GET['create_table']) && $_GET['create_table'] == 1) {
            $createTableQuery = "CREATE TABLE payments (
                id INT(11) NOT NULL AUTO_INCREMENT,
                student_id INT(11) NOT NULL,
                payment_type VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(50) NOT NULL,
                reference_number VARCHAR(100) NULL,
                payment_date DATE NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                notes TEXT NULL,
                created_by INT(11) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id)
            )";
            
            if ($conn->query($createTableQuery) === TRUE) {
                echo "<p style='color: green;'>Success: The payments table has been created.</p>";
            } else {
                echo "<p style='color: red;'>Error creating table: " . $conn->error . "</p>";
            }
        }
    } else {
        echo "<p>The payments table exists in the database.</p>";
        
        // Define columns that should exist in the payments table
        $requiredColumns = [
            'id' => 'INT(11) NOT NULL AUTO_INCREMENT',
            'student_id' => 'INT(11) NOT NULL',
            'payment_type' => 'VARCHAR(50) NOT NULL',
            'amount' => 'DECIMAL(10,2) NOT NULL',
            'payment_method' => 'VARCHAR(50) NOT NULL',
            'reference_number' => 'VARCHAR(100) NULL',
            'payment_date' => 'DATE NOT NULL',
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'pending'",
            'notes' => 'TEXT NULL',
            'created_by' => 'INT(11) NULL',
            'created_at' => 'DATETIME NOT NULL',
            'updated_at' => 'DATETIME NULL'
        ];
        
        // Get the existing columns
        $columnQuery = "SHOW COLUMNS FROM payments";
        $columnResult = $conn->query($columnQuery);
        $existingColumns = [];
        
        while ($row = $columnResult->fetch_assoc()) {
            $existingColumns[$row['Field']] = $row;
        }
        
        // Check and add missing columns
        $columnsAdded = false;
        
        foreach ($requiredColumns as $column => $definition) {
            if (!isset($existingColumns[$column])) {
                $alterQuery = "ALTER TABLE payments ADD COLUMN $column $definition";
                
                if ($conn->query($alterQuery) === TRUE) {
                    echo "<p style='color: green;'>Success: Added missing column '$column' to the payments table.</p>";
                    $columnsAdded = true;
                } else {
                    echo "<p style='color: red;'>Error adding '$column' column: " . $conn->error . "</p>";
                }
            } else {
                // Check if column type matches
                $currentType = strtoupper($existingColumns[$column]['Type']);
                $requiredType = strtoupper(preg_replace('/\s+NOT NULL|\s+NULL/', '', $definition));
                
                if ($currentType !== $requiredType) {
                    $alterQuery = "ALTER TABLE payments MODIFY COLUMN $column $definition";
                    
                    if ($conn->query($alterQuery) === TRUE) {
                        echo "<p style='color: green;'>Success: Updated column type for '$column' in the payments table.</p>";
                        $columnsAdded = true;
                    } else {
                        echo "<p style='color: red;'>Error updating column type for '$column': " . $conn->error . "</p>";
                    }
                }
            }
        }
        
        if (!$columnsAdded) {
            echo "<p style='color: green;'>All required columns exist with correct types in the payments table.</p>";
        }
    }

    // Close the connection
    $conn->close();
    echo "<p>Database connection closed.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin/dashboard.php'>Return to Dashboard</a> | <a href='admin/update_student_payment.php'>Try Payment Page</a></p>";
?> 