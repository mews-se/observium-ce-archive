UPDATE `alert_tests` SET `enable` = 1, `alert_name` = REPLACE(`alert_name`, '[MIGRATION FAILED] ', '') WHERE `alert_name` LIKE '[MIGRATION FAILED]%';
