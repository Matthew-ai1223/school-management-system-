<?php
/**
 * This file provides utility functions for handling redirects with proper
 * relative paths depending on the current script location.
 */

/**
 * Get the proper relative path for a redirect based on the current script location
 * 
 * @param string $targetPath The target path relative to the backends directory
 * @return string The properly adjusted path for redirection
 */
function getRedirectPath($targetPath) {
    // Get the current script path and extract directory level
    $currentScript = $_SERVER['SCRIPT_NAME'];
    $dir = dirname($currentScript);
    
    // Determine how many levels deep we are from the backends directory
    $levels = 0;
    if (strpos($dir, '/admin') !== false || 
        strpos($dir, '/@class_teacher') !== false ||
        strpos($dir, '/teacher') !== false ||
        strpos($dir, '/student') !== false) {
        $levels = 1;
    }
    
    // Build the proper relative path
    $prefix = str_repeat('../', $levels);
    return $prefix . ltrim($targetPath, '/');
}

/**
 * Perform a redirect using the proper relative path
 * 
 * @param string $targetPath The target path relative to the backends directory
 */
function redirectTo($targetPath) {
    $path = getRedirectPath($targetPath);
    header("Location: $path");
    exit();
}
?> 