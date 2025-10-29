# OMS Launch - Headless PHP Content Platform

A headless PHP-based content management and tracking platform for educational and training content. Supports SCORM packages, HTML content, and videos with comprehensive interaction tracking and AI-powered content tagging.

## Features

- **Multi-format Content Support**: SCORM packages, HTML zips, raw HTML, and videos (MP4)
- **AI-Powered Tagging**: Automatic content analysis and tagging using Claude API
- **Interaction Tracking**: Track user interactions with tagged content elements
- **SCORM API Hijacking**: Intercept and track SCORM API calls for standardized content
- **Event Publishing**: Publish tracking events to AWS SNS
- **Launch Links**: Generate unique tracking links for recipients
- **Score Tracking**: Track completion scores and tag-specific performance

## Project Structure

```
oms_launch/
├── api/                          # API endpoints
│   ├── create_launch_link.php    # Create content launch links
│   ├── track_completion.php      # Track content completion
│   ├── track_view.php            # Track content views
│   └── upload_content.php        # Upload and process content
├── config/
│   └── config.php                # Configuration file
├── content/                      # Processed content storage
├── includes/
│   ├── claude_api.php            # Claude API integration
│   ├── content_processor.php     # Content processing logic
│   ├── db.php                    # Database connection
│   ├── sns.php                   # AWS SNS integration
│   └── utils.php                 # Utility functions
├── js/
│   └── tracking.js               # Client-side tracking JavaScript
├── logs/                         # Application logs
├── uploads/                      # Temporary upload storage
│   ├── html/
│   ├── scorm/
│   └── video/
├── launch.php                    # Content player/launcher
└── schema.sql                    # PostgreSQL database schema

```

## Requirements

- PHP 7.4 or higher
- PostgreSQL 12 or higher
- PHP Extensions:
  - PDO with PostgreSQL support
  - cURL
  - JSON
  - ZIP
  - mbstring

## Installation

### 1. Clone the Repository

```bash
cd /path/to/webroot
git clone <repository-url> oms_launch
cd oms_launch
```

### 2. Set Up PostgreSQL Database

```bash
# Create database
createdb oms_launch

# Import schema
psql -d oms_launch -f schema.sql
```

### 3. Configure the Application

Edit `config/config.php` or set environment variables:

```bash
# Database
export DB_HOST=localhost
export DB_PORT=5432
export DB_NAME=oms_launch
export DB_USER=postgres
export DB_PASS=your_password

# Claude API
export CLAUDE_API_KEY=your_claude_api_key

# AWS SNS
export AWS_REGION=us-east-1
export AWS_ACCESS_KEY=your_aws_access_key
export AWS_SECRET_KEY=your_aws_secret_key
export SNS_TOPIC_ARN=arn:aws:sns:region:account:topic-name

# Application
export BASE_URL=http://your-domain.com
export SECRET_KEY=your_secret_key_here
export DEBUG_MODE=false
```

### 4. Set Permissions

```bash
chmod 755 api/ includes/ js/
chmod 777 uploads/ content/ logs/
```

### 5. Web Server Configuration

#### Apache (.htaccess)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    # API endpoints
    RewriteRule ^api/(.*)$ api/$1 [L]

    # Launch page
    RewriteRule ^launch$ launch.php [L]
</IfModule>
```

#### Nginx

```nginx
location /api/ {
    try_files $uri $uri/ /api/$uri.php?$query_string;
}

location /launch {
    try_files $uri /launch.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
}
```

## Usage

### 1. Upload Content

**Upload SCORM Package:**

```bash
curl -X POST http://your-domain.com/api/upload_content.php \
  -F "account_id=1" \
  -F "title=Phishing Training" \
  -F "description=Learn to identify phishing emails" \
  -F "content_type=training" \
  -F "upload_type=scorm" \
  -F "file=@/path/to/scorm.zip"
```

**Upload HTML ZIP:**

```bash
curl -X POST http://your-domain.com/api/upload_content.php \
  -F "account_id=1" \
  -F "title=Security Awareness" \
  -F "upload_type=html_zip" \
  -F "file=@/path/to/content.zip"
```

**Upload Raw HTML:**

```bash
curl -X POST http://your-domain.com/api/upload_content.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "account_id=1" \
  -d "title=Quick Quiz" \
  -d "upload_type=raw_html" \
  -d "html_content=<html><body><h1>Quiz</h1>...</body></html>"
```

**Upload Video:**

```bash
curl -X POST http://your-domain.com/api/upload_content.php \
  -F "account_id=1" \
  -F "title=Security Video" \
  -F "upload_type=video" \
  -F "file=@/path/to/video.mp4"
```

### 2. Create Launch Link

```bash
curl -X POST http://your-domain.com/api/create_launch_link.php \
  -H "Content-Type: application/json" \
  -d '{
    "recipient_id": 1,
    "content_id": 1
  }'
