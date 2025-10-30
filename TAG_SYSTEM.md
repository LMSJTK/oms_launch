# Tag System Documentation

## Overview

The OMS Launch platform uses a normalized tag system to categorize and track content topics. Tags are automatically detected by the Claude API during content processing and stored in a relational structure.

## Database Structure

### Tags Table

Stores unique tags across the entire platform.

```sql
CREATE TABLE tags (
  id SERIAL PRIMARY KEY,
  tag_name VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

**Example data:**
```
id | tag_name           | created_at
1  | phishing           | 2024-01-01 10:00:00
2  | password_security  | 2024-01-01 10:05:00
3  | ransomware         | 2024-01-01 10:10:00
```

### Content_Tags Table (Junction Table)

Links content to tags in a many-to-many relationship.

```sql
CREATE TABLE content_tags (
  id SERIAL PRIMARY KEY,
  content_id INTEGER NOT NULL REFERENCES content(id),
  tag_id INTEGER NOT NULL REFERENCES tags(id),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(content_id, tag_id)
);
```

**Example data:**
```
id | content_id | tag_id | created_at
1  | 1          | 1      | 2024-01-01 10:00:00
2  | 1          | 2      | 2024-01-01 10:00:00
3  | 2          | 1      | 2024-01-01 10:15:00
4  | 2          | 3      | 2024-01-01 10:15:00
```

This means:
- Content #1 has tags: phishing, password_security
- Content #2 has tags: phishing, ransomware

### Recipient_Tag_Scores Table

Tracks recipient performance on specific tags.

```sql
CREATE TABLE recipient_tag_scores (
  id SERIAL PRIMARY KEY,
  recipient_id INTEGER NOT NULL REFERENCES recipients(id),
  tag_id INTEGER NOT NULL REFERENCES tags(id),
  score INTEGER NOT NULL DEFAULT 0,
  attempts INTEGER NOT NULL DEFAULT 0,
  last_updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(recipient_id, tag_id)
);
```

**Example data:**
```
id | recipient_id | tag_id | score | attempts | success_rate
1  | 100         | 1      | 8     | 10       | 80%
2  | 100         | 2      | 5     | 7        | 71%
```

This means:
- Recipient #100 attempted phishing content 10 times, scored 8 (80% success)
- Recipient #100 attempted password_security content 7 times, scored 5 (71% success)

## How Tags Are Created

### Automatic Tagging During Upload

When content is uploaded, the following process occurs:

1. **Content Upload**: User uploads SCORM, HTML, or video content
2. **HTML Analysis**: Claude API analyzes the HTML for interactive elements
3. **Tag Extraction**: Claude identifies topics and adds `data-tag` attributes
4. **Tag Normalization**:
   ```php
   // For each detected tag:
   foreach ($tags as $tag) {
       // Step 1: Insert into tags table if doesn't exist
       INSERT INTO tags (tag_name) VALUES ('phishing')
       ON CONFLICT (tag_name) DO NOTHING;

       // Step 2: Get tag ID
       SELECT id FROM tags WHERE tag_name = 'phishing';

       // Step 3: Link to content
       INSERT INTO content_tags (content_id, tag_id) VALUES (1, 1)
       ON CONFLICT DO NOTHING;
   }
   ```

### Tag Naming Conventions

Tags follow these conventions:
- Lowercase only
- Underscores for multi-word tags
- No special characters
- Descriptive and specific

**Examples:**
- `phishing`
- `password_security`
- `social_engineering`
- `ransomware`
- `data_protection`
- `mobile_security`

## Using the Tag APIs

### Get All Tags

Returns all tags with content counts.

```bash
curl -X GET http://example.com/oms_launch/api/get_tags.php
```

**Response:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "tags": [
      {
        "id": 1,
        "tag_name": "phishing",
        "content_count": 5,
        "created_at": "2024-01-01 10:00:00"
      },
      {
        "id": 2,
        "tag_name": "password_security",
        "content_count": 3,
        "created_at": "2024-01-01 10:05:00"
      }
    ],
    "total": 2
  }
}
```

### Get Tags for Specific Content

```bash
curl -X GET "http://example.com/oms_launch/api/get_tags.php?content_id=1"
```

