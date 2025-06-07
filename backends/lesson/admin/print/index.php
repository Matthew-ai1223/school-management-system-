<?php
session_start();
require_once('../../confg.php');

// Fetch students from both morning and afternoon tables
$morning_query = "SELECT s.*, 'morning' as session FROM morning_students s WHERE s.is_active = 1";
$afternoon_query = "SELECT s.*, 'afternoon' as session FROM afternoon_students s WHERE s.is_active = 1";

$morning_result = $conn->query($morning_query);
$afternoon_result = $conn->query($afternoon_query);

$all_students = array();
$departments = array();

while($row = $morning_result->fetch_assoc()) {
    $row['photo'] = '../../student/uploads/' . $row['photo'];
    $all_students[] = $row;
    if (!in_array($row['department'], $departments)) {
        $departments[] = $row['department'];
    }
}
while($row = $afternoon_result->fetch_assoc()) {
    $row['photo'] = '../../student/uploads/' . $row['photo'];
    $all_students[] = $row;
    if (!in_array($row['department'], $departments)) {
        $departments[] = $row['department'];
    }
}
sort($departments); // Sort departments alphabetically
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Student Receipts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .student-card {
            border: 1px solid #ddd;
            padding: 10px;
            margin: 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
            height: 100%;
        }
        .student-card:hover {
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .student-photo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            background-color: #f8f9fa;
        }
        .student-photo.error {
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px dashed #ccc;
        }
        .filters {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .no-results {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
            margin: 20px 0;
        }
        .stats {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .select-all-container {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Select Students to Print Receipts</h2>
            <div class="select-all-container">
                <button type="button" class="btn btn-outline-primary" id="selectAllBtn">
                    Select All Filtered
                </button>
            </div>
        </div>

        <div class="filters">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchInput" 
                           placeholder="Search by name, department, or payment ref...">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Session</label>
                    <select class="form-select" id="sessionFilter">
                        <option value="">All Sessions</option>
                        <option value="morning">Morning</option>
                        <option value="afternoon">Afternoon</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select class="form-select" id="departmentFilter">
                        <option value="">All Departments</option>
                        <?php foreach($departments as $dept): ?>
                            <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="stats mt-3" id="filterStats"></div>
        </div>

        <form action="print_receit.php" method="post">
            <div class="row" id="studentsGrid">
                <?php foreach($all_students as $student): ?>
                <div class="col-md-4 mb-3 student-item" 
                     data-name="<?php echo strtolower($student['fullname']); ?>"
                     data-department="<?php echo strtolower($student['department']); ?>"
                     data-session="<?php echo strtolower($student['session']); ?>"
                     data-ref="<?php echo strtolower($student['payment_reference']); ?>">
                    <div class="student-card">
                        <div class="form-check">
                            <input class="form-check-input student-checkbox" type="checkbox" 
                                   name="selected_students[]" 
                                   value="<?php echo $student['id'] . '_' . $student['session']; ?>"
                                   id="student_<?php echo $student['id']; ?>">
                            <label class="form-check-label" for="student_<?php echo $student['id']; ?>">
                                <img src="<?php echo $student['photo']; ?>" 
                                     class="student-photo mb-2" 
                                     alt="<?php echo $student['fullname']; ?>'s Photo"
                                     onerror="this.onerror=null; this.classList.add('error'); this.src='../../student/uploads/default-user.png';">
                                <h6 class="mb-1"><?php echo $student['fullname']; ?></h6>
                                <p class="mb-1 small">Department: <?php echo $student['department']; ?></p>
                                <p class="mb-1 small">Session: <?php echo ucfirst($student['session']); ?></p>
                                <p class="mb-1 small">Payment Ref: <?php echo $student['payment_reference']; ?></p>
                                <p class="mb-1 small">Amount: <?php echo $student['payment_amount']; ?></p>
                                <p class="mb-0 small">
                                    Status: 
                                    <span class="badge bg-<?php 
                                        echo !$student['is_active'] ? 'danger' : 
                                            (strtotime($student['expiration_date']) < time() ? 'warning' : 'success'); 
                                    ?>">
                                        <?php 
                                        echo !$student['is_active'] ? 'Inactive' : 
                                            (strtotime($student['expiration_date']) < time() ? 'Expired' : 'Active'); 
                                        ?>
                                    </span>
                                </p>
                            </label>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-3 mb-5 position-sticky bottom-0 bg-white py-3 border-top">
                <button type="submit" class="btn btn-primary" id="printBtn" disabled>
                    Print Selected Receipts
                </button>
                <span class="ms-3" id="selectionCount">0 students selected</span>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            const printBtn = document.getElementById('printBtn');
            const selectionCount = document.getElementById('selectionCount');
            const searchInput = document.getElementById('searchInput');
            const sessionFilter = document.getElementById('sessionFilter');
            const departmentFilter = document.getElementById('departmentFilter');
            const studentItems = document.querySelectorAll('.student-item');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const studentsGrid = document.getElementById('studentsGrid');
            const filterStats = document.getElementById('filterStats');
            
            function updateSelection() {
                const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
                printBtn.disabled = selectedCount === 0;
                selectionCount.textContent = `${selectedCount} students selected`;
            }

            function filterStudents() {
                const searchTerm = searchInput.value.toLowerCase();
                const sessionValue = sessionFilter.value.toLowerCase();
                const departmentValue = departmentFilter.value.toLowerCase();
                let visibleCount = 0;
                let totalCount = studentItems.length;

                studentItems.forEach(item => {
                    const name = item.dataset.name;
                    const department = item.dataset.department;
                    const session = item.dataset.session;
                    const ref = item.dataset.ref;
                    
                    const matchesSearch = !searchTerm || 
                        name.includes(searchTerm) || 
                        department.includes(searchTerm) || 
                        ref.includes(searchTerm);
                    const matchesSession = !sessionValue || session === sessionValue;
                    const matchesDepartment = !departmentValue || department === departmentValue;

                    if (matchesSearch && matchesSession && matchesDepartment) {
                        item.style.display = '';
                        visibleCount++;
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Update stats
                filterStats.textContent = `Showing ${visibleCount} of ${totalCount} students`;

                // Show/hide no results message
                let noResultsMsg = studentsGrid.querySelector('.no-results');
                if (visibleCount === 0) {
                    if (!noResultsMsg) {
                        noResultsMsg = document.createElement('div');
                        noResultsMsg.className = 'no-results col-12';
                        noResultsMsg.innerHTML = 'No students found matching your filters';
                        studentsGrid.appendChild(noResultsMsg);
                    }
                } else if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }

            function toggleSelectAllFiltered() {
                const visibleCheckboxes = Array.from(checkboxes).filter(checkbox => 
                    checkbox.closest('.student-item').style.display !== 'none'
                );
                
                const allChecked = visibleCheckboxes.every(checkbox => checkbox.checked);
                
                visibleCheckboxes.forEach(checkbox => {
                    checkbox.checked = !allChecked;
                });
                
                selectAllBtn.textContent = allChecked ? 'Select All Filtered' : 'Deselect All Filtered';
                updateSelection();
            }
            
            // Event Listeners
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelection);
            });

            searchInput.addEventListener('input', filterStudents);
            sessionFilter.addEventListener('change', filterStudents);
            departmentFilter.addEventListener('change', filterStudents);
            selectAllBtn.addEventListener('click', toggleSelectAllFiltered);

            // Initial update
            filterStudents();
            updateSelection();
        });
    </script>
</body>
</html>