```

Response:
```json
{
  "success": true,
  "message": "Launch link created successfully",
  "data": {
    "tracking_link_id": 123,
    "unique_link_id": "abc123...",
    "launch_url": "http://your-domain.com/launch.php?id=abc123...",
    "recipient": {
      "id": 1,
      "email": "user@example.com",
      "name": "John Doe"
    },
    "content": {
      "id": 1,
      "title": "Phishing Training"
    }
  }
}
```

### 3. View Content

Recipients access content via the launch URL:
```
http://your-domain.com/launch.php?id=abc123...
```

The platform automatically:
- Tracks when content is viewed
- Monitors interactions with tagged elements
- Captures SCORM API calls
- Records completion and scores
- Publishes events to AWS SNS

## How It Works

### Content Processing Pipeline

1. **Upload**: Content is uploaded via API
2. **Extraction**: ZIP files are extracted to content directory
3. **Analysis**: HTML is sent to Claude API for interaction tagging
4. **Tagging**: Interactive elements get `data-tag` attributes
5. **Injection**: Tracking JavaScript is injected into content
6. **Conversion**: `index.html` becomes `index.php`
7. **Storage**: Tags are stored in database

### Interaction Tracking

The injected JavaScript:
- Detects interactions with tagged elements
- Hijacks SCORM API calls (`RecordTest`, `SetValue`, etc.)
- Builds interaction data structure
- Sends tracking events to API endpoints
- Publishes events to AWS SNS

### SCORM API Hijacking

The platform creates fake SCORM API objects:
- `window.API` (SCORM 1.2)
- `window.API_1484_11` (SCORM 2004)
- `window.RecordTest(score)` (custom function)

All calls are intercepted and tracked.

## Database Schema

### Key Tables

- **accounts**: Customer accounts
- **users**: Platform users (admin/manager)
- **recipients**: Content recipients
- **content**: Content library
- **content_tags**: AI-extracted tags
- **oms_tracking_links**: Launch links and tracking
- **recipient_tag_scores**: Tag-specific performance scores
- **deployments**: Content deployment campaigns
- **deployment_tracking**: Campaign tracking

## API Reference

### POST /api/upload_content.php

Upload and process content.

**Parameters:**
- `account_id` (required): Account ID
- `title` (required): Content title
- `description`: Content description
- `content_type`: training|simulation_landing|direct_email_body
- `upload_type` (required): scorm|html_zip|raw_html|video
- `file`: File upload (for scorm, html_zip, video)
- `html_content`: HTML string (for raw_html)

### POST /api/create_launch_link.php

Create a tracking link for a recipient.

**Body (JSON):**
```json
{
  "recipient_id": 1,
  "content_id": 1
}
```

### POST /api/track_view.php

Track content view (called automatically by launch.php).

**Body (JSON):**
```json
{
  "tracking_link_id": "unique_link_id"
}
```

### POST /api/track_completion.php

Track content completion (called by tracking JavaScript).

**Body (JSON):**
```json
{
  "tracking_link_id": "unique_link_id",
  "score": 85,
  "interactions": [
    {
      "tag": "phishing",
      "timestamp": "2024-01-01T12:00:00Z",
      "element_type": "input",
      "value": "user_answer"
    }
  ]
}
```

## Data Tagging

The Claude API analyzes HTML and adds `data-tag` attributes to interactive elements:

**Before:**
```html
<input type="text" name="password">
<button onclick="checkAnswer()">Submit</button>
```

**After:**
```html
<input type="text" name="password" data-tag="password_security">
<button onclick="checkAnswer()" data-tag="quiz_interaction">Submit</button>
```

Tags represent topics like:
- `phishing`
- `password_security`
- `ransomware`
- `social_engineering`
- `data_protection`

## SNS Event Format

### View Event
```json
{
  "event_type": "content_viewed",
  "recipient_id": 1,
  "content_id": 1,
  "tracking_link_id": "abc123",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

### Interaction Event
```json
{
  "event_type": "interaction",
  "recipient_id": 1,
  "content_id": 1,
  "tracking_link_id": "abc123",
  "tag": "phishing",
  "success": true,
  "timestamp": "2024-01-01T12:00:00Z"
}
```

### Completion Event
```json
{
  "event_type": "content_completed",
  "recipient_id": 1,
  "content_id": 1,
  "tracking_link_id": "abc123",
  "score": 85,
  "interactions": [...],
  "timestamp": "2024-01-01T12:00:00Z"
}
```

## Security Considerations

1. **Input Validation**: All API endpoints validate input
2. **SQL Injection**: Using prepared statements with PDO
3. **File Upload**: Validates file types and sizes
4. **Path Traversal**: Sanitizes filenames
5. **XSS**: Content is sandboxed (consider iframe isolation)
6. **API Keys**: Store in environment variables, never in code

## Troubleshooting

### Content Not Processing

Check logs:
```bash
tail -f logs/app.log
```

Verify Claude API key:
```bash
echo $CLAUDE_API_KEY
```

### Database Connection Failed

Test connection:
```bash
psql -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME
```

### SNS Events Not Publishing

Verify AWS credentials:
```bash
aws sns list-topics --region $AWS_REGION
```

### File Permissions

```bash
# Upload directories
chmod 777 uploads/ content/ logs/

# Code files
chmod 644 api/*.php includes/*.php
```

## Development

### Running Locally

```bash
# Using PHP built-in server
php -S localhost:8000

# Access at http://localhost:8000
```

### Testing Content Upload

```bash
# Create test account
psql -d oms_launch -c "INSERT INTO accounts (name) VALUES ('Test Account') RETURNING id;"

# Create test recipient
psql -d oms_launch -c "INSERT INTO recipients (account_id, email, first_name, last_name) VALUES (1, 'test@example.com', 'Test', 'User') RETURNING id;"
```

## License

Proprietary - All Rights Reserved

## Support

For issues and questions, contact your system administrator.
