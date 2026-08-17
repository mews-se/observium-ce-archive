<?php

class HtmlIncludesFunctionsTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @dataProvider providerNiceCase
   * @group string
   */
  public function testNiceCase($string, $result)
  {
    $this->assertSame($result, nicecase($string));
  }

  public static function providerNiceCase()
  {
    return array(
      array('bgp_peer', 'BGP Peer'),
      array('bgp_peer_af', 'BGP Peer (AFI/SAFI)'),
      array('netscaler_vsvr', 'Netscaler vServer'),
      array('netscaler_svc', 'Netscaler Service'),
      array('mempool', 'Memory'),
      array('ipsec_tunnels', 'IPSec Tunnels'),
      array('vrf', 'VRF'),
      array('isis', 'IS-IS'),
      array('cef', 'CEF'),
      array('eigrp', 'EIGRP'),
      array('ospf', 'OSPF'),
      array('bgp', 'BGP'),
      array('ases', 'ASes'),
      array('vpns', 'VPNs'),
      array('dbm', 'dBm'),
      array('mysql', 'MySQL'),
      array('powerdns', 'PowerDNS'),
      array('bind', 'BIND'),
      array('ntpd', 'NTPd'),
      array('powerdns-recursor', 'PowerDNS Recursor'),
      array('freeradius', 'FreeRADIUS'),
      array('postfix_mailgraph', 'Postfix Mailgraph'),
      array('ge', 'Greater or equal'),
      array('le', 'Less or equal'),
      array('notequals', 'Doesn\'t equal'),
      array('notmatch', 'Doesn\'t match'),
      array('diskio', 'Disk I/O'),
      array('ipmi', 'IPMI'),
      array('snmp', 'SNMP'),
      array('mssql', 'SQL Server'),
      array('apower', 'Apparent power'),
      array('proxysg', 'Proxy SG'),
      array('', ''),

      array(' some text here ', ' some text here '),
      array('some text here ', 'Some text here '),
      array(NULL, ''),
      array(FALSE, ''),
      array(array('test'), '')
    );
  }

    /**
     * @dataProvider providerGetDeviceIcon
     * @group icon
     */
    public function testGetDeviceIcon($device, $base_icon, $result) {
        $GLOBALS['config']['base_url'] = 'http://localhost';
        // for device_permitted
        $device['device_id'] = 98217;
        $_SESSION['userlevel'] = 7;
        $this->assertSame($result, get_device_icon($device, $base_icon));
    }

    public static function providerGetDeviceIcon() {
        return [
            // by $device['os']
            [ [ 'os' => 'screenos', 'icon' => '', 'sysObjectID' => '' ], TRUE, 'juniper-old' ],
            // by $device['os'] and icon definition
            [ [ 'os' => 'ios', 'icon' => '', 'sysObjectID' => '' ], TRUE, 'cisco' ],
            // by $device['os'] and vendor definition
            [ [ 'os' => 'liebert', 'icon' => '', 'sysObjectID' => '' ], TRUE, 'emerson' ],
            // by $device['os'] and vendor defined icon
            [ [ 'os' => 'summitd-wl', 'icon' => '', 'sysObjectID' => '' ], TRUE, 'summitd' ],
            // by $device['os'] and vendor defined icon
            [ [ 'os' => 'summitd-wl', 'icon' => '', 'sysObjectID' => '', 'vendor' => 'Summit Development' ], TRUE, 'summitd' ],
            // by $device['os'] and vendor definition (with non alpha chars)
            [ [ 'os' => 'wut-com' ], TRUE, 'wut' ], // W&T
            // by $device['os'] and distro name in array
            [ [ 'os' => 'linux', 'icon' => '', 'sysObjectID' => '', 'distro' => 'RedHat' ], TRUE, 'redhat' ],
            // by $device['os'] and icon in device array
            [ [ 'os' => 'ios', 'icon' => 'cisco-old', 'sysObjectID' => '' ], TRUE, 'cisco-old' ],
            // by all, who win?
            [ [ 'os' => 'liebert', 'distro' => 'RedHat', 'icon' => 'cisco-old', 'sysObjectID' => '' ], TRUE, 'cisco-old' ],
            // unknown
            [ [ 'os' => 'yohoho', 'icon' => '', 'sysObjectID' => '' ], TRUE, 'generic' ],
            // empty
            [ [], TRUE, 'generic' ],

            // Prevent use vendor icon for unix/window oses and visa-versa for others
            [ [ 'os' => 'pve',            'type' => 'hypervisor', 'sysObjectID' => '', 'vendor' => 'Supermicro' ], TRUE, 'proxmox' ],
            [ [ 'os' => 'proxmox-server', 'type' => 'server',     'sysObjectID' => '', 'vendor' => 'Supermicro' ], TRUE, 'proxmox' ],
            [ [ 'os' => 'truenas-core',   'type' => 'storage',    'sysObjectID' => '', 'vendor' => 'Supermicro' ], TRUE, 'truenas' ],
            [ [ 'os' => 'generic-ups',    'type' => 'power',      'sysObjectID' => '', 'vendor' => '' ],           TRUE, 'ups' ],
            [ [ 'os' => 'generic-ups',    'type' => 'power',      'sysObjectID' => '', 'vendor' => 'Supermicro' ], TRUE, 'supermicro' ],

            // Last, check with img tag
            [ [ 'os' => 'ios' ],   FALSE, '<img src="http://localhost/images/os/cisco.svg" style="max-height: 32px; max-width: 48px;" alt="" />' ],
            [ [ 'os' => 'screenos' ], FALSE, '<img src="http://localhost/images/os/juniper-old.png" srcset="http://localhost/images/os/juniper-old_2x.png 2x" alt="" />' ],
        ];
    }

    /**
     * @dataProvider providerHtmlHighlight
     * @group string
     */
    public function testHtmlHighlight($text, $search, $result, $replace = '') {
        $this->assertSame($result, html_highlight($text, $search, $replace));
    }

    public static function providerHtmlHighlight() {
        return [
            [ 'BGP Peer (AFI/SAFI)',    '', 'BGP Peer (AFI/SAFI)' ],
            [ 'BGP Peer (AFI/SAFI)', 'afi', 'BGP Peer (<em class="text-danger">AFI</em>/S<em class="text-danger">AFI</em>)' ],
            [ 'BGP Peer (AFI/SAFI)', 'eer', 'BGP P<em class="text-danger">eer</em> (AFI/SAFI)' ],
            [ 'BGP Peer (AFI/SAFI)', [ 'afi', 'eer' ], 'BGP P<em class="text-danger">eer</em> (<em class="text-danger">AFI</em>/S<em class="text-danger">AFI</em>)' ],
            // escape
            [ 'BGP Peer "AFI/SAFI"', [ 'afi', 'eer' ], 'BGP P<em class="text-danger">eer</em> &quot;<em class="text-danger">AFI</em>/S<em class="text-danger">AFI</em>&quot;' ],
        ];
    }

    /**
     * @dataProvider providerHtmlHighlightEntities
     * @group string
     */
    public function testHtmlHighlightEntities($text, $search, $result, $result_escaped = '') {
        $this->assertSame($result_escaped, html_highlight_entities($text, $search));
        $this->assertSame($result, html_highlight_entities($text, $search, '', FALSE));
    }

    public static function providerHtmlHighlightEntities() {
        // Ports
        $msg1 = 'id="2001" severity="info" sys="SecureNet" sub="packetfilter" name="Packet dropped" action="drop" fwrule="60002" initf="eth0" outitf="eth0" srcmac="d4:ca:6d:22:93:8c" dstmac="00:50:56:a1:7b:6c" srcip="5.49.1.8" dstip="1.20.20.9" proto="6" length="60" tos="0x00" prec="0x00" ttl="49" srcport="3666" dstport="23" tcpflags="SYN"';
        $search1 = [
            [ 'search'  => [ 'lo' ],
              'replace' => '<a href="device/device=245/tab=port/port=660131/" class="entity-popup " data-eid="660131" data-etype="port">$2</a>' ],
            [ 'search'  => [ 'eth0' ],
              'replace' => '<a href="device/device=245/tab=port/port=660132/" class="entity-popup " data-eid="660132" data-etype="port">$2</a>' ],
            [ 'search'  => [ 'eth1' ],
              'replace' => '<a href="device/device=245/tab=port/port=660133/" class="entity-popup " data-eid="660133" data-etype="port">$2</a>' ],
        ];
        $result1 = 'id="2001" severity="info" sys="SecureNet" sub="packetfilter" name="Packet dropped" action="drop" fwrule="60002" initf="<a href="device/device=245/tab=port/port=660132/" class="entity-popup " data-eid="660132" data-etype="port">eth0</a>" outitf="<a href="device/device=245/tab=port/port=660132/" class="entity-popup " data-eid="660132" data-etype="port">eth0</a>" srcmac="d4:ca:6d:22:93:8c" dstmac="00:50:56:a1:7b:6c" srcip="5.49.1.8" dstip="1.20.20.9" proto="6" length="60" tos="0x00" prec="0x00" ttl="49" srcport="3666" dstport="23" tcpflags="SYN"';
        $esc1    = 'id=&quot;2001&quot; severity=&quot;info&quot; sys=&quot;SecureNet&quot; sub=&quot;packetfilter&quot; name=&quot;Packet dropped&quot; action=&quot;drop&quot; fwrule=&quot;60002&quot; initf=&quot;<a href="device/device=245/tab=port/port=660132/" class="entity-popup " data-eid="660132" data-etype="port">eth0</a>&quot; outitf=&quot;<a href="device/device=245/tab=port/port=660132/" class="entity-popup " data-eid="660132" data-etype="port">eth0</a>&quot; srcmac=&quot;d4:ca:6d:22:93:8c&quot; dstmac=&quot;00:50:56:a1:7b:6c&quot; srcip=&quot;5.49.1.8&quot; dstip=&quot;1.20.20.9&quot; proto=&quot;6&quot; length=&quot;60&quot; tos=&quot;0x00&quot; prec=&quot;0x00&quot; ttl=&quot;49&quot; srcport=&quot;3666&quot; dstport=&quot;23&quot; tcpflags=&quot;SYN&quot;';

        $msg2    = 'id="2001" severity="info" sys="SecureNet" sub="packetfilter" name="Packet dropped" action="drop" fwrule="60002" initf="eth0" outitf="eth1,lo" srcmac="d4:ca:6d:22:93:8c" dstmac="00:50:56:a1:7b:6c" srcip="5.49.1.8" dstip="1.20.20.9" proto="6" length="60" tos="0x00" prec="0x00" ttl="49" srcport="3666" dstport="23" tcpflags="SYN"';
        $result2 = 'id="2001" severity="info" sys="SecureNet" sub="packetfilter" name="Packet dropped" action="drop" fwrule="60002" initf="<a href="device/device=245/tab=port/port=660132/" class="entity-popup " data-eid="660132" data-etype="port">eth0</a>" outitf="<a href="device/device=245/tab=port/port=660133/" class="entity-popup " data-eid="660133" data-etype="port">eth1</a>,<a href="device/device=245/tab=port/port=660131/" class="entity-popup " data-eid="660131" data-etype="port">lo</a>" srcmac="d4:ca:6d:22:93:8c" dstmac="00:50:56:a1:7b:6c" srcip="5.49.1.8" dstip="1.20.20.9" proto="6" length="60" tos="0x00" prec="0x00" ttl="49" srcport="3666" dstport="23" tcpflags="SYN"';
        $esc2    = 'id=&quot;2001&quot; severity=&quot;info&quot; sys=&quot;SecureNet&quot; sub=&quot;packetfilter&quot; name=&quot;Packet dropped&quot; action=&quot;drop&quot; fwrule=&quot;60002&quot; initf=&quot;<a href="device/device=245/tab=port/port=660132/" class="entity-popup " data-eid="660132" data-etype="port">eth0</a>&quot; outitf=&quot;<a href="device/device=245/tab=port/port=660133/" class="entity-popup " data-eid="660133" data-etype="port">eth1</a>,<a href="device/device=245/tab=port/port=660131/" class="entity-popup " data-eid="660131" data-etype="port">lo</a>&quot; srcmac=&quot;d4:ca:6d:22:93:8c&quot; dstmac=&quot;00:50:56:a1:7b:6c&quot; srcip=&quot;5.49.1.8&quot; dstip=&quot;1.20.20.9&quot; proto=&quot;6&quot; length=&quot;60&quot; tos=&quot;0x00&quot; prec=&quot;0x00&quot; ttl=&quot;49&quot; srcport=&quot;3666&quot; dstport=&quot;23&quot; tcpflags=&quot;SYN&quot;';

        $msg3    = "Syslog connection established; fd='34', server='AF_INET(77.222.50.30:514)', local='AF_INET(0.0.0.0:0)'";
        $result3 = "Syslog connection established; fd='34', server='AF_INET(77.222.50.30:514)', local='AF_INET(0.0.0.0:0)'";
        $esc3    = "Syslog connection established; fd=&#039;34&#039;, server=&#039;AF_INET(77.222.50.30:514)&#039;, local=&#039;AF_INET(0.0.0.0:0)&#039;";

        // BGP
        $msg4 = 'BGP peer 198.18.9.242 (External AS 57724): Error event Operation timed out(60) for I/O session - closing it (instance master)';
        $msg5 = 'BGP_RESET_PENDING_CONNECTION 77.222.49.254 (Internal AS 44112): reseting pending active connection (instance BACKBONE)';
        return [
            [ $msg1,    $search1, $result1, $esc1 ],
            [ $msg2,    $search1, $result2, $esc2 ],
            [ $msg3,    $search1, $result3, escape_html($result3) ],
        ];
    }

    protected function setUp(): void
    {
        // Start the session before each test
        @session_start();
    }

    protected function tearDown(): void
    {
        // Clean up the session after each test
        session_unset();
        session_destroy();
    }

    /**
     * @group session
     */
    public function test_single_key()
    {
        session_set_var('key', 'value');
        $this->assertEquals('value', $_SESSION['key']);
    }

    /**
     * @group session
     */
    public function test_nested_keys()
    {
        session_set_var('key1->key2->key3', 'value');
        $this->assertEquals('value', $_SESSION['key1']['key2']['key3']);
    }

    /**
     * @group session
     */
    public function test_unset_single_key()
    {
        $_SESSION['key'] = 'value';
        session_set_var('key', null);
        $this->assertArrayNotHasKey('key', $_SESSION);
    }

    /**
     * @group session
     */
    public function test_unset_nested_keys()
    {
        $_SESSION['key1']['key2']['key3'] = 'value';
        session_set_var('key1->key2->key3', null);
        $this->assertArrayNotHasKey('key3', $_SESSION['key1']['key2']);
    }

    /**
     * @group session
     */
    public function test_no_change_single_key()
    {
        $_SESSION['key'] = 'value';
        session_set_var('key', 'value');
        $this->assertEquals('value', $_SESSION['key']);
    }

    /**
     * @group session
     */
    public function test_no_change_nested_keys()
    {
        $_SESSION['key1']['key2']['key3'] = 'value';
        session_set_var('key1->key2->key3', 'value');
        $this->assertEquals('value', $_SESSION['key1']['key2']['key3']);
    }

    /**
     * @group session
     */
    public function test_single_key_array()
    {
        session_set_var(['key'], 'value');
        $this->assertEquals('value', $_SESSION['key']);
    }

    /**
     * @group session
     */
    public function test_nested_keys_array()
    {
        session_set_var(['key1', 'key2', 'key3'], 'value');
        $this->assertEquals('value', $_SESSION['key1']['key2']['key3']);
    }

    /**
     * @group session
     */
    public function test_unset_single_key_array()
    {
        $_SESSION['key'] = 'value';
        session_set_var(['key'], null);
        $this->assertArrayNotHasKey('key', $_SESSION);
    }

    /**
     * @group session
     */
    public function test_unset_nested_keys_array()
    {
        $_SESSION['key1']['key2']['key3'] = 'value';
        session_set_var(['key1', 'key2', 'key3'], null);
        $this->assertArrayNotHasKey('key3', $_SESSION['key1']['key2']);
    }

}

// EOF
