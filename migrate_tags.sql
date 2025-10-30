-- Migration script to normalize tags structure
-- This script converts the old tag_name-based structure to use normalized tags table
-- Run this on existing databases that have data

-- Step 1: Create the tags table
CREATE TABLE IF NOT EXISTS tags (
  id SERIAL PRIMARY KEY,
  tag_name VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_tags_tag_name ON tags(tag_name);

-- Step 2: Backup existing content_tags data
CREATE TABLE IF NOT EXISTS content_tags_backup AS SELECT * FROM content_tags;

-- Step 3: Extract unique tags from old content_tags and insert into tags table
INSERT INTO tags (tag_name)
SELECT DISTINCT tag_name
FROM content_tags
WHERE tag_name IS NOT NULL
ON CONFLICT (tag_name) DO NOTHING;

-- Step 4: Create new content_tags structure
ALTER TABLE content_tags RENAME TO content_tags_old;

CREATE TABLE content_tags (
  id SERIAL PRIMARY KEY,
  content_id INTEGER NOT NULL REFERENCES content(id) ON DELETE CASCADE ON UPDATE CASCADE,
  tag_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE ON UPDATE CASCADE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(content_id, tag_id)
);

CREATE INDEX idx_content_tags_content_id ON content_tags(content_id);
CREATE INDEX idx_content_tags_tag_id ON content_tags(tag_id);

-- Step 5: Migrate data from old structure to new structure
INSERT INTO content_tags (content_id, tag_id, created_at)
SELECT
  ct_old.content_id,
  t.id as tag_id,
  ct_old.created_at
FROM content_tags_old ct_old
JOIN tags t ON t.tag_name = ct_old.tag_name
ON CONFLICT (content_id, tag_id) DO NOTHING;

-- Step 6: Backup and update recipient_tag_scores
CREATE TABLE IF NOT EXISTS recipient_tag_scores_backup AS SELECT * FROM recipient_tag_scores;

ALTER TABLE recipient_tag_scores RENAME TO recipient_tag_scores_old;

CREATE TABLE recipient_tag_scores (
  id SERIAL PRIMARY KEY,
  recipient_id INTEGER NOT NULL REFERENCES recipients(id) ON DELETE CASCADE ON UPDATE CASCADE,
  tag_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE ON UPDATE CASCADE,
  score INTEGER NOT NULL DEFAULT 0,
  attempts INTEGER NOT NULL DEFAULT 0,
  last_updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(recipient_id, tag_id)
);

CREATE INDEX idx_recipient_tag_scores_recipient ON recipient_tag_scores(recipient_id);
CREATE INDEX idx_recipient_tag_scores_tag ON recipient_tag_scores(tag_id);

-- Step 7: Migrate recipient_tag_scores data
INSERT INTO recipient_tag_scores (recipient_id, tag_id, score, attempts, last_updated_at)
SELECT
  rts_old.recipient_id,
  t.id as tag_id,
  rts_old.score,
  rts_old.attempts,
  rts_old.last_updated_at
FROM recipient_tag_scores_old rts_old
JOIN tags t ON t.tag_name = rts_old.tag_name
ON CONFLICT (recipient_id, tag_id) DO NOTHING;

-- Step 8: Verify migration
SELECT 'Migration Summary:' as status;
SELECT COUNT(*) as total_tags FROM tags;
SELECT COUNT(*) as total_content_tags FROM content_tags;
SELECT COUNT(*) as total_recipient_tag_scores FROM recipient_tag_scores;

-- Step 9: Drop old tables (ONLY after verifying data is correct)
-- Uncomment these lines after you've verified the migration was successful:
-- DROP TABLE content_tags_old;
-- DROP TABLE recipient_tag_scores_old;
-- DROP TABLE content_tags_backup;
-- DROP TABLE recipient_tag_scores_backup;

-- To verify before dropping:
-- SELECT * FROM content_tags_old LIMIT 10;
-- SELECT * FROM content_tags_backup LIMIT 10;
-- Compare with:
-- SELECT ct.*, t.tag_name
-- FROM content_tags ct
-- JOIN tags t ON t.id = ct.tag_id
-- LIMIT 10;
