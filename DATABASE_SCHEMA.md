# OMS Launch Database Schema

## Database Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           OMS LAUNCH DATABASE SCHEMA                          │
└─────────────────────────────────────────────────────────────────────────────┘

┌────────────────────┐
│     accounts       │
├────────────────────┤
│ PK  id            │
│     name          │
│     description   │
│     created_at    │
│     updated_at    │
└────────┬───────────┘
         │
         │ 1:M (has many)
         │
    ┌────┴────────────────────────────────────────────────┐
    │                                                      │
    ▼                                                      ▼
┌────────────────┐                                 ┌──────────────────┐
│     users      │                                 │   recipients     │
├────────────────┤                                 ├──────────────────┤
│ PK  id        │                                 │ PK  id          │
│ FK  account_id │                                 │ FK  account_id  │
│     first_name │                                 │     email       │
│     last_name  │                                 │     first_name  │
│     email      │                                 │     last_name   │
│     password   │                                 │     external_id │
│     role       │                                 │     created_at  │
│     created_at │                                 │     updated_at  │
│     updated_at │                                 └────────┬─────────┘
└────────────────┘                                          │
                                                            │
                                                            │ 1:M
                                                            ▼
┌──────────────────┐                              ┌──────────────────────┐
│    licenses      │                              │ recipient_groups     │
├──────────────────┤                              ├──────────────────────┤
│ PK  account_id   │                              │ PK  id              │
│     total_seats  │                              │ FK  account_id      │
│     used_seats   │                              │ FK  created_by_user │
│     expires_at   │                              │     name            │
│     created_at   │                              │     created_at      │
│     updated_at   │                              │     updated_at      │
└──────────────────┘                              └──────────┬───────────┘
                                                            │
                                                            │ M:M
                                                            ▼
                                                   ┌─────────────────────────┐
                                                   │ recipient_group_members │
                                                   ├─────────────────────────┤
                                                   │ PK  id                 │
                                                   │ FK  recipient_group_id │
                                                   │ FK  recipient_id       │
                                                   │     created_at         │
                                                   └─────────────────────────┘


┌──────────────────────┐
│  email_templates     │
├──────────────────────┤
│ PK  id              │
│ FK  account_id      │
│     name            │
│     subject         │
│     body_html       │
│     created_at      │
│     updated_at      │
└──────────────────────┘


═══════════════════════════════════════════════════════════════════════════
                           CONTENT & TAG SYSTEM
═══════════════════════════════════════════════════════════════════════════

┌──────────────────────┐
│      content         │
├──────────────────────┤
│ PK  id              │
│ FK  account_id      │
│     title           │
│     description     │
│     content_type    │  ← training | simulation_landing | direct_email_body
│     upload_type     │  ← scorm | html_zip | raw_html | video
│     content_id      │  ← Path to content file
│     image_id        │  ← Preview image path
│     created_at      │
│     updated_at      │
└──────────┬───────────┘
           │
           │ 1:M
           │
           ▼
┌──────────────────────┐         M:M         ┌──────────────────┐
│    content_tags      │◄─────────────────────►│      tags       │
├──────────────────────┤                       ├──────────────────┤
│ PK  id              │                       │ PK  id          │
│ FK  content_id      │───────────────────────│     tag_name    │ UNIQUE
│ FK  tag_id          │                       │     created_at  │
│     created_at      │                       └────────┬─────────┘
└──────────────────────┘                               │
                                                       │
                                                       │ 1:M
                                                       ▼
                                              ┌─────────────────────────┐
                                              │ recipient_tag_scores    │
                                              ├─────────────────────────┤
                                              │ PK  id                 │
                                              │ FK  recipient_id       │
                                              │ FK  tag_id             │
                                              │     score              │
                                              │     attempts           │
                                              │     last_updated_at    │
                                              └─────────────────────────┘

Example: Content A has Tags "phishing" and "ransomware"

tags:
  1 | phishing
  2 | ransomware

content:
  1 | Content A | ...

content_tags:
  1 | 1 | 1    (Content A has phishing)
  2 | 1 | 2    (Content A has ransomware)


═══════════════════════════════════════════════════════════════════════════
                          TRACKING & LAUNCHES
═══════════════════════════════════════════════════════════════════════════

┌──────────────────────────┐
│   oms_tracking_links     │
├──────────────────────────┤
│ PK  id                  │
│ FK  recipient_id        │
│ FK  content_id          │
│     unique_link_id      │  ← Used in launch URL
│     status              │  ← PENDING | VIEWED | COMPLETED | FAILED
│     score               │
│     viewed_at           │
│     completed_at        │
│     interaction_data    │  ← JSONB: stores tagged interactions
│     created_at          │
│     updated_at          │
└──────────────────────────┘


═══════════════════════════════════════════════════════════════════════════
                        DEPLOYMENT SYSTEM
═══════════════════════════════════════════════════════════════════════════

