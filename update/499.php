<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage update
 *
 */

// Fixate for previous rule migration

/**
 * Main execution function to run the migration.
 */
function run_alert_rule_migration_failed() {
    $legacy_rules = dbFetchRows("SELECT * FROM `alert_tests` WHERE `alert_name` LIKE '[MIGRATION FAILED]%'");

    if (empty($legacy_rules)) {
        //echo "Alert Rule Migration: No legacy rules found to migrate.\n";
        return;
    }

    $success_count = 0;
    $failure_count = 0;

    foreach ($legacy_rules as $rule) {
        $associations = dbFetchRows("SELECT * FROM `alert_assoc` WHERE `alert_test_id` = ?", [$rule['alert_test_id']]);
        foreach ($associations as &$assoc) {
            $assoc['device_attribs'] = safe_json_decode($assoc['device_attribs']) ?: [];
            $assoc['entity_attribs'] = safe_json_decode($assoc['entity_attribs']) ?: [];
        }
        $rule['assocs'] = $associations;

        if ($new_ruleset = migrate_assoc_rules($rule)) {
            $restore_name = str_replace('[MIGRATION FAILED] ', '', $rule['alert_name']);
            dbUpdate(['alert_assoc' => safe_json_encode($new_ruleset), 'alert_name' => $restore_name, 'enable' => 1], 'alert_tests', '`alert_test_id` = ?', [$rule['alert_test_id']]);
            $success_count++;
        }
    }

    return $success_count;
}

// =======================================================
//                SCRIPT EXECUTION
// =======================================================
echo "Starting database cleanup and alert rule migration (fix)...\n";
run_alert_rule_migration_failed();
echo "Script finished.\n";

// EOF