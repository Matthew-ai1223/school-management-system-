<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Form Fields Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .field-row {
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .field-row:hover {
            background-color: #f0f0f0;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.765625rem;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <h2 class="mb-4">Registration Form Fields Management - <?php echo ucfirst($registrationType); ?></h2>
        
        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Form Fields</h5>
                    <div>
                        <a href="?type=kiddies" class="btn btn-sm <?php echo $registrationType === 'kiddies' ? 'btn-primary' : 'btn-outline-primary'; ?>">Kiddies</a>
                        <a href="?type=college" class="btn btn-sm <?php echo $registrationType === 'college' ? 'btn-primary' : 'btn-outline-primary'; ?>">College</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div id="fields-container">
                        <?php if(empty($fields)): ?>
                            <div class="alert alert-info">No fields found. Please add fields below.</div>
                        <?php else: ?>
                            <p>Drag and drop fields to reorder them:</p>
                            <div class="sortable-fields">
                                <?php foreach($fields as $field): ?>
                                    <div class="field-row" data-field-id="<?php echo $field['id']; ?>">
                                        <div class="row align-items-center">
                                            <div class="col-md-1 text-center">
                                                <i class="fas fa-grip-vertical handle" style="cursor: move;"></i>
                                                <input type="hidden" name="fields[<?php echo $field['id']; ?>][order]" value="<?php echo $field['field_order']; ?>" class="order-input">
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="label_<?php echo $field['id']; ?>" name="fields[<?php echo $field['id']; ?>][label]" value="<?php echo htmlspecialchars($field['field_label']); ?>" placeholder="Field Label">
                                                    <label for="label_<?php echo $field['id']; ?>">Field Label</label>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-floating">
                                                    <select class="form-select" id="type_<?php echo $field['id']; ?>" name="fields[<?php echo $field['id']; ?>][type]">
                                                        <option value="text" <?php echo $field['field_type'] === 'text' ? 'selected' : ''; ?>>Text</option>
                                                        <option value="number" <?php echo $field['field_type'] === 'number' ? 'selected' : ''; ?>>Number</option>
                                                        <option value="email" <?php echo $field['field_type'] === 'email' ? 'selected' : ''; ?>>Email</option>
                                                        <option value="date" <?php echo $field['field_type'] === 'date' ? 'selected' : ''; ?>>Date</option>
                                                        <option value="textarea" <?php echo $field['field_type'] === 'textarea' ? 'selected' : ''; ?>>Textarea</option>
                                                        <option value="select" <?php echo $field['field_type'] === 'select' ? 'selected' : ''; ?>>Dropdown</option>
                                                        <option value="checkbox" <?php echo $field['field_type'] === 'checkbox' ? 'selected' : ''; ?>>Checkbox</option>
                                                        <option value="file" <?php echo $field['field_type'] === 'file' ? 'selected' : ''; ?>>File Upload</option>
                                                    </select>
                                                    <label for="type_<?php echo $field['id']; ?>">Field Type</label>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-floating">
                                                    <select class="form-select" id="category_<?php echo $field['id']; ?>" name="fields[<?php echo $field['id']; ?>][category]">
                                                        <?php foreach ($fieldCategories as $categoryKey => $categoryName): ?>
                                                            <option value="<?php echo $categoryKey; ?>" <?php echo ($field['field_category'] ?? 'student_info') === $categoryKey ? 'selected' : ''; ?>><?php echo $categoryName; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label for="category_<?php echo $field['id']; ?>">Category</label>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-floating options-container" <?php echo !in_array($field['field_type'], ['select', 'checkbox']) ? 'style="display: none;"' : ''; ?>>
                                                    <input type="text" class="form-control options-input" id="options_<?php echo $field['id']; ?>" name="fields[<?php echo $field['id']; ?>][options]" value="<?php echo htmlspecialchars($field['options']); ?>" placeholder="Options (comma separated)">
                                                    <label for="options_<?php echo $field['id']; ?>">Options</label>
                                                </div>
                                            </div>
                                            <div class="col-md-1 text-center">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="required_<?php echo $field['id']; ?>" name="fields[<?php echo $field['id']; ?>][required]" <?php echo $field['required'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="required_<?php echo $field['id']; ?>">Required</label>
                                                </div>
                                            </div>
                                            <div class="col-md-1 text-end">
                                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this field?');">
                                                    <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" name="delete_field">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3" name="update_fields">Save Changes</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Add New Field</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="field_label" name="field_label" required placeholder="Field Label">
                                <label for="field_label">Field Label</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-floating">
                                <select class="form-select" id="field_type" name="field_type" required onchange="toggleOptions(this)">
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="email">Email</option>
                                    <option value="date">Date</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="select">Dropdown</option>
                                    <option value="checkbox">Checkbox</option>
                                    <option value="file">File Upload</option>
                                </select>
                                <label for="field_type">Field Type</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-floating">
                                <select class="form-select" id="field_category" name="field_category" required>
                                    <?php foreach ($fieldCategories as $categoryKey => $categoryName): ?>
                                        <option value="<?php echo $categoryKey; ?>"><?php echo $categoryName; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="field_category">Category</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="field_order" name="field_order" required min="1" value="<?php echo count($fields) + 1; ?>" placeholder="Order">
                                <label for="field_order">Order</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-floating" id="options_container" style="display: none;">
                                <input type="text" class="form-control" id="options" name="options" placeholder="Options (comma separated)">
                                <label for="options">Options</label>
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-center">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="required" name="required" checked>
                                <label class="form-check-label" for="required">Required</label>
                            </div>
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-success" name="add_field">Add Field</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js"></script>
    <script>
        // Toggle options field based on field type
        function toggleOptions(selectElement) {
            const needOptions = ['select', 'checkbox'].includes(selectElement.value);
            document.getElementById('options_container').style.display = needOptions ? 'block' : 'none';
            if (!needOptions) {
                document.getElementById('options').value = '';
            }
        }
        
        // Initialize sortable fields and handle type changes
        $(document).ready(function() {
            $('.sortable-fields').sortable({
                handle: '.handle',
                update: function(event, ui) {
                    // Update order inputs after sorting
                    $('.sortable-fields .field-row').each(function(index) {
                        $(this).find('.order-input').val(index + 1);
                    });
                }
            });
            
            // Toggle options field visibility based on select/checkbox type
            $('select[id^="type_"]').on('change', function() {
                const fieldId = $(this).attr('id').replace('type_', '');
                const needOptions = ['select', 'checkbox'].includes($(this).val());
                const optionsContainer = $(this).closest('.row').find('.options-container');
                
                if (needOptions) {
                    optionsContainer.show();
                } else {
                    optionsContainer.hide();
                    optionsContainer.find('.options-input').val('');
                }
            });
        });
    </script>
</body>
</html> 