<?php
require_once '../../config.php';
require_once '../../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Handle search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$employment_type = isset($_GET['employment_type']) ? $_GET['employment_type'] : '';
$staff_category = isset($_GET['staff_category']) ? $_GET['staff_category'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'surname';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';

// Build the query
$query = "SELECT * FROM staff_biodata WHERE 1=1";

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (staff_id LIKE '%$search%' 
                OR surname LIKE '%$search%' 
                OR first_name LIKE '%$search%' 
                OR email LIKE '%$search%'
                OR phone_number LIKE '%$search%')";
}

if (!empty($employment_type)) {
    $employment_type = $conn->real_escape_string($employment_type);
    $query .= " AND employment_type = '$employment_type'";
}

if (!empty($staff_category)) {
    $staff_category = $conn->real_escape_string($staff_category);
    $query .= " AND staff_category = '$staff_category'";
}

$query .= " ORDER BY $sort_by $sort_order";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Biodata Information</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a2b77;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
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

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 1.5rem;
        }

        .search-box {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow);
        }

        .staff-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: var(--transition);
            height: 100%;
        }

        .staff-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow);
        }

        .staff-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }

        .staff-info {
            padding: 1rem;
        }

        .staff-id {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .btn-view {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            color: white;
        }

        .modal-content {
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
        }

        .modal-body {
            padding: 2rem;
        }

        .detail-label {
            font-weight: 600;
            color: var(--primary-color);
        }

        .employment-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .employment-badge.full-time {
            background-color: #28a745;
            color: white;
        }

        .employment-badge.part-time {
            background-color: #ffc107;
            color: black;
        }

        .sort-icon {
            cursor: pointer;
            margin-left: 0.5rem;
        }

        .sort-icon:hover {
            color: var(--accent-color);
        }

        .category-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }

        .category-badge.teaching {
            background-color: #4895ef;
            color: white;
        }

        .category-badge.non-teaching {
            background-color: #f72585;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-users me-2"></i>Staff Biodata Information</h3>
            </div>
            <div class="card-body">
                <!-- Search and Filter Section -->
                <div class="search-box">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search by ID, Name, Email, or Phone" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="employment_type" class="form-select">
                                <option value="">All Employment Types</option>
                                <option value="Full Time" <?php echo $employment_type == 'Full Time' ? 'selected' : ''; ?>>Full Time</option>
                                <option value="Part Time" <?php echo $employment_type == 'Part Time' ? 'selected' : ''; ?>>Part Time</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="staff_category" class="form-select">
                                <option value="">All Staff Categories</option>
                                <option value="Teaching" <?php echo $staff_category == 'Teaching' ? 'selected' : ''; ?>>Teaching Staff</option>
                                <option value="Non-Teaching" <?php echo $staff_category == 'Non-Teaching' ? 'selected' : ''; ?>>Non-Teaching Staff</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Staff Cards -->
                <div class="row g-4">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($staff = $result->fetch_assoc()): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="staff-card">
                                    <div class="text-center pt-4">
                                        <img src="<?php echo !empty($staff['image_path']) ? $staff['image_path'] : '../../../images/default-user.png'; ?>" 
                                             alt="Staff Image" 
                                             class="staff-image mb-3">
                                        <div class="staff-id">
                                            <i class="fas fa-id-badge me-1"></i><?php echo htmlspecialchars($staff['staff_id']); ?>
                                        </div>
                                    </div>
                                    <div class="staff-info">
                                        <h5 class="text-center mb-3">
                                            <?php echo htmlspecialchars($staff['surname'] . ' ' . $staff['first_name']); ?>
                                        </h5>
                                        <div class="text-center mb-3">
                                            <span class="employment-badge <?php echo strtolower(str_replace(' ', '-', $staff['employment_type'])); ?>">
                                                <?php echo htmlspecialchars($staff['employment_type']); ?>
                                            </span>
                                            <span class="category-badge <?php echo strtolower(str_replace(' ', '-', $staff['staff_category'])); ?>">
                                                <?php echo htmlspecialchars($staff['staff_category']); ?>
                                            </span>
                                        </div>
                                        <div class="d-grid">
                                            <button type="button" 
                                                    class="btn btn-view" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#staffModal<?php echo $staff['id']; ?>">
                                                <i class="fas fa-eye me-2"></i>View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Staff Details Modal -->
                                <div class="modal fade" id="staffModal<?php echo $staff['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Staff Details</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-4 text-center mb-4">
                                                        <img src="<?php echo !empty($staff['image_path']) ? $staff['image_path'] : '../../../images/default-user.png'; ?>" 
                                                             alt="Staff Image" 
                                                             class="img-fluid rounded-circle mb-3"
                                                             style="width: 150px; height: 150px; object-fit: cover;">
                                                        <div class="staff-id mb-2"><?php echo htmlspecialchars($staff['staff_id']); ?></div>
                                                        <span class="employment-badge <?php echo strtolower(str_replace(' ', '-', $staff['employment_type'])); ?>">
                                                            <?php echo htmlspecialchars($staff['employment_type']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <p><span class="detail-label">Full Name:</span><br>
                                                                <?php echo htmlspecialchars($staff['surname'] . ' ' . $staff['first_name'] . ' ' . $staff['other_name']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><span class="detail-label">Sex:</span><br>
                                                                <?php echo htmlspecialchars($staff['sex']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><span class="detail-label">Email:</span><br>
                                                                <?php echo htmlspecialchars($staff['email']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><span class="detail-label">Phone:</span><br>
                                                                <?php echo htmlspecialchars($staff['phone_number']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><span class="detail-label">Nationality:</span><br>
                                                                <?php echo htmlspecialchars($staff['nationality']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><span class="detail-label">State of Origin:</span><br>
                                                                <?php echo htmlspecialchars($staff['state_of_origin']); ?></p>
                                                            </div>
                                                            <div class="col-12">
                                                                <p><span class="detail-label">Address:</span><br>
                                                                <?php echo htmlspecialchars($staff['address']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><span class="detail-label">Qualification:</span><br>
                                                                <?php echo htmlspecialchars($staff['highest_qualification']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><span class="detail-label">Course of Study:</span><br>
                                                                <?php echo htmlspecialchars($staff['course_of_study']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><span class="detail-label">Joining Date:</span><br>
                                                                <?php echo date('F j, Y', strtotime($staff['joining_date'])); ?></p>
                                                            </div>
                                                            <div class="col-12">
                                                                <hr>
                                                                <h6 class="detail-label">Next of Kin Information</h6>
                                                                <p><span class="detail-label">Name:</span><br>
                                                                <?php echo htmlspecialchars($staff['next_of_kin_name']); ?></p>
                                                                <p><span class="detail-label">Phone:</span><br>
                                                                <?php echo htmlspecialchars($staff['next_of_kin_phone']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><span class="detail-label">Employment Type:</span><br>
                                                                <?php echo htmlspecialchars($staff['employment_type']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><span class="detail-label">Staff Category:</span><br>
                                                                <?php echo htmlspecialchars($staff['staff_category']); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>No staff records found.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
