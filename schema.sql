-- PostgreSQL Schema for OMS Launch Platform
-- Adapted from MySQL schema

-- Drop tables in correct order (respecting foreign keys)
DROP TABLE IF EXISTS deployment_tracking CASCADE;
DROP TABLE IF EXISTS deployment_content CASCADE;
DROP TABLE IF EXISTS deployments CASCADE;
DROP TABLE IF EXISTS oms_tracking_links CASCADE;
DROP TABLE IF EXISTS recipient_tag_scores CASCADE;
DROP TABLE IF EXISTS content_tags CASCADE;
DROP TABLE IF EXISTS content CASCADE;
DROP TABLE IF EXISTS recipient_group_members CASCADE;
DROP TABLE IF EXISTS recipient_groups CASCADE;
DROP TABLE IF EXISTS recipients CASCADE;
DROP TABLE IF EXISTS email_templates CASCADE;
DROP TABLE IF EXISTS licenses CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS accounts CASCADE;

-- Accounts table
CREATE TABLE accounts (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Users table
CREATE TABLE users (
  id SERIAL PRIMARY KEY,
  account_id INTEGER REFERENCES accounts(id) ON DELETE SET NULL ON UPDATE CASCADE,
  first_name VARCHAR(255) NOT NULL,
  last_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL, -- Hashed password
  role VARCHAR(20) NOT NULL DEFAULT 'manager' CHECK (role IN ('admin', 'manager')),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_account_id ON users(account_id);

-- Licenses table
CREATE TABLE licenses (
  account_id INTEGER PRIMARY KEY REFERENCES accounts(id) ON DELETE CASCADE ON UPDATE CASCADE,
  total_seats INTEGER NOT NULL DEFAULT 0,
  used_seats INTEGER NOT NULL DEFAULT 0,
  expires_at DATE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Recipients table
CREATE TABLE recipients (
  id SERIAL PRIMARY KEY,
  account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE ON UPDATE CASCADE,
  email VARCHAR(255) NOT NULL,
  first_name VARCHAR(255),
  last_name VARCHAR(255),
  external_recipient_id VARCHAR(255),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(account_id, email)
);

CREATE INDEX idx_recipients_email ON recipients(email);

-- Recipient groups table
CREATE TABLE recipient_groups (
  id SERIAL PRIMARY KEY,
  account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE ON UPDATE CASCADE,
  name VARCHAR(255) NOT NULL,
  created_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_recipient_groups_account_id ON recipient_groups(account_id);
CREATE INDEX idx_recipient_groups_created_by ON recipient_groups(created_by_user_id);

-- Recipient group members (many-to-many)
CREATE TABLE recipient_group_members (
  id SERIAL PRIMARY KEY,
  recipient_group_id INTEGER NOT NULL REFERENCES recipient_groups(id) ON DELETE CASCADE ON UPDATE CASCADE,
  recipient_id INTEGER NOT NULL REFERENCES recipients(id) ON DELETE CASCADE ON UPDATE CASCADE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(recipient_group_id, recipient_id)
);

CREATE INDEX idx_group_members_recipient ON recipient_group_members(recipient_id);

-- Email templates table
CREATE TABLE email_templates (
  id SERIAL PRIMARY KEY,
  account_id INTEGER REFERENCES accounts(id) ON DELETE CASCADE ON UPDATE CASCADE, -- NULL for global template
  name VARCHAR(255) NOT NULL,
  subject VARCHAR(512) NOT NULL,
  body_html TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_email_templates_account_id ON email_templates(account_id);

-- Content table
CREATE TABLE content (
  id SERIAL PRIMARY KEY,
  account_id INTEGER REFERENCES accounts(id) ON DELETE CASCADE ON UPDATE CASCADE,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  content_type VARCHAR(50) NOT NULL DEFAULT 'training' CHECK (content_type IN ('training', 'simulation_landing', 'direct_email_body')),
  upload_type VARCHAR(20) NOT NULL CHECK (upload_type IN ('scorm', 'html_zip', 'raw_html', 'video')),
  content_identifier VARCHAR(512) NOT NULL, -- URL, relative path, or identifier for content
  image_identifier VARCHAR(512), -- URL, relative path, or identifier for preview image
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_content_account_id ON content(account_id);

-- Content tags table (stores tags extracted by Claude API)
CREATE TABLE content_tags (
  id SERIAL PRIMARY KEY,
  content_id INTEGER NOT NULL REFERENCES content(id) ON DELETE CASCADE ON UPDATE CASCADE,
  tag_name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(content_id, tag_name)
);

CREATE INDEX idx_content_tags_content_id ON content_tags(content_id);
CREATE INDEX idx_content_tags_tag_name ON content_tags(tag_name);

-- Recipient tag scores (tracks performance on specific topics/tags)
CREATE TABLE recipient_tag_scores (
  id SERIAL PRIMARY KEY,
  recipient_id INTEGER NOT NULL REFERENCES recipients(id) ON DELETE CASCADE ON UPDATE CASCADE,
  tag_name VARCHAR(100) NOT NULL,
  score INTEGER NOT NULL DEFAULT 0,
  attempts INTEGER NOT NULL DEFAULT 0,
  last_updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(recipient_id, tag_name)
);

CREATE INDEX idx_recipient_tag_scores_recipient ON recipient_tag_scores(recipient_id);

-- Deployments table
CREATE TABLE deployments (
  id SERIAL PRIMARY KEY,
  account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE ON UPDATE CASCADE,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  deployment_type VARCHAR(20) NOT NULL CHECK (deployment_type IN ('direct', 'simulation', 'follow_on')),
  recipient_group_id INTEGER NOT NULL REFERENCES recipient_groups(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  email_template_id INTEGER REFERENCES email_templates(id) ON DELETE RESTRICT ON UPDATE CASCADE, -- Required for simulation/follow_on
  created_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  scheduled_start_at TIMESTAMP,
  status VARCHAR(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'scheduled', 'running', 'completed', 'archived')),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_deployments_account_id ON deployments(account_id);
CREATE INDEX idx_deployments_type ON deployments(deployment_type);
CREATE INDEX idx_deployments_status ON deployments(status);
CREATE INDEX idx_deployments_recipient_group_id ON deployments(recipient_group_id);

-- Deployment content (links deployments to content)
CREATE TABLE deployment_content (
  id SERIAL PRIMARY KEY,
  deployment_id INTEGER NOT NULL REFERENCES deployments(id) ON DELETE CASCADE ON UPDATE CASCADE,
  content_id INTEGER NOT NULL REFERENCES content(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  content_role VARCHAR(20) NOT NULL CHECK (content_role IN ('primary', 'follow_on_1', 'follow_on_2', 'direct_email')),
  sequence SMALLINT NOT NULL DEFAULT 1,
  UNIQUE(deployment_id, content_role, sequence)
);

CREATE INDEX idx_deployment_content_content_id ON deployment_content(content_id);

-- Deployment tracking table
CREATE TABLE deployment_tracking (
  id BIGSERIAL PRIMARY KEY,
  deployment_id INTEGER NOT NULL REFERENCES deployments(id) ON DELETE CASCADE ON UPDATE CASCADE,
  recipient_id INTEGER NOT NULL REFERENCES recipients(id) ON DELETE CASCADE ON UPDATE CASCADE,
  unique_tracking_id VARCHAR(64) NOT NULL UNIQUE,
  primary_content_status VARCHAR(20) NOT NULL DEFAULT 'PENDING' CHECK (primary_content_status IN ('PENDING', 'SENT', 'FAILED_SEND', 'OPENED', 'CLICKED', 'COMPLETED', 'FAILED_COMPLETE')),
  follow_on_status VARCHAR(20) NOT NULL DEFAULT 'NA' CHECK (follow_on_status IN ('NA', 'PENDING', 'SENT', 'FAILED_SEND', 'OPENED', 'CLICKED', 'COMPLETED', 'FAILED_COMPLETE')),
  primary_score INTEGER,
  follow_on_score INTEGER,
  primary_sent_at TIMESTAMP,
  primary_opened_at TIMESTAMP,
  primary_clicked_at TIMESTAMP,
  primary_completed_at TIMESTAMP,
  follow_on_sent_at TIMESTAMP,
  follow_on_opened_at TIMESTAMP,
  follow_on_clicked_at TIMESTAMP,
  follow_on_completed_at TIMESTAMP,
  reported_at TIMESTAMP,
  last_action_at TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_tracking_deployment_recipient ON deployment_tracking(deployment_id, recipient_id);
CREATE INDEX idx_tracking_recipient ON deployment_tracking(recipient_id);
CREATE INDEX idx_tracking_primary_status ON deployment_tracking(primary_content_status);

-- OMS Tracking Links (for content launch tracking)
CREATE TABLE oms_tracking_links (
  id BIGSERIAL PRIMARY KEY,
  recipient_id INTEGER NOT NULL REFERENCES recipients(id) ON DELETE CASCADE ON UPDATE CASCADE,
  content_id INTEGER NOT NULL REFERENCES content(id) ON DELETE CASCADE ON UPDATE CASCADE,
  unique_link_id VARCHAR(64) NOT NULL UNIQUE,
  status VARCHAR(20) NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING', 'VIEWED', 'COMPLETED', 'FAILED')),
  score INTEGER,
  viewed_at TIMESTAMP,
  completed_at TIMESTAMP,
  interaction_data JSONB, -- Stores tagged interactions as JSON
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_tracking_links_recipient ON oms_tracking_links(recipient_id);
CREATE INDEX idx_tracking_links_content ON oms_tracking_links(content_id);
CREATE INDEX idx_tracking_links_unique_id ON oms_tracking_links(unique_link_id);

-- Function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for updated_at on all tables
CREATE TRIGGER update_accounts_updated_at BEFORE UPDATE ON accounts FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_licenses_updated_at BEFORE UPDATE ON licenses FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_recipients_updated_at BEFORE UPDATE ON recipients FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_recipient_groups_updated_at BEFORE UPDATE ON recipient_groups FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_email_templates_updated_at BEFORE UPDATE ON email_templates FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_content_updated_at BEFORE UPDATE ON content FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_deployments_updated_at BEFORE UPDATE ON deployments FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_deployment_tracking_updated_at BEFORE UPDATE ON deployment_tracking FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_oms_tracking_links_updated_at BEFORE UPDATE ON oms_tracking_links FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
