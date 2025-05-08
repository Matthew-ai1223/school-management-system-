<?php
/**
 * Student Registration Receipt Generator using dompdf
 */

// Check if file is accessed directly and reject
if (!defined('ALLOW_ACCESS')) {
    die('Direct access not permitted');
}

// Require dompdf autoloader
require_once __DIR__ . '/dompdf/autoload.inc.php';

// Reference the Dompdf namespace
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generate a PDF receipt for student registration
 * 
 * @param array $studentData Array of student registration data
 * @return bool Whether PDF generation was successful
 */
function generatePDFReceipt($studentData) {
    try {
        // Check if the dompdf library is installed
        if (!file_exists(__DIR__ . '/dompdf/autoload.inc.php')) {
            error_log('DOMPDF Error: Library not found at ' . __DIR__ . '/dompdf/autoload.inc.php');
            throw new Exception('DOMPDF library not installed. Please install it first.');
        }
        
        // Require dompdf autoloader
        require_once __DIR__ . '/dompdf/autoload.inc.php';
        
        // Initialize dompdf options
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true); // Allow loading of remote resources like images

        // Initialize dompdf
        $dompdf = new Dompdf($options);
        
        // Get the absolute path for the school logo
        $logoPath = realpath(__DIR__ . '/../../images/logo.png');
        $logoData = '';
        
        // Try to embed the logo as a data URI
        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = 'image/png'; // Assume PNG format for logo
            $logoSrc = 'data:' . $logoMime . ';base64,' . $logoData;
        } else {
            // Use a placeholder if logo not found
            error_log('Logo not found at: ' . $logoPath);
            $logoSrc = '';
        }
        
        // Get current date
        $currentDate = date('F j, Y');
        
        // Generate receipt content with HTML
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title>Student Registration Receipt</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    color: #333;
                }
                .receipt {
                    max-width: 800px;
                    margin: 0 auto;
                    border: 1px solid #ccc;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    padding-bottom: 10px;
                    border-bottom: 2px solid #007bff;
                    margin-bottom: 20px;
                }
                .school-name {
                    font-size: 24px;
                    font-weight: bold;
                    margin: 10px 0;
                }
                .receipt-title {
                    font-size: 18px;
                    margin-bottom: 5px;
                    color: #007bff;
                }
                .logo {
                    max-width: 100px;
                    max-height: 100px;
                }
                .section {
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #eee;
                }
                .section-title {
                    font-size: 16px;
                    font-weight: bold;
                    color: #007bff;
                    margin-bottom: 10px;
                    border-bottom: 1px solid #007bff;
                    display: inline-block;
                }
                .info-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .info-table td {
                    padding: 5px;
                    vertical-align: top;
                }
                .info-table td:first-child {
                    font-weight: bold;
                    width: 40%;
                }
                .footer {
                    text-align: center;
                    font-size: 12px;
                    margin-top: 30px;
                    color: #666;
                }
                .admission-number {
                    text-align: center;
                    font-size: 16px;
                    font-weight: bold;
                    margin: 20px 0;
                    padding: 10px;
                    border: 2px dashed #007bff;
                    background-color: #f8f9fa;
                }
            </style>
        </head>
        <body>
            <div class="receipt">
                <div class="header">
                    ' . ($logoSrc ? '<img src="' . $logoSrc . '" alt="School Logo" class="logo"><br>' : '') . '
                    <div class="school-name">' . APP_NAME . '</div>
                    <div class="receipt-title">STUDENT REGISTRATION RECEIPT</div>
                    <div>Date: ' . $currentDate . '</div>
                </div>
                
                <div class="admission-number">
                    ADMISSION/REGISTRATION NUMBER: ' . htmlspecialchars($studentData['registration_number']) . '
                </div>
                
                <div class="section">
                    <div class="section-title">STUDENT INFORMATION</div>
                    <table class="info-table">
                        <tr>
                            <td>Name:</td>
                            <td>' . htmlspecialchars($studentData['first_name'] . ' ' . $studentData['last_name']) . '</td>
                        </tr>
                        <tr>
                            <td>Email:</td>
                            <td>' . htmlspecialchars($studentData['email']) . '</td>
                        </tr>
                        <tr>
                            <td>Date of Birth:</td>
                            <td>' . htmlspecialchars($studentData['date_of_birth']) . '</td>
                        </tr>
                        <tr>
                            <td>Gender:</td>
                            <td>' . htmlspecialchars($studentData['gender']) . '</td>
                        </tr>
                        <tr>
                            <td>Phone:</td>
                            <td>' . htmlspecialchars($studentData['phone'] ?? 'N/A') . '</td>
                        </tr>
                        <tr>
                            <td>Address:</td>
                            <td>' . htmlspecialchars($studentData['address'] ?? 'N/A') . '</td>
                        </tr>';
                        
        // Handle class display correctly
        $html .= '<tr><td>Class:</td><td>';
        if (!empty($studentData['class_name'])) {
            $html .= htmlspecialchars($studentData['class_name']);
        } else if (!empty($studentData['class_type'])) {
            $html .= htmlspecialchars($studentData['class_type']);
        } else if (!empty($studentData['class_id'])) {
            $html .= htmlspecialchars($studentData['class_id']);
        } else {
            $html .= 'N/A';
        }
        $html .= '</td></tr>';
        
        $html .= '
                        <tr>
                            <td>Previous School:</td>
                            <td>' . htmlspecialchars($studentData['previous_school'] ?? 'N/A') . '</td>
                        </tr>
                    </table>
                </div>
                
                <div class="section">
                    <div class="section-title">PARENT/GUARDIAN INFORMATION</div>
                    <table class="info-table">
                        <tr>
                            <td>Name:</td>
                            <td>' . htmlspecialchars($studentData['parent_name'] ?? 'N/A') . '</td>
                        </tr>
                        <tr>
                            <td>Phone:</td>
                            <td>' . htmlspecialchars($studentData['parent_phone'] ?? 'N/A') . '</td>
                        </tr>
                        <tr>
                            <td>Email:</td>
                            <td>' . htmlspecialchars($studentData['parent_email'] ?? 'N/A') . '</td>
                        </tr>
                        <tr>
                            <td>Address:</td>
                            <td>' . htmlspecialchars($studentData['parent_address'] ?? 'N/A') . '</td>
                        </tr>
                    </table>
                </div>
                
                <div class="section">
                    <div class="section-title">LOGIN CREDENTIALS</div>
                    <table class="info-table">
                        <tr>
                            <td>Username:</td>
                            <td>' . htmlspecialchars($studentData['username']) . '</td>
                        </tr>
                        <tr>
                            <td>Password:</td>
                            <td>' . htmlspecialchars($studentData['password']) . '</td>
                        </tr>
                    </table>
                </div>
                
                <div class="footer">
                    <p>Please keep this receipt for your records.</p>
                    <p>The login credentials provided above will be needed to access your student account.</p>
                    <p>Welcome to ' . APP_NAME . '!</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        // Load the HTML content into dompdf
        $dompdf->loadHtml($html);
        
        // Set paper size and orientation (A4, portrait)
        $dompdf->setPaper('A4', 'portrait');
        
        // Render the PDF
        $dompdf->render();
        
        // Set appropriate headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="registration_receipt.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output the generated PDF to browser
        $dompdf->stream('registration_receipt.pdf', array('Attachment' => true));
        
        return true;
    } catch (Exception $e) {
        // Log the error
        error_log('DOMPDF Error: ' . $e->getMessage());
        return false;
    }
} 