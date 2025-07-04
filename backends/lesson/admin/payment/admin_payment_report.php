<?php
include '../../confg.php';

// Add missing columns if they don't exist
$alter_queries = [
    "ALTER TABLE cash_payments ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'cash' AFTER expiration_date"
];

foreach ($alter_queries as $query) {
    try {
        $conn->query($query);
    } catch (Exception $e) {
        // Log error but continue
        error_log("Error executing query: " . $e->getMessage());
    }
}

// Handle approval action
if (isset($_GET['approve']) && !empty($_GET['approve'])) {
    $reference = $_GET['approve'];
    $payment_source = $_GET['source'] ?? 'cash';
    
    if ($payment_source === 'cash') {
        // Approve the payment in cash_payments table
        $stmt = $conn->prepare("UPDATE cash_payments SET is_processed = 1, processed_at = NOW(), processed_by = 'admin' WHERE reference_number = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $stmt->close();
    } else if ($payment_source === 'renewal') {
        // Approve renewal payment in renew_payment table (use reference_number)
        $stmt = $conn->prepare("UPDATE renew_payment SET is_processed = 1, processed_at = NOW(), processed_by = 'admin' WHERE reference_number = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $stmt->close();
    } else {
        // Approve renewal payment in student tables (legacy, fallback)
        $table = $_GET['table'] ?? 'morning_students';
        $stmt = $conn->prepare("UPDATE $table SET is_processed = 1, processed_at = NOW(), processed_by = 'admin' WHERE reg_number = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $stmt->close();
    }
    
    // Redirect to avoid resubmission
    header('Location: admin_payment_report.php?approved=' . urlencode($reference));
    exit;
}

// Filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$payment_type = isset($_GET['payment_type']) ? $_GET['payment_type'] : '';
$session_type = isset($_GET['session_type']) ? $_GET['session_type'] : '';
$payment_source = isset($_GET['payment_source']) ? $_GET['payment_source'] : '';

// Build query based on payment_source filter
if ($payment_source === 'new') {
    // Only cash_payments
    $query = "SELECT 
        cp.reference_number,
        cp.fullname,
        cp.session_type,
        cp.department,
        cp.payment_type,
        cp.payment_amount,
        cp.class,
        cp.school,
        COALESCE(cp.created_at, cp.updated_at) as payment_date,
        cp.expiration_date,
        cp.payment_method,
        'New Registration' as payment_source,
        CASE WHEN cp.is_processed = 1 THEN 'Approved' ELSE 'Pending' END as status,
        cp.is_processed,
        'cash' as source_type,
        'cash_payments' as table_name
    FROM cash_payments cp
    LEFT JOIN reference_numbers rn ON cp.reference_number = rn.reference_number
    WHERE 1=1";
    if ($start_date) {
        $query .= " AND DATE(COALESCE(cp.created_at, cp.updated_at)) >= '$start_date'";
    }
    if ($end_date) {
        $query .= " AND DATE(COALESCE(cp.created_at, cp.updated_at)) <= '$end_date'";
    }
    if ($payment_type) {
        $query .= " AND cp.payment_type = '$payment_type'";
    }
    if ($session_type) {
        $query .= " AND cp.session_type = '$session_type'";
    }
} else if ($payment_source === 'renewal') {
    // Only renew_payment
    $query = "SELECT 
        rp.reference_number,
        rp.fullname,
        rp.session_type,
        rp.department,
        rp.payment_type,
        rp.payment_amount,
        rp.class,
        rp.school,
        COALESCE(rp.created_at, rp.updated_at) as payment_date,
        rp.expiration_date,
        rp.payment_method,
        'Renewal' as payment_source,
        CASE WHEN rp.is_processed = 1 THEN 'Approved' ELSE 'Pending' END as status,
        rp.is_processed,
        'renewal' as source_type,
        'renew_payment' as table_name
    FROM renew_payment rp
    WHERE 1=1";
    if ($start_date) {
        $query .= " AND DATE(COALESCE(rp.created_at, rp.updated_at)) >= '$start_date'";
    }
    if ($end_date) {
        $query .= " AND DATE(COALESCE(rp.created_at, rp.updated_at)) <= '$end_date'";
    }
    if ($payment_type) {
        $query .= " AND rp.payment_type = '$payment_type'";
    }
    if ($session_type) {
        $query .= " AND rp.session_type = '$session_type'";
    }
} else {
    // Both tables (All)
    $query = "SELECT 
        cp.reference_number,
        cp.fullname,
        cp.session_type,
        cp.department,
        cp.payment_type,
        cp.payment_amount,
        cp.class,
        cp.school,
        COALESCE(cp.created_at, cp.updated_at) as payment_date,
        cp.expiration_date,
        cp.payment_method,
        'New Registration' as payment_source,
        CASE WHEN cp.is_processed = 1 THEN 'Approved' ELSE 'Pending' END as status,
        cp.is_processed,
        'cash' as source_type,
        'cash_payments' as table_name
    FROM cash_payments cp
    LEFT JOIN reference_numbers rn ON cp.reference_number = rn.reference_number
    WHERE 1=1";
    if ($start_date) {
        $query .= " AND DATE(COALESCE(cp.created_at, cp.updated_at)) >= '$start_date'";
    }
    if ($end_date) {
        $query .= " AND DATE(COALESCE(cp.created_at, cp.updated_at)) <= '$end_date'";
    }
    if ($payment_type) {
        $query .= " AND cp.payment_type = '$payment_type'";
    }
    if ($session_type) {
        $query .= " AND cp.session_type = '$session_type'";
    }
    $query .= "\nUNION ALL\nSELECT \n    rp.reference_number,\n    rp.fullname,\n    rp.session_type,\n    rp.department,\n    rp.payment_type,\n    rp.payment_amount,\n    rp.class,\n    rp.school,\n    COALESCE(rp.created_at, rp.updated_at) as payment_date,\n    rp.expiration_date,\n    rp.payment_method,\n    'Renewal' as payment_source,\n    CASE WHEN rp.is_processed = 1 THEN 'Approved' ELSE 'Pending' END as status,\n    rp.is_processed,\n    'renewal' as source_type,\n    'renew_payment' as table_name\nFROM renew_payment rp\nWHERE 1=1";
    if ($start_date) {
        $query .= " AND DATE(COALESCE(rp.created_at, rp.updated_at)) >= '$start_date'";
    }
    if ($end_date) {
        $query .= " AND DATE(COALESCE(rp.created_at, rp.updated_at)) <= '$end_date'";
    }
    if ($payment_type) {
        $query .= " AND rp.payment_type = '$payment_type'";
    }
    if ($session_type) {
        $query .= " AND rp.session_type = '$session_type'";
    }
}
$query .= " ORDER BY payment_date DESC";

$result = $conn->query($query);

// Totals
$total_amount = 0;
$total_records = 0;
$payments_by_type = [];
$payments_by_session = [];
$today_payments = ['count' => 0, 'amount' => 0];
$today = date('Y-m-d');

if (
    $result
) {
    while ($row = $result->fetch_assoc()) {
        $total_amount += $row['payment_amount'];
        $total_records++;
        // Count by payment type
        $type = $row['payment_type'];
        if (!isset($payments_by_type[$type])) {
            $payments_by_type[$type] = ['count' => 0, 'amount' => 0];
        }
        $payments_by_type[$type]['count']++;
        $payments_by_type[$type]['amount'] += $row['payment_amount'];
        // Count by session
        $session = $row['session_type'];
        if (!isset($payments_by_session[$session])) {
            $payments_by_session[$session] = ['count' => 0, 'amount' => 0];
        }
        $payments_by_session[$session]['count']++;
        $payments_by_session[$session]['amount'] += $row['payment_amount'];
    }
    // Reset result pointer
    $result->data_seek(0);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Payment Approval Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background: linear-gradient(120deg, #e0eafc 0%, #cfdef3 100%);
            min-height: 100vh;
        }
        .header-bar {
            background: linear-gradient(90deg, #007bff 0%, #00c6ff 100%);
            color: #fff;
            padding: 32px 0 24px 0;
            border-radius: 0 0 24px 24px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            margin-bottom: 32px;
        }
        .header-bar h2 {
            font-weight: 700;
            letter-spacing: 1px;
        }
        .filters {
            background: #fff;
            padding: 20px 24px 10px 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .card {
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .card-body {
            padding: 2rem;
        }
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }
        #paymentsTable {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
        }
        #paymentsTable thead th {
            background: #f7faff;
            color: #007bff;
            font-weight: 600;
            border-bottom: 2px solid #e3e6f0;
        }
        #paymentsTable tbody tr {
            transition: background 0.2s;
        }
        #paymentsTable tbody tr:hover {
            background: #eaf6ff;
        }
        .badge-success {
            background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
            color: #222;
            font-weight: 600;
            font-size: 0.95em;
            border-radius: 8px;
            padding: 6px 14px;
        }
        .badge-warning {
            background: linear-gradient(90deg, #f7971e 0%, #ffd200 100%);
            color: #222;
            font-weight: 600;
            font-size: 0.95em;
            border-radius: 8px;
            padding: 6px 14px;
        }
        .btn-approve {
            background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
            color: #222;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: 6px 18px;
            transition: box-shadow 0.2s, background 0.2s;
        }
        .btn-approve:hover {
            background: linear-gradient(90deg, #38f9d7 0%, #43e97b 100%);
            box-shadow: 0 2px 8px rgba(67,233,123,0.15);
            color: #111;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .header-bar {
                padding: 20px 15px;
                margin-bottom: 20px;
            }
            .header-bar h2 {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            .header-bar p {
                font-size: 0.9rem;
            }
            
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .filters {
                padding: 15px;
                margin-bottom: 20px;
                border: 1px solid #e3e6f0;
            }
            
            .filters h5 {
                font-size: 1rem;
                font-weight: 600;
            }
            
            .filters .row {
                margin: 0;
            }
            
            .filters .col-12,
            .filters .col-md-6,
            .filters .col-lg-3 {
                margin-bottom: 15px;
            }
            
            .filters .form-label {
                font-size: 0.85rem;
                margin-bottom: 5px;
                color: #495057;
            }
            
            .filters .form-control,
            .filters .form-select {
                font-size: 0.9rem;
                padding: 8px 12px;
                border-radius: 6px;
            }
            
            .filters .form-control:focus,
            .filters .form-select:focus {
                border-color: #007bff;
                box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            }
            
            .filters .btn {
                font-size: 0.85rem;
                padding: 8px 16px;
                border-radius: 6px;
                font-weight: 500;
            }
            
            .filters .btn-sm {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            
            .filters .d-flex.gap-2 {
                gap: 0.5rem !important;
            }
            
            /* Collapse button styling */
            .filters .btn-outline-primary {
                border-width: 1px;
                color: #007bff;
                background-color: transparent;
            }
            
            .filters .btn-outline-primary:hover {
                background-color: #007bff;
                color: white;
            }
            
            /* Quick filters styling */
            .quick-filters {
                border-top: 1px solid #e3e6f0;
                padding-top: 10px;
            }
            
            .quick-filters .btn {
                font-size: 0.75rem;
                padding: 4px 8px;
                border-radius: 15px;
                white-space: nowrap;
            }
            
            .quick-filters .btn-outline-primary {
                border-color: #007bff;
                color: #007bff;
            }
            
            .quick-filters .btn-outline-primary:hover,
            .quick-filters .btn-outline-primary.active {
                background-color: #007bff;
                color: white;
            }
            
            .quick-filters .btn-outline-success {
                border-color: #28a745;
                color: #28a745;
            }
            
            .quick-filters .btn-outline-success:hover {
                background-color: #28a745;
                color: white;
            }
            
            /* Touch-friendly styling */
            .touch-friendly {
                min-height: 44px; /* iOS recommended touch target size */
            }
            
            /* Filter collapse animation */
            .collapse {
                transition: all 0.3s ease;
            }
            
            .collapse.show {
                animation: slideDown 0.3s ease;
            }
            
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Summary Cards Mobile */
            .summary-card {
                margin-bottom: 15px;
            }
            
            .summary-card .card-body {
                padding: 1rem;
            }
            
            .summary-card .card-title {
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }
            
            .summary-card .card-text {
                font-size: 0.9rem;
                margin-bottom: 0;
            }
            
            /* Table Mobile Optimization */
            .table-responsive {
                border-radius: 8px;
                border: 1px solid #e3e6f0;
            }
            
            #paymentsTable {
                font-size: 0.85rem;
                margin-bottom: 0;
            }
            
            #paymentsTable thead th {
                padding: 12px 8px;
                font-size: 0.8rem;
                white-space: nowrap;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-bottom: 2px solid #dee2e6;
                position: sticky;
                top: 0;
                z-index: 10;
            }
            
            #paymentsTable tbody td {
                padding: 12px 8px;
                vertical-align: middle;
                border-bottom: 1px solid #f1f3f4;
            }
            
            #paymentsTable tbody tr:hover {
                background-color: #f8f9fa;
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                transition: all 0.2s ease;
            }
            
            /* Mobile Card Layout - Convert table to cards on mobile */
            @media (max-width: 768px) {
                .table-responsive {
                    border: none;
                    background: transparent;
                }
                
                #paymentsTable {
                    display: none; /* Hide table on mobile */
                }
                
                /* Mobile Cards Container */
                .mobile-cards-container {
                    display: block;
                }
                
                .mobile-card {
                    background: white;
                    border-radius: 12px;
                    margin-bottom: 15px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    border: 1px solid #e3e6f0;
                    overflow: hidden;
                    transition: all 0.3s ease;
                }
                
                .mobile-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
                }
                
                .mobile-card-header {
                    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
                    color: white;
                    padding: 12px 15px;
                    font-weight: 600;
                    font-size: 0.9rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .mobile-card-body {
                    padding: 15px;
                }
                
                .mobile-card-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 8px 0;
                    border-bottom: 1px solid #f1f3f4;
                }
                
                .mobile-card-row:last-child {
                    border-bottom: none;
                }
                
                .mobile-card-label {
                    font-weight: 600;
                    color: #495057;
                    font-size: 0.8rem;
                    min-width: 80px;
                }
                
                .mobile-card-value {
                    color: #212529;
                    font-size: 0.85rem;
                    text-align: right;
                    flex: 1;
                    margin-left: 10px;
                }
                
                .mobile-card-actions {
                    padding: 12px 15px;
                    background: #f8f9fa;
                    border-top: 1px solid #e3e6f0;
                    text-align: center;
                }
                
                .mobile-status-badge {
                    display: inline-block;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 0.75rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .mobile-status-badge.approved {
                    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                    color: white;
                }
                
                .mobile-status-badge.pending {
                    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
                    color: white;
                }
                
                .mobile-amount {
                    font-weight: 700;
                    color: #28a745;
                    font-size: 1rem;
                }
                
                .mobile-reference {
                    font-family: 'Courier New', monospace;
                    background: #f8f9fa;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 0.8rem;
                }
                
                .mobile-date {
                    color: #6c757d;
                    font-size: 0.8rem;
                }
                
                .mobile-source-tag {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 12px;
                    font-size: 0.7rem;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                
                .mobile-source-tag.new {
                    background: #e3f2fd;
                    color: #1976d2;
                }
                
                .mobile-source-tag.renewal {
                    background: #f3e5f5;
                    color: #7b1fa2;
                }
            }
            
            /* Hide table on mobile, show cards */
            @media (max-width: 768px) {
                .table-responsive {
                    display: none;
                }
                
                /* Mobile search styling */
                .mobile-search-container {
                    background: white;
                    padding: 15px;
                    border-radius: 12px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    border: 1px solid #e3e6f0;
                }
                
                .mobile-search-container .input-group {
                    border-radius: 8px;
                    overflow: hidden;
                }
                
                .mobile-search-container .form-control {
                    border: none;
                    padding: 12px 15px;
                    font-size: 0.9rem;
                }
                
                .mobile-search-container .input-group-text {
                    background: #f8f9fa;
                    border: none;
                    color: #6c757d;
                }
                
                .mobile-search-container .btn {
                    border: none;
                    background: #f8f9fa;
                    color: #6c757d;
                    padding: 12px 15px;
                }
                
                .mobile-search-container .btn:hover {
                    background: #e9ecef;
                    color: #495057;
                }
                
                /* Card click animation */
                .mobile-card.card-clicked {
                    transform: scale(0.98);
                    transition: transform 0.2s ease;
                }
                
                /* Mobile card loading state */
                .mobile-card.loading {
                    opacity: 0.7;
                    pointer-events: none;
                }
                
                /* Mobile card empty state */
                .mobile-cards-empty {
                    text-align: center;
                    padding: 40px 20px;
                    color: #6c757d;
                }
                
                .mobile-cards-empty i {
                    font-size: 3rem;
                    margin-bottom: 15px;
                    opacity: 0.5;
                }
                
                .mobile-cards-empty h5 {
                    margin-bottom: 10px;
                    color: #495057;
                }
                
                .mobile-cards-empty p {
                    font-size: 0.9rem;
                    margin-bottom: 0;
                }
            }
            
            /* Badge adjustments */
            .badge-success,
            .badge-warning {
                font-size: 0.75rem;
                padding: 4px 8px;
            }
            
            /* Button adjustments */
            .btn-approve {
                font-size: 0.75rem;
                padding: 4px 8px;
                white-space: nowrap;
            }
            
            /* DataTables mobile optimization */
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                margin-bottom: 10px;
            }
            
            .dataTables_wrapper .dataTables_length select,
            .dataTables_wrapper .dataTables_filter input {
                font-size: 0.9rem;
                padding: 4px 8px;
            }
            
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                font-size: 0.8rem;
                margin-top: 10px;
            }
            
            .dataTables_wrapper .dataTables_paginate .paginate_button {
                padding: 4px 8px;
                font-size: 0.8rem;
            }
        }
        
        /* Extra small devices */
        @media (max-width: 576px) {
            .header-bar {
                padding: 15px 10px;
            }
            
            .header-bar h2 {
                font-size: 1.3rem;
            }
            
            .filters {
                padding: 12px;
                margin-bottom: 15px;
            }
            
            .filters h5 {
                font-size: 0.95rem;
            }
            
            .filters .col-12,
            .filters .col-md-6,
            .filters .col-lg-3 {
                width: 100%;
                margin-bottom: 12px;
            }
            
            .filters .form-label {
                font-size: 0.8rem;
                margin-bottom: 4px;
            }
            
            .filters .form-control,
            .filters .form-select {
                font-size: 0.85rem;
                padding: 6px 10px;
            }
            
            .filters .btn {
                font-size: 0.8rem;
                padding: 6px 12px;
            }
            
            .filters .d-flex.gap-2 {
                flex-direction: column;
                gap: 0.5rem !important;
            }
            
            .filters .d-flex.gap-2 .btn {
                width: 100%;
            }
            
            #paymentsTable {
                font-size: 0.8rem;
            }
            
            #paymentsTable thead th,
            #paymentsTable tbody td {
                padding: 6px 4px;
            }
            
            /* Further hide columns on very small screens */
            #paymentsTable th:nth-child(6),
            #paymentsTable td:nth-child(6) {
                display: none;
            }
            
            .card-body {
                padding: 0.75rem;
            }
        }
        
        /* Landscape orientation adjustments */
        @media (max-width: 768px) and (orientation: landscape) {
            .header-bar {
                padding: 15px 0;
                margin-bottom: 15px;
            }
            
            .filters {
                padding: 10px 15px;
            }
            
            .filters .col-md-2 {
                margin-bottom: 10px;
            }
        }
        
        /* Print styles */
        @media print {
            .filters,
            .btn-approve,
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_paginate {
                display: none !important;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="header-bar text-center">
        <h2>Admin Payment Approval Report</h2>
        <p class="mb-0">View, filter, and approve pending payments from both new registrations and renewals. All data is updated in real time.</p>
    </div>
    <div class="container-fluid py-2">
        <?php if (isset($_GET['approved'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Success!</strong> Payment with reference <?php echo htmlspecialchars($_GET['approved']); ?> has been approved.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card summary-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Payments</h5>
                        <p class="card-text">
                            Count: <?php echo $total_records; ?><br>
                            Amount: ₦<?php echo number_format($total_amount, 2); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php foreach ($payments_by_type as $type => $data): ?>
            <div class="col-md-3">
                <div class="card summary-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo ucfirst($type); ?> Payments</h5>
                        <p class="card-text">
                            Count: <?php echo $data['count']; ?><br>
                            Amount: ₦<?php echo number_format($data['amount'], 2); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php foreach ($payments_by_session as $session => $data): ?>
            <div class="col-md-3">
                <div class="card summary-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo ucfirst($session); ?> Session</h5>
                        <p class="card-text">
                            Count: <?php echo $data['count']; ?><br>
                            Amount: ₦<?php echo number_format($data['amount'], 2); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- End Summary Cards -->
        
        <div class="filters mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 text-primary">
                    <i class="fas fa-filter me-2"></i>Filter Options
                </h5>
                <button class="btn btn-sm btn-outline-primary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true" aria-controls="filterCollapse">
                    <i class="fas fa-chevron-up"></i>
                </button>
            </div>
            
            <form method="GET" id="filterForm">
                <div class="collapse show" id="filterCollapse">
                    <div class="row g-3">
                        <!-- Date Range Row -->
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-calendar me-1"></i>Start Date
                            </label>
                            <input type="date" class="form-control form-control-sm" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-calendar me-1"></i>End Date
                            </label>
                            <input type="date" class="form-control form-control-sm" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        
                        <!-- Payment Type Row -->
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-credit-card me-1"></i>Payment Type
                            </label>
                            <select class="form-select form-select-sm" name="payment_type">
                                <option value="">All Types</option>
                                <option value="full" <?php echo $payment_type === 'full' ? 'selected' : ''; ?>>Full Payment</option>
                                <option value="half" <?php echo $payment_type === 'half' ? 'selected' : ''; ?>>Half Payment</option>
                            </select>
                        </div>
                        
                        <!-- Session Type Row -->
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-clock me-1"></i>Session Type
                            </label>
                            <select class="form-select form-select-sm" name="session_type">
                                <option value="">All Sessions</option>
                                <option value="morning" <?php echo $session_type === 'morning' ? 'selected' : ''; ?>>Morning</option>
                                <option value="afternoon" <?php echo $session_type === 'afternoon' ? 'selected' : ''; ?>>Afternoon</option>
                            </select>
                        </div>
                        
                        <!-- Payment Source Row -->
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-database me-1"></i>Payment Source
                            </label>
                            <select class="form-select form-select-sm" name="payment_source">
                                <option value="">All Sources</option>
                                <option value="new" <?php echo $payment_source === 'new' ? 'selected' : ''; ?>>New Registration</option>
                                <option value="renewal" <?php echo $payment_source === 'renewal' ? 'selected' : ''; ?>>Renewal</option>
                            </select>
                        </div>
                        
                        <!-- Action Buttons Row -->
                        <div class="col-12 col-md-6 col-lg-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                <i class="fas fa-search me-1"></i>Apply Filters
                            </button>
                            <a href="admin_payment_report.php" class="btn btn-outline-secondary btn-sm flex-fill">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card">
            <div class="card-body">
                <!-- Desktop Table -->
                <div class="table-responsive">
                <table id="paymentsTable" class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference/Reg No</th>
                            <th>Name</th>
                            <th>Session</th>
                            <th>Department</th>
                            <th>Payment Type</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Expiry Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result): while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['reference_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                            <td><?php echo ucfirst($row['session_type']); ?></td>
                            <td><?php echo ucfirst($row['department']); ?></td>
                            <td><?php echo ucfirst($row['payment_type']); ?></td>
                            <td>₦<?php echo number_format($row['payment_amount'], 2); ?></td>
                            <td><?php echo isset($row['payment_method']) ? ucfirst($row['payment_method']) : '-'; ?></td>
                            <td><?php echo $row['payment_source']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $row['status'] === 'Approved' ? 'success' : 'warning'; ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td><?php echo $row['expiration_date']; ?></td>
                            <td>
                                <?php if ($row['status'] === 'Pending'): ?>
                                    <a href="?approve=<?php echo urlencode($row['reference_number']); ?>&source=<?php echo $row['source_type']; ?>&table=<?php echo $row['table_name']; ?>" 
                                       class="btn btn-approve btn-sm" 
                                       onclick="return confirm('Approve this payment?');">Approve</a>
                                <?php else: ?>
                                    <span class="text-success">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
                </div>
                
                <!-- Mobile Cards -->
                <div class="mobile-cards-container" style="display: none;">
                    <?php 
                    if ($result): 
                        $result->data_seek(0); // Reset result pointer
                        while ($row = $result->fetch_assoc()): 
                    ?>
                    <div class="mobile-card">
                        <div class="mobile-card-header">
                            <div>
                                <i class="fas fa-calendar me-2"></i>
                                <?php echo date('M d, Y H:i', strtotime($row['payment_date'])); ?>
                            </div>
                            <span class="mobile-status-badge <?php echo strtolower($row['status']); ?>">
                                <?php echo $row['status']; ?>
                            </span>
                        </div>
                        
                        <div class="mobile-card-body">
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Reference:</span>
                                <span class="mobile-card-value mobile-reference"><?php echo htmlspecialchars($row['reference_number']); ?></span>
                            </div>
                            
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Name:</span>
                                <span class="mobile-card-value"><?php echo htmlspecialchars($row['fullname']); ?></span>
                            </div>
                            
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Session:</span>
                                <span class="mobile-card-value"><?php echo ucfirst($row['session_type']); ?></span>
                            </div>
                            
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Department:</span>
                                <span class="mobile-card-value"><?php echo ucfirst($row['department']); ?></span>
                            </div>
                            
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Payment Type:</span>
                                <span class="mobile-card-value"><?php echo ucfirst($row['payment_type']); ?></span>
                            </div>
                            
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Amount:</span>
                                <span class="mobile-card-value mobile-amount">₦<?php echo number_format($row['payment_amount'], 2); ?></span>
                            </div>
                            
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Payment Method:</span>
                                <span class="mobile-card-value"><?php echo isset($row['payment_method']) ? ucfirst($row['payment_method']) : '-'; ?></span>
                            </div>
                            
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Source:</span>
                                <span class="mobile-card-value">
                                    <span class="mobile-source-tag <?php echo strtolower(str_replace(' ', '', $row['payment_source'])); ?>">
                                        <?php echo $row['payment_source']; ?>
                                    </span>
                                </span>
                            </div>
                            
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Expiry Date:</span>
                                <span class="mobile-card-value mobile-date"><?php echo $row['expiration_date']; ?></span>
                            </div>
                        </div>
                        
                        <div class="mobile-card-actions">
                            <?php if ($row['status'] === 'Pending'): ?>
                                <a href="?approve=<?php echo urlencode($row['reference_number']); ?>&source=<?php echo $row['source_type']; ?>&table=<?php echo $row['table_name']; ?>" 
                                   class="btn btn-approve btn-sm" 
                                   onclick="return confirm('Approve this payment?');">
                                    <i class="fas fa-check me-1"></i>Approve Payment
                                </a>
                            <?php else: ?>
                                <span class="text-success">
                                    <i class="fas fa-check-circle me-1"></i>Already Approved
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#paymentsTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search payments:"
                },
                responsive: true,
                scrollX: true,
                scrollCollapse: true,
                autoWidth: false,
                columnDefs: [
                    {
                        targets: [3, 4, 7, 9], // Session, Department, Payment Method, Expiry Date
                        responsivePriority: 2
                    },
                    {
                        targets: [0, 1, 2, 5, 6, 8, 10], // Date, Reference, Name, Payment Type, Amount, Source, Status, Action
                        responsivePriority: 1
                    }
                ],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                initComplete: function() {
                    // Add mobile-friendly search placeholder
                    $('.dataTables_filter input').attr('placeholder', 'Search payments...');
                }
            });
            
            // Mobile-specific adjustments
            function adjustForMobile() {
                if (window.innerWidth <= 768) {
                    $('.dataTables_length select').addClass('form-select-sm');
                    $('.dataTables_filter input').addClass('form-control-sm');
                    $('.dataTables_paginate .paginate_button').addClass('btn-sm');
                }
            }
            
            // Run on load and resize
            adjustForMobile();
            $(window).resize(adjustForMobile);
            
            // Mobile filter enhancements
            function enhanceMobileFilters() {
                if (window.innerWidth <= 768) {
                    // Add touch-friendly styling to filter inputs
                    $('.filters input, .filters select').addClass('touch-friendly');
                    
                    // Auto-collapse filters after selection on mobile
                    $('.filters select, .filters input[type="date"]').on('change', function() {
                        setTimeout(() => {
                            if (window.innerWidth <= 576) {
                                $('#filterCollapse').collapse('hide');
                            }
                        }, 500);
                    });
                    
                    // Add quick filter chips for common options
                    if (!$('.quick-filters').length) {
                        const quickFilters = `
                            <div class="quick-filters mt-2 mb-3">
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-filter="today">
                                        <i class="fas fa-calendar-day me-1"></i>Today
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-filter="week">
                                        <i class="fas fa-calendar-week me-1"></i>This Week
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-filter="month">
                                        <i class="fas fa-calendar-alt me-1"></i>This Month
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm quick-filter" data-filter="pending">
                                        <i class="fas fa-clock me-1"></i>Pending Only
                                    </button>
                                </div>
                            </div>
                        `;
                        $('.filters h5').after(quickFilters);
                        
                        // Quick filter functionality
                        $('.quick-filter').on('click', function() {
                            const filter = $(this).data('filter');
                            const today = new Date();
                            
                            switch(filter) {
                                case 'today':
                                    $('input[name="start_date"]').val(today.toISOString().split('T')[0]);
                                    $('input[name="end_date"]').val(today.toISOString().split('T')[0]);
                                    break;
                                case 'week':
                                    const weekStart = new Date(today.setDate(today.getDate() - today.getDay()));
                                    const weekEnd = new Date(today.setDate(today.getDate() - today.getDay() + 6));
                                    $('input[name="start_date"]').val(weekStart.toISOString().split('T')[0]);
                                    $('input[name="end_date"]').val(weekEnd.toISOString().split('T')[0]);
                                    break;
                                case 'month':
                                    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                                    const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                                    $('input[name="start_date"]').val(monthStart.toISOString().split('T')[0]);
                                    $('input[name="end_date"]').val(monthEnd.toISOString().split('T')[0]);
                                    break;
                                case 'pending':
                                    // This would need backend support to filter by status
                                    // For now, just show a message
                                    alert('Pending filter requires backend implementation');
                                    return;
                            }
                            
                            // Highlight the selected quick filter
                            $('.quick-filter').removeClass('btn-primary').addClass('btn-outline-primary');
                            $(this).removeClass('btn-outline-primary').addClass('btn-primary');
                        });
                    }
                }
            }
            
            // Initialize mobile filter enhancements
            enhanceMobileFilters();
            $(window).resize(enhanceMobileFilters);
            
            // Mobile/Desktop view switching
            function toggleMobileView() {
                const isMobile = window.innerWidth <= 768;
                const tableContainer = $('.table-responsive');
                const cardsContainer = $('.mobile-cards-container');
                
                if (isMobile) {
                    // Show mobile cards, hide table
                    tableContainer.hide();
                    cardsContainer.show();
                    
                    // Disable DataTables on mobile for better performance
                    if ($.fn.DataTable.isDataTable('#paymentsTable')) {
                        $('#paymentsTable').DataTable().destroy();
                    }
                } else {
                    // Show table, hide mobile cards
                    tableContainer.show();
                    cardsContainer.hide();
                    
                    // Reinitialize DataTables on desktop
                    if (!$.fn.DataTable.isDataTable('#paymentsTable')) {
                        $('#paymentsTable').DataTable({
                            order: [[0, 'desc']],
                            pageLength: 25,
                            language: {
                                search: "Search payments:"
                            },
                            responsive: true,
                            scrollX: true,
                            scrollCollapse: true,
                            autoWidth: false,
                            columnDefs: [
                                {
                                    targets: [3, 4, 7, 9], // Session, Department, Payment Method, Expiry Date
                                    responsivePriority: 2
                                },
                                {
                                    targets: [0, 1, 2, 5, 6, 8, 10], // Date, Reference, Name, Payment Type, Amount, Source, Status, Action
                                    responsivePriority: 1
                                }
                            ],
                            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                                 '<"row"<"col-sm-12"tr>>' +
                                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                            initComplete: function() {
                                $('.dataTables_filter input').attr('placeholder', 'Search payments...');
                            }
                        });
                    }
                }
            }
            
            // Initialize view switching
            toggleMobileView();
            $(window).resize(toggleMobileView);
            
            // Mobile card interactions
            $('.mobile-card').on('click', function(e) {
                // Don't trigger if clicking on action buttons
                if ($(e.target).closest('.mobile-card-actions').length) {
                    return;
                }
                
                // Add visual feedback
                $(this).addClass('card-clicked');
                setTimeout(() => {
                    $(this).removeClass('card-clicked');
                }, 200);
            });
            
            // Mobile search functionality
            function setupMobileSearch() {
                if (window.innerWidth <= 768) {
                    // Add mobile search bar
                    if (!$('.mobile-search-container').length) {
                        const mobileSearch = `
                            <div class="mobile-search-container mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="mobileSearchInput" placeholder="Search payments by name, reference, or amount...">
                                    <button class="btn btn-outline-secondary" type="button" id="clearMobileSearch">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                        $('.mobile-cards-container').before(mobileSearch);
                        
                        // Mobile search functionality
                        $('#mobileSearchInput').on('input', function() {
                            const searchTerm = $(this).val().toLowerCase();
                            $('.mobile-card').each(function() {
                                const cardText = $(this).text().toLowerCase();
                                if (cardText.includes(searchTerm)) {
                                    $(this).show();
                                } else {
                                    $(this).hide();
                                }
                            });
                        });
                        
                        // Clear search
                        $('#clearMobileSearch').on('click', function() {
                            $('#mobileSearchInput').val('');
                            $('.mobile-card').show();
                        });
                    }
                }
            }
            
            // Initialize mobile search
            setupMobileSearch();
            $(window).resize(setupMobileSearch);
        });
    </script>
</body>
</html>
