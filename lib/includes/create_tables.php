<?php
require_once 'config.php';

echo "<h2>Database Table Creation</h2>";

// Check if videos table exists
$result = $conn->query("SHOW TABLES LIKE 'videos'");
if ($result->num_rows == 0) {
    echo "<p>Creating videos table...</p>";
    
    $sql = "CREATE TABLE videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        youtube_url VARCHAR(255) NOT NULL,
        video_id VARCHAR(50) NOT NULL,
        description TEXT,
        thumbnail_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✓ Videos table created successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating videos table: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✓ Videos table already exists!</p>";
}

// Check if pdfs table exists
$result = $conn->query("SHOW TABLES LIKE 'pdfs'");
if ($result->num_rows == 0) {
    echo "<p>Creating pdfs table...</p>";
    
    $sql = "CREATE TABLE pdfs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        description TEXT,
        file_size INT,
        downloads INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✓ PDFs table created successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating pdfs table: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✓ PDFs table already exists!</p>";
}

// Check if downloads table exists
$result = $conn->query("SHOW TABLES LIKE 'downloads'");
if ($result->num_rows == 0) {
    echo "<p>Creating downloads table...</p>";
    
    $sql = "CREATE TABLE downloads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pdf_id INT,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pdf_id) REFERENCES pdfs(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✓ Downloads table created successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating downloads table: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✓ Downloads table already exists!</p>";
}

echo "<p><strong>Database setup complete!</strong></p>";
echo "<p><a href='../dashboard.php'>Return to Dashboard</a></p>";
?> 