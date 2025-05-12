<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Form Management - <?php echo ucfirst($registrationType); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage <?php echo ucfirst($registrationType); ?> Registration Form Fields</h2>
            <div class="btn-group">
                <a href="?type=kiddies" class="btn btn-outline-primary <?php echo $registrationType === 'kiddies' ? 'active' : ''; ?>">Kiddies Form</a>
                <a href="?type=college" class="btn btn-outline-primary <?php echo $registrationType === 'college' ? 'active' : ''; ?>">College Form</a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Existing Fields Table -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Existing Fields</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="update_fields" value="1">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Label</th>
                                <th>Type</th>
                                <th>Order</th>
                                <th>Required</th>
                                <th>Options</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fields as $field): ?>
                                <tr>
                                    <td>
                                        <input type="text" class="form-control" name="fields[<?php echo $field['id']; ?>][label]" 
                                               value="<?php echo htmlspecialchars($field['field_label']); ?>" required>
                                    </td>
                                    <td>
                                        <select class="form-select" name="fields[<?php echo $field['id']; ?>][type]" required>
                                            <?php
                                            $types = ['text', 'email', 'number', 'date', 'textarea', 'select', 'file'];
                                            foreach ($types as $type):
                                                $selected = ($type === $field['field_type']) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $type; ?>" <?php echo $selected; ?>><?php echo ucfirst($type); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control" name="fields[<?php echo $field['id']; ?>][order]" 
                                               value="<?php echo $field['field_order']; ?>" required min="1">
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input" name="fields[<?php echo $field['id']; ?>][required]" 
                                               <?php echo $field['required'] ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="fields[<?php echo $field['id']; ?>][options]" 
                                               value="<?php echo htmlspecialchars($field['options']); ?>" 
                                               placeholder="Option1,Option2,Option3">
                                    </td>
                                    <td>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="delete_field" value="1">
                                            <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Are you sure you want to delete this field?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Add New Field Form -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Add New Field</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="add_field" value="1">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Field Label</label>
                            <input type="text" class="form-control" name="field_label" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Field Type</label>
                            <select class="form-select" name="field_type" required>
                                <option value="text">Text</option>
                                <option value="email">Email</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="textarea">Textarea</option>
                                <option value="select">Select</option>
                                <option value="file">File</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Order</label>
                            <input type="number" class="form-control" name="field_order" required min="1">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Required</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" name="required" id="required">
                                <label class="form-check-label" for="required">Yes</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Options (comma-separated)</label>
                            <input type="text" class="form-control" name="options" placeholder="Option1,Option2,Option3">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">Add Field</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 