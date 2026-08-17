-- Discovery performance history tracking
-- Stores per-module discovery timing data for historical analysis

CREATE TABLE IF NOT EXISTS `discovery_perf_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `device_id` int(10) unsigned NOT NULL,
  `module` varchar(64) NOT NULL,
  `time_taken` decimal(8,3) unsigned NOT NULL,
  `discovery_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_device_time` (`device_id`, `discovery_time`),
  KEY `idx_module_time` (`module`, `discovery_time`),
  KEY `idx_time` (`discovery_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Discovery module performance history';