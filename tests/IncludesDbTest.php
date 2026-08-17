<?php

/**
 * @backupGlobals disabled
 */
class IncludesDbTest extends \PHPUnit\Framework\TestCase {
    /**
     * @dataProvider providerGenerateQueryValues
     * @group sql
     */
    public function testGenerateQueryValues($value, $column, $condition, $result) {
        $this->assertSame($result, generate_query_values_and($value, $column, $condition));
    }

    /**
     * @dataProvider providerGenerateQueryValues
     * @group sql
     */
    public function testGenerateQueryValuesNoAnd($value, $column, $condition, $result) {
        $result = preg_replace('/^ AND/', '', $result);
        $this->assertSame($result, generate_query_values($value, $column, $condition));
    }

    public static function providerGenerateQueryValues() {
        return [

            // Basic values
            [ 0,                            'test', FALSE, " AND `test` = '0'" ],
            [ 1,                            'test', FALSE, " AND `test` = 1" ],
            [ [ 0, '35', 4.44 ],            'test', FALSE, " AND `test` IN ('0',35,'4.44')" ],
            [ '1,sf,98u8',                '`test`', FALSE, " AND `test` = '1,sf,98u8'" ],
            [  [ '1,sf,98u8' ],           'I.test', FALSE, " AND `I`.`test` = '1,sf,98u8'" ],
            [  [ '1,sf','98u8', '' ], '`I`.`test`', FALSE, " AND IFNULL(`I`.`test`, '') IN ('1,sf','98u8','')" ],
            [ OBS_VAR_UNSET,              '`test`', FALSE, " AND IFNULL(`test`, '') = ''" ],
            [ '"*%F@W)b\'_u<[`R1/#F"',      'test', FALSE, " AND `test` = '\\\"*%F@W)b\'_u<[`R1/#F\\\"'" ],
            [ '*?%_',                       'test', FALSE, " AND `test` = '*?%_'" ],

            // Negative condition
            [ 0,                            'test',  '!=', " AND `test` != '0'" ],
            [ 1,                            'test',  '!=', " AND `test` != 1" ],
            [ [ 0, '35', 4.44 ],            'test',  '!=', " AND `test` NOT IN ('0',35,'4.44')" ],
            [  [ '1,sf,98u8' ],           'I.test', 'NOT', " AND `I`.`test` != '1,sf,98u8'" ],
            [  [ '1,sf,98u8' ],           'I.test',  '!=', " AND `I`.`test` != '1,sf,98u8'" ],
            [  [ '1,sf,98u8', '' ],   '`I`.`test`',  '!=', " AND IFNULL(`I`.`test`, '') NOT IN ('1,sf,98u8','')" ],

            // LIKE conditions
            [ 0,                            'test',  '%LIKE', " AND (`test` LIKE '%0')" ],
            [ '1,sf,98u8',                '`test`',  'LIKE%', " AND (`test` LIKE '1,sf,98u8%')" ],
            [  [ '1,sf,98u8' ],           'I.test', '%LIKE%', " AND (`I`.`test` LIKE '%1,sf,98u8%')" ],
            [  [ '1,sf,98u8', '' ],   '`I`.`test`',   'LIKE', " AND (`I`.`test` LIKE '1,sf,98u8' OR COALESCE(`I`.`test`, '') LIKE '')" ],
            [ OBS_VAR_UNSET,              '`test`',   'LIKE', " AND (`test` LIKE '".OBS_VAR_UNSET."')" ],
            [ '"*%F@W)b\'_u<[`R1/#F"',      'test',   'LIKE', " AND (`test` LIKE '\\\"%\%F@W)b\'\_u<[`R1/#F\\\"')" ],

            // LIKE with match *?
            [ '*?%_',                       'test',   'LIKE', " AND (`test` LIKE '%_\%\_')" ],

            // Negative LIKE
            [ '1,sf,98u8',                '`test`', 'NOT LIKE%', " AND (`test` NOT LIKE '1,sf,98u8%')" ],
            [ '1,sf,98u8',                '`test`', 'NOT %LIKE', " AND (`test` NOT LIKE '%1,sf,98u8')" ],
            [ '1,sf,98u8',                '`test`', 'NOT %LIKE%', " AND (`test` NOT LIKE '%1,sf,98u8%')" ],
            [  [ '1,sf,98u8', '' ],       '`I`.`test`',  'NOT LIKE', " AND (`I`.`test` NOT LIKE '1,sf,98u8' AND COALESCE(`I`.`test`, '') NOT LIKE '')" ],

            // REGEXP
            [ '^[A-Z]E?PON0/13$',             'test', 'REGEXP',     " AND (`test` REGEXP '^[A-Z]E?PON0/13$')" ],
            [ '^[A-Z]E?PON0/13$',             'test', 'NOT REGEXP', " AND (`test` NOT REGEXP '^[A-Z]E?PON0/13$')" ],

            // Numeric conditions
            [ '13',                           'test', '>',          " AND `test` > 13" ],
            [ '13',                           'test', '>=',         " AND `test` >= 13" ],
            [ [ 9, '13'],                     'test', '>',          " AND `test` > 13" ],
            [ [ 9, '13'],                     'test', '>=',         " AND `test` >= 13" ],

            [ '2024-09-16 09:25:00',          'test', '>',          " AND `test` > '2024-09-16 09:25:00'" ],
            [ '2024-09-16 09:25:00',          'test', '>=',         " AND `test` >= '2024-09-16 09:25:00'" ],
            [ 'DATE_SUB(NOW(), INTERVAL 33 HOUR)', 'test', '>',     " AND `test` > DATE_SUB(NOW(), INTERVAL 33 HOUR)" ],
            [ 'DATE_SUB(NOW(), INTERVAL 33 HOUR)', 'test', '>=',    " AND `test` >= DATE_SUB(NOW(), INTERVAL 33 HOUR)" ],

            [ '13',                           'test', '<',          " AND `test` < 13" ],
            [ '13',                           'test', '<=',         " AND `test` <= 13" ],
            [ [ 9, '13'],                     'test', '<',          " AND `test` < 9" ],
            [ [ 9, '13'],                     'test', '<=',         " AND `test` <= 9" ],

            [ '2024-09-16 09:25:00',          'test', '<',          " AND `test` < '2024-09-16 09:25:00'" ],
            [ '2024-09-16 09:25:00',          'test', '<=',         " AND `test` <= '2024-09-16 09:25:00'" ],
            [ 'DATE_SUB(NOW(), INTERVAL 33 HOUR)', 'test', '<',     " AND `test` < DATE_SUB(NOW(), INTERVAL 33 HOUR)" ],
            [ 'DATE_SUB(NOW(), INTERVAL 33 HOUR)', 'test', '<=',    " AND `test` <= DATE_SUB(NOW(), INTERVAL 33 HOUR)" ],

            // incorrect numbers
            [ '',                             'test', '>',          " AND 0" ],
            [ '',                             'test', '<',          " AND 0" ],

            // Duplicates
            [  [ '1','sf','1','1','98u8','' ], '`I`.`test`', FALSE, " AND IFNULL(`I`.`test`, '') IN (1,'sf','98u8','')" ],
            [  [ '1','sf','98u8','1','sf','' ], 'I.test', '%LIKE%', " AND (`I`.`test` LIKE '%1%' OR `I`.`test` LIKE '%sf%' OR `I`.`test` LIKE '%98u8%' OR COALESCE(`I`.`test`, '') LIKE '')" ],

            // Wrong conditions
            [ '"*%F@W)b\'_u<[`R1/#F"',      'test',    'wtf', " AND `test` = '\\\"*%F@W)b\'_u<[`R1/#F\\\"'" ],
            [ 'ssdf',                     '`test`',     TRUE, " AND (`test` LIKE 'ssdf')" ],

            // Empty values
            [ NULL,                       '`test`',    FALSE, " AND IFNULL(`test`, '') = ''" ],
            [ '',                         '`test`',    FALSE, " AND IFNULL(`test`, '') = ''" ],
            [ [],                         '`test`',    FALSE, " AND 0" ],
            [ NULL,                       '`test`',   'LIKE', " AND (COALESCE(`test`, '') LIKE '')" ],
            [ '',                         '`test`',   'LIKE', " AND (COALESCE(`test`, '') LIKE '')" ],
            [ [],                         '`test`',   'LIKE', " AND 0" ],

            // Empty values negative condition
            [ NULL,                       '`test`',     '!=', " AND IFNULL(`test`, '') != ''" ],
            [ '',                         '`test`',     '!=', " AND IFNULL(`test`, '') != ''" ],
            [ [],                         '`test`',     '!=', " AND 1" ],
            [ NULL,                       '`test`', 'NOT LIKE', " AND (COALESCE(`test`, '') NOT LIKE '')" ],
            [ '',                         '`test`', 'NOT LIKE', " AND (COALESCE(`test`, '') NOT LIKE '')" ],
            [ [],                         '`test`', 'NOT LIKE', " AND 1" ],

            // injection?
            [ 'DATE_SUB(NOW(), INTERVAL 33 HOUR);', 'test',   '<',  " AND 0" ],
            [ 'DATE_SUB(NOW(), INTERVAL 33 HOUR);', 'test', FALSE,  " AND `test` = 'DATE_SUB(NOW(), INTERVAL 33 HOUR);'" ],
            [ 'admin\'--',                          'test', FALSE,  " AND `test` = 'admin\'--'" ],
        ];
    }

