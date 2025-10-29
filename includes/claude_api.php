<?php
/**
 * Claude API integration for content tagging
 */

require_once __DIR__ . '/../config/config.php';

class ClaudeAPI {
    private $apiKey;
    private $apiUrl;
    private $model;

    public function __construct() {
        $this->apiKey = CLAUDE_API_KEY;
        $this->apiUrl = CLAUDE_API_URL;
        $this->model = CLAUDE_MODEL;

        if (empty($this->apiKey)) {
            throw new Exception("Claude API key not configured");
        }
    }

    /**
     * Send request to Claude API
     */
    private function sendRequest($messages, $maxTokens = 4096) {
        $data = [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'messages' => $messages
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Claude API request failed with status $httpCode: $response");
            throw new Exception("Claude API request failed");
        }

        $result = json_decode($response, true);

        if (!isset($result['content'][0]['text'])) {
            throw new Exception("Invalid Claude API response");
        }

        return $result['content'][0]['text'];
    }

    /**
     * Tag HTML content with data-tag attributes for interactive elements
     * Returns modified HTML and array of discovered tags
     */
    public function tagHtmlContent($html) {
        $prompt = <<<EOT
You are analyzing HTML content to identify interactive learning elements and tag them with topics.

Your task:
1. Analyze the HTML content for interactive elements like:
   - Form inputs (text, radio, checkbox, select)
   - Buttons with actions
   - Quiz questions
   - Interactive simulations
   - Clickable elements that test knowledge

2. For each interactive element that tests knowledge or covers a specific topic:
   - Add a data-tag attribute with a relevant topic/tag (e.g., data-tag="phishing", data-tag="password_security")
   - Use lowercase with underscores for multi-word tags
   - Be specific and relevant to cybersecurity/training topics

3. Return the modified HTML with data-tag attributes added

4. After the HTML, on a new line starting with "TAGS:", list all unique tags you added (comma-separated)

Example:
Input: <input type="text" name="password">
Output: <input type="text" name="password" data-tag="password_security">

Input: <button onclick="checkAnswer()">Submit Answer</button>
Output: <button onclick="checkAnswer()" data-tag="quiz_interaction">Submit Answer</button>

Only add data-tag to elements that represent actual learning interactions or knowledge checks.
Do not add data-tag to purely navigational or decorative elements.

Here is the HTML to analyze:

EOT;

        $messages = [
            [
                'role' => 'user',
                'content' => $prompt . "\n\n" . $html
            ]
        ];

        try {
            $response = $this->sendRequest($messages, 8192);

            // Extract tags from response
            $tags = [];
            if (preg_match('/TAGS:\s*(.+)$/m', $response, $matches)) {
                $tagString = trim($matches[1]);
                $tags = array_map('trim', explode(',', $tagString));
                // Remove the TAGS line from the HTML
                $response = preg_replace('/\nTAGS:\s*.+$/m', '', $response);
            }

            return [
                'html' => trim($response),
                'tags' => $tags
            ];
        } catch (Exception $e) {
            error_log("Claude API tagging failed: " . $e->getMessage());
            // Return original HTML if tagging fails
            return [
                'html' => $html,
                'tags' => []
            ];
        }
    }

    /**
     * Extract topics/tags from content description or title
     */
    public function extractTopics($text) {
        $prompt = <<<EOT
Extract the main cybersecurity/training topics from this text.
Return only a comma-separated list of topic tags (lowercase with underscores).

Examples:
- "phishing"
- "ransomware"
- "password_security"
- "social_engineering"
- "data_protection"

Text:
EOT;

        $messages = [
            [
                'role' => 'user',
                'content' => $prompt . "\n\n" . $text
            ]
        ];

        try {
            $response = $this->sendRequest($messages, 256);
            $tags = array_map('trim', explode(',', trim($response)));
            return $tags;
        } catch (Exception $e) {
            error_log("Claude API topic extraction failed: " . $e->getMessage());
            return [];
        }
    }
}
