<?php
/**
 * Get Tags API
 * Returns all tags or tags for specific content
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/utils.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

try {
    $contentId = $_GET['content_id'] ?? null;
    $db = db();

    if ($contentId) {
        // Get tags for specific content
        $tags = $db->fetchAll(
            "SELECT t.id, t.tag_name, ct.created_at as associated_at
             FROM content_tags ct
             JOIN tags t ON t.id = ct.tag_id
             WHERE ct.content_id = ?
             ORDER BY t.tag_name",
            [$contentId]
        );

        successResponse([
            'content_id' => (int)$contentId,
            'tags' => $tags,
            'total' => count($tags)
        ]);
    } else {
        // Get all tags with content count
        $tags = $db->fetchAll(
            "SELECT
                t.id,
                t.tag_name,
                COUNT(ct.content_id) as content_count,
                t.created_at
             FROM tags t
             LEFT JOIN content_tags ct ON ct.tag_id = t.id
             GROUP BY t.id, t.tag_name, t.created_at
             ORDER BY t.tag_name"
        );

        successResponse([
            'tags' => $tags,
            'total' => count($tags)
        ]);
    }

} catch (Exception $e) {
    error_log("Get tags error: " . $e->getMessage());
    errorResponse('Failed to retrieve tags: ' . $e->getMessage(), 500);
}
