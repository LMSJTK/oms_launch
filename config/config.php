<?php
/**
 * Configuration file for OMS Launch Platform
 * Copy this file and update with your actual credentials
 */

// Load .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'oms_launch');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Claude API Configuration
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: '');
define('CLAUDE_API_URL', 'https://api.anthropic.com/v1/messages');
define('CLAUDE_MODEL', 'claude-3-5-sonnet-20241022');

// AWS SNS Configuration
define('AWS_REGION', getenv('AWS_REGION') ?: 'us-east-1');
define('AWS_ACCESS_KEY', getenv('AWS_ACCESS_KEY') ?: '');
define('AWS_SECRET_KEY', getenv('AWS_SECRET_KEY') ?: '');
define('SNS_TOPIC_ARN', getenv('SNS_TOPIC_ARN') ?: '');

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('CONTENT_DIR', __DIR__ . '/../content');
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB

// Application URLs
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost');
define('LAUNCH_URL', BASE_URL . '/launch.php');

// Security
define('SECRET_KEY', getenv('SECRET_KEY') ?: 'change-this-secret-key');

// Error Reporting (set to false in production)
define('DEBUG_MODE', getenv('DEBUG_MODE') === 'true');

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('UTC');
