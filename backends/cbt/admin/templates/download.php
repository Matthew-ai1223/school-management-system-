<?php
$file = 'questions_template.csv';
$filepath = __DIR__ . '/' . $file;

if (file_exists($filepath)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit();
} else {
    header('HTTP/1.0 404 Not Found');
    echo 'File not found';
} 