    /**
     * @dataProvider providerDbQuoteString
     * @group sql
     */
    public function testDbQuoteString($value, $result, $numeric = FALSE, $escape = TRUE) {
        $this->assertSame($result, db_quote_string($value, $escape, $numeric));
    }

    public static function providerDbQuoteString() {
        return [
            // common
            [ NULL,    "''" ],
            [ '12345', '12345',  TRUE ], // numeric
            [ '12345', "'12345'" ],
            [ '3.14',  "'3.14'" ],
            [ '3.14',  "'3.14'", TRUE ], // numeric, only for int
            [    0.1,   "'0.1'" ],
            [    0.1,   "'0.1'", TRUE ],

            // Zeroes
            [     0,   "'0'" ],
            [   0.0,   "'0'" ], // NOTE. This float always converted to 0, but a float type is never passed in db functions
            [   '0',   "'0'" ],
            [ '0.0', "'0.0'" ],
            [     0,   "'0'", TRUE ],
            [   0.0,   "'0'", TRUE ], // NOTE. This float always converted to 0, but a float type is never passed in db functions
            [   '0',   "'0'", TRUE ],
            [ '0.0', "'0.0'", TRUE ],

            // sql func
            [ 'DATE_SUB(NOW(), INTERVAL 33 HOUR)', 'DATE_SUB(NOW(), INTERVAL 33 HOUR)' ],

            // fixates
            [ 'V200R011C10SPC600 (V200R011SPH009)', "'V200R011C10SPC600 (V200R011SPH009)'" ],
            [ "V200R011C10SPC600 (V200R011SPH009) \n\r", "'V200R011C10SPC600 (V200R011SPH009) \\n\\r'" ],
            [ 'POP (default)', "'POP (default)'" ],

            // injection?
            [ 'DATE_SUB(NOW(), INTERVAL 33 HOUR);', "'DATE_SUB(NOW(), INTERVAL 33 HOUR);'" ],
            [ 'admin\'--',                          "'admin\'--'" ],
        ];
    }

