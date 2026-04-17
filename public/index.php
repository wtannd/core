<?php

declare(strict_types=1);

session_start();

define('CORE_APP', true);

// --- Error Reporting based on Environment ---
// Note: Ensure define('ENVIRONMENT', 'development'); is set in config.php
require_once __DIR__ . '/../config/config.php';
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// Initialize CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set custom error handlers
require_once __DIR__ . '/../app/engine/ErrorHandler.php';
set_error_handler(['app\engine\ErrorHandler', 'handleError']);
set_exception_handler(['app\engine\ErrorHandler', 'handleException']);

/**
 * Basic Autoloader
 */
spl_autoload_register(function ($class) {
    $base_dir = __DIR__ . '/../';
    $file = $base_dir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use app\controllers\AuthController;

// Check for Remember Me token
AuthController::checkRememberMe();

// Define global auth flags to be used by routes and views
$isLoggedIn = isset($_SESSION['mID']);
$isAdmin = isset($_SESSION['admin_role']) && (int)$_SESSION['admin_role'] >= ADMIN_ROLE_MIN;
$isGoodStanding = !empty($_SESSION['is_good']);

// --- Load Router ---
require_once __DIR__ . '/../app/config/routes.php';
