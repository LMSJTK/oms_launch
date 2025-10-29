<?php
/**
 * Create Launch Link API
 * Creates a unique tracking link for a recipient to access content
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/utils.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

try {
    $input = getJsonInput();

    // Validate required fields
    validateRequired($input, ['recipient_id', 'content_id']);

    $recipientId = $input['recipient_id'];
    $contentId = $input['content_id'];

    $db = db();

    // Verify recipient exists
    $recipient = $db->fetchOne(
        "SELECT id, email, first_name, last_name FROM recipients WHERE id = ?",
        [$recipientId]
    );

    if (!$recipient) {
        errorResponse('Recipient not found', 404);
    }

    // Verify content exists
    $content = $db->fetchOne(
        "SELECT id, title, content_type, content_identifier FROM content WHERE id = ?",
        [$contentId]
    );

    if (!$content) {
        errorResponse('Content not found', 404);
    }

    // Generate unique link ID
    $uniqueLinkId = generateUniqueId();

    // Create tracking link record
    $sql = "INSERT INTO oms_tracking_links
            (recipient_id, content_id, unique_link_id, status, interaction_data)
            VALUES (?, ?, ?, 'PENDING', '{}')
            RETURNING id";

    $stmt = $db->getConnection()->prepare($sql);
    $stmt->execute([$recipientId, $contentId, $uniqueLinkId]);
    $result = $stmt->fetch();
    $trackingLinkId = $result['id'];

    // Generate launch URL
    $launchUrl = LAUNCH_URL . '?id=' . $uniqueLinkId;

    successResponse([
        'tracking_link_id' => $trackingLinkId,
        'unique_link_id' => $uniqueLinkId,
        'launch_url' => $launchUrl,
        'recipient' => [
            'id' => $recipient['id'],
            'email' => $recipient['email'],
            'name' => trim($recipient['first_name'] . ' ' . $recipient['last_name'])
        ],
        'content' => [
            'id' => $content['id'],
            'title' => $content['title']
        ]
    ], 'Launch link created successfully');

} catch (Exception $e) {
    error_log("Create launch link error: " . $e->getMessage());
    errorResponse('Failed to create launch link: ' . $e->getMessage(), 500);
}
