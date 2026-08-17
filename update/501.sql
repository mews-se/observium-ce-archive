ALTER TABLE `eigrp_peers` ADD COLUMN `last_change` TIMESTAMP NULL DEFAULT NULL AFTER `peer_version`;
UPDATE `eigrp_peers` SET `peer_addr` = LOWER(`peer_addr`);
