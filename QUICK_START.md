# Quick Start Guide

## 1. Install Dependencies

Ensure you have:
- PostgreSQL 12+
- PHP 7.4+ with extensions: pdo_pgsql, curl, json, zip, mbstring

## 2. Set Up Database

```bash
# Create database
createdb oms_launch

# Import schema
psql -d oms_launch -f schema.sql
```

## 3. Configure Environment

```bash
# Copy example environment file
cp .env.example .env

# Edit .env with your settings
nano .env
```

Minimum required settings:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `CLAUDE_API_KEY` (get from https://console.anthropic.com)
- `BASE_URL` (e.g., http://localhost:8000)

Optional:
- AWS SNS settings (for event publishing)

## 4. Set Permissions

```bash
chmod 777 uploads/ content/ logs/
```

## 5. Verify Installation

```bash
php test_setup.php
```

## 6. Create Test Data

```bash
php test_create_data.php
```

This creates:
- Test account
- Test user (admin@test.com / password123)
- 3 test recipients
- Sample content

## 7. Start Server

```bash
php -S localhost:8000
```

## 8. Test APIs

```bash
# Run automated tests
./test_api.sh http://localhost:8000

# Or manually test upload
curl -X POST http://localhost:8000/api/upload_content.php \
  -F "account_id=1" \
  -F "title=My Content" \
  -F "upload_type=raw_html" \
  -F "html_content=<html><body><h1>Test</h1></body></html>"
```

## Common Tasks

### Upload SCORM Package

```bash
curl -X POST http://localhost:8000/api/upload_content.php \
  -F "account_id=1" \
  -F "title=SCORM Training" \
  -F "upload_type=scorm" \
  -F "file=@path/to/package.zip"
```

### Create Launch Link

```bash
curl -X POST http://localhost:8000/api/create_launch_link.php \
  -H "Content-Type: application/json" \
  -d '{"recipient_id": 1, "content_id": 1}'
```

### View Database Records

```bash
# View uploaded content
psql -d oms_launch -c "SELECT id, title, upload_type, content_identifier FROM content;"

# View tracking links
psql -d oms_launch -c "SELECT id, recipient_id, content_id, status, score FROM oms_tracking_links;"

# View content tags
psql -d oms_launch -c "SELECT c.title, ct.tag_name FROM content c JOIN content_tags ct ON c.id = ct.content_id;"
```

## Troubleshooting

### "Database connection failed"
- Check PostgreSQL is running: `pg_isready`
- Verify credentials in .env file
- Test connection: `psql -h localhost -U postgres -d oms_launch`

### "Failed to extract ZIP"
- Ensure PHP zip extension is installed: `php -m | grep zip`
- Check file permissions on uploads/ directory

### "Claude API request failed"
- Verify API key is set: `echo $CLAUDE_API_KEY`
- Check API key is valid at https://console.anthropic.com

### Content not displaying
- Check content directory permissions: `ls -la content/`
- Verify content was processed: `SELECT content_identifier FROM content WHERE id = X;`
- Check logs: `tail -f logs/app.log`

## File Structure Reference

```
api/
  ├── upload_content.php       - Upload & process content
  ├── create_launch_link.php   - Generate tracking links
  ├── track_view.php           - Record content views
  └── track_completion.php     - Record completions

includes/
  ├── db.php                   - Database connection
  ├── utils.php                - Utility functions
  ├── claude_api.php           - Claude API integration
  ├── content_processor.php    - Content processing
  └── sns.php                  - AWS SNS integration

js/
  └── tracking.js              - Client-side tracking

config/
  └── config.php               - Configuration

launch.php                     - Content player
schema.sql                     - Database schema
```

## Next Steps

1. Review full documentation in README.md
2. Customize tracking JavaScript in js/tracking.js
3. Modify content processing logic in includes/content_processor.php
4. Set up production web server (Apache/Nginx)
5. Configure SSL/HTTPS
6. Set up automated backups
7. Configure monitoring and alerts