    /**
     * @dataProvider providerIsDbFunction
     * @group sql
     */
    public function testIsDbFunction($value, $result) {
        $this->assertSame($result, is_db_function($value));
    }

    public static function providerIsDbFunction() {
        return [
            // Valid MySQL/MariaDB functions
            [ 'NOW()',                                 TRUE ],
            [ 'CURDATE()',                             TRUE ],
            [ 'CURTIME()',                             TRUE ],
            [ 'UNIX_TIMESTAMP()',                      TRUE ],
            [ 'COUNT(*)',                              TRUE ],
            [ 'SUM(amount)',                           TRUE ],
            [ 'AVG(value)',                            TRUE ],
            [ 'MAX(price)',                            TRUE ],
            [ 'MIN(price)',                            TRUE ],
            [ 'CONCAT("foo", "bar")',                  TRUE ],
            [ 'CONCAT_WS(",", col1, col2)',            TRUE ],
            [ 'COALESCE(col1, col2, 0)',               TRUE ],
            [ 'IFNULL(col, "")',                       TRUE ],
            [ 'CAST(value AS DECIMAL)',                TRUE ],
            [ 'DATE_FORMAT(date, "%Y-%m-%d")',         TRUE ],
            [ 'DATE_SUB(NOW(), INTERVAL 33 HOUR)',     TRUE ],
            [ 'DATE_ADD(NOW(), INTERVAL 1 DAY)',       TRUE ],
            [ 'FROM_UNIXTIME(timestamp)',              TRUE ],
            [ 'SUBSTRING(text, 1, 10)',                TRUE ],
            [ 'UPPER(name)',                           TRUE ],
            [ 'LOWER(name)',                           TRUE ],
            [ 'TRIM(text)',                            TRUE ],
            [ 'LENGTH(field)',                         TRUE ],
            [ 'MD5(password)',                         TRUE ],
            [ 'SHA1(data)',                            TRUE ],
            [ 'INET_ATON("192.168.1.1")',              TRUE ],
            [ 'INET_NTOA(ipaddr)',                     TRUE ],
            [ 'GROUP_CONCAT(tags)',                    TRUE ],
            [ 'IF(condition, true_val, false_val)',    TRUE ],

            // Valid INTERVAL expressions
            [ 'NOW() - INTERVAL 1 DAY',                TRUE ],
            [ 'NOW() + INTERVAL 1 DAY',                TRUE ],
            [ 'NOW() - INTERVAL 7 DAY',                TRUE ],
            [ 'NOW() + INTERVAL 1 WEEK',               TRUE ],
            [ 'NOW() - INTERVAL 1 MONTH',              TRUE ],
            [ 'NOW() + INTERVAL 1 YEAR',               TRUE ],
            [ 'NOW() - INTERVAL 1 HOUR',               TRUE ],
            [ 'NOW() + INTERVAL 30 MINUTE',            TRUE ],
            [ 'NOW() - INTERVAL 60 SECOND',            TRUE ],
            [ '(NOW() - INTERVAL 1 DAY)',              TRUE ],
            [ '(NOW() + INTERVAL 1 WEEK)',             TRUE ],
            [ 'CURDATE() + INTERVAL 1 DAY',            TRUE ],
            [ 'CURTIME() + INTERVAL 1 HOUR',           TRUE ],
            [ 'UNIX_TIMESTAMP() + 3600',               TRUE ],
            [ 'UNIX_TIMESTAMP() - 86400',              TRUE ],

            // Functions with whitespace
            [ '  NOW()  ',                             TRUE ],
            [ '  COUNT(*)  ',                          TRUE ],

            // Invalid functions (not in whitelist)
            [ 'INVALID_FUNCTION()',                    FALSE ],
            [ 'MY_CUSTOM_FUNC()',                      FALSE ],
            [ 'DROP_TABLE()',                          FALSE ],
            [ 'DELETE_ALL()',                          FALSE ],

            // SQL injection attempts
            [ 'NOW(); DROP TABLE users',               FALSE ],
            [ 'NOW();--',                              FALSE ],
            [ 'NOW(); DELETE FROM users',              FALSE ],
            [ 'COUNT(*); UPDATE users SET admin=1',    FALSE ],
            [ 'UNIX_TIMESTAMP(); -- comment',          FALSE ],

            // Non-function strings
            [ 'regular string',                        FALSE ],
            [ 'text with spaces',                      FALSE ],
            [ '2024-12-18 10:30:00',                   FALSE ],
            [ '192.168.1.1',                           FALSE ],
            [ 'admin\'--',                             FALSE ],
            [ 'some text (with parentheses)',          FALSE ],
            [ '(not a function)',                      FALSE ],

            // Invalid INTERVAL expressions
            [ 'NOW() + INTERVAL',                      FALSE ],
            [ 'NOW() - INTERVAL DAY',                  FALSE ],
            [ 'NOW() + INTERVAL 1',                    FALSE ],
            [ 'NOW() INTERVAL 1 DAY',                  FALSE ],
            [ 'INVALID_FUNC() + INTERVAL 1 DAY',       FALSE ],
            [ 'NOW() + INTERVAL 1 DAY;',               FALSE ],

            // Non-string values
            [ NULL,                                    FALSE ],
            [ 123,                                     FALSE ],
            [ 45.67,                                   FALSE ],
            [ TRUE,                                    FALSE ],
            [ FALSE,                                   FALSE ],
            [ [],                                      FALSE ],

            // Empty values
            [ '',                                      FALSE ],
            [ '()',                                    FALSE ],

            // Edge cases
            [ 'NOW()',                                 TRUE ],
            [ 'now()',                                 FALSE ], // lowercase not allowed
            [ 'NOW ()',                                FALSE ], // space before parenthesis
            [ 'A()',                                   FALSE ], // single char (valid pattern but not in whitelist)
            [ 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456()',   FALSE ], // too long (>32 chars)
            [ 'NOW(arg1, arg2, arg3)',                 TRUE ],  // functions with multiple args
            [ 'CONCAT("test", NOW())',                 TRUE ],  // nested functions

            // Case sensitivity (MySQL functions must be uppercase in our validation)
            [ 'Now()',                                 FALSE ],
            [ 'concat("a", "b")',                      FALSE ],
            [ 'Count(*)',                              FALSE ],

            // Special characters in function calls
            [ 'CONCAT("foo\'bar")',                    TRUE ],
            [ 'CONCAT("foo", "bar")',                  TRUE ],
            [ 'DATE_FORMAT(date, "%Y-%m-%d %H:%i:%s")', TRUE ],
            [ 'SUBSTRING(text FROM 1 FOR 10)',         TRUE ],

            // Real-world examples from Observium
            [ 'DATE_SUB(NOW(), INTERVAL 33 HOUR)',     TRUE ],
            [ 'IFNULL(col, \'\')',                     TRUE ],
            [ 'COALESCE(col, \'\')',                   TRUE ],
            [ 'FROM_UNIXTIME(last_polled)',            TRUE ],
        ];
    }
}

// EOF
