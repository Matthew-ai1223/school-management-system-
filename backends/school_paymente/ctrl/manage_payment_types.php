<?php
require_once 'db_config.php';
require_once 'payment_types.php';

session_start();

// Initialize PaymentTypes class
$paymentTypes = new PaymentTypes($conn);

// Handle form submissions
$message = '';
$error = '';

// Add new payment type
if (isset($_POST['add_payment'])) {
    $name = $_POST['name'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $academic_term = $_POST['academic_term'];
    $min_amount = isset($_POST['min_amount']) ? $_POST['min_amount'] : $amount; // Default to full amount if not set
    
    // Validate minimum amount
    if ($min_amount > $amount) {
        $error = "Minimum payment amount cannot be greater than total amount.";
    } else {
        if ($paymentTypes->addPaymentType($name, $amount, $description, $academic_term, $min_amount)) {
            $message = "Payment type added successfully!";
        } else {
            $error = "Error adding payment type.";
        }
    }
}

// Update payment type
if (isset($_POST['update_payment'])) {
    $id = $_POST['payment_id'];
    $name = $_POST['name'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $academic_term = $_POST['academic_term'];
    $min_amount = isset($_POST['min_amount']) ? $_POST['min_amount'] : $amount; // Default to full amount if not set
    
    // Validate minimum amount
    if ($min_amount > $amount) {
        $error = "Minimum payment amount cannot be greater than total amount.";
    } else {
        if ($paymentTypes->updatePaymentType($id, $name, $amount, $description, $academic_term, $min_amount)) {
            $message = "Payment type updated successfully!";
        } else {
            $error = "Error updating payment type.";
        }
    }
}

// Deactivate payment type
if (isset($_POST['deactivate'])) {
    $id = $_POST['payment_id'];
    if ($paymentTypes->deactivatePaymentType($id)) {
        $message = "Payment type deactivated successfully!";
    } else {
        $error = "Error deactivating payment type.";
    }
}

// Get all payment types
$payment_types = $paymentTypes->getPaymentTypes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payment Types - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
            --light-bg: #f8f9fa;
            --border-radius: 10px;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            padding: 2rem 0;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            margin-bottom: 2rem;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            padding: 1rem 1.5rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            background: var(--light-bg);
        }

        .btn {
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            font-weight: 500;
        }

        .btn-primary {
            background-color: var(--secondary-color);
            border: none;
        }

        .btn-danger {
            background-color: var(--danger-color);
            border: none;
        }

        .alert {
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }

        .form-control {
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
        }

        .modal-content {
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .modal-header .btn-close {
            color: white;
        }

        .badge {
            padding: 0.5rem 0.8rem;
            border-radius: var(--border-radius);
        }

        .amount-wrapper {
            display: flex;
            gap: 1rem;
        }
        .amount-wrapper .form-group {
            flex: 1;
        }
        .input-group {
            position: relative;
        }
        .input-group-text {
            background: transparent;
            border-right: none;
        }
        .input-group .form-control {
            border-left: none;
        }
        .min-amount-info {
            font-size: 0.875rem;
            color: #666;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-cog"></i> Manage Payment Types</h4>
                        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                            <i class="fas fa-plus"></i> Add New Payment Type
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Total Amount (₦)</th>
                                        <th>Min. Payment (₦)</th>
                                        <th>Description</th>
                                        <th>Academic Term</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($payment_types as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['name']); ?></td>
                                            <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td>₦<?php echo number_format($payment['min_payment_amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($payment['description']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['academic_term']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $payment['is_active'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $payment['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary edit-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editPaymentModal"
                                                        data-id="<?php echo $payment['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($payment['name']); ?>"
                                                        data-amount="<?php echo $payment['amount']; ?>"
                                                        data-min-amount="<?php echo $payment['min_payment_amount']; ?>"
                                                        data-description="<?php echo htmlspecialchars($payment['description']); ?>"
                                                        data-term="<?php echo htmlspecialchars($payment['academic_term']); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($payment['is_active']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <button type="submit" name="deactivate" class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('Are you sure you want to deactivate this payment type?')">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
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
    </div>

    <!-- Add Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add New Payment Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="addPaymentForm">
                        <div class="mb-3">
                            <label for="name" class="form-label">Payment Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="amount-wrapper">
                            <div class="mb-3 form-group">
                                <label for="amount" class="form-label">Total Amount (₦)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                                </div>
                            </div>
                            <div class="mb-3 form-group">
                                <label for="min_amount" class="form-label">Minimum Payment (₦)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" id="min_amount" name="min_amount" step="0.01" required>
                                </div>
                                <div class="min-amount-info">Minimum amount allowed for installment payment</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="academic_term" class="form-label">Academic Term</label>
                            <input type="text" class="form-control" id="academic_term" name="academic_term" required>
                        </div>
                        <button type="submit" name="add_payment" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Save Payment Type
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Payment Modal -->
    <div class="modal fade" id="editPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Payment Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editPaymentForm">
                        <input type="hidden" id="edit_payment_id" name="payment_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Payment Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="amount-wrapper">
                            <div class="mb-3 form-group">
                                <label for="edit_amount" class="form-label">Total Amount (₦)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" id="edit_amount" name="amount" step="0.01" required>
                                </div>
                            </div>
                            <div class="mb-3 form-group">
                                <label for="edit_min_amount" class="form-label">Minimum Payment (₦)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" id="edit_min_amount" name="min_amount" step="0.01" required>
                                </div>
                                <div class="min-amount-info">Minimum amount allowed for installment payment</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_academic_term" class="form-label">Academic Term</label>
                            <input type="text" class="form-control" id="edit_academic_term" name="academic_term" required>
                        </div>
                        <button type="submit" name="update_payment" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Update Payment Type
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit button clicks
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const name = this.dataset.name;
                const amount = this.dataset.amount;
                const minAmount = this.dataset.minAmount;
                const description = this.dataset.description;
                const term = this.dataset.term;

                document.getElementById('edit_payment_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_amount').value = amount;
                document.getElementById('edit_min_amount').value = minAmount;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_academic_term').value = term;
            });
        });

        // Validate minimum amount on form submission
        document.getElementById('addPaymentForm').addEventListener('submit', validateForm);
        document.getElementById('editPaymentForm').addEventListener('submit', validateForm);

        function validateForm(e) {
            const form = e.target;
            const amount = parseFloat(form.querySelector('[name="amount"]').value);
            const minAmount = parseFloat(form.querySelector('[name="min_amount"]').value);

            if (minAmount > amount) {
                e.preventDefault();
                alert('Minimum payment amount cannot be greater than total amount.');
                return false;
            }
            return true;
        }

        // Auto-calculate suggested minimum amount (50% of total by default)
        document.getElementById('amount').addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            document.getElementById('min_amount').value = (amount * 0.5).toFixed(2);
        });

        document.getElementById('edit_amount').addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            document.getElementById('edit_min_amount').value = (amount * 0.5).toFixed(2);
        });
    </script>
</body>
</html> 