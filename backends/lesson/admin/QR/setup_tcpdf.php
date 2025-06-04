<?php
// URL of the TCPDF release
$tcpdfUrl = 'https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.6.2.zip';
$zipFile = 'tcpdf.zip';
$extractPath = './';

// Download TCPDF
if (file_put_contents($zipFile, file_get_contents($tcpdfUrl))) {
    echo "Downloaded TCPDF successfully.\n";

    // Create ZIP archive object
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        // Extract ZIP file
        $zip->extractTo($extractPath);
        $zip->close();
        echo "Extracted TCPDF successfully.\n";

        // Rename the extracted folder
        rename('TCPDF-6.6.2', 'tcpdf');
        
        // Remove the ZIP file
        unlink($zipFile);
        
        echo "TCPDF setup completed successfully.\n";
    } else {
        echo "Failed to extract TCPDF.\n";
    }
} else {
    echo "Failed to download TCPDF.\n";
}
?> 