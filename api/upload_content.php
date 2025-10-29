<?php
/**
 * Content Upload API
 * Handles uploading of SCORM, HTML, and video content
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/content_processor.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

try {
    // Get POST data
    $accountId = $_POST['account_id'] ?? null;
    $title = $_POST['title'] ?? null;
    $description = $_POST['description'] ?? '';
    $contentType = $_POST['content_type'] ?? 'training';
    $uploadType = $_POST['upload_type'] ?? null;

    // Validate required fields
    if (!$accountId || !$title || !$uploadType) {
        errorResponse('Missing required fields: account_id, title, upload_type');
    }

    // Validate upload type
    $validUploadTypes = ['scorm', 'html_zip', 'raw_html', 'video'];
    if (!in_array($uploadType, $validUploadTypes)) {
        errorResponse('Invalid upload_type. Must be one of: ' . implode(', ', $validUploadTypes));
    }

    $db = db();
    $processor = new ContentProcessor();

    // Handle different upload types
    $sourcePath = null;
    $contentIdentifier = '';

    switch ($uploadType) {
        case 'scorm':
        case 'html_zip':
            // Handle file upload
            if (!isset($_FILES['file'])) {
                errorResponse('No file uploaded');
            }

            $validation = validateFileUpload($_FILES['file'], ['zip']);
            if (!$validation['valid']) {
                errorResponse($validation['error']);
            }

            // Save uploaded file
            $uploadDir = UPLOAD_DIR . '/' . ($uploadType === 'scorm' ? 'scorm' : 'html');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = uniqid() . '_' . sanitizeFilename($_FILES['file']['name']);
            $sourcePath = $uploadDir . '/' . $filename;

            if (!move_uploaded_file($_FILES['file']['tmp_name'], $sourcePath)) {
                errorResponse('Failed to save uploaded file');
            }

            $contentIdentifier = 'pending'; // Will be updated after processing
            break;

        case 'raw_html':
            // Handle raw HTML content
            $htmlContent = $_POST['html_content'] ?? null;
            if (!$htmlContent) {
                errorResponse('Missing html_content field');
            }

            $sourcePath = $htmlContent;
            $contentIdentifier = 'pending'; // Will be updated after processing
            break;

        case 'video':
            // Handle video upload
            if (!isset($_FILES['file'])) {
                errorResponse('No file uploaded');
            }

            $validation = validateFileUpload($_FILES['file'], ['mp4', 'webm', 'ogg']);
            if (!$validation['valid']) {
                errorResponse($validation['error']);
            }

            // Save uploaded video
            $uploadDir = UPLOAD_DIR . '/video';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = uniqid() . '_' . sanitizeFilename($_FILES['file']['name']);
            $sourcePath = $uploadDir . '/' . $filename;

            if (!move_uploaded_file($_FILES['file']['tmp_name'], $sourcePath)) {
                errorResponse('Failed to save uploaded file');
            }

            $contentIdentifier = str_replace(UPLOAD_DIR . '/', '', $sourcePath);
            break;
    }

    // Create content record
    $sql = "INSERT INTO content (account_id, title, description, content_type, upload_type, content_identifier)
            VALUES (?, ?, ?, ?, ?, ?) RETURNING id";

    $stmt = $db->getConnection()->prepare($sql);
    $stmt->execute([$accountId, $title, $description, $contentType, $uploadType, $contentIdentifier]);
    $result = $stmt->fetch();
    $contentId = $result['id'];

    if (!$contentId) {
        errorResponse('Failed to create content record');
    }

    // Process content asynchronously (or synchronously for now)
    $processResult = $processor->processContent($contentId, $uploadType, $sourcePath);

    // Clean up uploaded file if it was a zip
    if (in_array($uploadType, ['scorm', 'html_zip']) && file_exists($sourcePath)) {
        unlink($sourcePath);
    }

    successResponse([
        'content_id' => $contentId,
        'title' => $title,
        'upload_type' => $uploadType,
        'content_path' => $processResult['content_path'],
        'tags' => $processResult['tags']
    ], 'Content uploaded and processed successfully');

} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    errorResponse('Upload failed: ' . $e->getMessage(), 500);
}
