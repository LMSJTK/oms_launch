<?php
/**
 * AWS SNS integration for event publishing
 */

require_once __DIR__ . '/../config/config.php';

class SNSPublisher {
    private $region;
    private $accessKey;
    private $secretKey;
    private $topicArn;

    public function __construct() {
        $this->region = AWS_REGION;
        $this->accessKey = AWS_ACCESS_KEY;
        $this->secretKey = AWS_SECRET_KEY;
        $this->topicArn = SNS_TOPIC_ARN;

        if (empty($this->topicArn)) {
            error_log("SNS Topic ARN not configured - events will not be published");
        }
    }

    /**
     * Sign AWS request (AWS Signature Version 4)
     */
    private function signRequest($method, $service, $host, $uri, $queryString, $payload, $headers) {
        $algorithm = 'AWS4-HMAC-SHA256';
        $timestamp = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');

        // Task 1: Create canonical request
        $canonicalHeaders = '';
        $signedHeaders = '';
        ksort($headers);
        foreach ($headers as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
            $signedHeaders .= strtolower($key) . ';';
        }
        $signedHeaders = rtrim($signedHeaders, ';');

        $canonicalRequest = $method . "\n" . $uri . "\n" . $queryString . "\n" .
                          $canonicalHeaders . "\n" . $signedHeaders . "\n" .
                          hash('sha256', $payload);

        // Task 2: Create string to sign
        $credentialScope = $dateStamp . '/' . $this->region . '/' . $service . '/aws4_request';
        $stringToSign = $algorithm . "\n" . $timestamp . "\n" . $credentialScope . "\n" .
                       hash('sha256', $canonicalRequest);

        // Task 3: Calculate signature
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        // Task 4: Create authorization header
        $authorizationHeader = $algorithm . ' Credential=' . $this->accessKey . '/' . $credentialScope .
                              ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

        return [
            'Authorization' => $authorizationHeader,
            'X-Amz-Date' => $timestamp
        ];
    }

    /**
     * Publish message to SNS topic
     */
    public function publish($message, $subject = null, $attributes = []) {
        if (empty($this->topicArn)) {
            error_log("SNS publish skipped - Topic ARN not configured");
            return false;
        }

        try {
            $host = "sns.{$this->region}.amazonaws.com";
            $endpoint = "https://{$host}/";

            $params = [
                'Action' => 'Publish',
                'TopicArn' => $this->topicArn,
                'Message' => is_array($message) ? json_encode($message) : $message,
                'Version' => '2010-03-31'
            ];

            if ($subject) {
                $params['Subject'] = $subject;
            }

            // Add message attributes
            $attrIndex = 1;
            foreach ($attributes as $key => $value) {
                $params["MessageAttributes.entry.{$attrIndex}.Name"] = $key;
                $params["MessageAttributes.entry.{$attrIndex}.Value.DataType"] = 'String';
                $params["MessageAttributes.entry.{$attrIndex}.Value.StringValue"] = $value;
                $attrIndex++;
            }

            $queryString = http_build_query($params);
            $payload = '';

            $headers = [
                'Host' => $host,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ];

            $authHeaders = $this->signRequest('POST', 'sns', $host, '/', '', $queryString, $headers);
            $headers = array_merge($headers, $authHeaders);

            $headerArray = [];
            foreach ($headers as $key => $value) {
                $headerArray[] = "{$key}: {$value}";
            }

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log("SNS publish failed with status {$httpCode}: {$response}");
                return false;
            }

            return true;
        } catch (Exception $e) {
            error_log("SNS publish error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish content view event
     */
    public function publishViewEvent($recipientId, $contentId, $trackingLinkId) {
        $message = [
            'event_type' => 'content_viewed',
            'recipient_id' => $recipientId,
            'content_id' => $contentId,
            'tracking_link_id' => $trackingLinkId,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
        ];

        return $this->publish($message, 'Content Viewed', [
            'event_type' => 'content_viewed'
        ]);
    }

    /**
     * Publish interaction event
     */
    public function publishInteractionEvent($recipientId, $contentId, $trackingLinkId, $tag, $success) {
        $message = [
            'event_type' => 'interaction',
            'recipient_id' => $recipientId,
            'content_id' => $contentId,
            'tracking_link_id' => $trackingLinkId,
            'tag' => $tag,
            'success' => $success,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
        ];

        return $this->publish($message, 'Content Interaction', [
            'event_type' => 'interaction',
            'tag' => $tag
        ]);
    }

    /**
     * Publish completion event
     */
    public function publishCompletionEvent($recipientId, $contentId, $trackingLinkId, $score, $interactions) {
        $message = [
            'event_type' => 'content_completed',
            'recipient_id' => $recipientId,
            'content_id' => $contentId,
            'tracking_link_id' => $trackingLinkId,
            'score' => $score,
            'interactions' => $interactions,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
        ];

        return $this->publish($message, 'Content Completed', [
            'event_type' => 'content_completed',
            'score' => (string)$score
        ]);
    }
}
