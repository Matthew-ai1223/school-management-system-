<?php
require_once '../../config.php';
require_once '../../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Create staff_biodata table if not exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS staff_biodata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(20) UNIQUE,
    image_path VARCHAR(255),
    certificate_path VARCHAR(255),
    surname VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    other_name VARCHAR(100),
    sex ENUM('Male', 'Female') NOT NULL,
    staff_category ENUM('Teaching', 'Non-Teaching') NOT NULL,
    state_of_origin VARCHAR(100) NOT NULL,
    nationality VARCHAR(100) NOT NULL,
    marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed') NOT NULL,
    religion VARCHAR(50) NOT NULL,
    highest_qualification VARCHAR(100) NOT NULL,
    qualification_date DATE NOT NULL,
    course_of_study VARCHAR(150) NOT NULL,
    employment_type ENUM('Full Time', 'Part Time') NOT NULL,
    joining_date DATE NOT NULL,
    phone_number VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    next_of_kin_name VARCHAR(200),
    next_of_kin_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($create_table_sql) === FALSE) {
    die("Error creating table: " . $conn->error);
}

// Add staff_category column if it doesn't exist
$check_column_sql = "SHOW COLUMNS FROM staff_biodata LIKE 'staff_category'";
$result = $conn->query($check_column_sql);
if ($result->num_rows === 0) {
    $alter_table_sql = "ALTER TABLE staff_biodata 
                       ADD COLUMN staff_category ENUM('Teaching', 'Non-Teaching') NOT NULL 
                       AFTER sex";
    if ($conn->query($alter_table_sql) === FALSE) {
        die("Error adding staff_category column: " . $conn->error);
    }
}

