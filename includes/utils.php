<?php
/**
 * Utility functions
 */

/**
 * Generate a unique ID for tracking links
 */
function generateUniqueId($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send error response
 */
function errorResponse($message, $statusCode = 400) {
    jsonResponse(['error' => $message], $statusCode);
}

/**
 * Send success response
 */
function successResponse($data = [], $message = 'Success') {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

/**
 * Get JSON input from request body
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        errorResponse("Missing required fields: " . implode(', ', $missing), 400);
    }
}

/**
 * Extract ZIP file
 */
function extractZip($zipPath, $destination) {
    $zip = new ZipArchive();

    if ($zip->open($zipPath) === true) {
        // Create destination directory if it doesn't exist
        if (!file_exists($destination)) {
            mkdir($destination, 0755, true);
        }

        $zip->extractTo($destination);
        $zip->close();
        return true;
    }

    return false;
}

/**
 * Recursively delete directory
 */
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }

    return rmdir($dir);
}

/**
 * Find file in directory (case-insensitive)
 */
function findFile($directory, $filename) {
    $files = scandir($directory);
    foreach ($files as $file) {
        if (strcasecmp($file, $filename) === 0) {
            return $directory . DIRECTORY_SEPARATOR . $file;
        }
    }
    return null;
}

/**
 * Find index.html in directory (searches common locations)
 */
function findIndexHtml($directory) {
    // Try direct path first
    $paths = [
        'index.html',
        'index.htm',
        'INDEX.HTML',
        'INDEX.HTM'
    ];

    foreach ($paths as $path) {
        $fullPath = $directory . DIRECTORY_SEPARATOR . $path;
        if (file_exists($fullPath)) {
            return $fullPath;
        }
    }

    // Search in subdirectories (one level deep)
    $subdirs = glob($directory . '/*', GLOB_ONLYDIR);
    foreach ($subdirs as $subdir) {
        foreach ($paths as $path) {
            $fullPath = $subdir . DIRECTORY_SEPARATOR . $path;
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }
    }

    return null;
}

/**
 * Sanitize filename
 */
function sanitizeFilename($filename) {
    // Remove any path components
    $filename = basename($filename);

    // Replace spaces and special characters
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

    return $filename;
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedExtensions = []) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload failed'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['valid' => false, 'error' => 'File size exceeds maximum allowed'];
    }

    $extension = getFileExtension($file['name']);
    if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions)) {
        return ['valid' => false, 'error' => 'Invalid file type'];
    }

    return ['valid' => true];
}

/**
 * Log message
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    error_log($logMessage, 3, __DIR__ . '/../logs/app.log');
}
