# Dashboard ownership, visibility, slug, and metadata
# ERROR_IGNORE

-- Add ownership and visibility
ALTER TABLE `dashboards`  ADD COLUMN `user_id` bigint NULL AFTER `dash_id`,  ADD COLUMN `is_public` tinyint(1) NOT NULL DEFAULT 1 AFTER `dash_name`;

-- Backfill visibility for existing rows
UPDATE `dashboards` SET `is_public` = 1 WHERE `is_public` IS NULL;

-- Helpful indexes for ownership/visibility
ALTER TABLE `dashboards`  ADD KEY `idx_dashboards_user_id` (`user_id`),  ADD KEY `idx_dashboards_public` (`is_public`);

-- Add slug for friendly URLs
ALTER TABLE `dashboards`  ADD COLUMN `slug` varchar(128) NULL AFTER `dash_name`;

-- Ensure slug uniqueness when present
ALTER TABLE `dashboards`  ADD UNIQUE KEY `uniq_dashboards_slug` (`slug`);

-- Add metadata and ordering
ALTER TABLE `dashboards`  ADD COLUMN `descr` varchar(255) NULL AFTER `dash_name`,  ADD COLUMN `category` varchar(64) NULL AFTER `descr`,  ADD COLUMN `dash_order` int NOT NULL DEFAULT 0 AFTER `category`,  ADD COLUMN `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `dash_order`,  ADD COLUMN `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created`,  ADD COLUMN `created_by` bigint NULL AFTER `updated`,  ADD COLUMN `updated_by` bigint NULL AFTER `created_by`;

-- Indexes for metadata/useful queries
ALTER TABLE `dashboards`  ADD KEY `idx_dashboards_category` (`category`),  ADD KEY `idx_dashboards_order` (`dash_order`),  ADD KEY `idx_dashboards_created_by` (`created_by`);

