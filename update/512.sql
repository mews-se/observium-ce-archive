SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

DROP TABLE IF EXISTS `eigrp_ases`;
CREATE TABLE `eigrp_ases` (
  `eigrp_as_id` int NOT NULL AUTO_INCREMENT,
  `eigrp_vpn` int NOT NULL,
  `eigrp_as` int NOT NULL,
  `device_id` int NOT NULL,
  `cEigrpNbrCount` int NOT NULL,
  `cEigrpAsRouterIdType` enum('unknown','ipv4','ipv6','ipv4z','ipv6z','dns') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'ipv4',
  `cEigrpAsRouterId` varchar(32) COLLATE utf8mb3_unicode_ci NOT NULL,
  `cEigrpTopoRoutes` int NOT NULL,
  `cEigrpSiaQueriesSent` bigint unsigned DEFAULT NULL,
  `cEigrpSiaQueriesRcvd` bigint unsigned DEFAULT NULL,
  `cEigrpActiveCount` int unsigned DEFAULT NULL,
  `cEigrpStuckInActiveCount` int unsigned DEFAULT NULL,
  `last_poll` timestamp NULL DEFAULT NULL,
  `active_routes` int unsigned DEFAULT NULL,
  `sia_routes` int unsigned DEFAULT NULL,
  `peers_up` int unsigned DEFAULT NULL,
  `peers_down_recent` int unsigned DEFAULT NULL,
  `peers_flapping_24h` int unsigned DEFAULT NULL,
  `routes_int` int unsigned DEFAULT NULL,
  `routes_ext` int unsigned DEFAULT NULL,
  PRIMARY KEY (`eigrp_as_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `eigrp_peers`;
CREATE TABLE `eigrp_peers` (
  `eigrp_peer_id` int NOT NULL AUTO_INCREMENT,
  `device_id` int NOT NULL,
  `eigrp_vpn` int NOT NULL,
  `eigrp_as` int NOT NULL,
  `peer_addrtype` enum('unknown','ipv4','ipv6','ipv4z','ipv6z','dns') COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'inetAddrType',
  `peer_addr` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL,
  `peer_ifindex` int NOT NULL,
  `peer_holdtime` int NOT NULL,
  `peer_uptime` int NOT NULL,
  `peer_srtt` int NOT NULL,
  `peer_rto` int NOT NULL,
  `peer_version` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `last_change` timestamp NULL DEFAULT NULL,
  `peer_qcount` int unsigned DEFAULT NULL,
  `last_seq` int unsigned DEFAULT NULL,
  `retrans` bigint unsigned DEFAULT NULL,
  `retries` int unsigned DEFAULT NULL,
  `state` enum('up','down','init','unknown') COLLATE utf8mb3_unicode_ci DEFAULT 'up',
  `first_seen` datetime DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL,
  `peer_handle` varchar(128) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `bad_q_consec` tinyint unsigned DEFAULT '0',
  `srtt_baseline` int unsigned DEFAULT NULL,
  `down_since` datetime DEFAULT NULL,
  PRIMARY KEY (`eigrp_peer_id`),
  UNIQUE KEY `table_unique` (`device_id`,`eigrp_vpn`,`eigrp_as`,`peer_addr`),
  UNIQUE KEY `uniq_eigrp_peer` (`device_id`,`eigrp_vpn`,`eigrp_as`,`peer_addr`),
  UNIQUE KEY `uniq_eigrp_peer_if` (`device_id`,`eigrp_vpn`,`eigrp_as`,`peer_addr`,`peer_ifindex`),
  UNIQUE KEY `uniq_eigrp_peer_handle` (`device_id`,`eigrp_vpn`,`eigrp_as`,`peer_handle`),
  KEY `idx_device_as_vrf` (`device_id`,`eigrp_vpn`,`eigrp_as`),
  KEY `idx_device_ifindex` (`device_id`,`peer_ifindex`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `eigrp_ports`;
CREATE TABLE `eigrp_ports` (
  `eigrp_port_id` int NOT NULL AUTO_INCREMENT,
  `eigrp_vpn` int NOT NULL,
  `eigrp_as` int NOT NULL,
  `eigrp_ifIndex` int NOT NULL,
  `port_id` int NOT NULL,
  `device_id` int NOT NULL,
  `eigrp_peer_count` int NOT NULL,
  `eigrp_MeanSrtt` int NOT NULL,
  `eigrp_authmode` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `eigrp_HelloInterval` int unsigned DEFAULT NULL,
  `eigrp_PacingReliable` int unsigned DEFAULT NULL,
  `eigrp_PacingUnreliable` int unsigned DEFAULT NULL,
  `eigrp_XmitReliableQ` int unsigned DEFAULT NULL,
  `eigrp_XmitUnreliableQ` int unsigned DEFAULT NULL,
  `eigrp_PendingRoutes` int unsigned DEFAULT NULL,
  `last_poll` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`eigrp_port_id`),
  UNIQUE KEY `eigrp_vpn` (`eigrp_vpn`,`eigrp_as`,`eigrp_ifIndex`,`device_id`),
  KEY `device_id` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `eigrp_vpns`;
CREATE TABLE `eigrp_vpns` (
  `eigrp_vpn_id` int NOT NULL AUTO_INCREMENT,
  `eigrp_vpn` int NOT NULL,
  `eigrp_vpn_name` varchar(32) COLLATE utf8mb3_unicode_ci NOT NULL,
  `device_id` int NOT NULL,
  PRIMARY KEY (`eigrp_vpn_id`),
  KEY `device_id` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
