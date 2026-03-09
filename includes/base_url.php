<?php
function get_base_url() {
    // Handle CLI execution
    if (php_sapi_name() === 'cli') {
        return '/';
    }
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 0) == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Auto-detect base path from script location
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Check if we're in a subdirectory (XAMPP/local dev) or at root (Hostinger)
    if (strpos($scriptName, '/UrbanGroup/') !== false) {
        // XAMPP local development
        return $protocol . $host . '/UrbanGroup/php-app/public/';
    } elseif (strpos($scriptName, '/public/') !== false) {
        // Development server with public folder
        $basePath = substr($scriptName, 0, strpos($scriptName, '/public/') + 8);
        return $protocol . $host . rtrim($basePath, '/') . '/';
    } else {
        // Hostinger or direct root deployment
        return $protocol . $host . '/';
    }
}

if (!defined('BASE_URL')) {
    define('BASE_URL', get_base_url());
}
?>