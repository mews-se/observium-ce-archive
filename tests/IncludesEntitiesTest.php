<?php

// Test-specific setup (bootstrap.php handles common setup)
// Load any specific includes needed for this test suite

/**
 * @backupGlobals disabled
 */
class IncludesEntitiesTest extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        // Load test definition, for stable output
        include(__DIR__ . '/data/test_definitions.inc.php');
    }

    /**
     * @dataProvider providerGetDeviceMibs
     * @group mibs
     */
    public function testGetDeviceMibs($device, $result)
    {
        $mibs = array_values(get_device_mibs($device, FALSE)); // Use array_values for reset keys
        $this->assertEquals($result, $mibs);
    }

    /**
     * @dataProvider providerGetDeviceMibs
     * @group mibs
     */
    public function testGetDeviceMibs2($device, $result)
    {
        $device['device_id'] = 13;

        // Empty sysORID MIBs
        $GLOBALS['cache']['entity_attribs']['device'][$device['device_id']]['sysORID'] = '[]';
        $mibs = array_values(get_device_mibs($device, TRUE)); // Use array_values for reset keys
        $this->assertEquals($result, $mibs);

        // Any sysORID MIBs
        $GLOBALS['cache']['entity_attribs']['device'][$device['device_id']]['sysORID'] = '["SOME-MIB", "SOME2-MIB"]';
        $mibs = array_values(get_device_mibs($device, TRUE)); // Use array_values for reset keys
        if ($device['os'] === 'test_dlinkfw' && isset($device['sysObjectID'])) {
            // model definition first
            $new_result[] = array_shift($result);
            $new_result[] = 'SOME-MIB';
            $new_result[] = 'SOME2-MIB';
            $result = array_merge($new_result, $result);
        } else {
            $result = array_merge([ 'SOME-MIB', 'SOME2-MIB' ], $result);
        }
        //$result[] = 'SOME-MIB';
        //$result[] = 'SOME2-MIB';
        $this->assertEquals($result, $mibs);
    }

    public static function providerGetDeviceMibs()
    {
        return array(
            // OS with empty mibs (only default)
            array(
                array(
                    'device_id' => 1,
                    'os'        => 'test_generic'
                ),
                array(
                    'UCD-SNMP-MIB',
                    'HOST-RESOURCES-MIB',
                    'LSI-MegaRAID-SAS-MIB',
                    'EtherLike-MIB',
                    'ENTITY-MIB',
                    'ENTITY-SENSOR-MIB',
                    'CISCO-ENTITY-VENDORTYPE-OID-MIB',
                    //'HOST-RESOURCES-MIB',
                    'Q-BRIDGE-MIB',
                    'LLDP-MIB',
                    'CISCO-CDP-MIB',
                    'PW-STD-MIB',
                    'DISMAN-PING-MIB',
                    'BGP4-MIB',
                )
            ),

            // OS with group mibs
            array(
                array(
                    'device_id' => 1,
                    'os'        => 'test_ios'
                ),
                array(
                    'CISCO-IETF-IP-MIB',
                    'CISCO-ENTITY-SENSOR-MIB',
                    'CISCO-VTP-MIB',
                    'CISCO-ENVMON-MIB',
                    'CISCO-ENTITY-QFP-MIB',
                    'CISCO-IP-STAT-MIB',
                    'CISCO-FIREWALL-MIB',
                    'CISCO-ENHANCED-MEMPOOL-MIB',
                    'CISCO-MEMORY-POOL-MIB',
                    'CISCO-PROCESS-MIB',
                    'EtherLike-MIB',
                    'ENTITY-MIB',
                    'ENTITY-SENSOR-MIB',
                    'CISCO-ENTITY-VENDORTYPE-OID-MIB',
                    'HOST-RESOURCES-MIB',
                    'Q-BRIDGE-MIB',
                    'LLDP-MIB',
                    'CISCO-CDP-MIB',
                    'PW-STD-MIB',
                    'DISMAN-PING-MIB',
                    'BGP4-MIB',
                )
            ),

            // OS with group and os mibs
            array(
                array(
                    'device_id' => 1,
                    'os'        => 'test_linux'
                ),
                array(
                    'LM-SENSORS-MIB',
                    'SUPERMICRO-HEALTH-MIB',
                    'MIB-Dell-10892',
                    'CPQHLTH-MIB',
                    'CPQIDA-MIB',
                    'UCD-SNMP-MIB',
                    'HOST-RESOURCES-MIB',
                    'LSI-MegaRAID-SAS-MIB',
                    'EtherLike-MIB',
                    'ENTITY-MIB',
                    'ENTITY-SENSOR-MIB',
                    'CISCO-ENTITY-VENDORTYPE-OID-MIB',
                    //'HOST-RESOURCES-MIB',
                    'Q-BRIDGE-MIB',
                    'LLDP-MIB',
                    'CISCO-CDP-MIB',
                    'PW-STD-MIB',
                    'DISMAN-PING-MIB',
                    'BGP4-MIB',
                )
            ),
            // OS with in os blacklisted mibs
            array(
                array(
                    'device_id' => 1,
                    'os'        => 'test_junos'
                ),
                array(
                    'JUNIPER-MIB',
                    'JUNIPER-ALARM-MIB',
                    'JUNIPER-DOM-MIB',
                    'JUNIPER-SRX5000-SPU-MONITORING-MIB',
                    'JUNIPER-VLAN-MIB',
                    'JUNIPER-MAC-MIB',
                    'EtherLike-MIB',
                    //'ENTITY-MIB',
                    //'ENTITY-SENSOR-MIB',
                    'CISCO-ENTITY-VENDORTYPE-OID-MIB',
                    'HOST-RESOURCES-MIB',
                    'Q-BRIDGE-MIB',
                    'LLDP-MIB',
                    'CISCO-CDP-MIB',
                    'PW-STD-MIB',
                    'DISMAN-PING-MIB',
                    'BGP4-MIB',
                )
            ),

            // OS with in os and group blacklisted mibs
            array(
                array(
                    'device_id' => 1,
                    'os'        => 'test_freebsd'
                ),
                array(
                    'CISCO-IETF-IP-MIB',
                    'CISCO-ENTITY-SENSOR-MIB',
                    'EtherLike-MIB',
                    'ENTITY-MIB',
                    //'ENTITY-SENSOR-MIB',
                    'CISCO-ENTITY-VENDORTYPE-OID-MIB',
                    'HOST-RESOURCES-MIB',
                    //'Q-BRIDGE-MIB',
                    'LLDP-MIB',
                    'CISCO-CDP-MIB',
                    'PW-STD-MIB',
                    'DISMAN-PING-MIB',
                    'BGP4-MIB',
                )
            ),

            // OS with per-HW mibs
            array(
                array(
                    'device_id' => 1,
                    'os'        => 'test_dlinkfw'
                ),
                array(
                    'JUST-TEST-MIB',
                    'EtherLike-MIB',
                    'ENTITY-MIB',
                    'ENTITY-SENSOR-MIB',
                    'CISCO-ENTITY-VENDORTYPE-OID-MIB',
                    'HOST-RESOURCES-MIB',
                    'Q-BRIDGE-MIB',
                    'LLDP-MIB',
                    'CISCO-CDP-MIB',
                    'PW-STD-MIB',
                    'DISMAN-PING-MIB',
                    'BGP4-MIB',
                )
            ),
            // OS with per-HW mibs, but with correct sysObjectID
            array(
                array(
                    'device_id' => 1,
                    'os'        => 'test_dlinkfw',
                    'sysObjectID' => '.1.3.6.1.4.1.171.20.1.2.1'
                ),
                array(
                    'DFL800-MIB', // HW specific MIB
                    'JUST-TEST-MIB',
                    'EtherLike-MIB',
                    'ENTITY-MIB',
                    'ENTITY-SENSOR-MIB',
                    'CISCO-ENTITY-VENDORTYPE-OID-MIB',
                    'HOST-RESOURCES-MIB',
                    'Q-BRIDGE-MIB',
                    'LLDP-MIB',
                    'CISCO-CDP-MIB',
                    'PW-STD-MIB',
                    'DISMAN-PING-MIB',
                    'BGP4-MIB',
                )
            ),

        );
    }

    /**
     * @dataProvider providerGetDeviceMibsOrder
     * @group mibs
     */
    public function testGetDeviceMibsOrder($device, $order, $result)
    {
        $mibs = array_values(get_device_mibs($device, FALSE, $order)); // Use array_values for reset keys
        $this->assertEquals($result, $mibs);
    }

    public static function providerGetDeviceMibsOrder()
    {
        $device = array(
            'device_id' => 1,
            'os'        => 'test_order',
            'sysObjectID' => '.1.3.6.1.4.1.171.20.1.2.1'
        );
        $default = array(
            'EtherLike-MIB',
            'ENTITY-MIB',
            'ENTITY-SENSOR-MIB',
            'CISCO-ENTITY-VENDORTYPE-OID-MIB',
            'HOST-RESOURCES-MIB',
            //'Q-BRIDGE-MIB', // Blacklisted in group 'test_black'
            'LLDP-MIB',
            'CISCO-CDP-MIB',
            'PW-STD-MIB',
            'DISMAN-PING-MIB',
            'BGP4-MIB',
        );
        $model = array(
            'DFL800-MIB',
        );
        $os    = array(
            'JUST-TEST-MIB',
        );
        $group = array(
            'CISCO-IETF-IP-MIB',
            'CISCO-ENTITY-SENSOR-MIB',
        );

        return array(
            // Empty (default order)
            array(
                $device,
                NULL,
                array_merge($model, $os, $group, $default),
            ),
            // Same but with default order passed or unknown data
            array(
                $device,
                array('model', 'os', 'group', 'default'),
                array_merge($model, $os, $group, $default),
            ),
            array(
                $device,
                'model,os,group,default',
                array_merge($model, $os, $group, $default),
            ),
            array(
                $device,
                'asdasd,asdasdsw,asdasda',
                array_merge($model, $os, $group, $default),
            ),
            // Order changed
            array(
                $device,
                'default', // Default first
                array_merge($default, $model, $os, $group),
            ),
            array(
                $device,
                array('group', 'os'), // group and os first
                array_merge($group, $os, $model, $default),
            ),
            array(
                $device,
                array('group', 'os', 'default', 'model'), // full changed order
                array_merge($group, $os, $default, $model),
            ),

        );
    }

    /**
     * @dataProvider providerBgpASNumber
     * @group bgp
     */
    public function testBgpASdot($asplain, $asdot, $private)
    {
        $this->assertSame(bgp_asplain_to_asdot($asplain), $asdot);
        $this->assertSame(bgp_asplain_to_asdot($asdot),   $asdot);
    }

    /**
     * @dataProvider providerBgpASNumber
     * @group bgp
     */
    public function testBgpASplain($asplain, $asdot, $private)
    {
        $this->assertSame(bgp_asdot_to_asplain($asdot),   $asplain);
        $this->assertSame(bgp_asdot_to_asplain($asplain), $asplain);
    }

    /**
     * @dataProvider providerBgpASNumber
     * @group bgp
     */
    public function testBgpASprivate($asplain, $asdot, $private)
    {
        $this->assertSame(is_bgp_as_private($asdot),   $private);
        $this->assertSame(is_bgp_as_private($asplain), $private);
    }

    public static function providerBgpASNumber()
    {
        return array(
            //         ASplain, ASdot, Private?
            /* 16bit */
            array(         '0',           '0', FALSE),
            array(     '64511',       '64511', FALSE),
            array(     '64512',       '64512', TRUE),
            array(     '65534',       '65534', TRUE),
            array(     '65535',       '65535', TRUE), // This AS not
            /* 32bit */
            array(     '65536',         '1.0', FALSE),
            array(    '327700',        '5.20', FALSE),
            array('4199999999', '64086.59903', FALSE),
            array('4200000000', '64086.59904', TRUE),
            array('4294967294', '65535.65534', TRUE),
            array('4294967295', '65535.65535', TRUE),
        );
    }

    /**
     * @dataProvider providerParseBgpPeerIndex
     * @group bgp
     */
    public function testParseBgpPeerIndex($mib, $index, $result)
    {
        $peer = array();
        parse_bgp_peer_index($peer, $index, $mib);
        $this->assertSame($result, $peer);
    }

    public static function providerParseBgpPeerIndex()
    {
        $results = array(
            // IPv4
            array('BGP4-V2-MIB-JUNIPER', '0.1.203.153.47.15.1.203.153.47.207',
                  array('jnxBgpM2PeerRoutingInstance' => '0',
                        'jnxBgpM2PeerLocalAddrType'   => 'ipv4',
                        'jnxBgpM2PeerLocalAddr'       => '203.153.47.15',
                        'jnxBgpM2PeerRemoteAddrType'  => 'ipv4',
                        'jnxBgpM2PeerRemoteAddr'      => '203.153.47.207')),
            array('BGP4-V2-MIB-JUNIPER', '47.1.0.0.0.0.1.10.241.224.142',
                  array('jnxBgpM2PeerRoutingInstance' => '47',
                        'jnxBgpM2PeerLocalAddrType'   => 'ipv4',
                        'jnxBgpM2PeerLocalAddr'       => '0.0.0.0',
                        'jnxBgpM2PeerRemoteAddrType'  => 'ipv4',
                        'jnxBgpM2PeerRemoteAddr'      => '10.241.224.142')),
            // IPv6
            array('BGP4-V2-MIB-JUNIPER', '0.2.32.1.4.112.0.20.0.101.0.0.0.0.0.0.0.2.2.32.1.4.112.0.20.0.101.0.0.0.0.0.0.0.1',
                  array('jnxBgpM2PeerRoutingInstance' => '0',
                        'jnxBgpM2PeerLocalAddrType'   => 'ipv6',
                        'jnxBgpM2PeerLocalAddr'       => '2001:0470:0014:0065:0000:0000:0000:0002',
                        'jnxBgpM2PeerRemoteAddrType'  => 'ipv6',
                        'jnxBgpM2PeerRemoteAddr'      => '2001:0470:0014:0065:0000:0000:0000:0001')),
            // Wrong data
            //array('4a7d343dd',              FALSE),
        );
        return $results;
    }

    /**
     * @dataProvider providerStateStringToNumeric
     * @group states
     */
    public function testStateStringToNumeric($type, $value, $result)
    {
        $this->assertSame($result, state_string_to_numeric($type, $value)); // old without mib
    }

    public static function providerStateStringToNumeric() {
        $results = array(
            array('mge-status-state',           'No',              2),
            array('mge-status-state',           'no',              2),
            array('mge-status-state',           'Banana',      FALSE),
            array('inexistent-status-state',    'Vanilla',     FALSE),
            array('radlan-hwenvironment-state', 'notFunctioning',  6),
            array('radlan-hwenvironment-state', 'notFunctioning ', 6),
            array('cisco-envmon-state',         'warning',         2),
            array('cisco-envmon-state',         'war ning',    FALSE),
            array('powernet-sync-state',        'inSync',          1),
            array('power-ethernet-mib-pse-state', 'off',           2),
            // Numeric value
            array('cisco-envmon-state',         '2',               2),
            array('cisco-envmon-state',          2,                2),
            array('cisco-envmon-state',         '2.34',        FALSE),
            array('cisco-envmon-state',          10,           FALSE),
        );
        return $results;
    }

    /**
     * @dataProvider providerStateStringToNumeric2
     * @group states
     */
    public function testStateStringToNumeric2($type, $mib, $value, $result)
    {
        $this->assertSame($result, state_string_to_numeric($type, $value, $mib));
    }

    public static function providerStateStringToNumeric2() {
        $results = array(
            // String statuses
            array('status', 'QSAN-SNMP-MIB', 'Checking (0%)',   2), // warning
            array('status', 'QSAN-SNMP-MIB', 'Online',          1), // ok
            array('status', 'QSAN-SNMP-MIB', 'ajhbxsjshab',     3), // alert
            // numeric as float
            array('aten-state', 'ATEN-IPMI-MIB', '1',         1),
            array('aten-state', 'ATEN-IPMI-MIB', '1.000',     1),
        );
        return $results;
    }

    /**
     * @dataProvider providerGetStateArray
     * @group states
     */
    public function testGetStateArray($type, $value, $poller, $result)
    {
        $this->assertSame($result, get_state_array($type, $value, '', NULL, $poller)); // old without know mib
    }

    public static function providerGetStateArray()
    {
        $results = array(
            array('mge-status-state',           'No',             'snmp', array('value' => 2, 'name' => 'no', 'event' => 'ok', 'mib' => 'MG-SNMP-UPS-MIB')),
            array('mge-status-state',           'no',             'snmp', array('value' => 2, 'name' => 'no', 'event' => 'ok', 'mib' => 'MG-SNMP-UPS-MIB')),
            array('mge-status-state',           'Banana',         'snmp', array('value' => FALSE)),
            array('inexistent-status-state',    'Vanilla',        'snmp', array('value' => FALSE)),
            array('radlan-hwenvironment-state', 'notFunctioning', 'snmp', array('value' => 6, 'name' => 'notFunctioning', 'event' => 'ignore', 'mib' => 'RADLAN-HWENVIROMENT')),
            array('radlan-hwenvironment-state', 'notFunctioning ','snmp', array('value' => 6, 'name' => 'notFunctioning', 'event' => 'ignore', 'mib' => 'RADLAN-HWENVIROMENT')),
            array('cisco-envmon-state',         'warning',        'snmp', array('value' => 2, 'name' => 'warning', 'event' => 'warning', 'mib' => 'CISCO-ENVMON-MIB')),
            array('cisco-envmon-state',         'war ning',       'snmp', array('value' => FALSE)),
            array('powernet-sync-state',        'inSync',         'snmp', array('value' => 1, 'name' => 'inSync', 'event' => 'ok', 'mib' => 'PowerNet-MIB')),
            array('power-ethernet-mib-pse-state', 'off',          'snmp', array('value' => 2, 'name' => 'off', 'event' => 'ignore', 'mib' => 'POWER-ETHERNET-MIB')),
            // Numeric value
            array('cisco-envmon-state',         '2',              'snmp', array('value' => 2, 'name' => 'warning', 'event' => 'warning', 'mib' => 'CISCO-ENVMON-MIB')),
            array('cisco-envmon-state',          2,               'snmp', array('value' => 2, 'name' => 'warning', 'event' => 'warning', 'mib' => 'CISCO-ENVMON-MIB')),
            array('cisco-envmon-state',         '2.34',           'snmp', array('value' => FALSE)),
            array('cisco-envmon-state',          10,              'snmp', array('value' => FALSE)),
            // agent, ipmi
            array('unix-agent-state',           'warn',          'agent', array('value' => 2, 'name' => 'warn', 'event' => 'warning', 'mib' => '')),
            array('unix-agent-state',           0,               'agent', array('value' => 0, 'name' => 'fail', 'event' => 'alert',   'mib' => '')),
        );
        return $results;
    }

    /**
     * @dataProvider providerGetStateArray2
     * @group states
     */
    public function testGetStateArray2($type, $value, $event_value, $mib, $result)
    {
        $this->assertSame($result, get_state_array($type, $value, $mib, $event_value));
    }

    public static function providerGetStateArray2()
    {
        $mib = 'PowerNet-MIB';
        $results = array(
            array('emsInputContactStatusInputContactState', 'contactClosedEMS', 'normallyClosedEMS',   '', array('value' => 1, 'name' => 'contactClosedEMS', 'event' => 'ok',    'mib' => 'PowerNet-MIB')),
            array('emsInputContactStatusInputContactState', 'contactClosedEMS',   'normallyOpenEMS',   '', array('value' => 1, 'name' => 'contactClosedEMS', 'event' => 'alert', 'mib' => 'PowerNet-MIB')),
            array('emsInputContactStatusInputContactState',   'contactOpenEMS', 'normallyClosedEMS',   '', array('value' => 2, 'name' => 'contactOpenEMS',   'event' => 'alert', 'mib' => 'PowerNet-MIB')),
            array('emsInputContactStatusInputContactState',   'contactOpenEMS',   'normallyOpenEMS',   '', array('value' => 2, 'name' => 'contactOpenEMS',   'event' => 'ok',    'mib' => 'PowerNet-MIB')),
            array('emsInputContactStatusInputContactState', 'contactClosedEMS', 'normallyClosedEMS', $mib, array('value' => 1, 'name' => 'contactClosedEMS', 'event' => 'ok',    'mib' => 'PowerNet-MIB')),
            array('emsInputContactStatusInputContactState', 'contactClosedEMS',   'normallyOpenEMS', $mib, array('value' => 1, 'name' => 'contactClosedEMS', 'event' => 'alert', 'mib' => 'PowerNet-MIB')),
            array('emsInputContactStatusInputContactState',   'contactOpenEMS', 'normallyClosedEMS', $mib, array('value' => 2, 'name' => 'contactOpenEMS',   'event' => 'alert', 'mib' => 'PowerNet-MIB')),
            array('emsInputContactStatusInputContactState',   'contactOpenEMS',   'normallyOpenEMS', $mib, array('value' => 2, 'name' => 'contactOpenEMS',   'event' => 'ok',    'mib' => 'PowerNet-MIB')),

        );
        // String statuses
        $mib = 'QSAN-SNMP-MIB';
        $results[] = [ 'status',   'Checking (0%)',   NULL, $mib, [ 'value' => 2, 'name' => 'Checking (0%)', 'event' => 'warning', 'mib' => 'QSAN-SNMP-MIB' ] ];
        return $results;
    }

    /**
     * @dataProvider providerGetBitsStateArray
     * @group states_bits
     */
    /* WiP
    public function testGetBitsStateArray($hex, $mib, $object, $result)
    {
      $this->assertSame($result, get_bits_state_array($hex, $mib, $object));
    }

    public static function providerGetBitsStateArray()
    {
      $results = array(
        array('40 00', 'CISCO-STACK-MIB', 'portAdditionalOperStatus', [ 1 => 'connected' ]), // CISCO-STACK-MIB::portAdditionalOperStatus.1.1 = BITS: 40 00 connected(1)
      );
      return $results;
    }
    */

    /**
     * @dataProvider providerGetBitsStateArray2
     * @group states_bits
     */
    /* WiP
    public function testGetBitsStateArray2($hex, $def, $result)
    {
      $this->assertSame($result, get_bits_state_array($hex, NULL, NULL, $def));
    }

    public static function providerGetBitsStateArray2()
    {
      $results = array(
        array('40 00', [], [ 1 => 'connected' ]), // CISCO-STACK-MIB::portAdditionalOperStatus.1.1 = BITS: 40 00 connected(1)
      );
      return $results;
    }
    */

    /**
    * @dataProvider providerEntityDescrDefinition
    * @group descr
    */
    public function testEntityDescrDefinition($type, $result, $definition, $descr_entry, $count = 1) {
        $this->assertSame($result, entity_descr_definition($type, $definition, $descr_entry, $count));
    }


    public static function providerEntityDescrDefinition() {
        $result = array();

        // Mempool
        $type = 'mempool';
        $definition = array();
        $array      = array('i' => '22', 'index' => '33');

        // Defaults from entity definition
        $result[] = array($type, 'Memory',          $definition, $array);
        $result[] = array($type, 'Memory Pool 33',  $definition, $array, 2);

        // Descr from oid_descr, but it empty
        $definition['oid_descr'] = 'OidName';
        $result[] = array($type, 'Memory',          $definition, $array);
        // Descr from descr
        $definition['descr'] = 'Name from Descr';
        $result[] = array($type, 'Name from Descr', $definition, $array);
        $result[] = array($type, 'Name from Descr 33', $definition, $array, 2);
        // Descr from oid_descr
        $array['OidName'] = 'Name from Oid';
        $result[] = array($type, 'Name from Oid',   $definition, $array);
        $result[] = array($type, 'Name from Oid',   $definition, $array, 2);
        // Now descr use tags
        $definition['descr'] = 'Name from Descr with Tags (%i%) {%index%} [%oid_descr%]';
        $result[] = array($type, 'Name from Descr with Tags (22) {33} [Name from Oid]', $definition, $array);
        $definition['descr'] = 'Name from Descr with Tags (%OidName%)';
        $result[] = array($type, 'Name from Descr with Tags (Name from Oid)', $definition, $array);
        // Tag multiple times
        $definition['descr'] = 'Name from Descr with multiple Tags {%oid_descr%} [%oid_descr%]';
        $result[] = array($type, 'Name from Descr with multiple Tags {Name from Oid} [Name from Oid]', $definition, $array);
        // Multipart indexes
        $definition['descr'] = 'Name from Descr with Tags {%index0%}';
        $result[] = array($type, 'Name from Descr with Tags {33}', $definition, $array);
        $array['index'] = '11.22.33.44.55';
        $definition['descr'] = 'Name from Descr with Multipart Index {%index1%} {%index3%} {%index2%} [%index%]';
        $result[] = array($type, 'Name from Descr with Multipart Index {22} {44} {33} [11.22.33.44.55]', $definition, $array);

        // Sensors
        $type = 'sensor';
        $definition = array();
        $array      = array('i' => '22', 'index' => '33');

        $definition['oid_descr'] = 'jnxOperatingDescr';
        $definition['descr_transform'] = ['action' => 'entity_name'];

        $array['jnxOperatingDescr'] = "PIC: 4x 10GE(LAN) SFP+     @ 0/0/*";
        $result[] = array($type, 'PIC: 4x 10GE(LAN) SFP+ @ 0/0/*',   $definition, $array);

        return $result;
    }

    /**
     * @dataProvider providerGetDeviceSnmpArgv
     * @group device
     */
    public function testGetDeviceSnmpArgv($argv, $result, $options = []) {
        $this->assertSame($result, get_device_snmp_argv($argv, $snmp_options));
        if ($snmp_options || $options) {
            // Common snmp v3 or context
            $this->assertSame($options, $snmp_options);
        }
    }

    public static function providerGetDeviceSnmpArgv() {
        $array = [];

        // SNMP v1
        // hostname.test community v1
        $array[] = [ [ 'hostname.test', 'community', 'v1' ],
                     [ 'hostname' => 'hostname.test', 'snmp_community' => [ 'community' ], 'snmp_version' => 'v1', 'snmp_transport' => 'udp', 'snmp_port' => 161 ] ];
        // hostname.test community v1 tcp context
        $array[] = [ [ 'hostname.test', 'community', 'v1', 'tcp', 'context' ],
                     [ 'hostname' => 'hostname.test', 'snmp_community' => [ 'community' ], 'snmp_version' => 'v1', 'snmp_transport' => 'tcp', 'snmp_port' => 161 ],
                     [ 'snmp_context' => 'context' ] ];

        // SNMP v2c (default)
        // hostname.test community
        $array[] = [ [ 'hostname.test', 'community', ],
                     [ 'hostname' => 'hostname.test', 'snmp_community' => [ 'community' ], 'snmp_version' => 'v2c', 'snmp_transport' => 'udp', 'snmp_port' => 161 ] ];
        // hostname.test community v2c
        $array[] = [ [ 'hostname.test', 'community', 'v2c' ],
                     [ 'hostname' => 'hostname.test', 'snmp_community' => [ 'community' ], 'snmp_version' => 'v2c', 'snmp_transport' => 'udp', 'snmp_port' => 161 ] ];
        // hostname.test community v2c tcp context
        $array[] = [ [ 'hostname.test', 'community', 'v2c', 'tcp', 'context' ],
                     [ 'hostname' => 'hostname.test', 'snmp_community' => [ 'community' ], 'snmp_version' => 'v2c', 'snmp_transport' => 'tcp', 'snmp_port' => 161 ],
                     [ 'snmp_context' => 'context' ] ];

        // SNMP v3
        // hostname.test nanp v3 username
        $snmp_v3_auth = [ [ 'authlevel' => 'noAuthNoPriv', 'authname' => 'username' ] ];
        $array[] = [ [ 'hostname.test', 'nanp', 'v3', 'username' ],
                     [ 'hostname' => 'hostname.test', 'snmp_v3_auth' => $snmp_v3_auth, 'snmp_version' => 'v3', 'snmp_transport' => 'udp', 'snmp_port' => 161 ] ];
        // hostname.test nanp v3 tcp context
        $snmp_v3_auth = [ [ 'authlevel' => 'noAuthNoPriv', 'authname' => 'observium' ] ];
        $array[] = [ [ 'hostname.test', 'nanp', 'v3', 'tcp', 'context' ],
                     [ 'hostname' => 'hostname.test', 'snmp_v3_auth' => $snmp_v3_auth, 'snmp_version' => 'v3', 'snmp_transport' => 'tcp', 'snmp_port' => 161 ],
                     [ 'snmp_context' => 'context' ] ];

        // hostname.test anp v3 username password sha
        $snmp_v3_auth = [ [ 'authlevel' => 'authNoPriv', 'authname' => 'username', 'authpass' => 'password', 'authalgo' => 'sha' ] ];
        $array[] = [ [ 'hostname.test', 'anp', 'v3', 'username', 'password', 'sha' ],
                     [ 'hostname' => 'hostname.test', 'snmp_v3_auth' => $snmp_v3_auth, 'snmp_version' => 'v3', 'snmp_transport' => 'udp', 'snmp_port' => 161 ] ];
        // hostname.test anp v3 username password tcp context
        $snmp_v3_auth = [ [ 'authlevel' => 'authNoPriv', 'authname' => 'username', 'authpass' => 'password', 'authalgo' => 'MD5' ] ];
        $array[] = [ [ 'hostname.test', 'anp', 'v3', 'username', 'password', 'tcp', 'context' ],
                     [ 'hostname' => 'hostname.test', 'snmp_v3_auth' => $snmp_v3_auth, 'snmp_version' => 'v3', 'snmp_transport' => 'tcp', 'snmp_port' => 161 ],
                     [ 'snmp_context' => 'context' ] ];

        // hostname.test ap v3 username password encpass sha des
        $snmp_v3_auth = [ [ 'authlevel' => 'authPriv', 'authname' => 'username', 'authpass' => 'password', 'authalgo' => 'sha', 'cryptopass' => 'encpass', 'cryptoalgo' => 'des' ] ];
        $array[] = [ [ 'hostname.test', 'ap', 'v3', 'username', 'password', 'encpass', 'sha', 'des' ],
                     [ 'hostname' => 'hostname.test', 'snmp_v3_auth' => $snmp_v3_auth, 'snmp_version' => 'v3', 'snmp_transport' => 'udp', 'snmp_port' => 161 ] ];
        // hostname.test ap v3 username password encpass tcp context
        $snmp_v3_auth = [ [ 'authlevel' => 'authPriv', 'authname' => 'username', 'authpass' => 'password', 'authalgo' => 'MD5', 'cryptopass' => 'encpass', 'cryptoalgo' => 'AES' ] ];
        $array[] = [ [ 'hostname.test', 'ap', 'v3', 'username', 'password', 'encpass', 'tcp', 'context' ],
                     [ 'hostname' => 'hostname.test', 'snmp_v3_auth' => $snmp_v3_auth, 'snmp_version' => 'v3', 'snmp_transport' => 'tcp', 'snmp_port' => 161 ],
                     [ 'snmp_context' => 'context' ] ];

        // SNMP port
        // hostname.test:123 community
        $array[] = [ [ 'hostname.test:123', 'community' ],
                     [ 'hostname' => 'hostname.test', 'snmp_community' => [ 'community' ], 'snmp_version' => 'v2c', 'snmp_transport' => 'udp', 'snmp_port' => 123 ] ];
        // 127.0.0.1:123 community
        $array[] = [ [ '127.0.0.1:123', 'community', 'v2c' ],
                     [ 'hostname' => '127.0.0.1', 'snmp_community' => [ 'community' ], 'snmp_version' => 'v2c', 'snmp_transport' => 'udp', 'snmp_port' => 123 ] ];
        // hostname.test community v2c 123
        $array[] = [ [ 'hostname.test', 'community', 'v2c', '123' ],
                     [ 'hostname' => 'hostname.test', 'snmp_community' => [ 'community' ], 'snmp_version' => 'v2c', 'snmp_transport' => 'udp', 'snmp_port' => 123 ] ];
        // ::1 community v2c 123
        $array[] = [ [ '::1', 'community', 'v2c', '123' ],
                     [ 'hostname' => '::1', 'snmp_community' => [ 'community' ], 'snmp_version' => 'v2c', 'snmp_transport' => 'udp', 'snmp_port' => 123 ] ];

        // Only hostname/ip for detect all possible params
        // hostname.test
        $array[] = [ [ 'hostname.test' ],
                     [ 'hostname' => 'hostname.test', 'snmp_version' => NULL, 'snmp_transport' => 'udp', 'snmp_port' => 161 ] ];
        // 127.0.0.1
        $array[] = [ [ '127.0.0.1' ],
                     [ 'hostname' => '127.0.0.1', 'snmp_version' => NULL, 'snmp_transport' => 'udp', 'snmp_port' => 161 ] ];

        return $array;
    }

    /**
     * @dataProvider providerIsModuleEnabled
     * @group device
     */
    public function testIsModuleEnabled($device, $module, $default, $enabled, $disabled, $attrib = TRUE) {
        $process = 'poller';
        // Pseudo cache for attribs:
        $GLOBALS['cache']['entity_attribs_all']['device'][$device['device_id']] = []; // set array, for skip query db
        // Check definition(s)
        $orig = $GLOBALS['config']['poller_modules'][$module];
        $this->assertSame($default, is_module_enabled($device, $module, $process));
        $GLOBALS['config']['poller_modules'][$module] = 1;
        $this->assertSame($enabled, is_module_enabled($device, $module, $process));
        $GLOBALS['config']['poller_modules'][$module] = 0;
        $this->assertSame($disabled, is_module_enabled($device, $module, $process));
        $GLOBALS['config']['poller_modules'][$module] = $orig;

        if ($module === 'os') { return; } // os ignore attrib

        // Check attrib
        $setting_name = 'poll_' . $module;
        $GLOBALS['cache']['entity_attribs']['device'][$device['device_id']][$setting_name] = '1'; // attrib true
        if ($attrib) {
            $this->assertTrue(is_module_enabled($device, $module, $process));
        } else {
            $this->assertFalse(is_module_enabled($device, $module, $process));
        }
        $GLOBALS['cache']['entity_attribs']['device'][$device['device_id']][$setting_name] = '0'; // attrib false
        $this->assertFalse(is_module_enabled($device, $module, $process));
    }

    public static function providerIsModuleEnabled() {
        $device_linux = [ 'device_id' => 999, 'os' => 'linux', 'os_group' => 'unix' ];
        $device_win   = [ 'device_id' => 998, 'os' => 'windows' ];
        $device_ios   = [ 'device_id' => 997, 'os' => 'ios', 'os_group' => 'cisco', 'type' => 'network' ];
        $device_vrp   = [ 'device_id' => 996, 'os' => 'vrp', 'type' => 'network' ];
        $device_amm   = [ 'device_id' => 995, 'os' => 'ibm-amm' ]; // poller/discovery blacklists
        $device_gen   = [ 'device_id' => 994, 'os' => 'generic' ];
        $device_aruba = [ 'device_id' => 993, 'os' => 'arubaos', 'type' => 'wireless' ];

        $result = [];
        $result[] = [ $device_linux, 'os', TRUE, TRUE, TRUE ];
        $result[] = [ $device_win,   'os', TRUE, TRUE, TRUE ];
        $result[] = [ $device_ios,   'os', TRUE, TRUE, TRUE ];
        $result[] = [ $device_vrp,   'os', TRUE, TRUE, TRUE ];
        $result[] = [ $device_amm,   'os', TRUE, TRUE, TRUE ];
        $result[] = [ $device_gen,   'os', TRUE, TRUE, TRUE ];

        $result[] = [ $device_linux, 'unix-agent', FALSE,  TRUE, FALSE ];
        $result[] = [ $device_win,   'unix-agent', FALSE, FALSE, FALSE, FALSE ];
        $result[] = [ $device_ios,   'unix-agent', FALSE, FALSE, FALSE, FALSE ];
        $result[] = [ $device_vrp,   'unix-agent', FALSE, FALSE, FALSE, FALSE ];
        $result[] = [ $device_amm,   'unix-agent', FALSE, FALSE, FALSE, FALSE ];
        //$result[] = [ $device_gen,   'unix-agent', FALSE,  TRUE, FALSE ];

        $result[] = [ $device_linux, 'ipmi',  TRUE,  TRUE, FALSE ];
        $result[] = [ $device_win,   'ipmi',  TRUE,  TRUE, FALSE ];
        $result[] = [ $device_ios,   'ipmi', FALSE, FALSE, FALSE, FALSE ];
        $result[] = [ $device_vrp,   'ipmi', FALSE, FALSE, FALSE, FALSE ];
        $result[] = [ $device_amm,   'ipmi',  TRUE,  TRUE, FALSE ];
        $result[] = [ $device_gen,   'ipmi',  TRUE,  TRUE, FALSE ];

        $result[] = [ $device_linux, 'ports',  TRUE,  TRUE, FALSE ];
        $result[] = [ $device_win,   'ports',  TRUE,  TRUE, FALSE ];
        $result[] = [ $device_ios,   'ports',  TRUE,  TRUE, FALSE ];
        $result[] = [ $device_vrp,   'ports',  TRUE,  TRUE, FALSE ];
        $result[] = [ $device_amm,   'ports', FALSE, FALSE, FALSE, FALSE ];
        $result[] = [ $device_gen,   'ports',  TRUE,  TRUE, FALSE ];

        $result[] = [ $device_linux, 'wifi', FALSE, FALSE, FALSE ];
        $result[] = [ $device_win,   'wifi', FALSE, FALSE, FALSE ];
        $result[] = [ $device_ios,   'wifi',  TRUE,  TRUE, FALSE ];
        $result[] = [ $device_vrp,   'wifi',  TRUE,  TRUE, FALSE ];
        $result[] = [ $device_amm,   'wifi', FALSE, FALSE, FALSE ];
        $result[] = [ $device_gen,   'wifi', FALSE, FALSE, FALSE ];
        $result[] = [ $device_aruba, 'wifi',  TRUE,  TRUE, FALSE ];

        foreach ([ 'cipsec-tunnels', 'cisco-ipsec-flow-monitor', 'cisco-remote-access-monitor',
                   'cisco-cef', 'cisco-cbqos', 'cisco-eigrp', /*'cisco-vpdn'*/ ] as $module) {
            $result[] = [ $device_linux, $module, FALSE, FALSE, FALSE, FALSE ];
            $result[] = [ $device_win,   $module, FALSE, FALSE, FALSE, FALSE ];
            $result[] = [ $device_ios,   $module, TRUE, TRUE, FALSE ];
            $result[] = [ $device_vrp,   $module, FALSE, FALSE, FALSE, FALSE ];
            $result[] = [ $device_amm,   $module, FALSE, FALSE, FALSE, FALSE ];
            $result[] = [ $device_gen,   $module, FALSE, FALSE, FALSE, FALSE ];
        }

        $result[] = [ $device_linux, 'aruba-controller', FALSE, FALSE, FALSE, FALSE ];
        $result[] = [ $device_win,   'aruba-controller', FALSE, FALSE, FALSE, FALSE ];
        $result[] = [ $device_ios,   'aruba-controller', FALSE, FALSE, FALSE, FALSE ];
        $result[] = [ $device_vrp,   'aruba-controller', FALSE, FALSE, FALSE, FALSE ];
        $result[] = [ $device_amm,   'aruba-controller', FALSE, FALSE, FALSE, FALSE ];
        $result[] = [ $device_gen,   'aruba-controller', FALSE, FALSE, FALSE, FALSE ];
        $result[] = [ $device_aruba, 'aruba-controller',  TRUE,  TRUE, FALSE ];

        return $result;
    }
}

// EOF
