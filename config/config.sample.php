<?php

/**
 * CORE Configuration File
 * 
 * Defines global constants for the application.
 */

// Prevent direct web access
if (!defined('CORE_APP')) {
    die('Direct access forbidden.');
}

// Set default timezone to UTC for this site
date_default_timezone_set('UTC');

// Application Environment ('development' or 'production')
define('ENVIRONMENT', 'production');

// Database Configuration
define('DB_HOST', '{{DB_HOST}}');
define('DB_NAME', '{{DB_NAME}}');
define('DB_USER', '{{DB_USER}}');
define('DB_PASS', '{{DB_PASS}}');
define('DB_CHARSET', 'utf8mb4');

// File Upload Settings
define('MAX_UPLOAD_SIZE', {{MAX_MB}} * 1024 * 1024);
define('ALLOWED_EXTENSIONS', 'pdf');

// Site Information
define('SITE_NAME', '{{SITE_NAME}}');
define('SITE_SUFFIX', 'CORE');
define('SITE_TITLE', SITE_NAME . ' (' . SITE_SUFFIX . ')');
define('SITE_URL', '{{SITE_URL}}');
define('SITE_DEBUG', false);
define('SITE_EMAIL', '{{SITE_EMAIL}}');
define('SUBMISSION_EMAIL', '{{SUBMISSION_EMAIL}}');

// ORCID API Configuration
define('ORCID_CLIENT_ID', '{{ORCID_CLIENT_ID}}');
define('ORCID_CLIENT_SECRET', '{{ORCID_CLIENT_SECRET}}');
define('ORCID_REDIRECT_URI', rtrim(SITE_URL, '/') . '/orcid_callback');

// System Paths
define('UPLOAD_PATH', __DIR__ . '/../storage/uploads');
define('LOG_PATH', __DIR__ . '/../storage/logs');
define('VIEWS_PATH', __DIR__ . '/../app/views');

// Cookie Settings
define('REMEMBER_ME_DURATION', 30 * 24 * 60 * 60); // 30 days
define('COOKIE_DOMAIN', ''); // Default to current domain

// Roles
define('GUEST_ROLE', 1);
define('ADMIN_ROLE_MIN', 600);
define('MOD_ROLE_MIN', 30);
define('VISIBILITY_ON_HOLD', 60);

// CORE Parameters
define('DOC_BRANCH_MAX', 3);