**Response:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "content_id": 1,
    "tags": [
      {
        "id": 1,
        "tag_name": "phishing",
        "associated_at": "2024-01-01 10:00:00"
      },
      {
        "id": 2,
        "tag_name": "password_security",
        "associated_at": "2024-01-01 10:00:00"
      }
    ],
    "total": 2
  }
}
```

### Get Recipient Tag Scores

Returns a recipient's performance on all tags they've attempted.

```bash
curl -X GET "http://example.com/oms_launch/api/get_recipient_scores.php?recipient_id=1"
```

**Response:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "recipient": {
      "id": 1,
      "email": "user@example.com",
      "name": "John Doe"
    },
    "tag_scores": [
      {
        "id": 1,
        "tag_id": 1,
        "tag_name": "phishing",
        "score": 8,
        "attempts": 10,
        "success_rate": 80.00,
        "last_updated_at": "2024-01-15 14:30:00"
      },
      {
        "id": 2,
        "tag_id": 2,
        "tag_name": "password_security",
        "score": 5,
        "attempts": 7,
        "success_rate": 71.43,
        "last_updated_at": "2024-01-10 09:15:00"
      }
    ],
    "statistics": {
      "total_tags_attempted": 2,
      "total_score": 13,
      "total_attempts": 17,
      "overall_success_rate": 76.47
    }
  }
}
```

## Tag Score Tracking

### How Scores Are Calculated

When a recipient completes content:

1. **Completion Check**: System checks if score >= 70 (passing threshold)
2. **Tag Lookup**: Retrieves all tags associated with the completed content
3. **Score Update**: For each tag, updates recipient_tag_scores:
   ```sql
   INSERT INTO recipient_tag_scores (recipient_id, tag_id, score, attempts)
   VALUES (1, 1, 1, 1)  -- Add 1 to score and attempts
   ON CONFLICT (recipient_id, tag_id)
   DO UPDATE SET
       score = recipient_tag_scores.score + 1,
       attempts = recipient_tag_scores.attempts + 1;
   ```

### Passing vs. Failing

- **Passing (score >= 70)**: Score AND attempts increment
- **Failing (score < 70)**: Only attempts increment (score stays same)

This allows calculation of success rate:
```
Success Rate = (score / attempts) × 100
```

## Database Queries

### Find All Content with a Specific Tag

```sql
SELECT c.id, c.title, c.description
FROM content c
JOIN content_tags ct ON ct.content_id = c.id
JOIN tags t ON t.id = ct.tag_id
WHERE t.tag_name = 'phishing'
ORDER BY c.created_at DESC;
```

### Get Top Performing Recipients on a Tag

```sql
SELECT
    r.id,
    r.email,
    r.first_name,
    r.last_name,
    rts.score,
    rts.attempts,
    ROUND((rts.score::numeric / rts.attempts * 100), 2) as success_rate
FROM recipient_tag_scores rts
JOIN recipients r ON r.id = rts.recipient_id
JOIN tags t ON t.id = rts.tag_id
WHERE t.tag_name = 'phishing'
ORDER BY success_rate DESC
LIMIT 10;
```

### Get Recipients Who Need Training on a Tag

Find recipients with low scores on a specific tag:

```sql
SELECT
    r.id,
    r.email,
    r.first_name,
    r.last_name,
    rts.score,
    rts.attempts,
    ROUND((rts.score::numeric / rts.attempts * 100), 2) as success_rate
FROM recipient_tag_scores rts
JOIN recipients r ON r.id = rts.recipient_id
JOIN tags t ON t.id = rts.tag_id
WHERE t.tag_name = 'phishing'
    AND (rts.score::numeric / rts.attempts) < 0.7  -- Less than 70%
ORDER BY success_rate ASC;
```

### Get Most Common Tags

```sql
SELECT
    t.tag_name,
    COUNT(ct.content_id) as content_count
FROM tags t
LEFT JOIN content_tags ct ON ct.tag_id = t.id
GROUP BY t.id, t.tag_name
ORDER BY content_count DESC
LIMIT 10;
```

### Get Average Success Rate Per Tag

```sql
SELECT
    t.tag_name,
    COUNT(DISTINCT rts.recipient_id) as recipient_count,
    SUM(rts.score) as total_score,
    SUM(rts.attempts) as total_attempts,
    ROUND(AVG(rts.score::numeric / NULLIF(rts.attempts, 0) * 100), 2) as avg_success_rate
FROM tags t
JOIN recipient_tag_scores rts ON rts.tag_id = t.id
GROUP BY t.id, t.tag_name
ORDER BY avg_success_rate ASC;
```

