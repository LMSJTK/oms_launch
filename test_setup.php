<?php
/**
 * Setup Test Script
 * Verifies that the OMS Launch platform is properly configured
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "OMS Launch Platform - Setup Verification\n";
echo "=========================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// Test 1: Check PHP version
echo "1. Checking PHP version... ";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "✓ OK (" . PHP_VERSION . ")\n";
    $success[] = "PHP version is compatible";
} else {
    echo "✗ FAIL\n";
    $errors[] = "PHP 7.4 or higher required (current: " . PHP_VERSION . ")";
}

// Test 2: Check required PHP extensions
echo "2. Checking PHP extensions...\n";
$requiredExtensions = ['pdo', 'pdo_pgsql', 'curl', 'json', 'zip', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    echo "   - {$ext}: ";
    if (extension_loaded($ext)) {
        echo "✓\n";
        $success[] = "Extension {$ext} loaded";
    } else {
        echo "✗\n";
        $errors[] = "Missing PHP extension: {$ext}";
    }
}

// Test 3: Check config file
echo "3. Checking configuration file... ";
if (file_exists(__DIR__ . '/config/config.php')) {
    echo "✓ OK\n";
    $success[] = "Configuration file exists";
    require_once __DIR__ . '/config/config.php';
} else {
    echo "✗ FAIL\n";
    $errors[] = "Configuration file not found";
}

// Test 4: Check directory permissions
echo "4. Checking directory permissions...\n";
$directories = [
    'uploads' => __DIR__ . '/uploads',
    'content' => __DIR__ . '/content',
    'logs' => __DIR__ . '/logs'
];

foreach ($directories as $name => $path) {
    echo "   - {$name}: ";
    if (is_writable($path)) {
        echo "✓ writable\n";
        $success[] = "{$name} directory is writable";
    } else {
        echo "✗ not writable\n";
        $errors[] = "{$name} directory is not writable (chmod 777 {$path})";
    }
}

// Test 5: Check database connection
echo "5. Testing database connection... ";
if (defined('DB_HOST')) {
    try {
        require_once __DIR__ . '/includes/db.php';
        $db = Database::getInstance();
        $result = $db->fetchOne("SELECT 1 as test");
        if ($result['test'] == 1) {
            echo "✓ OK\n";
            $success[] = "Database connection successful";
        }
    } catch (Exception $e) {
        echo "✗ FAIL\n";
        $errors[] = "Database connection failed: " . $e->getMessage();
    }
} else {
    echo "✗ SKIP (not configured)\n";
    $warnings[] = "Database not configured";
}

// Test 6: Check Claude API key
echo "6. Checking Claude API configuration... ";
if (defined('CLAUDE_API_KEY') && !empty(CLAUDE_API_KEY)) {
    if (CLAUDE_API_KEY === 'your_claude_api_key_here' || CLAUDE_API_KEY === '') {
        echo "⚠ Not configured\n";
        $warnings[] = "Claude API key not set";
    } else {
        echo "✓ OK\n";
        $success[] = "Claude API key configured";
    }
} else {
    echo "✗ FAIL\n";
    $errors[] = "Claude API key not configured";
}

// Test 7: Check AWS SNS configuration
echo "7. Checking AWS SNS configuration... ";
if (defined('SNS_TOPIC_ARN') && !empty(SNS_TOPIC_ARN)) {
    if (strpos(SNS_TOPIC_ARN, 'arn:aws:sns:') === 0) {
        echo "✓ OK\n";
        $success[] = "AWS SNS configured";
    } else {
        echo "⚠ Invalid ARN format\n";
        $warnings[] = "SNS Topic ARN format is invalid";
    }
} else {
    echo "⚠ Not configured\n";
    $warnings[] = "AWS SNS not configured (events won't be published)";
}

// Test 8: Check database schema
echo "8. Checking database tables... ";
if (isset($db)) {
    try {
        $tables = $db->fetchAll("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_type = 'BASE TABLE'
            ORDER BY table_name
        ");

        $requiredTables = [
            'accounts', 'users', 'recipients', 'content',
            'content_tags', 'oms_tracking_links'
        ];

        $existingTables = array_column($tables, 'table_name');
        $missingTables = array_diff($requiredTables, $existingTables);

        if (empty($missingTables)) {
            echo "✓ OK (" . count($tables) . " tables found)\n";
            $success[] = "All required database tables exist";
        } else {
            echo "✗ FAIL\n";
            $errors[] = "Missing database tables: " . implode(', ', $missingTables);
        }
    } catch (Exception $e) {
        echo "✗ FAIL\n";
        $errors[] = "Could not check database tables: " . $e->getMessage();
    }
}

// Summary
echo "\n=========================================\n";
echo "SUMMARY\n";
echo "=========================================\n\n";

if (!empty($success)) {
    echo "✓ SUCCESS (" . count($success) . "):\n";
    foreach ($success as $msg) {
        echo "  - {$msg}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠ WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $msg) {
        echo "  - {$msg}\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "✗ ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $msg) {
        echo "  - {$msg}\n";
    }
    echo "\n";
    echo "Please fix the errors above before using the platform.\n\n";
    exit(1);
} else {
    echo "✓ All checks passed! The platform is ready to use.\n\n";
    echo "Next steps:\n";
    echo "1. Create test data: php test_create_data.php\n";
    echo "2. Start PHP server: php -S localhost:8000\n";
    echo "3. Access at: http://localhost:8000\n\n";
    exit(0);
}
