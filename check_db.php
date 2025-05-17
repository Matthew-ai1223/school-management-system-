<?php
// Script to check database structure
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'ace_model_college';

// Create a direct connection
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Database Structure Check</h1>";

// Check students table structure
echo "<h2>Students Table Structure</h2>";
$result = $conn->query("DESCRIBE students");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Check files table structure
echo "<h2>Files Table Structure</h2>";
$result = $conn->query("DESCRIBE files");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Check for actual file data in the files table
echo "<h2>Files Table Data Sample</h2>";
$result = $conn->query("SELECT * FROM files LIMIT 5");
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1'>";
        
        // Get field names
        $first_row = $result->fetch_assoc();
        $result->data_seek(0);
        
        echo "<tr>";
        foreach (array_keys($first_row) as $field) {
            echo "<th>" . $field . "</th>";
        }
        echo "</tr>";
        
        // Output data
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . $value . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No data found in files table.";
    }
} else {
    echo "Error: " . $conn->error;
}

// Check for image-related fields in students table
echo "<h2>Students with Image Data</h2>";
$image_fields = [
    'file', 
    'profile_picture', 
    'photo', 
    'student_photo', 
    'image', 
    'picture', 
    'avatar',
    'passport'
];

$image_queries = [];
foreach ($image_fields as $field) {
    $image_queries[] = "COLUMN_NAME = '$field'";
}

// Check if these columns exist
$check_fields_sql = "
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'students' 
    AND (" . implode(" OR ", $image_queries) . ")";

$result = $conn->query($check_fields_sql);
if ($result) {
    echo "<h3>Image-related fields found in students table:</h3>";
    
    if ($result->num_rows > 0) {
        $found_fields = [];
        while ($row = $result->fetch_assoc()) {
            $found_fields[] = $row['COLUMN_NAME'];
        }
        
        // Query students with any of these fields having values
        if (!empty($found_fields)) {
            echo "<ul>";
            foreach ($found_fields as $field) {
                echo "<li>" . $field . "</li>";
            }
            echo "</ul>";
            
            $conditions = [];
            foreach ($found_fields as $field) {
                $conditions[] = "($field IS NOT NULL AND $field != '')";
            }
            
            $sample_query = "
                SELECT id, registration_number, " . implode(", ", $found_fields) . "
                FROM students
                WHERE " . implode(" OR ", $conditions) . "
                LIMIT 10";
                
            $sample_result = $conn->query($sample_query);
            if ($sample_result && $sample_result->num_rows > 0) {
                echo "<h3>Sample students with image data:</h3>";
                echo "<table border='1'>";
                
                // Get field names
                $first_row = $sample_result->fetch_assoc();
                $sample_result->data_seek(0);
                
                echo "<tr>";
                foreach (array_keys($first_row) as $field) {
                    echo "<th>" . $field . "</th>";
                }
                echo "</tr>";
                
                // Output data
                while ($row = $sample_result->fetch_assoc()) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . $value . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "No students found with image data in the identified fields.";
            }
        }
    } else {
        echo "None of the common image fields were found in the students table.";
    }
} else {
    echo "Error checking for image fields: " . $conn->error;
}

// Check uploads directory
echo "<h2>Uploads Directory Check</h2>";
$upload_paths = [
    $_SERVER['DOCUMENT_ROOT'] . '/backends/uploads/student_files/',
    $_SERVER['DOCUMENT_ROOT'] . '/uploads/student_files/',
    dirname(__FILE__) . '/backends/uploads/student_files/',
    dirname(__FILE__) . '/uploads/student_files/',
    './backends/uploads/student_files/',
    './uploads/student_files/'
];

foreach ($upload_paths as $path) {
    echo "<h3>Checking path: " . $path . "</h3>";
    
    if (is_dir($path)) {
        echo "Directory exists!<br>";
        $files = scandir($path);
        $image_files = array_filter($files, function($file) {
            return $file != '.' && $file != '..' && 
                   (strpos($file, '.jpg') !== false || 
                    strpos($file, '.jpeg') !== false || 
                    strpos($file, '.png') !== false || 
                    strpos($file, '.gif') !== false || 
                    strpos($file, '.webp') !== false);
        });
        
        if (!empty($image_files)) {
            echo "Found " . count($image_files) . " image files.<br>";
            echo "<ul>";
            foreach ($image_files as $file) {
                echo "<li>" . $file . " (" . filesize($path . $file) . " bytes)</li>";
            }
            echo "</ul>";
        } else {
            echo "No image files found in this directory.<br>";
        }
    } else {
        echo "Directory does not exist.<br>";
    }
}

echo "<p>End of report.</p>";
?> 