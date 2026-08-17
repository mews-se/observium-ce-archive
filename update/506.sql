-- API Token System
-- Add table for API token management

CREATE TABLE IF NOT EXISTS `api_tokens` (
  `token_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `token_hash` varchar(64) NOT NULL COMMENT 'SHA256 hash of the actual token',
  `token_name` varchar(100) NOT NULL COMMENT 'Human readable name for the token',
  `token_prefix` varchar(12) NOT NULL COMMENT 'First 12 chars for identification (obs_XXXXXXXX)',
  `permissions` text DEFAULT NULL COMMENT 'JSON array of allowed endpoints/actions (future use)',
  `enabled` tinyint(1) DEFAULT 1 COMMENT 'Whether token is active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'When token expires (NULL = never)',
  `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'Last time token was used',
  `created_by` bigint(20) DEFAULT NULL COMMENT 'User ID who created this token',
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  KEY `enabled_expires` (`enabled`, `expires_at`),
  KEY `token_prefix` (`token_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='API authentication tokens';
