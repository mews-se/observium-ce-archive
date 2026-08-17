ALTER TABLE `sensors` CHANGE `poller_type` `poller_type` ENUM('snmp','agent','ipmi','custom') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'snmp';
