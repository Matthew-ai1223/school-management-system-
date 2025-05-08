<?php
// Include required files
require_once 'backends/config.php';
require_once 'backends/utils.php';
require_once 'backends/auth.php';

// Log the user out
logout();

// Redirect to login page
redirect('login.php');
?> 