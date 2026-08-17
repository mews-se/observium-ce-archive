-- NOTE update log/history tables, may be long operation ~30-45min
ALTER TABLE `alert_log` CHANGE `event_id` `event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `bill_history` CHANGE `bill_hist_id` `bill_hist_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `eventlog` CHANGE `event_id` `event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `notifications_queue` CHANGE `log_id` `log_id` BIGINT UNSIGNED NOT NULL;
ALTER TABLE `syslog_alerts` CHANGE `lal_id` `lal_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `snmp_errors` CHANGE `error_id` `error_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `observium_processes` CHANGE `process_id` `process_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `authlog` CHANGE `id` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;