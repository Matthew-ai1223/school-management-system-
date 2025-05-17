<?php
/**
 * Direct Class/Level Field Injection
 * 
 * This file can be included in reg_form.php to add the Class/Level field
 * directly, bypassing the need for database configuration.
 * 
 * Usage: Include this file at the end of the form, just before the submit button.
 */

// Check if Class/Level field already exists in the form fields
$hasClassField = false;
if (isset($fields) && is_array($fields)) {
    foreach ($fields as $field) {
        $lowerLabel = strtolower($field['field_label'] ?? '');
        if (strpos($lowerLabel, 'class') !== false || 
            strpos($lowerLabel, 'level') !== false || 
            strpos($lowerLabel, 'grade') !== false) {
            $hasClassField = true;
            break;
        }
    }
}

// If no Class/Level field exists, render a hardcoded one
if (!$hasClassField): 
?>
<div class="form-section mt-4">
    <h5 class="mb-3">Additional Information</h5>
    <div class="mb-3">
        <label class="form-label required-field">Class/Level</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
            <input type="text" name="student_class" class="form-control" 
                   placeholder="Enter student's class or level" required>
        </div>
        <div class="form-text small text-muted mt-1">
            <i class="fas fa-info-circle me-1"></i>
            Please enter the class or grade level for the student.
        </div>
    </div>
</div>
<?php endif; ?> 