// Add certificate_path column if it doesn't exist
$check_column_sql = "SHOW COLUMNS FROM staff_biodata LIKE 'certificate_path'";
$result = $conn->query($check_column_sql);
if ($result->num_rows === 0) {
    $alter_table_sql = "ALTER TABLE staff_biodata 
                       ADD COLUMN certificate_path VARCHAR(255) 
                       AFTER image_path";
    if ($conn->query($alter_table_sql) === FALSE) {
        die("Error adding certificate_path column: " . $conn->error);
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Generate staff ID (e.g., STAFF_2024_001)
    $year = date('Y');
    $query = "SELECT MAX(CAST(SUBSTRING_INDEX(staff_id, '_', -1) AS UNSIGNED)) as max_id FROM staff_biodata WHERE staff_id LIKE 'STAFF_${year}_%'";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $next_id = str_pad(($row['max_id'] + 1), 3, '0', STR_PAD_LEFT);
    $staff_id = "STAFF_${year}_${next_id}";

    // Handle image upload
    $image_path = '';
    if (isset($_FILES['staff_image']) && $_FILES['staff_image']['error'] == 0) {
        $upload_dir = '../../../uploads/staff_images/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES['staff_image']['name'], PATHINFO_EXTENSION);
        $image_path = $upload_dir . $staff_id . '.' . $file_extension;
        move_uploaded_file($_FILES['staff_image']['tmp_name'], $image_path);
    }

    // Handle certificate upload
    $certificate_path = '';
    if (isset($_FILES['staff_certificate']) && $_FILES['staff_certificate']['error'] == 0) {
        $upload_dir = '../../../uploads/staff_certificates/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES['staff_certificate']['name'], PATHINFO_EXTENSION);
        $certificate_path = $upload_dir . $staff_id . '_certificate.' . $file_extension;
        move_uploaded_file($_FILES['staff_certificate']['tmp_name'], $certificate_path);
    }

    // Prepare and execute INSERT statement
    $stmt = $conn->prepare("INSERT INTO staff_biodata (
        staff_id, image_path, certificate_path, surname, first_name, other_name, sex, 
        staff_category, state_of_origin, nationality, marital_status, religion,
        highest_qualification, qualification_date, course_of_study,
        employment_type, joining_date, phone_number, email, address,
        next_of_kin_name, next_of_kin_phone
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssssssssssssssssssss",
        $staff_id,
        $image_path,
        $certificate_path,
        $_POST['surname'],
        $_POST['first_name'],
        $_POST['other_name'],
        $_POST['sex'],
        $_POST['staff_category'],
        $_POST['state_of_origin'],
        $_POST['nationality'],
        $_POST['marital_status'],
        $_POST['religion'],
        $_POST['highest_qualification'],
        $_POST['qualification_date'],
        $_POST['course_of_study'],
        $_POST['employment_type'],
        $_POST['joining_date'],
        $_POST['phone_number'],
        $_POST['email'],
        $_POST['address'],
        $_POST['next_of_kin_name'],
        $_POST['next_of_kin_phone']
    );

    if ($stmt->execute()) {
        $success_message = '
            <div class="staff-id-container">
                <p class="mb-2"><i class="fas fa-check-circle me-2"></i>Staff biodata saved successfully!</p>
                <div class="staff-id-box">
                    <p class="mb-1">Your Staff ID is:</p>
                    <div class="id-copy-container">
                        <span id="staffId" class="staff-id">' . $staff_id . '</span>
                        <button onclick="copyStaffId()" class="btn btn-sm btn-outline-primary copy-btn">
                            <i class="fas fa-copy"></i> Copy ID
                        </button>
                    </div>
                    <p class="text-danger mt-2">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Please save this ID for future reference!
                    </p>
                </div>
            </div>';
    } else {
        $error_message = "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Biodata Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a2b77;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
            --warning-color: #ffd60a;
            --danger-color: #ef476f;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --border-radius: 10px;
            --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 20px 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            background: white;
            overflow: hidden;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.2);
            transform: translateY(-5px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            border-bottom: none;
            position: relative;
        }

        .card-header h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .card-body {
            padding: 2rem;
        }

        .form-section {
            background: #ffffff;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }

        .form-section:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }

        .form-section h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent-color);
            display: inline-block;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .form-label.required::after {
            content: " *";
            color: var(--danger-color);
            font-weight: bold;
        }

        .form-control, .form-select {
            border: 2px solidrgb(222, 41, 41);
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: var(--transition);
            background-color: #f8f9fa;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(72, 149, 239, 0.25);
            background-color: #ffffff;
        }

        .input-group-text {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius) 0 0 var(--border-radius);
            padding: 0.75rem 1rem;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            margin-top: 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .preview-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--border-radius);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.2);
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
        }

        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1.1rem;
        }

        .alert {
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            box-shadow: 0 4px 6px rgba(21, 87, 36, 0.1);
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            box-shadow: 0 4px 6px rgba(114, 28, 36, 0.1);
        }

        .form-text {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-header {
                padding: 1.5rem;
            }

            .card-header h3 {
                font-size: 1.5rem;
            }

            .card-body {
                padding: 1rem;
            }

            .form-section {
                padding: 1rem;
            }

            .btn-lg {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
            }
        }

        /* Custom animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            animation: fadeIn 0.6s ease-out;
        }

        .form-section {
            animation: fadeIn 0.6s ease-out;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        /* Add these new styles after your existing styles */
        .staff-id-container {
            text-align: left;
        }
        
        .staff-id-box {
            background: rgba(26, 43, 119, 0.05);
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            margin-top: 1rem;
            border-radius: var(--border-radius);
        }
        
        .id-copy-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            margin: 0.5rem 0;
        }
        
        .staff-id {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            font-family: monospace;
            letter-spacing: 1px;
        }
        
        .copy-btn {
            padding: 0.25rem 0.75rem;
            font-size: 0.9rem;
            border-color: var(--primary-color);
            color: var(--primary-color);
            transition: var(--transition);
        }
        
        .copy-btn:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .copy-btn.copied {
            background-color: var(--success-color);
            border-color: var(--success-color);
            color: white;
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center mb-0">Staff Biodata Form</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($success_message)): ?>
                            <?php echo $success_message; ?>
                        <?php endif; ?>

                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <!-- Personal Information -->
                            <div class="row mb-4">
                                <h4 class="mb-3">Personal Information</h4>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Staff Image</label>
                                    <input type="file" class="form-control" name="staff_image" accept="image/*" required>
                                    <div id="imagePreview" class="preview-image"></div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Surname</label>
                                    <input type="text" class="form-control" name="surname" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">First Name</label>
                                    <input type="text" class="form-control" name="first_name" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Other Name</label>
                                    <input type="text" class="form-control" name="other_name">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Sex</label>
                                    <select class="form-select" name="sex" required>
                                        <option value="">Select Sex</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Staff Category</label>
                                    <select class="form-select" name="staff_category" required>
                                        <option value="">Select Staff Category</option>
                                        <option value="Teaching">Teaching Staff</option>
                                        <option value="Non-Teaching">Non-Teaching Staff</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">State of Origin</label>
                                    <input type="text" class="form-control" name="state_of_origin" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Nationality</label>
                                    <input type="text" class="form-control" name="nationality" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Marital Status</label>
                                    <select class="form-select" name="marital_status" required>
                                        <option value="">Select Marital Status</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Religion</label>
                                    <input type="text" class="form-control" name="religion" required>
                                </div>
                            </div>

                            <!-- Educational Information -->
                            <div class="row mb-4">
                                <h4 class="mb-3">Educational Information</h4>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Highest Qualification</label>
                                    <input type="text" class="form-control" name="highest_qualification" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Qualification Date</label>
                                    <input type="date" class="form-control" name="qualification_date" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Course of Study</label>
                                    <input type="text" class="form-control" name="course_of_study" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Certificate Upload</label>
                                    <input type="file" class="form-control" name="staff_certificate" accept=".pdf,.jpg,.jpeg,.png" required>
                                    <div class="form-text">Upload your highest qualification certificate (PDF, JPG, JPEG, or PNG)</div>
                                </div>
                            </div>

                            <!-- Employment Information -->
                            <div class="row mb-4">
                                <h4 class="mb-3">Employment Information</h4>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Employment Type</label>
                                    <select class="form-select" name="employment_type" required>
                                        <option value="">Select Employment Type</option>
                                        <option value="Full Time">Full Time</option>
                                        <option value="Part Time">Part Time</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Joining Date</label>
                                    <input type="date" class="form-control" name="joining_date" required>
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div class="row mb-4">
                                <h4 class="mb-3">Contact Information</h4>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone_number" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Address</label>
                                    <textarea class="form-control" name="address" required></textarea>
                                </div>
                            </div>

                            <!-- Next of Kin Information -->
                            <div class="row mb-4">
                                <h4 class="mb-3">Next of Kin Information</h4>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Next of Kin Name</label>
                                    <input type="text" class="form-control" name="next_of_kin_name" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Next of Kin Phone</label>
                                    <input type="tel" class="form-control" name="next_of_kin_phone" required>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Save Staff Biodata
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image preview
        document.querySelector('input[name="staff_image"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').innerHTML = `
                        <img src="${e.target.result}" class="preview-image">
                    `;
                }
                reader.readAsDataURL(file);
            }
        });

        // Form validation
        (function () {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Add this new function after your existing scripts
        function copyStaffId() {
            const staffId = document.getElementById('staffId').textContent;
            const copyBtn = document.querySelector('.copy-btn');
            
            // Create a temporary textarea element to copy the text
            const textarea = document.createElement('textarea');
            textarea.value = staffId;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            // Update button text and style
            const originalHTML = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            copyBtn.classList.add('copied');
            
            // Show a tooltip or notification
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--success-color);
                color: white;
                padding: 1rem;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                z-index: 1000;
                animation: fadeIn 0.3s ease-in, fadeOut 0.3s ease-out 2s forwards;
            `;
            notification.innerHTML = '<i class="fas fa-check-circle me-2"></i>Staff ID copied to clipboard!';
            document.body.appendChild(notification);
            
            // Reset button after 2 seconds
            setTimeout(() => {
                copyBtn.innerHTML = originalHTML;
                copyBtn.classList.remove('copied');
            }, 2000);
            
            // Remove notification after 2.5 seconds
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 2500);
        }
    </script>
</body>
</html>
