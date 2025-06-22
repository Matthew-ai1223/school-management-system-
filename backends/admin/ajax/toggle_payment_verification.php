<?php
require_once '../include/settings.php';
require_once '../../auth.php';

// Ensure only admin can access this
$auth = new Auth();
$auth->requireRole('admin');

// Handle the toggle request
$response = ['success' => false, 'message' => '', 'new_status' => null];

try {
    $settings = Settings::getInstance();
    $current_status = $settings->getSetting('payment_verification_required');
    
    // Toggle the status (1 to 0 or 0 to 1)
    $new_status = $current_status == '1' ? '0' : '1';
    
    if ($settings->updateSetting('payment_verification_required', $new_status)) {
        $response['success'] = true;
        $response['message'] = 'Payment verification requirement has been ' . 
            ($new_status == '1' ? 'enabled' : 'disabled');
        $response['new_status'] = $new_status;
    } else {
        throw new Exception('Failed to update setting');
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response); 