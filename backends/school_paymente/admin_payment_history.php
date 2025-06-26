<?php
require_once 'ctrl/db_config.php';
require_once 'ctrl/view_payments.php';

session_start();

// Initialize PaymentRecords class
$paymentRecords = new PaymentRecords($conn);

// Get filter parameters
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Get payments based on filters
if ($student_id) {
    $payments = $paymentRecords->getStudentPayments($student_id);
} elseif ($start_date && $end_date) {
    $payments = $paymentRecords->getPaymentsByDate($start_date, $end_date);
} else {
    $payments = $paymentRecords->getAllPayments();
}

// Calculate totals based on base_amount
$total_amount = 0;
$total_completed = 0;
$total_pending = 0;

foreach ($payments as $payment) {
    if ($payment['payment_status'] === 'completed') {
        $total_completed += $payment['base_amount'];
    } elseif ($payment['payment_status'] === 'pending') {
        $total_pending += $payment['base_amount'];
    }
    $total_amount += $payment['base_amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .stats-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .table-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }

        .payment-method-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            display: inline-block;
        }

        .method-online {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .method-cash {
            background-color: #d4edda;
            color: #155724;
        }

        .approval-status {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            display: inline-block;
        }

        .approval-under_review {
            background-color: #fff3cd;
            color: #856404;
        }

        .approval-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .approval-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Export Buttons Styling */
        .dt-buttons .btn {
            margin-right: 5px;
        }
        
        .dt-button-collection {
            padding: 8px;
            border-radius: 5px;
        }
        
        .dt-button-collection .dt-button {
            display: block;
            margin: 5px 0;
            padding: 8px 16px;
            width: 100%;
            text-align: left;
        }
        
        .dt-button-collection .dt-button i {
            margin-right: 8px;
            width: 16px;
        }
        
        .buttons-collection {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <h1><i class="fas fa-chart-line"></i> Payment History Dashboard</h1>
            <p class="mb-0">Comprehensive view of all school payments</p>
        </div>

        <div class="container">
            <a href="ctrl/manage_payment_types.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add and Manage Payment Types</a>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-money-bill-wave"></i> Total Base Amount</h5>
                        <h3 class="mb-0">₦<?php echo number_format($total_amount, 2); ?></h3>
                        <small class="text-white-50">(Excluding Service Charges)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-check-circle"></i> Completed Base Amount</h5>
                        <h3 class="mb-0">₦<?php echo number_format($total_completed, 2); ?></h3>
                        <small class="text-white-50">(Excluding Service Charges)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-clock"></i> Pending Base Amount</h5>
                        <h3 class="mb-0">₦<?php echo number_format($total_pending, 2); ?></h3>
                        <small class="text-white-50">(Excluding Service Charges)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <h4 class="mb-3"><i class="fas fa-filter"></i> Filter Payments</h4>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="student_id" class="form-label">Student ID</label>
                    <input type="text" class="form-control" id="student_id" name="student_id" 
                           value="<?php echo htmlspecialchars($student_id); ?>" 
                           placeholder="Enter Student ID">
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="admin_payment_history.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="table-section">
            <h4 class="mb-3"><i class="fas fa-table"></i> Payment Records</h4>
            <div class="table-responsive">
                <table class="table table-hover" id="paymentsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Payment Type</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Base Amount</th>
                            <th>Service Charge</th>
                            <th>Status</th>
                            <th>Approval Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['reference_code']); ?></td>
                                <td><?php echo htmlspecialchars($payment['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($payment['student_name'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_type_name']); ?></td>
                                <td>
                                    <span class="payment-method-badge method-<?php echo $payment['payment_method']; ?>">
                                        <?php echo strtoupper($payment['payment_method']); ?>
                                    </span>
                                </td>
                                <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                <td>₦<?php echo number_format($payment['base_amount'], 2); ?></td>
                                <td>₦<?php echo number_format($payment['service_charge'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($payment['payment_status']); ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['payment_method'] === 'cash' && isset($payment['approval_status'])): ?>
                                        <span class="approval-status approval-<?php echo strtolower($payment['approval_status'] ?? 'under_review'); ?>">
                                            <?php 
                                            switch($payment['approval_status'] ?? 'under_review') {
                                                case 'under_review':
                                                    echo 'Under Review';
                                                    break;
                                                case 'approved':
                                                    echo 'Approved';
                                                    break;
                                                case 'rejected':
                                                    echo 'Rejected';
                                                    break;
                                                default:
                                                    echo 'Under Review';
                                            }
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewDetails('<?php echo $payment['reference_code']; ?>', '<?php echo $payment['payment_method']; ?>')" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($payment['payment_method'] === 'cash' && $payment['receipt_number']): ?>
                                        <button class="btn btn-sm btn-success" onclick="printCashReceipt('<?php echo $payment['receipt_number']; ?>', <?php echo $payment['id']; ?>)" title="Print Receipt">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    <?php else: ?>
                                        <a href="payment_receipt.php?reference=<?php echo urlencode($payment['reference_code']); ?>" 
                                           class="btn btn-sm btn-success" title="Print Receipt" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($payment['payment_status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-primary approve-btn" 
                                                onclick="approvePayment('<?php echo $payment['reference_code']; ?>', '<?php echo $payment['payment_method']; ?>', this)" 
                                                title="Approve Payment">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($payment['payment_method'] === 'cash' && ($payment['approval_status'] ?? 'under_review') === 'under_review'): ?>
                                        <button class="btn btn-sm btn-warning approve-cash-btn" 
                                                onclick="approveCashPayment('<?php echo $payment['reference_code']; ?>', this)" 
                                                title="Approve Cash Payment">
                                            <i class="fas fa-thumbs-up"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="paymentDetailsContent">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables Buttons -->
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <!-- Add SweetAlert2 for better notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let table;
        $(document).ready(function() {
            // Initialize DataTable
            table = $('#paymentsTable').DataTable({
                order: [[7, 'desc']], // Sort by date column by default
                pageLength: 25,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search payments..."
                },
                dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                buttons: [
                    {
                        extend: 'collection',
                        text: '<i class="fas fa-download"></i> Export',
                        className: 'btn-primary',
                        buttons: [
                            {
                                extend: 'excel',
                                text: '<i class="fas fa-file-excel"></i> Excel',
                                className: 'btn-success',
                                exportOptions: {
                                    columns: [0,1,2,3,4,5,6,7,8,9]
                                },
                                title: 'Payment Records - ' + new Date().toLocaleDateString(),
                                customize: function(xlsx) {
                                    var sheet = xlsx.xl.worksheets['sheet1.xml'];
                                    $('row c[r^="E"], row c[r^="F"], row c[r^="G"]', sheet).each(function() {
                                        $(this).attr('s', '60'); // Apply number format
                                    });
                                }
                            },
                            {
                                extend: 'pdf',
                                text: '<i class="fas fa-file-pdf"></i> PDF',
                                className: 'btn-danger',
                                exportOptions: {
                                    columns: [0,1,2,3,4,5,6,7,8,9]
                                },
                                title: 'Payment Records',
                                customize: function(doc) {
                                    doc.content[1].table.widths = Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                                    doc.styles.tableHeader.alignment = 'left';
                                    doc.styles.tableBodyEven.alignment = 'left';
                                    doc.styles.tableBodyOdd.alignment = 'left';
                                }
                            },
                            {
                                extend: 'csv',
                                text: '<i class="fas fa-file-csv"></i> CSV',
                                className: 'btn-info',
                                exportOptions: {
                                    columns: [0,1,2,3,4,5,6,7,8,9]
                                }
                            },
                            {
                                extend: 'print',
                                text: '<i class="fas fa-print"></i> Print',
                                className: 'btn-secondary',
                                exportOptions: {
                                    columns: [0,1,2,3,4,5,6,7,8,9]
                                },
                                customize: function(win) {
                                    $(win.document.body).css('font-size', '10pt');
                                    $(win.document.body).find('table')
                                        .addClass('compact')
                                        .css('font-size', 'inherit');
                                }
                            }
                        ]
                    }
                ]
            });

            // Date range validation
            $('#start_date, #end_date').on('change', function() {
                let startDate = $('#start_date').val();
                let endDate = $('#end_date').val();
                
                if (startDate && endDate) {
                    if (startDate > endDate) {
                        alert('End date must be after start date');
                        $('#end_date').val('');
                    }
                }
            });
        });

        // View payment details
        function viewDetails(reference, method) {
            let modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
            
            // Show modal with loading state
            modal.show();
            
            // Fetch payment details
            fetch(`get_payment_details.php?reference=${reference}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('paymentDetailsContent').innerHTML = `
                        <div class="payment-details">
                            <p><strong>Reference:</strong> ${data.reference_code}</p>
                            <p><strong>Student ID:</strong> ${data.student_id}</p>
                            <p><strong>Student Name:</strong> ${data.student_name || 'N/A'}</p>
                            <p><strong>Payment Type:</strong> ${data.payment_type_name}</p>
                            <p><strong>Method:</strong> ${strtoupper(method)}</p>
                            <p><strong>Amount:</strong> ₦${parseFloat(data.amount).toLocaleString('en-NG', {minimumFractionDigits: 2})}</p>
                            <p><strong>Base Amount:</strong> ₦${parseFloat(data.base_amount).toLocaleString('en-NG', {minimumFractionDigits: 2})}</p>
                            <p><strong>Service Charge:</strong> ₦${parseFloat(data.service_charge).toLocaleString('en-NG', {minimumFractionDigits: 2})}</p>
                            <p><strong>Status:</strong> <span class="status-badge status-${data.payment_status.toLowerCase()}">${data.payment_status}</span></p>
                            <p><strong>Date:</strong> ${new Date(data.payment_date).toLocaleString()}</p>
                        </div>
                    `;
                })
                .catch(error => {
                    document.getElementById('paymentDetailsContent').innerHTML = 
                        '<div class="alert alert-danger">Error loading payment details</div>';
                });
        }

        // Function to approve payment
        function approvePayment(reference, method, button) {
            Swal.fire({
                title: 'Confirm Approval',
                text: `Are you sure you want to approve this ${method} payment?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, approve it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                    // Send approval request
                    fetch('ctrl/approve_payment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'reference=' + encodeURIComponent(reference) + '&method=' + encodeURIComponent(method)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Update the payment status in the table
                            let row = button.closest('tr');
                            let statusCell = row.querySelector('td:nth-child(9)'); // Status column
                            statusCell.innerHTML = '<span class="status-badge status-completed">Completed</span>';
                            
                            // Remove the approve button
                            button.remove();

                            // Update totals using base_amount
                            let baseAmount = parseFloat(data.payment.base_amount);
                            let totalCompletedElement = document.querySelector('.bg-success h3');
                            let totalPendingElement = document.querySelector('.bg-warning h3');
                            
                            let currentCompleted = parseFloat(totalCompletedElement.textContent.replace('₦', '').replace(/,/g, ''));
                            let currentPending = parseFloat(totalPendingElement.textContent.replace('₦', '').replace(/,/g, ''));
                            
                            totalCompletedElement.textContent = '₦' + (currentCompleted + baseAmount).toLocaleString('en-NG', {minimumFractionDigits: 2});
                            totalPendingElement.textContent = '₦' + (currentPending - baseAmount).toLocaleString('en-NG', {minimumFractionDigits: 2});

                            // Show success message
                            Swal.fire(
                                'Approved!',
                                data.message,
                                'success'
                            );
                        } else {
                            throw new Error(data.message || 'Failed to approve payment');
                        }
                    })
                    .catch(error => {
                        // Re-enable button
                        button.disabled = false;
                        button.innerHTML = '<i class="fas fa-check"></i>';
                        
                        // Show error message
                        Swal.fire(
                            'Error!',
                            error.message || 'Failed to approve payment',
                            'error'
                        );
                    });
                }
            });
        }

        // Function to approve cash payment
        function approveCashPayment(reference, button) {
            Swal.fire({
                title: 'Confirm Cash Payment Approval',
                text: 'Are you sure you want to approve this cash payment?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, approve it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                    // Send approval request
                    fetch('ctrl/approve_cash_payment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'reference=' + encodeURIComponent(reference)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Update the approval status in the table
                            let row = button.closest('tr');
                            let approvalStatusCell = row.querySelector('td:nth-child(10)'); // Approval Status column
                            approvalStatusCell.innerHTML = '<span class="approval-status approval-approved">Approved</span>';
                            
                            // Remove the approve button
                            button.remove();

                            // Show success message
                            Swal.fire(
                                'Approved!',
                                data.message,
                                'success'
                            );
                        } else {
                            throw new Error(data.message || 'Failed to approve cash payment');
                        }
                    })
                    .catch(error => {
                        // Re-enable button
                        button.disabled = false;
                        button.innerHTML = '<i class="fas fa-thumbs-up"></i>';
                        
                        // Show error message
                        Swal.fire(
                            'Error!',
                            error.message || 'Failed to approve cash payment',
                            'error'
                        );
                    });
                }
            });
        }

        // Function to print cash receipt
        function printCashReceipt(receiptNumber, paymentId) {
            // Show loading
            const printButton = event.target;
            const originalText = printButton.innerHTML;
            printButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            printButton.disabled = true;

            // Fetch receipt data from cash payment interface
            fetch(`payment_interface_cash.php?generate_receipt=1&receipt_number=${receiptNumber}&payment_id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create a new window for printing
                        const printWindow = window.open('', '_blank', 'width=800,height=600');
                        printWindow.document.write(data.receipt_html);
                        printWindow.document.close();
                        
                        // Wait for content to load then print
                        printWindow.onload = function() {
                            printWindow.print();
                            printWindow.close();
                        };
                    } else {
                        alert('Error generating receipt: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error generating receipt. Please try again.');
                })
                .finally(() => {
                    // Restore button
                    printButton.innerHTML = originalText;
                    printButton.disabled = false;
                });
        }
    </script>
</body>
</html> 