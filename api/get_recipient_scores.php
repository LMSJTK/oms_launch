<?php
/**
 * Get Recipient Tag Scores API
 * Returns tag-specific scores for a recipient
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/utils.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

try {
    $recipientId = $_GET['recipient_id'] ?? null;

    if (!$recipientId) {
        errorResponse('Missing required parameter: recipient_id');
    }

    $db = db();

    // Get recipient info
    $recipient = $db->fetchOne(
        "SELECT id, email, first_name, last_name FROM recipients WHERE id = ?",
        [$recipientId]
    );

    if (!$recipient) {
        errorResponse('Recipient not found', 404);
    }

    // Get tag scores for recipient
    $tagScores = $db->fetchAll(
        "SELECT
            rts.id,
            t.id as tag_id,
            t.tag_name,
            rts.score,
            rts.attempts,
            rts.last_updated_at,
            ROUND((rts.score::numeric / NULLIF(rts.attempts, 0) * 100), 2) as success_rate
         FROM recipient_tag_scores rts
         JOIN tags t ON t.id = rts.tag_id
         WHERE rts.recipient_id = ?
         ORDER BY rts.last_updated_at DESC",
        [$recipientId]
    );

    // Get overall statistics
    $totalScore = 0;
    $totalAttempts = 0;
    foreach ($tagScores as $tagScore) {
        $totalScore += $tagScore['score'];
        $totalAttempts += $tagScore['attempts'];
    }

    $overallSuccessRate = $totalAttempts > 0 ? round(($totalScore / $totalAttempts) * 100, 2) : 0;

    successResponse([
        'recipient' => [
            'id' => $recipient['id'],
            'email' => $recipient['email'],
            'name' => trim($recipient['first_name'] . ' ' . $recipient['last_name'])
        ],
        'tag_scores' => $tagScores,
        'statistics' => [
            'total_tags_attempted' => count($tagScores),
            'total_score' => $totalScore,
            'total_attempts' => $totalAttempts,
            'overall_success_rate' => $overallSuccessRate
        ]
    ]);

} catch (Exception $e) {
    error_log("Get recipient scores error: " . $e->getMessage());
    errorResponse('Failed to retrieve recipient scores: ' . $e->getMessage(), 500);
}
