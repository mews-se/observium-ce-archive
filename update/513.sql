CREATE TABLE IF NOT EXISTS `stp_vlan_map` (`device_id` INT UNSIGNED NOT NULL, `vlan_vlan` INT UNSIGNED NOT NULL, `stp_instance_id` INT UNSIGNED NOT NULL, PRIMARY KEY (`device_id`,`vlan_vlan`), KEY `stp_instance_id` (`stp_instance_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
# ERROR_IGNORE . Field 'admin_enable' already created in db schema 503
ALTER TABLE `stp_ports`  ADD COLUMN `admin_enable` TINYINT(1) DEFAULT NULL AFTER `base_port`;
