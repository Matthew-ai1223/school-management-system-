<?php
/**
 * Test script for DOMPDF
 * This file tests if DOMPDF is properly installed and configured
 */

// Try to load DOMPDF
$dompdfExists = file_exists(__DIR__ . '/dompdf/autoload.inc.php');
$dompdfLoaded = false;
$dompdfError = null;

try {
    if ($dompdfExists) {
        require_once __DIR__ . '/dompdf/autoload.inc.php';
        
        // Check if the DOMPDF classes exist
        $dompdfLoaded = class_exists('Dompdf\Dompdf');
    }
} catch (Exception $e) {
    $dompdfError = $e->getMessage();
}

// Output as HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DOMPDF Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #4a5568;
        }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .success {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
        .warning {
            background-color: #fff3cd;
            border-color: #ffeeba;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        code {
            font-family: monospace;
        }
        ul {
            padding-left: 20px;
        }
        .btn {
            display: inline-block;
            background: #4299e1;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 10px;
        }
        .btn:hover {
            background: #3182ce;
        }
    </style>
</head>
<body>
    <h1>DOMPDF Installation Test</h1>
    
    <div class="card <?php echo $dompdfExists ? 'info' : 'error'; ?>">
        <h2>Step 1: Checking for DOMPDF files</h2>
        <?php if ($dompdfExists): ?>
            <p>✅ DOMPDF files found at: <code><?php echo __DIR__ . '/dompdf/autoload.inc.php'; ?></code></p>
        <?php else: ?>
            <p>❌ DOMPDF files not found at: <code><?php echo __DIR__ . '/dompdf/autoload.inc.php'; ?></code></p>
            <p>Please follow these steps to install DOMPDF:</p>
            <ol>
                <li>Make sure you have Composer installed. If not, get it from <a href="https://getcomposer.org/download/" target="_blank">getcomposer.org</a></li>
                <li>Run the following commands in your terminal:
                    <pre><code>cd <?php echo __DIR__; ?>
composer install</code></pre>
                </li>
                <li>Alternatively, download DOMPDF manually from <a href="https://github.com/dompdf/dompdf/releases" target="_blank">github.com/dompdf/dompdf/releases</a></li>
            </ol>
        <?php endif; ?>
    </div>
    
    <div class="card <?php echo $dompdfLoaded ? 'success' : 'warning'; ?>">
        <h2>Step 2: Verifying DOMPDF classes</h2>
        <?php if ($dompdfLoaded): ?>
            <p>✅ DOMPDF classes loaded successfully!</p>
        <?php elseif ($dompdfExists): ?>
            <p>❌ DOMPDF classes could not be loaded. Error:</p>
            <pre><?php echo $dompdfError ?? 'Unknown error'; ?></pre>
        <?php else: ?>
            <p>⚠️ Cannot verify DOMPDF classes until the library is installed.</p>
        <?php endif; ?>
    </div>
    
    <div class="card info">
        <h2>System Information</h2>
        <ul>
            <li>PHP Version: <?php echo phpversion(); ?></li>
            <li>Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></li>
            <li>Document Root: <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></li>
            <li>Current Script: <?php echo __FILE__; ?></li>
        </ul>
    </div>
    
    <?php if ($dompdfLoaded): ?>
    <div class="card success">
        <h2>Generate Test PDF</h2>
        <p>DOMPDF is correctly installed! You can now generate a test PDF:</p>
        <a href="generate_test_pdf.php" class="btn" target="_blank">Generate Test PDF</a>
    </div>
    <?php endif; ?>
    
    <div class="card info">
        <h2>Next Steps</h2>
        <p>After installing DOMPDF:</p>
        <ol>
            <li>Refresh this page to verify the installation.</li>
            <li>Try generating a test PDF to confirm everything works.</li>
            <li>Implement the PDF receipt functionality in your application.</li>
        </ol>
    </div>
</body>
</html> 