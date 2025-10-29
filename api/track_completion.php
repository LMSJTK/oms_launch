<?php
/**
 * Track Completion API
 * Records when content is completed with score and interactions
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
    $score = $input['score'] ?? 0;
    $interactions = $input['interactions'] ?? [];

    $db = db();
    $db->beginTransaction();

    // Get tracking link record
    $trackingLink = $db->fetchOne(
        "SELECT id, recipient_id, content_id, status FROM oms_tracking_links WHERE unique_link_id = ?",
        [$trackingLinkId]
    );

    if (!$trackingLink) {
        $db->rollback();
        errorResponse('Tracking link not found', 404);
    }

    // Update status, score, and completion timestamp
    $sql = "UPDATE oms_tracking_links
            SET status = 'COMPLETED',
                score = ?,
                completed_at = CURRENT_TIMESTAMP,
                interaction_data = ?::jsonb,
                updated_at = CURRENT_TIMESTAMP
            WHERE unique_link_id = ?";

    $db->update($sql, [$score, json_encode($interactions), $trackingLinkId]);

    // Get content tags
    $contentTags = $db->fetchAll(
        "SELECT tag_name FROM content_tags WHERE content_id = ?",
        [$trackingLink['content_id']]
    );

    // Update recipient tag scores if they passed (score >= 70)
    $passed = $score >= 70;

    if ($passed && !empty($contentTags)) {
        foreach ($contentTags as $tagRow) {
            $tagName = $tagRow['tag_name'];

            // Update or insert recipient tag score
            $sql = "INSERT INTO recipient_tag_scores (recipient_id, tag_name, score, attempts, last_updated_at)
                    VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP)
                    ON CONFLICT (recipient_id, tag_name)
                    DO UPDATE SET
                        score = recipient_tag_scores.score + 1,
                        attempts = recipient_tag_scores.attempts + 1,
                        last_updated_at = CURRENT_TIMESTAMP";

            $db->query($sql, [$trackingLink['recipient_id'], $tagName, 1]);
        }
    }

    $db->commit();

    // Publish completion event to SNS
    try {
        $sns = new SNSPublisher();
        $sns->publishCompletionEvent(
            $trackingLink['recipient_id'],
            $trackingLink['content_id'],
            $trackingLinkId,
            $score,
            $interactions
        );
    } catch (Exception $e) {
        error_log("SNS publish failed: " . $e->getMessage());
        // Don't fail the request if SNS publish fails
    }

    successResponse([
        'tracking_link_id' => $trackingLinkId,
        'status' => 'COMPLETED',
        'score' => $score,
        'passed' => $passed,
        'interactions_recorded' => count($interactions)
    ], 'Completion tracked successfully');

} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollback();
    }
    error_log("Track completion error: " . $e->getMessage());
    errorResponse('Failed to track completion: ' . $e->getMessage(), 500);
}
