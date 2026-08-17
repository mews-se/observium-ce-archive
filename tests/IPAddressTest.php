<?php

use PHPUnit\Framework\TestCase;

class IpAddressTest extends TestCase
{

    /**
     * @dataProvider providerHex2IP
     * @group ip
     */
    public function testHex2IP($string, $result)
    {
        $this->assertSame($result, hex2ip($string));
    }

    public static function providerHex2IP()
    {
        $results = array(
            // IPv4
            array('C1 9C 5A 26',  '193.156.90.38'),
            array('4a7d343d',     '74.125.52.61'),
            array('207d343d',     '32.125.52.61'),
            // cisco IPv4
            array('54 2E 68 02 FF FF FF FF ', '84.46.104.2'),
            array('90 7F 8A ',    '144.127.138.0'), // should be '90 7F 8A 00 '
            // IPv4 (converted to snmp string)
            array('J}4=',         '74.125.52.61'),
            array('J}4:',         '74.125.52.58'),
            // with newline
            array('
^KL=', '94.75.76.61'),
            // with first space char (possible for OBS_SNMP_CONCAT)
            array(' ^KL=',        '94.75.76.61'),
            array('  KL=',        '32.75.76.61'),
            array('    ',         '32.32.32.32'),
            // hex string
            array('31 38 35 2E 31 39 2E 31 30 30 2E 31 32 ', '185.19.100.12'),
            // IPv6
            array('20 01 07 F8 00 12 00 01 00 00 00 00 00 05 02 72',  '2001:07f8:0012:0001:0000:0000:0005:0272'),
            array('20:01:07:F8:00:12:00:01:00:00:00:00:00:05:02:72',  '2001:07f8:0012:0001:0000:0000:0005:0272'),
            array('200107f8001200010000000000050272',                 '2001:07f8:0012:0001:0000:0000:0005:0272'),
            // IPv6z
            //array('20 01 07 F8 00 12 00 01 00 00 00 00 00 05 02 72',  '2001:07f8:0012:0001:0000:0000:0005:0272'),
            array('2a:02:a0:10:80:03:00:00:00:00:00:00:00:00:00:01%503316482',  '2a02:a010:8003:0000:0000:0000:0000:0001'),
            //array('200107f8001200010000000000050272',                 '2001:07f8:0012:0001:0000:0000:0005:0272'),
            // Wrong data
            array('4a7d343dd',                        '4a7d343dd'),
            array('200107f800120001000000000005027',  '200107f800120001000000000005027'),
            array('193.156.90.38',                    '193.156.90.38'),
            array('Simple String',                    'Simple String'),
            array('',  ''),
            array(FALSE,  FALSE),
        );
        return $results;
    }

    /**
     * @dataProvider providerIp2Hex
     * @group ip
     */
    public function testIp2Hex($string, $separator, $result)
    {
        $this->assertSame($result, ip2hex($string, $separator));
    }

    public static function providerIp2Hex()
    {
        $results = array(
            // IPv4
            array('193.156.90.38', ' ', 'c1 9c 5a 26'),
            array('74.125.52.61',  ' ', '4a 7d 34 3d'),
            array('74.125.52.61',   '', '4a7d343d'),
            // IPv6
            array('2001:07f8:0012:0001:0000:0000:0005:0272', ' ', '20 01 07 f8 00 12 00 01 00 00 00 00 00 05 02 72'),
            array('2001:7f8:12:1::5:0272',                   ' ', '20 01 07 f8 00 12 00 01 00 00 00 00 00 05 02 72'),
            array('2001:7f8:12:1::5:0272',                    '', '200107f8001200010000000000050272'),
            // Wrong data
            array('4a7d343dd',                       NULL, '4a7d343dd'),
            array('200107f800120001000000000005027', NULL, '200107f800120001000000000005027'),
            array('300.156.90.38',                   NULL, '300.156.90.38'),
            array('Simple String',                   NULL, 'Simple String'),
            array('',    NULL, ''),
            array(FALSE, NULL, FALSE),
        );
        return $results;
    }

    /**
     * @dataProvider providerParseNetwork
     * @group ip
     */
    public function testParseNetwork($network, $result) {
        $test = parse_network($network);
        if (is_array($test)) {
            ksort($test);
        }

        if (is_array($result)) {
            ksort($result);
        }
        $this->assertSame($result, $test);
    }

    public static function providerParseNetwork()
    {
        $array = array();

        // Valid IPv4
        $array[] = array('10.0.0.0/8',   array('query_type' => 'network', 'ip_version' => 4, 'ip_type' => 'ipv4',
                                               'address' => '10.0.0.0', 'prefix' => '8',
                                               'network_start' => '10.0.0.0', 'network_end' => '10.255.255.255', 'network' => '10.0.0.0/8'));
        $array[] = array('10.0.0.0/255.255.255.0', array('query_type' => 'network', 'ip_version' => 4, 'ip_type' => 'ipv4',
                                                         'address' => '10.0.0.0', 'prefix' => '24',
                                                         'network_start' => '10.0.0.0', 'network_end' => '10.0.0.255', 'network' => '10.0.0.0/24'));
        $array[] = array('10.12.0.3/8',  array('query_type' => 'network', 'ip_version' => 4, 'ip_type' => 'ipv4',
                                               'address' => '10.12.0.3', 'prefix' => '8',
                                               'network_start' => '10.0.0.0', 'network_end' => '10.255.255.255', 'network' => '10.0.0.0/8'));
        $array[] = array('10.12.0.3/255.0.0.0', array('query_type' => 'network', 'ip_version' => 4, 'ip_type' => 'ipv4',
                                                      'address' => '10.12.0.3', 'prefix' => '8',
                                                      'network_start' => '10.0.0.0', 'network_end' => '10.255.255.255', 'network' => '10.0.0.0/8'));
        // Inverse mask
        $array[] = array('10.12.0.3/0.0.0.255', array('query_type' => 'network', 'ip_version' => 4, 'ip_type' => 'ipv4',
                                                      'address' => '10.12.0.3', 'prefix' => '24',
                                                      'network_start' => '10.12.0.0', 'network_end' => '10.12.0.255', 'network' => '10.12.0.0/24'));
        $array[] = array('10.12.0.3',    array('query_type' => 'single', 'ip_version' => 4, 'ip_type' => 'ipv4',
                                               'address' => '10.12.0.3', 'prefix' => '32',
                                               'network_start' => '10.12.0.3', 'network_end' => '10.12.0.3', 'network' => '10.12.0.3/32'));
        $array[] = array('10.12.0.3/32', array('query_type' => 'single', 'ip_version' => 4, 'ip_type' => 'ipv4',
                                               'address' => '10.12.0.3', 'prefix' => '32',
                                               'network_start' => '10.12.0.3', 'network_end' => '10.12.0.3', 'network' => '10.12.0.3/32'));
        $array[] = array('10.12.0.3/0', array('query_type' => 'network', 'ip_version' => 4, 'ip_type' => 'ipv4',
                                              'address' => '10.12.0.3', 'prefix' => '0',
                                              'network_start' => '0.0.0.0', 'network_end' => '255.255.255.255', 'network' => '0.0.0.0/0'));
        $array[] = array('0.0.0.0/0',   array('query_type' => 'network', 'ip_version' => 4, 'ip_type' => 'ipv4',
                                              'address' => '0.0.0.0', 'prefix' => '0',
                                              'network_start' => '0.0.0.0', 'network_end' => '255.255.255.255', 'network' => '0.0.0.0/0'));
        $array[] = array('*.12.0.3',     array('query_type' => 'like', 'ip_version' => 4, 'ip_type' => 'ipv4',
                                               'address' => '*.12.0.3'));
        $array[] = array('10.?.?.3',     array('query_type' => 'like', 'ip_version' => 4, 'ip_type' => 'ipv4',
                                               'address' => '10.?.?.3'));
        $array[] = array('10.12',        array('query_type' => '%like%', 'ip_version' => 4, 'ip_type' => 'ipv4',
                                               'address' => '10.12'));
        // Valid IPv6
        $array[] = array('1762:0:0:0:0:B03:1:AF18/99', array('query_type' => 'network', 'ip_version' => 6, 'ip_type' => 'ipv6',
                                                             'address' => '1762:0:0:0:0:B03:1:AF18', 'prefix' => '99',
                                                             'network_start' => '1762:0:0:0:0:b03:0:0', 'network_end' => '1762:0:0:0:0:b03:1fff:ffff', 'network' => '1762:0:0:0:0:b03:0:0/99'));
        $array[] = array('1762:0:0:0:0:b03:0:0/99',    array('query_type' => 'network', 'ip_version' => 6, 'ip_type' => 'ipv6',
                                                             'address' => '1762:0:0:0:0:b03:0:0', 'prefix' => '99',
                                                             'network_start' => '1762:0:0:0:0:b03:0:0', 'network_end' => '1762:0:0:0:0:b03:1fff:ffff', 'network' => '1762:0:0:0:0:b03:0:0/99'));
        $array[] = array('1762:0:0:0:0:B03:1:AF18',    array('query_type' => 'single', 'ip_version' => 6, 'ip_type' => 'ipv6',
                                                             'address' => '1762:0:0:0:0:B03:1:AF18', 'prefix' => '128',
                                                             'network_start' => '1762:0:0:0:0:B03:1:AF18', 'network_end' => '1762:0:0:0:0:B03:1:AF18', 'network' => '1762:0:0:0:0:B03:1:AF18/128'));
        $array[] = array('::ffff:192.0.2.47/127', array('query_type' => 'network', 'ip_version' => 6, 'ip_type' => 'ipv6',
                                                        'address' => '::ffff:192.0.2.47', 'prefix' => '127',
                                                        'network_start' => '0:0:0:0:0:ffff:c000:22e', 'network_end' => '0:0:0:0:0:ffff:c000:22f', 'network' => '0:0:0:0:0:ffff:c000:22e/127'));
        //$array[] = array('2001:0002:6c::430/48', array('query_type' => 'network', 'ip_version' => 6, 'ip_type' => 'ipv6',
        //                                       'address' => '::ffff:192.0.2.47', 'prefix' => '48',
        //                                       'network_start' => '0:0:0:0:0:ffff:c000:22e', 'network_end' => '0:0:0:0:0:ffff:c000:22f', 'network' => '0:0:0:0:0:ffff:c000:22e/48'));
        $array[] = array('1762:0:0:0:0:B03:1:AF18/128', array('query_type' => 'single', 'ip_version' => 6, 'ip_type' => 'ipv6',
                                                              'address' => '1762:0:0:0:0:B03:1:AF18', 'prefix' => '128',
                                                              'network_start' => '1762:0:0:0:0:B03:1:AF18', 'network_end' => '1762:0:0:0:0:B03:1:AF18', 'network' => '1762:0:0:0:0:B03:1:AF18/128'));
        $array[] = array('1762:0:0:0:0:B03:1:AF18/0', array('query_type' => 'network', 'ip_version' => 6, 'ip_type' => 'ipv6',
                                                            'address' => '1762:0:0:0:0:B03:1:AF18', 'prefix' => '0',
                                                            'network_start' => '0:0:0:0:0:0:0:0', 'network_end' => 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 'network' => '0:0:0:0:0:0:0:0/0'));
        $array[] = array('::/0',               array('query_type' => 'network', 'ip_version' => 6, 'ip_type' => 'ipv6',
                                                     'address' => '::', 'prefix' => '0',
                                                     'network_start' => '0:0:0:0:0:0:0:0', 'network_end' => 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 'network' => '0:0:0:0:0:0:0:0/0'));
        $array[] = array('1762::*:AF18',       array('query_type' => 'like', 'ip_version' => 6, 'ip_type' => 'ipv6',
                                                     'address' => '1762::*:AF18'));
        $array[] = array('?::B03:1:AF18',      array('query_type' => 'like', 'ip_version' => 6, 'ip_type' => 'ipv6',
                                                     'address' => '?::B03:1:AF18'));
        $array[] = array('1762:b03',           array('query_type' => '%like%', 'ip_version' => 6, 'ip_type' => 'ipv6',
                                                     'address' => '1762:b03'));

        return $array;
    }

    /**
     * @dataProvider providerGetIpType
     * @group ip
     */
    public function testGetIpType($ip, $result) {
        $this->assertSame($result, get_ip_type($ip));
    }

    public static function providerGetIpType() {
        return [
            [ '0.0.0.0',                  'unspecified' ],
            [ '::',                       'unspecified' ],
            [ '10.255.255.255/32',        'private' ], // Do not set /31 and /32 as broadcast!
            [ '10.255.255.255/31',        'private' ], // Do not set /31 and /32 as broadcast!
            [ '10.255.255.255/8',         'broadcast' ],
            [ '127.0.0.1',                'loopback' ],
            [ '::1',                      'loopback' ],
            [ '0:0:0:0:0:0:0:1/128',      'loopback' ],
            [ '127.0.1.1',                'loopback' ],
            [ '10.12.0.3',                'private' ],
            [ '172.16.1.1',               'private' ],
            [ '192.168.0.3',              'private' ],
            [ 'fdf8:f53b:82e4::53',       'private' ],
            [ '100.80.76.30',             'cgnat' ],
            [ '100.105.0.49',             'cgnat' ],
            [ '0:0:0:0:0:ffff:c000:22f',  'ipv4mapped' ],
            [ '::ffff:192.0.2.47',        'ipv4mapped' ],
            [ '77.222.50.30',             'unicast' ],
            [ '2a02:408:7722:5030::5030', 'unicast' ],
            [ '169.254.2.47',             'link-local' ],
            [ 'fe80::200:5aee:feaa:20a2', 'link-local' ],
            [ '2001:0000:4136:e378:8000:63bf:3fff:fdd2', 'teredo' ],
            [ '198.18.0.1',               'benchmark' ],
            [ '2001:0002:0:6C::430',      'benchmark' ],
            [ '2001:10:240:ab::a',        'orchid' ],
            [ '1:0002:6c::430',           'reserved' ],
            [ 'ff02::1:ff8b:4d51/0',      'multicast' ],
        ];
    }

    /**
     * @dataProvider providerMatchNetwork
     * @group ip
     */
    public function testMatchNetwork($result, $ip, $nets, $first = FALSE)
    {
        $this->assertSame($result, match_network($ip, $nets, $first));
    }

    public static function providerMatchNetwork()
    {
        $nets1 = array('127.0.0.0/8', '192.168.0.0/16', '10.0.0.0/8', '172.16.0.0/12', '!172.16.6.7/32');
        $nets2 = array('fe80::/16', '!fe80:ffff:0:ffff:1:144:52:0/112', '192.168.0.0/16', '172.16.0.0/12', '!172.16.6.7/32');
        $nets3 = array('fe80::/16', 'fe80:ffff:0:ffff:1:144:52:0/112', '!fe80:ffff:0:ffff:1:144:52:0/112');
        $nets4 = array('172.16.0.0/12', '!172.16.6.7');
        $nets5 = array('fe80::/16', '!FE80:FFFF:0:FFFF:1:144:52:38');
        $nets6 = "I'm a stupid";
        $nets7 = array('::ffff/96', '2001:0002:6c::/48');
        $nets8 = array("10.11.1.0/24",  "10.11.2.0/24",  "10.11.11.0/24", "10.11.12.0/24", "10.11.21.0/24", "10.11.22.0/24",
                       "10.11.30.0/23", "10.11.32.0/24", "10.11.33.0/24", "10.11.34.0/24", "10.11.41.0/24", "10.11.42.0/24",
                       "10.11.43.0/24", "10.11.51.0/24", "10.11.52.0/24", "10.11.53.0/24", "10.11.61.0/24", "10.11.62.0/24");

        return array(
            // Only IPv4 nets
            array(TRUE,  '127.0.0.1',  $nets1),
            array(FALSE, '1.1.1.1',    $nets1),       // not in ranges
            array(TRUE,  '172.16.6.6', $nets1),
            array(FALSE, '172.16.6.7', $nets1),       // excluded net
            array(TRUE,  '172.16.6.7', $nets1, TRUE), // excluded, but first match
            array(FALSE, '256.16.6.1', $nets1),       // wrong IP
            // Both IPv4 and IPv6
            array(FALSE, '1.1.1.1',    $nets2),
            array(TRUE,  '172.16.6.6', $nets2),
            array(TRUE,  'FE80:FFFF:0:FFFF:129:144:52:38', $nets2),
            array(FALSE, 'FE81:FFFF:0:FFFF:129:144:52:38', $nets2), // not in ranges
            array(FALSE, 'FE80:FFFF:0:FFFF:1:144:52:38',   $nets2), // excluded net
            // Only IPv6 nets
            array(FALSE, '1.1.1.1',    $nets3),
            array(FALSE, '172.16.6.6', $nets3),
            array(TRUE,  'FE80:FFFF:0:FFFF:129:144:52:38', $nets3),
            //array(TRUE,  '2001:0002:0:6c::430',            $nets7),
            array(FALSE, 'FE81:FFFF:0:FFFF:129:144:52:38', $nets3),
            array(FALSE, 'FE80:FFFF:0:FFFF:1:144:52:38',   $nets3),
            array(TRUE,  'FE80:FFFF:0:FFFF:1:144:52:38',   $nets3, TRUE), // excluded, but first match
            // IPv4 net without mask
            array(TRUE,  '172.16.6.6', $nets4),
            array(FALSE, '172.16.6.7', $nets4),       // excluded net
            // IPv6 net without mask
            array(TRUE,  'FE80:FFFF:0:FFFF:129:144:52:38', $nets5),
            array(FALSE, 'FE81:FFFF:0:FFFF:129:144:52:38', $nets5),
            array(FALSE, 'FE80:FFFF:0:FFFF:1:144:52:38',   $nets5),
            array(TRUE,  'FE80:FFFF:0:FFFF:1:144:52:38',   $nets5, TRUE), // excluded, but first match
            // IPv6 IPv4 mapped
            array(TRUE,  '::ffff:192.0.2.47', $nets7),
            // Are you stupid? YES :)
            array(FALSE, '172.16.6.6', $nets6),
            array(FALSE, 'FE80:FFFF:0:FFFF:129:144:52:38', $nets6),
            // Issues test
            array(FALSE, '10.52.25.254', $nets8),
            array(TRUE,  '10.52.25.254',  [ '!217.66.159.18' ]),
            array(FALSE, '217.66.159.18', [ '!217.66.159.18' ]),
            array(TRUE,  '217.66.159.18', [ '217.66.159.18' ]),
        );
    }

    /**
     * @dataProvider providerGetIpVersion
     * @group ip
     */
    public function testGetIpVersion($string, $result)
    {
        $this->assertSame($result, get_ip_version($string));
    }

    public static function providerGetIpVersion()
    {
        return [
            // IPv4
            array('193.156.90.38',    4),
            array('32.125.52.61',     4),
            array('127.0.0.1',        4),
            array('0.0.0.0',          4),
            array('255.255.255.255',  4),
            // IPv6
            [ '0000:0000:0000:0000:0000:0000:0000:0000', 6 ],
            [ '::',                                      6 ],
            [ '0000:0000:0000:0000:0000:0000:0000:0001', 6 ],
            [ '::1',                                     6 ],
            [ 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 6 ],
            [ '2001:0db8:0000:0000:0000:ff00:0042:8329', 6 ],
            [ '2001:07f8:0012:0001:0000:0000:0005:0272', 6 ],
            [ '2001:7f8:12:1::5:0272',                   6 ],
            array('::ffff:192.0.2.128',                       6), // IPv4 mapped to IPv6
            array('2002:c000:0204::',                         6), // 6to4 address 192.0.2.4
            // Wrong data
            array('4a7d343dd',              FALSE),
            array('my.domain.name',         FALSE),
            array('256.156.90.38',          FALSE),
            array('1.1.1.1.1',              FALSE),
            array('2001:7f8:12:1::5:0272f', FALSE),
            array('gggg:7f8:12:1::5:272f',  FALSE),
            //array('2002::',                 FALSE), // 6to4 address, must be full
            array('',                       FALSE),
            array(FALSE,                    FALSE),
            // IP with mask also wrong!
            array('193.156.90.38/32',           FALSE),
            array('2001:7f8:12:1::5:0272/128',  FALSE),
        ];
    }

    /**
     * Data provider for IP compression tests.
     *
     * Each data set contains:
     *  - input address (string)
     *  - expected result (string)
     *  - optional force flag (bool)
     *
     * @return array
     */
    public static function providerIpCompress(): array
    {
        return [
            // IPv4 without prefix, must remain unchanged
            'ipv4_without_prefix' => [
                'input'    => '192.0.2.1',
                'expected' => '192.0.2.1',
            ],

            // IPv4 with prefix, must remain unchanged
            'ipv4_with_prefix' => [
                'input'    => '192.0.2.1/24',
                'expected' => '192.0.2.1/24',
            ],

            // Full expanded IPv6 without prefix, must be compressed
            'ipv6_full_no_prefix' => [
                'input'    => '2001:0db8:0000:0000:0000:ff00:0042:8329',
                'expected' => '2001:db8::ff00:42:8329',
            ],

            // Full expanded IPv6 with prefix, must be compressed and keep prefix
            'ipv6_full_with_prefix' => [
                'input'    => '2001:0db8:0000:0000:0000:ff00:0042:8329/64',
                'expected' => '2001:db8::ff00:42:8329/64',
            ],

            // Already compressed IPv6 with prefix, force = FALSE
            'ipv6_already_compressed_no_force' => [
                'input'    => '2001:db8::1/64',
                'expected' => '2001:db8::1/64',
                'force'    => FALSE,
            ],

            // IPv6 unspecified short form
            'ipv6_unspecified_short' => [
                'input'    => '0:0:0:0:0:0:0:0',
                'expected' => '::',
            ],

            // IPv6 unspecified expanded form
            'ipv6_unspecified_expanded' => [
                'input'    => '0000:0000:0000:0000:0000:0000:0000:0000',
                'expected' => '::',
            ],

            // IPv6 unspecified expanded with prefix
            'ipv6_unspecified_expanded_with_prefix' => [
                'input'    => '0000:0000:0000:0000:0000:0000:0000:0000/0',
                'expected' => '::/0',
            ],

            // IPv6 loopback short form
            'ipv6_loopback_short' => [
                'input'    => '0:0:0:0:0:0:0:1',
                'expected' => '::1',
            ],

            // IPv6 loopback expanded form
            'ipv6_loopback_expanded' => [
                'input'    => '0000:0000:0000:0000:0000:0000:0000:0001',
                'expected' => '::1',
            ],

            // IPv6 loopback expanded with prefix
            'ipv6_loopback_expanded_with_prefix' => [
                'input'    => '0000:0000:0000:0000:0000:0000:0000:0001/128',
                'expected' => '::1/128',
            ],

            // Invalid address, must return empty string
            'invalid_address' => [
                'input'    => 'foobar-123',
                'expected' => '',
            ],
        ];
    }

    /**
     * @dataProvider providerIpCompress
     *
     * Test IP compression for various IPv4 and IPv6 inputs.
     *
     * @param string      $input    Input IP address string
     * @param string      $expected Expected compressed result
     * @param bool|string $force    Optional force flag, defaults to TRUE
     */
    public function testIpCompress(string $input, string $expected, $force = TRUE): void
    {
        // Result must strictly match the expected string
        $this->assertSame($expected, ip_compress($input, $force));
    }

    /**
     * Test recursive behaviour when input is an array of addresses.
     */
    public function testIpCompressArray(): void
    {
        $input = [
            '192.0.2.1',
            '192.0.2.1/24',
            '2001:0db8:0000:0000:0000:ff00:0042:8329',
            '2001:0db8:0000:0000:0000:ff00:0042:8329/64',
        ];

        $expected = [
            '192.0.2.1',
            '192.0.2.1/24',
            '2001:db8::ff00:42:8329',
            '2001:db8::ff00:42:8329/64',
        ];

        // Array input must be processed recursively element by element
        $this->assertSame($expected, ip_compress($input));
    }
}