┌────────────────────────┐
│     deployments        │
├────────────────────────┤
│ PK  id                │
│ FK  account_id        │
│ FK  recipient_group_id │
│ FK  email_template_id │
│ FK  created_by_user   │
│     name              │
│     description       │
│     deployment_type   │  ← direct | simulation | follow_on
│     scheduled_start   │
│     status            │  ← draft | scheduled | running | completed | archived
│     created_at        │
│     updated_at        │
└──────────┬─────────────┘
           │
           │ 1:M
           ├──────────────────────────────────┐
           │                                  │
           ▼                                  ▼
┌─────────────────────┐            ┌──────────────────────┐
│ deployment_content  │            │ deployment_tracking  │
├─────────────────────┤            ├──────────────────────┤
│ PK  id             │            │ PK  id              │
│ FK  deployment_id  │            │ FK  deployment_id   │
│ FK  content_id     │            │ FK  recipient_id    │
│     content_role   │            │     unique_track_id │
│     sequence       │            │     primary_status  │
└─────────────────────┘            │     follow_on_status │
                                   │     primary_score    │
                                   │     follow_on_score  │
                                   │     primary_sent_at  │
                                   │     primary_opened   │
                                   │     primary_clicked  │
                                   │     primary_complete │
                                   │     follow_on_sent   │
                                   │     follow_on_opened │
                                   │     follow_on_click  │
                                   │     follow_on_complt │
                                   │     reported_at      │
                                   │     last_action_at   │
                                   │     created_at       │
                                   │     updated_at       │
                                   └──────────────────────┘
```

## Table Details

### Core Tables (15 total)

| # | Table Name | Purpose | Records |
|---|------------|---------|---------|
| 1 | accounts | Customer organizations | Low |
| 2 | users | Platform administrators/managers | Low |
| 3 | licenses | Seat tracking per account | Low |
| 4 | recipients | Training recipients (employees) | High |
| 5 | recipient_groups | Groups of recipients | Medium |
| 6 | recipient_group_members | M:M recipients↔groups | High |
| 7 | email_templates | Email templates for campaigns | Low |
| 8 | content | Training content library | Medium |
| 9 | **tags** | **Normalized unique tags** | **Medium** |
| 10 | **content_tags** | **M:M content↔tags** | **High** |
| 11 | **recipient_tag_scores** | **Performance by tag** | **High** |
| 12 | oms_tracking_links | Content launch tracking | Very High |
| 13 | deployments | Training campaigns | Medium |
| 14 | deployment_content | Content in deployments | Medium |
| 15 | deployment_tracking | Campaign recipient tracking | Very High |

## Key Relationships

### Tag System (The New Normalized Structure)

```sql
-- One content can have many tags
content (1) ──→ (M) content_tags (M) ──→ (1) tags

-- One recipient can have scores on many tags
recipients (1) ──→ (M) recipient_tag_scores (M) ──→ (1) tags

-- One tag can be associated with many pieces of content
tags (1) ──→ (M) content_tags (M) ──→ (1) content

-- One tag can have scores from many recipients
tags (1) ──→ (M) recipient_tag_scores (M) ──→ (1) recipients
```

### Content Flow

```
1. Content uploaded → stored in 'content' table
2. Claude API analyzes → extracts tags
3. Tags stored in 'tags' table (if new)
4. Relationships created in 'content_tags'
5. Launch link created → 'oms_tracking_links'
6. Recipient completes → scores updated in 'recipient_tag_scores'
```

## Indexes

### Performance Indexes

```sql
-- Tags
CREATE INDEX idx_tags_tag_name ON tags(tag_name);

-- Content Tags
CREATE INDEX idx_content_tags_content_id ON content_tags(content_id);
CREATE INDEX idx_content_tags_tag_id ON content_tags(tag_id);

-- Recipient Tag Scores
CREATE INDEX idx_recipient_tag_scores_recipient ON recipient_tag_scores(recipient_id);
CREATE INDEX idx_recipient_tag_scores_tag ON recipient_tag_scores(tag_id);

-- Other key indexes
CREATE INDEX idx_content_account_id ON content(account_id);
CREATE INDEX idx_tracking_links_unique_id ON oms_tracking_links(unique_link_id);
CREATE INDEX idx_tracking_links_recipient ON oms_tracking_links(recipient_id);
CREATE INDEX idx_tracking_links_content ON oms_tracking_links(content_id);
```

## Constraints

### Unique Constraints

```sql
-- One unique tag name
tags: UNIQUE(tag_name)

-- No duplicate content-tag relationships
content_tags: UNIQUE(content_id, tag_id)

-- One score record per recipient-tag combo
recipient_tag_scores: UNIQUE(recipient_id, tag_id)

-- Tracking links are unique
oms_tracking_links: UNIQUE(unique_link_id)
```

### Foreign Key Cascade Rules

```sql
-- If content is deleted, remove its tag associations
content_tags: content_id ON DELETE CASCADE

