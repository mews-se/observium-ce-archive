<?php

//define('OBS_DEBUG', 1);

include(__DIR__ . '/../includes/port-descr-parser.inc.php');

class IncludesPortDescrParserTest extends \PHPUnit\Framework\TestCase {
    /**
     * @dataProvider providerParser
     */
    public function testParser($string, $result) {
        global $config;

        // Add in custom interface groups for testing
        $config['int_groups'] = [ 'TestGroup1', 'TestGroup2', 'abr' ];

        $this->assertSame($result, custom_port_parser([ 'ifAlias' => $string ]));
    }

  public static function providerParser() {
    return array(
      array('Cust: Example Customer',
            array('type'    => 'Cust',
                  'descr'   => 'Example Customer',
                  //'circuit' => null,
                  //'speed'   => null,
                  //'notes'   => null,
            )
      ),
      array('Cust: Example Customer {CIRCUIT}',
            array('type'    => 'Cust',
                  'descr'   => 'Example Customer',
                  'circuit' => 'CIRCUIT',
                  //'speed'   => null,
                  //'notes'   => null,
            )
      ),
      array('Cust: Example Customer [SPEED]',
            array('type'    => 'Cust',
                  'descr'   => 'Example Customer',
                  //'circuit' => null,
                  'speed'   => 'SPEED',
                  //'notes'   => null,
            )
      ),
      array('Cust: Example Customer (NOTE)',
            array('type'    => 'Cust',
                  'descr'   => 'Example Customer',
                  //'circuit' => null,
                  //'speed'   => null,
                  'notes'   => 'NOTE',
            )
      ),
      array('Cust: Example Customer {CIRCUIT} (NOTE)',
            array('type'    => 'Cust',
                  'descr'   => 'Example Customer',
                  'circuit' => 'CIRCUIT',
                  //'speed'   => null,
                  'notes'   => 'NOTE',
            )
      ),
      array('Cust: Example Customer {CIRCUIT} [SPEED]',
            array('type'    => 'Cust',
                  'descr'   => 'Example Customer',
                  'circuit' => 'CIRCUIT',
                  'speed'   => 'SPEED',
                  //'notes'   => null,
            )
      ),
      array('Cust: Example Customer [SPEED] (NOTE)',
            array('type'    => 'Cust',
                  'descr'   => 'Example Customer',
                  //'circuit' => null,
                  'speed'   => 'SPEED',
                  'notes'   => 'NOTE',
            )
      ),
      array('Cust: Example Customer {CIRCUIT} [SPEED] (NOTE)',
            array('type'    => 'Cust',
                  'descr'   => 'Example Customer',
                  'circuit' => 'CIRCUIT',
                  'speed'   => 'SPEED',
                  'notes'   => 'NOTE',
            )
      ),
      array('Cust: Example Customer{CIRCUIT}[SPEED](NOTE)',
            array('type'    => 'Cust',
                  'descr'   => 'Example Customer',
                  'circuit' => 'CIRCUIT',
                  'speed'   => 'SPEED',
                  'notes'   => 'NOTE',
            )
      ),
      array('Cust: !@#$%^&*_-=+/|\.,`~";:<>?\' {CIRCUIT}[SPEED](NOTE)',
            array('type'    => 'Cust',
                  'descr'   => '!@#$%^&*_-=+/|\.,`~";:<>?\'',
                  'circuit' => 'CIRCUIT',
                  'speed'   => 'SPEED',
                  'notes'   => 'NOTE',
            )
      ),
      // website example
      array('Cust: Example Customer [10Mbit] (T1 Telco Y CCID129031) {EXAMP0001}',
            array('type'    => 'Cust',
                  'descr'   => 'Example Customer',
                  'circuit' => 'EXAMP0001',
                  'speed'   => '10Mbit',
                  'notes'   => 'T1 Telco Y CCID129031',
            )
      ),

      # Transit
      array('Transit: Example Provider {CIRCUIT} [SPEED] (NOTE)',
            array('type'    => 'Transit',
                  'descr'   => 'Example Provider',
                  'circuit' => 'CIRCUIT',
                  'speed'   => 'SPEED',
                  'notes'   => 'NOTE',
            )
      ),

      # Core
      array('Core: Example Core {CIRCUIT} [SPEED] (NOTE)',
            array('type'    => 'Core',
                  'descr'   => 'Example Core',
                  'circuit' => 'CIRCUIT',
                  'speed'   => 'SPEED',
                  'notes'   => 'NOTE',
            )
      ),

      # Peering
      array('Peering: Example Peer {CIRCUIT} [SPEED] (NOTE)',
            array('type'    => 'Peering',
                  'descr'   => 'Example Peer',
                  'circuit' => 'CIRCUIT',
                  'speed'   => 'SPEED',
                  'notes'   => 'NOTE',
            )
      ),

      # Server
      array('Server: Example Server {CIRCUIT} [SPEED] (NOTE)',
            array('type'    => 'Server',
                  'descr'   => 'Example Server',
                  'circuit' => 'CIRCUIT',
                  'speed'   => 'SPEED',
                  'notes'   => 'NOTE',
            )
      ),

      # L2TP
      array('L2TP: Example L2TP {CIRCUIT} [SPEED] (NOTE)',
            array('type'    => 'L2TP',
                  'descr'   => 'Example L2TP',
                  'circuit' => 'CIRCUIT',
                  'speed'   => 'SPEED',
                  'notes'   => 'NOTE',
            )
      ),

      # Custom: TestGroup1
      array('TestGroup1: Test Group 1 {CIRCUIT} [SPEED] (NOTE)',
            array('type'    => 'TestGroup1',
                  'descr'   => 'Test Group 1',
                  'circuit' => 'CIRCUIT',
                  'speed'   => 'SPEED',
                  'notes'   => 'NOTE',
            )
      ),

      # Custom: TestGroup2
      array('TestGroup2: Test Group 2 {CIRCUIT} [SPEED] (NOTE)',
            array('type'    => 'TestGroup2',
                  'descr'   => 'Test Group 2',
                  'circuit' => 'CIRCUIT',
                  'speed'   => 'SPEED',
                  'notes'   => 'NOTE',
            )
      ),

      # Issues
      [
        'ABR: aepripb1 - RIPATRANSONE {OPEN FIBER E0000000044} [1Gbit]',
        [
          'type'    => 'ABR',
          'descr'   => 'aepripb1 - RIPATRANSONE',
          'circuit' => 'OPEN FIBER E0000000044',
          'speed'   => '1Gbit',
          //'notes'   => NULL,
        ]
      ],

      [
          'Core: Intersite AU-SF AT&T PtP #1 {CirID BFEC.678749.ATI} [100Gbit]',
          [
              'type'    => 'Core',
              'descr'   => 'Intersite AU-SF AT&T PtP #1',
              'circuit' => 'CirID BFEC.678749.ATI',
              'speed'   => '100Gbit',
              //'notes'   => NULL,
          ]
      ],

      [
          'Core: Intersite AU-SF AT&T PtP #1 [100Gbit] {CirID BFEC.678749.ATI}',
          [
              'type'    => 'Core',
              'descr'   => 'Intersite AU-SF AT&T PtP #1',
              'circuit' => 'CirID BFEC.678749.ATI',
              'speed'   => '100Gbit',
              //'notes'   => NULL,
          ]
      ],

      # Errors

      # Missing description
      array('Core: {CIRCUIT} [SPEED] (NOTE)',
            array(),
      ),
      # Missing type
      array('Example {CIRCUIT} [SPEED] (NOTE)',
            array(),
      ),
      # B0rken circuit
      array('Core: Example {CIRCUIT',
            array('type'    => 'Core',
                  'descr'   => 'Example',
                  //'circuit' => null,
                  //'speed'   => null,
                  //'notes'   => null,
            )
      ),
      # B0rken circuit
      array('Core: Example CIRCUIT}',
            array('type'    => 'Core',
                  'descr'   => 'Example CIRCUIT',
                  //'circuit' => null,
                  //'speed'   => null,
                  //'notes'   => null,
            )
      ),
      # B0rken speed
      array('Core: Example [SPEED',
            array('type'    => 'Core',
                  'descr'   => 'Example',
                  //'circuit' => null,
                  //'speed'   => null,
                  //'notes'   => null,
            )
      ),
      # B0rken speed
      array('Core: Example SPEED]',
            array('type'    => 'Core',
                  'descr'   => 'Example SPEED',
                  //'circuit' => null,
                  //'speed'   => null,
                  //'notes'   => null,
            )
      ),
      # B0rken notes
      array('Core: Example (NOTE',
            array('type'    => 'Core',
                  'descr'   => 'Example',
                  //'circuit' => null,
                  //'speed'   => null,
                  //'notes'   => null,
            )
      ),
      # B0rken notes
      array('Core: Example NOTE)',
            array('type'    => 'Core',
                  'descr'   => 'Example NOTE',
                  //'circuit' => null,
                  //'speed'   => null,
                  //'notes'   => null,
            )
      ),

      # Bogus type
      [ 'Foo: Example {CIRCUIT} [SPEED] (NOTE)',
        [], ],
    );
  }
}

// EOF
