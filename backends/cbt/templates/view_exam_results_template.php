<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Exam Results - ACE MODEL COLLEGE</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.4/css/buttons.bootstrap4.min.css">
    
    <!-- Chart.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.css">
    
    <style>
        body {
            background-color: #f8f9fc;
        }
        
        .navbar {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            padding: 1rem;
        }
        
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            border: none;
            border-radius: 0.35rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            border-left: 4px solid #4e73df;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.passed {
            border-left-color: #1cc88a;
        }
        
        .stat-card.failed {
            border-left-color: #e74a3b;
        }
        
        .stat-card.average {
            border-left-color: #36b9cc;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1.5rem;
        }
        
        .table th {
            background-color: #f8f9fc;
        }
        
        .badge {
            padding: 0.5em 0.75em;
        }
        
        .btn-export {
            transition: all 0.2s;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-laptop-code mr-2"></i> ACE MODEL COLLEGE - CBT System
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-user-circle mr-1"></i> <?php echo $teacherName; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="dashboard.php">
                            <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if ($exam): ?>
            <!-- Exam Details -->
            <div class="card mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php echo htmlspecialchars($exam['title']); ?> - Results
                    </h6>
                    <form method="post" class="d-inline">
                        <button type="submit" name="export_results" class="btn btn-sm btn-success btn-export">
                            <i class="fas fa-file-export mr-1"></i> Export Results
                        </button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card stat-card mb-3">
                                <div class="card-body">
                                    <h6 class="text-muted">Total Students</h6>
                                    <h3><?php echo $statistics['total_students']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card passed mb-3">
                                <div class="card-body">
                                    <h6 class="text-muted">Passed</h6>
                                    <h3><?php echo $statistics['passed']; ?></h3>
                                    <small class="text-success">
                                        <?php echo round(($statistics['passed'] / $statistics['total_students']) * 100, 1); ?>%
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card failed mb-3">
                                <div class="card-body">
                                    <h6 class="text-muted">Failed</h6>
                                    <h3><?php echo $statistics['failed']; ?></h3>
                                    <small class="text-danger">
                                        <?php echo round(($statistics['failed'] / $statistics['total_students']) * 100, 1); ?>%
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card average mb-3">
                                <div class="card-body">
                                    <h6 class="text-muted">Average Score</h6>
                                    <h3><?php echo round($statistics['average_score'], 1); ?>%</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Score Distribution Chart -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="m-0 font-weight-bold text-primary">Score Distribution</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="scoreDistributionChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="m-0 font-weight-bold text-primary">Pass/Fail Distribution</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="passFailChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Results Table -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Student Results</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="resultsTable">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Registration Number</th>
                                            <th>Class</th>
                                            <th>Score</th>
                                            <th>Status</th>
                                            <th>Completion Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $result): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($result['registration_number']); ?></td>
                                                <td><?php echo htmlspecialchars($result['class']); ?></td>
                                                <td><?php echo number_format($result['score'], 1); ?>%</td>
                                                <td>
                                                    <?php if ($result['score'] >= $result['passing_score']): ?>
                                                        <span class="badge badge-success">Passed</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Failed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y H:i', strtotime($result['completed_at'])); ?></td>
                                                <td>
                                                    <a href="view_student_answers.php?exam_id=<?php echo $examId; ?>&student_id=<?php echo $result['student_id']; ?>" 
                                                       class="btn btn-sm btn-info" title="View Answers">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Select Exam Form -->
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Select Exam to View Results</h6>
                </div>
                <div class="card-body">
                    <form method="get" class="form-inline">
                        <div class="form-group mr-3">
                            <label for="exam_id" class="mr-2">Select Exam:</label>
                            <select name="exam_id" id="exam_id" class="form-control" required>
                                <option value="">Choose an exam...</option>
                                <?php
                                $query = "SELECT id, title, subject, class FROM cbt_exams WHERE is_active = 1";
                                if ($teacherRole !== 'class_teacher') {
                                    $query .= " AND teacher_id = :teacher_id";
                                }
                                $query .= " ORDER BY created_at DESC";
                                
                                $stmt = $pdo->prepare($query);
                                if ($teacherRole !== 'class_teacher') {
                                    $stmt->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
                                }
                                $stmt->execute();
                                
                                while ($row = $stmt->fetch()) {
                                    echo '<option value="' . $row['id'] . '">' . 
                                         htmlspecialchars($row['title'] . ' (' . $row['subject'] . ' - ' . $row['class'] . ')') . 
                                         '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search mr-1"></i> View Results
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.4/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>

    <?php if ($exam && !empty($statistics)): ?>
    <script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#resultsTable').DataTable({
            "order": [[3, "desc"]], // Sort by score
            "pageLength": 25,
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ]
        });
        
        // Score Distribution Chart
        var scoreCtx = document.getElementById('scoreDistributionChart').getContext('2d');
        new Chart(scoreCtx, {
            type: 'bar',
            data: {
                labels: ['90-100%', '80-89%', '70-79%', '60-69%', '50-59%', '0-49%'],
                datasets: [{
                    label: 'Number of Students',
                    data: [
                        <?php echo $statistics['score_distribution']['90-100']; ?>,
                        <?php echo $statistics['score_distribution']['80-89']; ?>,
                        <?php echo $statistics['score_distribution']['70-79']; ?>,
                        <?php echo $statistics['score_distribution']['60-69']; ?>,
                        <?php echo $statistics['score_distribution']['50-59']; ?>,
                        <?php echo $statistics['score_distribution']['0-49']; ?>
                    ],
                    backgroundColor: [
                        '#1cc88a',
                        '#36b9cc',
                        '#4e73df',
                        '#f6c23e',
                        '#e74a3b',
                        '#858796'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    }]
                }
            }
        });
        
        // Pass/Fail Chart
        var passFailCtx = document.getElementById('passFailChart').getContext('2d');
        new Chart(passFailCtx, {
            type: 'pie',
            data: {
                labels: ['Passed', 'Failed'],
                datasets: [{
                    data: [
                        <?php echo $statistics['passed']; ?>,
                        <?php echo $statistics['failed']; ?>
                    ],
                    backgroundColor: ['#1cc88a', '#e74a3b']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    });
    </script>
    <?php endif; ?>
</body>
</html> 