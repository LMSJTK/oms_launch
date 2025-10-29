<?php
/**
 * Create Test Data
 * Creates sample accounts, recipients, and content for testing
 */

require_once __DIR__ . '/includes/db.php';

echo "OMS Launch Platform - Create Test Data\n";
echo "======================================\n\n";

try {
    $db = db();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    // Create test account
    echo "Creating test account... ";
    $sql = "INSERT INTO accounts (name, description)
            VALUES ('Test Account', 'Sample account for testing')
            ON CONFLICT DO NOTHING
            RETURNING id";
    $result = $db->query($sql)->fetch();

    if ($result) {
        $accountId = $result['id'];
        echo "✓ (ID: {$accountId})\n";
    } else {
        // Account already exists, get it
        $existing = $db->fetchOne("SELECT id FROM accounts WHERE name = 'Test Account'");
        $accountId = $existing['id'];
        echo "✓ (Already exists, ID: {$accountId})\n";
    }

    // Create test user
    echo "Creating test user... ";
    $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (account_id, first_name, last_name, email, password, role)
            VALUES (?, 'Admin', 'User', 'admin@test.com', ?, 'admin')
            ON CONFLICT (email) DO NOTHING
            RETURNING id";
    $result = $db->query($sql, [$accountId, $hashedPassword])->fetch();

    if ($result) {
        $userId = $result['id'];
        echo "✓ (ID: {$userId}, email: admin@test.com, password: password123)\n";
    } else {
        $existing = $db->fetchOne("SELECT id FROM users WHERE email = 'admin@test.com'");
        $userId = $existing['id'];
        echo "✓ (Already exists, ID: {$userId})\n";
    }

    // Create test recipients
    echo "Creating test recipients... ";
    $recipients = [
        ['john.doe@test.com', 'John', 'Doe'],
        ['jane.smith@test.com', 'Jane', 'Smith'],
        ['bob.wilson@test.com', 'Bob', 'Wilson']
    ];

    $recipientIds = [];
    foreach ($recipients as $recipient) {
        $sql = "INSERT INTO recipients (account_id, email, first_name, last_name)
                VALUES (?, ?, ?, ?)
                ON CONFLICT (account_id, email) DO NOTHING
                RETURNING id";
        $result = $db->query($sql, [$accountId, $recipient[0], $recipient[1], $recipient[2]])->fetch();

        if ($result) {
            $recipientIds[] = $result['id'];
        } else {
            $existing = $db->fetchOne(
                "SELECT id FROM recipients WHERE account_id = ? AND email = ?",
                [$accountId, $recipient[0]]
            );
            $recipientIds[] = $existing['id'];
        }
    }
    echo "✓ (Created/found " . count($recipientIds) . " recipients)\n";

    // Create test recipient group
    echo "Creating test recipient group... ";
    $sql = "INSERT INTO recipient_groups (account_id, name, created_by_user_id)
            VALUES (?, 'Test Group', ?)
            ON CONFLICT DO NOTHING
            RETURNING id";
    $result = $db->query($sql, [$accountId, $userId])->fetch();

    if ($result) {
        $groupId = $result['id'];
        echo "✓ (ID: {$groupId})\n";

        // Add recipients to group
        echo "Adding recipients to group... ";
        foreach ($recipientIds as $recipientId) {
            $sql = "INSERT INTO recipient_group_members (recipient_group_id, recipient_id)
                    VALUES (?, ?)
                    ON CONFLICT DO NOTHING";
            $db->query($sql, [$groupId, $recipientId]);
        }
        echo "✓\n";
    } else {
        $existing = $db->fetchOne(
            "SELECT id FROM recipient_groups WHERE account_id = ? AND name = 'Test Group'",
            [$accountId]
        );
        $groupId = $existing['id'];
        echo "✓ (Already exists, ID: {$groupId})\n";
    }

    // Create sample HTML content
    echo "Creating sample content... ";
    $sampleHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Sample Training Content</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .question { margin: 20px 0; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Phishing Awareness Training</h1>

    <p>This is a sample training module. Answer the questions below:</p>

    <div class="question">
        <p><strong>1. What should you check in a suspicious email?</strong></p>
        <input type="text" name="answer1" placeholder="Your answer">
    </div>

    <div class="question">
        <p><strong>2. Have you ever received a phishing email?</strong></p>
        <label><input type="radio" name="answer2" value="yes"> Yes</label>
        <label><input type="radio" name="answer2" value="no"> No</label>
    </div>

    <button onclick="submitAnswers()">Submit</button>

    <script>
        function submitAnswers() {
            // This will be intercepted by our SCORM hijacking
            if (window.RecordTest) {
                window.RecordTest(85);
                alert('Training completed! Score: 85%');
            }
        }
    </script>
</body>
</html>
HTML;

    $sql = "INSERT INTO content (account_id, title, description, content_type, upload_type, content_identifier)
            VALUES (?, 'Sample Phishing Training', 'Test content for phishing awareness', 'training', 'raw_html', 'sample-content-1')
            ON CONFLICT DO NOTHING
            RETURNING id";
    $result = $db->query($sql, [$accountId])->fetch();

    if ($result) {
        $contentId = $result['id'];

        // Save sample HTML
        $contentDir = __DIR__ . '/content/' . $contentId;
        if (!file_exists($contentDir)) {
            mkdir($contentDir, 0755, true);
        }
        file_put_contents($contentDir . '/index.html', $sampleHtml);

        echo "✓ (ID: {$contentId})\n";
    } else {
        $existing = $db->fetchOne(
            "SELECT id FROM content WHERE account_id = ? AND title = 'Sample Phishing Training'",
            [$accountId]
        );
        $contentId = $existing['id'];
        echo "✓ (Already exists, ID: {$contentId})\n";
    }

    $pdo->commit();

    echo "\n======================================\n";
    echo "✓ Test data created successfully!\n\n";

    echo "Test Data Summary:\n";
    echo "- Account ID: {$accountId}\n";
    echo "- User: admin@test.com / password123\n";
    echo "- Recipients: " . count($recipientIds) . " created\n";
    echo "- Recipient Group ID: {$groupId}\n";
    echo "- Sample Content ID: {$contentId}\n\n";

    echo "To create a launch link:\n";
    echo "curl -X POST http://localhost:8000/api/create_launch_link.php \\\n";
    echo "  -H 'Content-Type: application/json' \\\n";
    echo "  -d '{\"recipient_id\": {$recipientIds[0]}, \"content_id\": {$contentId}}'\n\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
