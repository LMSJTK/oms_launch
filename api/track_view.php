<?php
/**
 * Track View API
 * Records when content is viewed
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/sns.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

try {
    $input = getJsonInput();

    // Validate required fields
    validateRequired($input, ['tracking_link_id']);

    $trackingLinkId = $input['tracking_link_id'];

    $db = db();

    // Get tracking link record
    $trackingLink = $db->fetchOne(
        "SELECT id, recipient_id, content_id, status FROM oms_tracking_links WHERE unique_link_id = ?",
        [$trackingLinkId]
    );

    if (!$trackingLink) {
        errorResponse('Tracking link not found', 404);
    }

    // Update status and viewed_at timestamp
    $sql = "UPDATE oms_tracking_links
            SET status = 'VIEWED',
                viewed_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE unique_link_id = ? AND viewed_at IS NULL";

    $db->update($sql, [$trackingLinkId]);

    // Publish view event to SNS
    try {
        $sns = new SNSPublisher();
        $sns->publishViewEvent(
            $trackingLink['recipient_id'],
            $trackingLink['content_id'],
            $trackingLinkId
        );
    } catch (Exception $e) {
        error_log("SNS publish failed: " . $e->getMessage());
        // Don't fail the request if SNS publish fails
    }

    successResponse([
        'tracking_link_id' => $trackingLinkId,
        'status' => 'VIEWED'
    ], 'View tracked successfully');

} catch (Exception $e) {
    error_log("Track view error: " . $e->getMessage());
    errorResponse('Failed to track view: ' . $e->getMessage(), 500);
}
