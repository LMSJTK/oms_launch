<?php
/**
 * Content Launch Player
 * Displays content to recipients and tracks their interactions
 */

session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/utils.php';

// Get tracking link ID from query parameter
$uniqueLinkId = $_GET['id'] ?? null;

if (!$uniqueLinkId) {
    die('Invalid launch link');
}

try {
    $db = db();

    // Get tracking link and related data
    $sql = "SELECT
                tl.id,
                tl.recipient_id,
                tl.content_id,
                tl.unique_link_id,
                tl.status,
                c.title,
                c.content_type,
                c.upload_type,
                c.content_identifier,
                r.first_name,
                r.last_name,
                r.email
            FROM oms_tracking_links tl
            JOIN content c ON tl.content_id = c.id
            JOIN recipients r ON tl.recipient_id = r.id
            WHERE tl.unique_link_id = ?";

    $data = $db->fetchOne($sql, [$uniqueLinkId]);

    if (!$data) {
        die('Launch link not found');
    }

    // Store tracking info in session
    $_SESSION['tracking_link_id'] = $data['unique_link_id'];
    $_SESSION['recipient_id'] = $data['recipient_id'];
    $_SESSION['content_id'] = $data['content_id'];

    // Determine content path
    $contentPath = CONTENT_DIR . '/' . $data['content_identifier'];

    // Handle different content types
    if ($data['upload_type'] === 'video') {
        // Display video player
        displayVideoPlayer($data);
    } else {
        // Display HTML/SCORM content
        displayHtmlContent($data, $contentPath);
    }

} catch (Exception $e) {
    error_log("Launch error: " . $e->getMessage());
    die('Error loading content');
}

/**
 * Display video player
 */
function displayVideoPlayer($data) {
    $videoUrl = BASE_URL . '/uploads/' . $data['content_identifier'];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($data['title']); ?></title>
        <style>
            body {
                margin: 0;
                padding: 20px;
                font-family: Arial, sans-serif;
                background: #f5f5f5;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            h1 {
                margin-top: 0;
                color: #333;
            }
            video {
                width: 100%;
                max-width: 100%;
                border-radius: 4px;
            }
            .completion-button {
                margin-top: 20px;
                padding: 12px 24px;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                cursor: pointer;
            }
            .completion-button:hover {
                background: #0056b3;
            }
            .completion-button:disabled {
                background: #ccc;
                cursor: not-allowed;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1><?php echo htmlspecialchars($data['title']); ?></h1>
            <video id="contentVideo" controls>
                <source src="<?php echo htmlspecialchars($videoUrl); ?>" type="video/mp4">
                Your browser does not support the video tag.
            </video>
            <button id="completeButton" class="completion-button" disabled>Mark as Complete</button>
        </div>

        <script>
            window.OMS_TRACKING = {
                contentId: <?php echo $data['content_id']; ?>,
                trackingLinkId: '<?php echo $data['unique_link_id']; ?>',
                recipientId: <?php echo $data['recipient_id']; ?>,
                apiBase: '<?php echo BASE_URL; ?>/api',
                interactions: [],
                initialized: false
            };

            const video = document.getElementById('contentVideo');
            const completeButton = document.getElementById('completeButton');
            const API_BASE = window.OMS_TRACKING.apiBase;

            // Track view on load
            fetch(API_BASE + '/track_view.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tracking_link_id: window.OMS_TRACKING.trackingLinkId })
            });

            // Enable complete button when video ends
            video.addEventListener('ended', function() {
                completeButton.disabled = false;
            });

            // Handle completion
            completeButton.addEventListener('click', function() {
                fetch(API_BASE + '/track_completion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tracking_link_id: window.OMS_TRACKING.trackingLinkId,
                        score: 100,
                        interactions: []
                    })
                })
                .then(response => response.json())
                .then(data => {
                    alert('Video completed successfully!');
                    completeButton.disabled = true;
                })
                .catch(err => console.error('Failed to record completion:', err));
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Display HTML/SCORM content
 */
function displayHtmlContent($data, $contentPath) {
    if (!file_exists($contentPath)) {
        die('Content file not found');
    }

    // Include the content (which is now index.php with tracking injected)
    include $contentPath;
    exit;
}