-- If tag is deleted, remove all associations
content_tags: tag_id ON DELETE CASCADE
recipient_tag_scores: tag_id ON DELETE CASCADE

-- If recipient is deleted, remove their scores
recipient_tag_scores: recipient_id ON DELETE CASCADE
```

## Sample Data Walkthrough

### Scenario: Upload content about phishing

**1. Upload triggers content creation:**
```sql
INSERT INTO content (account_id, title, upload_type, content_identifier)
VALUES (1, 'Phishing Awareness Training', 'html_zip', '1/index.php');
-- Returns: content_id = 1
```

**2. Claude API detects tags: ["phishing", "email_security"]**

**3. Tags inserted (if new):**
```sql
-- Check/insert "phishing"
INSERT INTO tags (tag_name) VALUES ('phishing')
ON CONFLICT (tag_name) DO NOTHING RETURNING id;
-- Returns: tag_id = 1 (or existing ID)

-- Check/insert "email_security"
INSERT INTO tags (tag_name) VALUES ('email_security')
ON CONFLICT (tag_name) DO NOTHING RETURNING id;
-- Returns: tag_id = 2 (or existing ID)
```

**4. Link content to tags:**
```sql
INSERT INTO content_tags (content_id, tag_id) VALUES (1, 1);
INSERT INTO content_tags (content_id, tag_id) VALUES (1, 2);
```

**5. Create launch link for recipient:**
```sql
INSERT INTO oms_tracking_links (recipient_id, content_id, unique_link_id, status)
VALUES (100, 1, 'abc123xyz789', 'PENDING');
```

**6. Recipient completes with score 85:**
```sql
-- Update tracking
UPDATE oms_tracking_links
SET status = 'COMPLETED', score = 85, completed_at = NOW()
WHERE unique_link_id = 'abc123xyz789';

-- Update tag scores (score >= 70 = pass)
INSERT INTO recipient_tag_scores (recipient_id, tag_id, score, attempts)
VALUES (100, 1, 1, 1)  -- phishing
ON CONFLICT (recipient_id, tag_id)
DO UPDATE SET
    score = recipient_tag_scores.score + 1,
    attempts = recipient_tag_scores.attempts + 1;

INSERT INTO recipient_tag_scores (recipient_id, tag_id, score, attempts)
VALUES (100, 2, 1, 1)  -- email_security
ON CONFLICT (recipient_id, tag_id)
DO UPDATE SET
    score = recipient_tag_scores.score + 1,
    attempts = recipient_tag_scores.attempts + 1;
```

**Resulting Database State:**

```
tags:
id | tag_name
1  | phishing
2  | email_security

content:
id | title                         | ...
1  | Phishing Awareness Training   | ...

content_tags:
id | content_id | tag_id
1  | 1          | 1
2  | 1          | 2

oms_tracking_links:
id | recipient_id | content_id | unique_link_id  | status    | score
1  | 100         | 1          | abc123xyz789    | COMPLETED | 85

recipient_tag_scores:
id | recipient_id | tag_id | score | attempts | success_rate
1  | 100         | 1      | 1     | 1        | 100%
2  | 100         | 2      | 1     | 1        | 100%
```

## Query Examples

### Find all content with a specific tag

```sql
SELECT c.id, c.title, c.description
FROM content c
JOIN content_tags ct ON ct.content_id = c.id
JOIN tags t ON t.id = ct.tag_id
WHERE t.tag_name = 'phishing';
```

### Get recipient's performance summary

```sql
SELECT
    r.email,
    t.tag_name,
    rts.score,
    rts.attempts,
    ROUND((rts.score::numeric / rts.attempts * 100), 2) as success_rate
FROM recipient_tag_scores rts
JOIN recipients r ON r.id = rts.recipient_id
JOIN tags t ON t.id = rts.tag_id
WHERE r.id = 100
ORDER BY success_rate DESC;
```

### Find recipients who need training on a tag

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

### Get most popular tags

```sql
SELECT
    t.tag_name,
    COUNT(ct.content_id) as content_count,
    COUNT(DISTINCT rts.recipient_id) as recipients_attempted
FROM tags t
LEFT JOIN content_tags ct ON ct.tag_id = t.id
LEFT JOIN recipient_tag_scores rts ON rts.tag_id = t.id
GROUP BY t.id, t.tag_name
ORDER BY content_count DESC;
```

## Storage Estimates

Assuming 10,000 recipients, 500 content items, 50 tags:

| Table | Est. Rows | Storage |
|-------|-----------|---------|
| tags | 50 | < 1 MB |
| content | 500 | < 10 MB |
| content_tags | 1,500 | < 1 MB |
| recipients | 10,000 | < 5 MB |
| recipient_tag_scores | 50,000 | < 10 MB |
| oms_tracking_links | 100,000+ | ~50 MB+ |
| deployment_tracking | 50,000+ | ~25 MB+ |

**Total DB Size:** ~100-200 MB for moderate usage
