ALTER TABLE `mempools` CHANGE `mempool_table` `mempool_object` VARCHAR(64) DEFAULT NULL;
ALTER TABLE `storage` DROP INDEX `index_unique`;
ALTER TABLE `storage` ADD UNIQUE KEY `index_unique` (`device_id`, `storage_mib`, `storage_object`, `storage_index`);