## Migration from Old System

If you have an existing database with the old tag_name-based structure, use the migration script:

```bash
psql -d oms_launch -f migrate_tags.sql
```

This script will:
1. Create the tags table
2. Extract unique tags from content_tags
3. Populate the tags table
4. Migrate content_tags to use tag_id
5. Migrate recipient_tag_scores to use tag_id
6. Create backups of old tables
7. Provide verification queries

**After migration, verify:**

```sql
-- Check tag counts match
SELECT COUNT(*) FROM tags;
SELECT COUNT(DISTINCT tag_name) FROM content_tags_backup;

-- Verify relationships
SELECT
    c.id,
    c.title,
    STRING_AGG(t.tag_name, ', ') as tags
FROM content c
JOIN content_tags ct ON ct.content_id = c.id
JOIN tags t ON t.id = ct.tag_id
GROUP BY c.id, c.title;
```

## Best Practices

### Tag Creation
- Let Claude API auto-generate tags when possible
- Use consistent naming conventions
- Keep tags focused and specific
- Review and merge similar tags periodically

### Tag Management
- Regularly audit unused tags
- Merge duplicate or similar tags
- Monitor tag effectiveness through success rates
- Create training content for low-performing tags

### Reporting
- Track tag performance over time
- Identify knowledge gaps per recipient
- Target training based on tag scores
- Monitor trending security topics

## Tag Analytics Queries

### Content Gap Analysis

Find tags that have few content items:

```sql
SELECT
    t.tag_name,
    COUNT(ct.content_id) as content_count
FROM tags t
LEFT JOIN content_tags ct ON ct.tag_id = t.id
GROUP BY t.id, t.tag_name
HAVING COUNT(ct.content_id) < 3
ORDER BY content_count ASC;
```

### Training Effectiveness

Compare before/after scores for recipients who received training:

```sql
SELECT
    t.tag_name,
    AVG(CASE WHEN rts.attempts <= 3 THEN rts.score::numeric / rts.attempts END) * 100 as early_success_rate,
    AVG(CASE WHEN rts.attempts > 3 THEN rts.score::numeric / rts.attempts END) * 100 as later_success_rate
FROM tags t
JOIN recipient_tag_scores rts ON rts.tag_id = t.id
GROUP BY t.id, t.tag_name
HAVING COUNT(*) > 10;
```

## Troubleshooting

### Tags Not Being Created

1. Check Claude API configuration:
   ```bash
   grep CLAUDE_API_KEY .env
   ```

2. Check logs during content upload:
   ```bash
   tail -f logs/app.log
   ```

3. Verify content was processed:
   ```sql
   SELECT * FROM content WHERE id = X;
   SELECT * FROM content_tags WHERE content_id = X;
   ```

### Duplicate Tags

If you have similar tags (e.g., "phishing" and "phishing_email"):

```sql
-- Find similar tags
SELECT tag_name FROM tags WHERE tag_name LIKE '%phishing%';

-- Merge tags (update references then delete)
UPDATE content_tags SET tag_id = 1 WHERE tag_id = 5;
UPDATE recipient_tag_scores SET tag_id = 1 WHERE tag_id = 5;
DELETE FROM tags WHERE id = 5;
```

### Score Not Updating

Check if content has tags:
```sql
SELECT c.id, c.title, t.tag_name
FROM content c
LEFT JOIN content_tags ct ON ct.content_id = c.id
LEFT JOIN tags t ON t.id = ct.tag_id
WHERE c.id = X;
```

If no tags, the content wasn't properly processed. Re-upload or manually add tags.

## Future Enhancements

Potential improvements to the tag system:

1. **Tag Hierarchies**: Parent/child relationships (e.g., "security" → "phishing")
2. **Tag Aliases**: Multiple names for the same concept
3. **Tag Suggestions**: ML-based tag recommendations
4. **Tag Merging UI**: Admin interface to merge similar tags
5. **Tag Trends**: Time-series analysis of tag performance
6. **Custom Tags**: Allow manual tag addition/editing
7. **Tag Difficulty**: Classify tags by difficulty level
8. **Learning Paths**: Recommended content order based on tag mastery
