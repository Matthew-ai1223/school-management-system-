<?php
// Database connection details
$host = 'localhost';
$username = 'root';  // Default XAMPP username
$password = '';      // Default XAMPP password

echo "<h2>Database Setup & Fix Tool</h2>";

try {
    // First connect without specifying a database
    $conn = new mysqli($host, $username, $password);

    // Check connection
    if ($conn->connect_error) {
        die("<p style='color: red;'>Connection failed: " . $conn->connect_error . "</p>");
    }
    
    echo "<p>Connected to MySQL server successfully.</p>";
    
    // Get all databases
    $dbQuery = "SHOW DATABASES";
    $dbResult = $conn->query($dbQuery);
    
    $databases = [];
    while ($row = $dbResult->fetch_assoc()) {
        $databases[] = $row['Database'];
    }
    
    // Check if a specific database was selected
    $selectedDB = isset($_GET['db']) ? $_GET['db'] : '';
    $dbExists = in_array($selectedDB, $databases);
    
    echo "<h3>Available Databases:</h3>";
    echo "<ul>";
    foreach ($databases as $db) {
        echo "<li>" . $db . " <a href='?db=" . $db . "'>Select</a></li>";
    }
    echo "</ul>";
    
    // Option to create a new database
    echo "<h3>Create New Database:</h3>";
    echo "<form method='GET' action=''>";
    echo "<input type='text' name='create_db' placeholder='Enter database name' required>";
    echo "<input type='submit' value='Create Database'>";
    echo "</form>";
    
    // Create database if requested
    if (isset($_GET['create_db']) && !empty($_GET['create_db'])) {
        $newDbName = $_GET['create_db'];
        $createDbQuery = "CREATE DATABASE `$newDbName`";
        
        if ($conn->query($createDbQuery) === TRUE) {
            echo "<p style='color: green;'>Success: Database '$newDbName' has been created.</p>";
            echo "<p><a href='?db=$newDbName'>Select This Database</a></p>";
            $selectedDB = $newDbName;
            $dbExists = true;
        } else {
            echo "<p style='color: red;'>Error creating database: " . $conn->error . "</p>";
        }
    }
    
    // Work with selected database
    if (!empty($selectedDB) && $dbExists) {
        echo "<h3>Working with database: $selectedDB</h3>";
        
        // Select this database
        $conn->select_db($selectedDB);
        
        // Check for payments table
        $tableQuery = "SHOW TABLES LIKE 'payments'";
        $tableResult = $conn->query($tableQuery);
        
        if ($tableResult->num_rows === 0) {
            echo "<p>The payments table does not exist in this database.</p>";
            echo "<p><a href='?db=$selectedDB&create_table=1'>Create Payments Table</a></p>";
            
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
            echo "<p>The payments table exists in this database.</p>";
            
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
                $existingColumns[$row['Field']] = true;
            }
            
            // Check and add missing columns
            $columnsAdded = false;
            
            foreach ($requiredColumns as $column => $definition) {
                if (!isset($existingColumns[$column])) {
                    $alterQuery = "ALTER TABLE payments ADD COLUMN $column $definition";
                    
                    // For the primary key
                    if ($column === 'id') {
                        $alterQuery .= ", ADD PRIMARY KEY (id)";
                    }
                    
                    if ($conn->query($alterQuery) === TRUE) {
                        echo "<p style='color: green;'>Success: The '$column' column has been added to the payments table.</p>";
                        $columnsAdded = true;
                    } else {
                        echo "<p style='color: red;'>Error adding '$column' column: " . $conn->error . "</p>";
                    }
                }
            }
            
            if (!$columnsAdded) {
                echo "<p>All required columns already exist in the payments table.</p>";
            }
        }
        
        // Create a file with database configuration
        if (isset($_GET['save_config']) && $_GET['save_config'] == 1) {
            $configContent = "<?php
// Database configuration
\$host = '$host';
\$username = '$username';
\$password = '$password';
\$dbname = '$selectedDB';
?>";
            
            $configFile = __DIR__ . "/db_config.php";
            if (file_put_contents($configFile, $configContent) !== false) {
                echo "<p style='color: green;'>Success: Database configuration has been saved to db_config.php.</p>";
            } else {
                echo "<p style='color: red;'>Error: Could not save database configuration file.</p>";
            }
        }
        
        echo "<p><a href='?db=$selectedDB&save_config=1'>Save This Database Configuration</a></p>";
    }

    // Close the connection
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin/dashboard.php'>Return to Dashboard</a></p>";
?> 