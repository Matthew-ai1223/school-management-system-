<?php
// Payment Gateway Configuration (Paystack)
define('PAYSTACK_PUBLIC_KEY', 'pk_test_fff1d31f74a43da37f1322e466e0e27d1c1900f7');  // Test public key
define('PAYSTACK_SECRET_KEY', 'sk_test_ba85c77b3ea04ae33627b38ca46cf8e3b5a4edc5'); 
define('PAYSTACK_BASE_URL', 'https://api.paystack.co');

// Application Fees (in Naira)
define('KIDDIES_APPLICATION_FEE', 5000.00);  // ₦5,000
define('COLLEGE_APPLICATION_FEE', 7500.00);   // ₦7,500

// Payment Status Constants
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_COMPLETED', 'completed');
define('PAYMENT_STATUS_FAILED', 'failed');
define('PAYMENT_STATUS_CANCELLED', 'cancelled');

// Payment Methods
define('PAYMENT_METHOD_PAYSTACK', 'paystack');
define('PAYMENT_METHOD_BANK_TRANSFER', 'bank_transfer');
define('PAYMENT_METHOD_CARD', 'card');

// Bank Account Details (for bank transfer)
define('BANK_NAME', 'Your Bank Name');
define('BANK_ACCOUNT_NAME', 'ACE MODEL COLLEGE');
define('BANK_ACCOUNT_NUMBER', 'XXXXXXXXXX');

// Verification Settings
define('PAYMENT_VERIFICATION_TIMEOUT', 3600); // 1 hour in seconds
define('MAX_VERIFICATION_ATTEMPTS', 3);

// Currency
define('CURRENCY_CODE', 'NGN');
// define('CURRENCY_SYMBOL', '₦');

// API Endpoints
define('PAYSTACK_INITIALIZE_URL', PAYSTACK_BASE_URL . '/transaction/initialize');
define('PAYSTACK_VERIFY_URL', PAYSTACK_BASE_URL . '/transaction/verify/');

// Payment Session Keys
define('SESSION_PAYMENT_REF', 'payment_reference');
define('SESSION_PAYMENT_AMOUNT', 'payment_amount');
define('SESSION_PAYMENT_TYPE', 'payment_type');
define('SESSION_PAYMENT_EMAIL', 'payment_email');

/**
 * Get formatted amount with currency symbol
 * @param float $amount
 * @return string
 */
function formatAmount($amount) {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

/**
 * Convert amount to lowest currency unit (kobo for NGN)
 * @param float $amount
 * @return int
 */
function convertToLowestUnit($amount) {
    return $amount * 100;
}

/**
 * Get application fee based on type
 * @param string $type
 * @return float
 */
function getApplicationFee($type) {
    return strtolower($type) === 'kiddies' ? KIDDIES_APPLICATION_FEE : COLLEGE_APPLICATION_FEE;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
