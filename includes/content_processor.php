<?php
/**
 * Content processing - handles post-upload processing of content
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/claude_api.php';

class ContentProcessor {
    private $db;
    private $claudeAPI;

    public function __construct() {
        $this->db = db()->getConnection();
        $this->claudeAPI = new ClaudeAPI();
    }

    /**
     * Process uploaded content
     */
    public function processContent($contentId, $uploadType, $sourcePath) {
        try {
            $this->db->beginTransaction();

            switch ($uploadType) {
                case 'scorm':
                    $result = $this->processSCORM($contentId, $sourcePath);
                    break;

                case 'html_zip':
                    $result = $this->processHTMLZip($contentId, $sourcePath);
                    break;

                case 'raw_html':
                    $result = $this->processRawHTML($contentId, $sourcePath);
                    break;

                case 'video':
                    $result = $this->processVideo($contentId, $sourcePath);
                    break;

                default:
                    throw new Exception("Unknown upload type: $uploadType");
            }

            $this->db->commit();
            return $result;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Content processing failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process SCORM package
     */
    private function processSCORM($contentId, $zipPath) {
        // Create extraction directory
        $extractPath = CONTENT_DIR . '/' . $contentId;

        if (!extractZip($zipPath, $extractPath)) {
            throw new Exception("Failed to extract SCORM package");
        }

        // Find index.html
        $indexPath = findIndexHtml($extractPath);

        if (!$indexPath) {
            throw new Exception("Could not find index.html in SCORM package");
        }

        // Process the HTML file
        return $this->processHTMLFile($contentId, $indexPath);
    }

    /**
     * Process HTML ZIP package
     */
    private function processHTMLZip($contentId, $zipPath) {
        // Create extraction directory
        $extractPath = CONTENT_DIR . '/' . $contentId;

        if (!extractZip($zipPath, $extractPath)) {
            throw new Exception("Failed to extract HTML package");
        }

        // Find index.html
        $indexPath = findIndexHtml($extractPath);

        if (!$indexPath) {
            throw new Exception("Could not find index.html in package");
        }

        // Process the HTML file
        return $this->processHTMLFile($contentId, $indexPath);
    }

    /**
     * Process raw HTML content
     */
    private function processRawHTML($contentId, $htmlContent) {
        // Create content directory
        $contentDir = CONTENT_DIR . '/' . $contentId;
        if (!file_exists($contentDir)) {
            mkdir($contentDir, 0755, true);
        }

        // Save HTML to file
        $indexPath = $contentDir . '/index.html';
        file_put_contents($indexPath, $htmlContent);

        // Process the HTML file
        return $this->processHTMLFile($contentId, $indexPath);
    }

    /**
     * Process video file
     */
    private function processVideo($contentId, $videoPath) {
        // Videos don't need processing, just store the path
        // The video is already in the uploads directory

        return [
            'processed' => true,
            'content_path' => $videoPath,
            'tags' => []
        ];
    }

    /**
     * Process HTML file - tag with Claude API and inject tracking code
     */
    private function processHTMLFile($contentId, $indexPath) {
        // Read HTML content
        $html = file_get_contents($indexPath);

        if ($html === false) {
            throw new Exception("Failed to read HTML file");
        }

        // Tag HTML with Claude API
        $taggedResult = $this->claudeAPI->tagHtmlContent($html);
        $taggedHtml = $taggedResult['html'];
        $tags = $taggedResult['tags'];

        // Inject tracking JavaScript
        $finalHtml = $this->injectTrackingCode($taggedHtml, $contentId);

        // Rename to index.php
        $phpPath = str_replace('.html', '.php', $indexPath);

        // Write modified content
        file_put_contents($phpPath, $finalHtml);

        // Delete original HTML file if different from PHP file
        if ($phpPath !== $indexPath && file_exists($indexPath)) {
            unlink($indexPath);
        }

        // Save tags to database
        $this->saveTags($contentId, $tags);

        // Update content record with relative path
        $relativePath = str_replace(CONTENT_DIR . '/', '', $phpPath);
        $stmt = $this->db->prepare("UPDATE content SET content_identifier = ? WHERE id = ?");
        $stmt->execute([$relativePath, $contentId]);

        return [
            'processed' => true,
            'content_path' => $relativePath,
            'tags' => $tags
        ];
    }

    /**
     * Inject tracking JavaScript into HTML
     */
    private function injectTrackingCode($html, $contentId) {
        // Read the tracking script
        $trackingScript = file_get_contents(__DIR__ . '/../js/tracking.js');

        // Create initialization script
        $initScript = <<<SCRIPT

<script>
// OMS Launch tracking initialization
window.OMS_TRACKING = {
    contentId: {$contentId},
    trackingLinkId: null,
    recipientId: null,
    interactions: [],
    initialized: false
};

// Initialize from PHP session/parameters
<?php
if (isset(\$_SESSION['tracking_link_id'])) {
    echo "window.OMS_TRACKING.trackingLinkId = '" . \$_SESSION['tracking_link_id'] . "';";
}
if (isset(\$_SESSION['recipient_id'])) {
    echo "window.OMS_TRACKING.recipientId = '" . \$_SESSION['recipient_id'] . "';";
}
?>
</script>

<script>
{$trackingScript}
</script>

SCRIPT;

        // Try to inject before </body> tag
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $initScript . '</body>', $html);
        } else {
            // If no body tag, append to end
            $html .= $initScript;
        }

        return $html;
    }

    /**
     * Save tags to database
     */
    private function saveTags($contentId, $tags) {
        if (empty($tags)) {
            return;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO content_tags (content_id, tag_name) VALUES (?, ?)
             ON CONFLICT (content_id, tag_name) DO NOTHING"
        );

        foreach ($tags as $tag) {
            if (!empty($tag)) {
                $stmt->execute([$contentId, trim($tag)]);
            }
        }
    }

    /**
     * Get content tags
     */
    public function getContentTags($contentId) {
        $stmt = $this->db->prepare("SELECT tag_name FROM content_tags WHERE content_id = ?");
        $stmt->execute([$contentId]);

        $tags = [];
        while ($row = $stmt->fetch()) {
            $tags[] = $row['tag_name'];
        }

        return $tags;
    }
}
