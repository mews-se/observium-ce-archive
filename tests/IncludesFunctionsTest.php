<?php

// Test-specific setup (bootstrap.php handles common setup)
// Load any specific includes needed for this test suite

class IncludesFunctionsTest extends \PHPUnit\Framework\TestCase
{
  /**
  * @dataProvider providerEmail
  */
  public function testParseEmail($string, $result)
  {
    $this->assertSame($result, parse_email($string));
  }

  public static function providerEmail()
  {
    return array(
        array('test@example.com',     array('test@example.com' => NULL)),
        array(' test@example.com ',   array('test@example.com' => NULL)),
        array('<test@example.com>',   array('test@example.com' => NULL)),
        array('<test@example.com> ',  array('test@example.com' => NULL)),
        array(' <test@example.com> ', array('test@example.com' => NULL)),

        //array('Test Title <test@example>',          array('test@example' => 'Test Title')), // Non fqdn
        array('Test Title <test@example.com>',      array('test@example.com' => 'Test Title')),
        array('Test Title<test@example.com>',       array('test@example.com' => 'Test Title')),
        array('"Test Title" <test@example.com>',    array('test@example.com' => 'Test Title')),
        //array('"Test Title <test@example.com>',     array('test@example.com' => 'Test Title')), // incorrect test
        //array('Test Title" <test@example.com>',     array('test@example.com' => 'Test Title')), // incorrect test
        array('" Test Title " <test@example.com>',  array('test@example.com' => 'Test Title')),
        array('\'Test Title\' <test@example.com>',  array('test@example.com' => 'Test Title')),

        array('"Test Title" <test@example.com>,"Test Title 2" <test2@example.com>',
              array('test@example.com' => 'Test Title', 'test2@example.com' => 'Test Title 2')),
        array('\'Test Title\' <test@example.com>, "Test Title 2" <test2@sub.example.com>,     test3@example.com',
              array('test@example.com' => 'Test Title', 'test2@sub.example.com' => 'Test Title 2', 'test3@example.com' => NULL)),

        array('example.com',                 FALSE),
        array('<example.com>',               FALSE),
        array('Test Title test@example.com', FALSE),
        array('Test Title <example.com>',    FALSE),
    );
  }

    /**
     * @dataProvider providerSiToScale
     * @group numbers
     */
    public function testSiToScale($units, $precision, $result) {
        $this->assertSame($result, si_to_scale($units, $precision));
    }

    public static function providerSiToScale() {
        return [
            [ 'yocto',  5, 1.0E-29 ],
            [ 'zepto', -6, 1.0E-21 ],
            [ 'atto',   9, 1.0E-27 ],
            [ 'femto',  8, 1.0E-23 ],
            [ 'pico',   0, 1.0E-12 ],
            [ 'nano',  -7, 1.0E-9 ],
            [ 'micro',  4, 1.0E-10 ],
            [ 'milli',  7, 1.0E-10 ],
            [ 'deci',   0, 0.1 ],
            [ 'units',  3, 0.001 ],
            [ 'deca',   0, 10 ],
            [ 'kilo',   2, 10 ],
            [ 'mega',  -2, 1000000 ],
            [ 'giga',  -1, 1000000000 ],
            [ 'tera',  -4, 1000000000000 ],
            [ 'peta',   4, 100000000000 ],
            [ 'exa',   -3, 1000000000000000000 ],
            [ 'zetta',  1, 1.0E+20 ],
            [ 'yotta',  7, 100000000000000000 ],
            [ '',      -6, 1 ],
            [ 'test',   6, 1.0E-6 ],
            [ '0',     -3, 1 ],
            [ '5',      2, 1000 ],
            [ '-1',     1, 0.01 ],

            // incorrect binary prefix
            [ 'tebi',  -1, 1099511627776 ],
        ];
    }

    /**
     * @dataProvider providerSiToScaleValue
     * @group numbers
     */
    public function testSiToScaleValue($value, $scale, $result) {
        if (method_exists($this, 'assertEqualsWithDelta')) {
            $this->assertEqualsWithDelta($result, $value * si_to_scale($scale), 0.00001);
        } else {
            $this->assertSame($result, $value * si_to_scale($scale));
        }
    }

    public static function providerSiToScaleValue() {
        return [
            [ '330',  '-2', 3.3 ],
            [ '1194', '-2', 11.94 ],
            [ '928',  NULL, 928 ],
            [ '9',     '1', 90 ],
            [ '22',    '0', 22 ],
            [ '1194', 'milli', 1.194 ],
        ];
    }

    /**
     * @dataProvider providerBiToScale
     * @group numbers
     */
    public function testBiToScale($units, $result) {
        $this->assertSame($result, bi_to_scale($units));
    }

    public static function providerBiToScale() {
        return [
            [ 'kibi', 1024 ],
            [ 'mebi', 1048576 ],
            [ 'gibi', 1073741824 ],
            [ 'tebi', 1099511627776 ],
            [ 'pebi', 1125899906842624 ],
            [ 'exbi', 1152921504606846976 ],
            [ 'zebi', 1.1805916207174113E+21 ],
            [ 'yobi', 1.2089258196146292E+24 ],
            [ '',     1 ],

            [ 'test', 1 ],
            [ '0',    1 ],
            [ '5',    32 ],
            [ '-1',   0 ],
        ];
    }

  /**
   * @dataProvider providerFloatCompare
   * @group numbers
   */
  public function testFloatCompare($a, $b, $epsilon, $result)
  {
    $this->assertSame($result, float_cmp($a, $b, $epsilon));
  }

  public static function providerFloatCompare()
  {
    return array(
        // numeric tests
        array('330', '-2', NULL,  1), // $a > $b
        array('1',    '2', 0.1,  -1), // $a < $b
        array(-1,      -2, 0.1,   1), // $a > $b
        array(-1.1,  -1.4, 0.5,   0), // $a == $b
        array(-1.1,  -1.4, -0.5,  0), // $a == $b
        array( 0.0,  70.0,  0.1, -1), // $a < $b and $a == 0
        array(70.0,   0.0,  0.1,  1), // $a > $b and $b == 0
        array(   0,   0.0, NULL,  0), // $a == $b
        array(0.001,    0.000999999,  0.00001,  0), // $a == $b
        array(-0.001,  -0.000999999,  0.00001,  0), // $a == $b
        array(-0.001,  -0.000899999,  0.00001, -1), // $a < $b
        //array('-0.00000001', 0.00000002, NULL,  0), // $a == $b, FIXME, FALSE
        //array(0.00000002, '-0.00000001', NULL,  0), // $a == $b, FIXME, FALSE
        array(0.2, '-0.000000000001', NULL,  1), // $a == $b
        array(0.99999999, 1.00000002, NULL,  0), // $a == $b
        array(0.001,   -0.000999999,  NULL,  1), // $a > $b
        array(-0.000999999,   0.001,  NULL, -1), // $a < $b
        array(3672,   3888,           0.05,  0), // big numbers, greater epsilon
        array(3888,   3672,           0.05,  0), // big numbers, greater epsilon
        array(4000,   4810,            0.1,  0), // big numbers, greater epsilon
        array(4000,   4000.01,        NULL,  0), // big numbers

        /* Regular large numbers */
        array(1000000,      1000001,  NULL,  0),
        array(1000001,      1000000,  NULL,  0),
        array(10000,          10001,  NULL, -1),
        array(10001,          10000,  NULL,  1),
        /* Negative large numbers */
        array(-1000000,    -1000001,  NULL,  0),
        array(-1000001,    -1000000,  NULL,  0),
        array(-10000,        -10001,  NULL,  1),
        array(-10001,        -10000,  NULL, -1),
        /* Numbers around 1 */
        array(1.0000001,  1.0000002,  NULL,  0),
        array(1.0000002,  1.0000001,  NULL,  0),
        array(1.0002,        1.0001,  NULL,  1),
        array(1.0001,        1.0002,  NULL, -1),
        /* Numbers around -1 */
        array(-1.0000001,-1.0000002,  NULL,  0),
        array(-1.0000002,-1.0000001,  NULL,  0),
        array(-1.0002,      -1.0001,  NULL, -1),
        array(-1.0001,      -1.0002,  NULL,  1),
        /* Numbers between 1 and 0 */
        array(0.000000001000001,   0.000000001000002,  NULL,  0),
        array(0.000000001000002,   0.000000001000001,  NULL,  0),
        array(0.000000000001002,   0.000000000001001,  NULL,  1),
        array(0.000000000001001,   0.000000000001002,  NULL, -1),
        /* Numbers between -1 and 0 */
        array(-0.000000001000001, -0.000000001000002,  NULL,  0),
        array(-0.000000001000002, -0.000000001000001,  NULL,  0),
        array(-0.000000000001002, -0.000000000001001,  NULL, -1),
        array(-0.000000000001001, -0.000000000001002,  NULL,  1),
        /* Comparisons involving zero */
        array(0.0,              0.0,  NULL,  0),
        array(0.0,             -0.0,  NULL,  0),
        array(-0.0,            -0.0,  NULL,  0),
        array(0.00000001,       0.0,  NULL,  1),
        array(0.0,       0.00000001,  NULL, -1),
        array(-0.00000001,      0.0,  NULL, -1),
        array(0.0,      -0.00000001,  NULL,  1),

        array(0.0,     1.0E-10,        0.1,  0),
        array(1.0E-10,     0.0,        0.1,  0),
        array(1.0E-10,     0.0, 0.00000001,  1),
        array(0.0,     1.0E-10, 0.00000001, -1),

        array(0.0,    -1.0E-10,        0.1,  0),
        array(-1.0E-10,    0.0,        0.1,  0),
        array(-1.0E-10,    0.0, 0.00000001, -1),
        array(0.0,    -1.0E-10, 0.00000001,  1),
        /* Comparisons of numbers on opposite sides of 0 */
        array(1.000000001, -1.0,  NULL,  1),
        array(-1.0,   1.0000001,  NULL, -1),
        array(-1.000000001, 1.0,  NULL, -1),
        array(1.0, -1.000000001,  NULL,  1),
        /* Comparisons involving extreme values (overflow potential) */
        array(PHP_INT_MAX,  PHP_INT_MAX,  NULL,  0),
        array(PHP_INT_MAX, -PHP_INT_MAX,  NULL,  1),
        array(-PHP_INT_MAX, PHP_INT_MAX,  NULL, -1),
        array(PHP_INT_MAX,  PHP_INT_MAX / 2, NULL,  1),
        array(PHP_INT_MAX, -PHP_INT_MAX / 2, NULL,  1),
        array(-PHP_INT_MAX, PHP_INT_MAX / 2, NULL, -1),

        // other tests
        array('test',       'milli', 1.194,  1),
        array(array('NULL'),    '0',  0.01,  1),
        array(array('NULL'), array('NULL'), NULL, 0),
    );
  }

    /**
    * @dataProvider providerIntAdd
    * @group numbers
    */
    public function testIntAdd($a, $b, $result) {
        $this->assertSame($result, int_add($a, $b));
    }

    public static function providerIntAdd() {
        // $a = "18446742978492891134"; $b = "0"; $sum = gmp_add($a, $b); echo gmp_strval($sum) . "\n"; // Result: 18446742978492891134
        // $a = "18446742978492891134"; $b = "0"; $sum = $a + $b; printf("%.0f\n", $sum);               // Result: 18446742978492891136
        // Accurate math
        return [
            array( '18446742978492891134', '0',  '18446742978492891134'),
            array('-18446742978492891134', '0', '-18446742978492891134'),
            array( '18446742978492891134', '18446742978492891134', '36893485956985782268'),
            array('-18446742978492891134', '18446742978492891134', '0'),

            // Floats
            [ '1111111111111111111111111.6', 0, '1111111111111111111111112' ],
            [ 0, '1111111111111111111111111.6', '1111111111111111111111112' ],
            [ '18446742978492891134.3', '18446742978492891134.6', '36893485956985782269' ],

            // numbers with comma
            [ '7,619,627.6010', 0, '7619628' ],
            [ 0, '7,619,627.6010', '7619628' ],
        ];
    }

    /**
    * @dataProvider providerIntSub
    * @group numbers
    */
    public function testIntSub($a, $b, $result) {
        $this->assertSame($result, int_sub($a, $b));
    }

    public static function providerIntSub() {
        // Accurate math
        return [
            array( '18446742978492891134', '0',  '18446742978492891134'),
            array('-18446742978492891134', '0', '-18446742978492891134'),
            array( '18446742978492891134', '18446742978492891134', '0'),
            array('-18446742978492891134', '18446742978492891134', '-36893485956985782268'),

            // Floats
            [ '1111111111111111111111111.6', 0, '1111111111111111111111112' ],
            [ 0, '1111111111111111111111111.6', '-1111111111111111111111112' ],
            [ '-18446742978492891134.3', '18446742978492891134.6', '-36893485956985782269' ],

            // numbers with comma
            [ '7,619,627.6010', 0, '7619628' ],
            [ 0, '7,619,627.6010', '-7619628' ],
        ];
    }

    /**
     * @dataProvider providerFloatDiv
     * @group numbers
     */
    public function testFloatDiv($a, $b, $result) {
        if (method_exists($this, 'assertEqualsWithDelta')) {
            $this->assertEqualsWithDelta($result, float_div($a, $b), 0.00001);
        } else {
            $this->assertSame($result, float_div($a, $b));
        }
    }

    public static function providerFloatDiv() {
        // Accurate math
        return [
            [ '18446742978492891134', '0',  0 ],
            [ '-18446742978492891134', '0',  0 ],
            [ '18446742978492891134', '18446742978492891134',  1.0 ],
            [ '-18446742978492891134', '18446742978492891134', -1.0 ],

            // Floats
            [ '1111111111111111111111111.6', 0, 0 ],
            [ 0, '1111111111111111111111111.6', 0 ],
            [ '18446742978492891134.3', '18446742978492891134.6', 1.0 ],

            // numbers with comma
            [ '7,619,627.6010', 0, 0 ],
            [ 0, '7,619,627.6010', 0 ],
            [ '1,192.0036', 6.3, 189.20692 ]
        ];
    }

    /**
     * @dataProvider providerFloatPow
     * @group numbers
     */
    public function testFloatPow($a, $b, $result) {
        if (method_exists($this, 'assertEqualsWithDelta')) {
            $this->assertEqualsWithDelta($result, float_pow($a, $b), 0.00001);
        } else {
            $this->assertSame($result, float_pow($a, $b));
        }
    }

    public static function providerFloatPow() {
        // Accurate math
        return [
            [ '18446742978492891134', '0',  1.0 ],
            [ '-18446742978492891134', '0',  1.0 ],
            [ '0', '18446742978492891134',  0 ],
            [ '0', '-18446742978492891134', 0 ],

            // negative power
            [ 0, -1, 0 ],
            [ 0, -1.1, 0 ],

            // Floats
            [ '11.6', 0, 1.0 ],
            [ 0, '11.6', 0 ],
            [ '34.3', '4.6', 11543910.531516898 ],

            // numbers with comma
            [ '7,619,627.6010', 0, 1.0 ],
            [ 0, '7,619,627.6010', 0 ],
            [ '1,192.0036', 1.3, 9980.696216867238 ]
        ];
    }

    /**
     * @dataProvider providerHexToFloat
     * @group numbers
     */
    public function testHexToFloat($hex, $result)
    {
        $this->assertSame($result, hex2float($hex));
    }

    public static function providerHexToFloat()
    {
        // Accurate math
        $array = [

            [ '429241f0', 73.1287841796875 ],

        ];

        return $array;
    }

    /**
     * @dataProvider providerIeeeIntToFloat
     * @group numbers
     */
    public function testIeeeIntToFloat($int, $result)
    {
        $this->assertSame($result, ieeeint2float($int));
    }

    public static function providerIeeeIntToFloat()
    {
        $array = [

            [ 1070575314,          1.6225225925445557 ],
            [ 2998520959,         -2.1629828594882383E-8 ],
            [ '1070575314',        1.6225225925445557 ],
            [ hexdec('429241f0'), 73.1287841796875 ],
            [ 0,                   0.0 ],

        ];

        return $array;
    }

  /**
   * @dataProvider providerIsHexString
   * @group hex
   */
  public function testIsHexString($string, $result)
  {
    $this->assertSame($result, is_hex_string($string));
  }

  public static function providerIsHexString()
  {
    $results = array(
      array('49 6E 70 75 74 20 31 00 ', TRUE),
      array('49 6E 70 75 74 20 31 00',  TRUE),
      array('496E707574203100',         FALSE), // SNMP HEX string only with spaces!
      array('49 6E 70 75 74 20 31 0',   FALSE),
      array('Simple String',            FALSE),
      array('49 6E 70 75 74 20 31 0R ', FALSE)
    );
    return $results;
  }

  /**
   * @dataProvider providerStr2Hex
   * @group hex
   */
  public function testStr2Hex($string, $result)
  {
    $this->assertSame($result, str2hex($string));
  }

  public static function providerStr2Hex()
  {
    $results = array(
      array(' ',              '20'),
      array('Input 1',        '496e7075742031'),
      array('J}4=',           '4a7d343d'),
      array('Simple String',  '53696d706c6520537472696e67'),
      array('PC$rnu',  '504324726e75'),
      array('Pärnu',   '50c3a4726e75'),
    );
    return $results;
  }

  /**
   * @dataProvider providerMatchOidNum
   * @group oid
   */
  public function testMatchOidNum($oid, $needle, $result)
  {
    $this->assertSame($result, match_oid_num($oid, $needle));
  }

  public static function providerMatchOidNum()
  {
    return [
      # true
      [ '.1.3.6.1.4.1.2011.2.27', '1.3.6.1.4.1.2011',        TRUE ],
      [ '1.3.6.1.4.1.2011.2.27',  '.1.3.6.1.4.1.2011',       TRUE ],
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.4.1.2011',       TRUE ],
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.4.1.2011.',      TRUE ],
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.4.1.2011.2.27',  TRUE ],
      # false
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.4.1.20110',      FALSE ],
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.4.1.201',        FALSE ],
      [ '.1.3.6.1.4.1.2011.2.27', '3.6.1.4.1.2011.2.27',     FALSE ],
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.4.1.2011.2.27.', FALSE ],
      # list true
      [ '.1.3.6.1.4.1.2011.2.27', '1.3.6.1.4.1.2011.*',      TRUE ],
      [ '1.3.6.1.4.1.2011.2.27',  '.1.3.6.1.*.1.2011',       TRUE ],
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.(0|4).1.2011*',  TRUE ],
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.4.[1-5].2011.',  TRUE ],
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.4.1.*.27',       TRUE ],
      # list false
      [ '.1.3.6.1.4.1.2011.2.27', '1.3.6.1.4.1.2011.3*',     FALSE ],
      [ '1.3.6.1.4.1.2011.2.27',  '.1.3.6.1.4.*.1.2011',     FALSE ],
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.(0|3).1.2011*',  FALSE ],
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.4.[2-4].2011.',  FALSE ],
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.4.1.1*.27',      FALSE ],
      # array compare
      [ '.1.3.6.1.4.1.2011.2.27', [ '.1.3.6.1.4.1.20110', '.1.3.6.1.4.1.2011' ],   TRUE ],
      [ '.1.3.6.1.4.1.2011.2.27', [ '.1.3.6.1.4.1.20110', '3.6.1.4.1.2011.2.27' ], FALSE ],
      [ '.1.3.6.1.4.1.2011.2.27', [],                                              FALSE ],
      # incorrect data
      [ '.1.3.6.1.4.1.2011.2.27', '.1.3.6.1.4.1.2011..',      FALSE ],
      [ '..1.3.6.1.4.1.2011.2.27', '.1.3.6.1.4.1.2011.',      FALSE ],
      [ '.1.3.6.1.4.1.2011.2.27', 'gg',      FALSE ],
      [ '.1.3.6.1.4.1.2011.2.27', NULL,      FALSE ],
      [ 'as',      '.1.3.6.1.4.1.2011',      FALSE ],
      [ NULL,      '.1.3.6.1.4.1.2011',      FALSE ],
    ];
  }

  /**
  * @dataProvider providerPriorityStringToNumeric
  */
  public function testPriorityStringToNumeric($value, $result)
  {
    $this->assertSame($result, priority_string_to_numeric($value));
  }

  public static function providerPriorityStringToNumeric()
  {
    $results = array(
      // Named value
      array('emerg',    0),
      array('alert',    1),
      array('crit',     2),
      array('err',      3),
      array('warning',  4),
      array('notice',   5),
      array('info',     6),
      array('debug',    7),
      array('DeBuG',    7),
      // Numeric value
      array('0',        0),
      array('7',        7),
      array(8,          8),
      // Wrong value
      array('some',    15),
      array(array(),   15),
      array(0.1,       15),
      array('100',     15),
    );
    return $results;
  }

  /**
  * @dataProvider providerArrayMergeIndexed
  * @group array
  */
  public function testArrayMergeIndexed($result, $array1, $array2)
  {

    $this->assertSame($result, array_merge_indexed($array1, $array2));
    //if ($array3 == NULL)
    //{
    //  $this->assertSame($result, array_merge_indexed($array1, $array2));
    //} else {
    //  $this->assertSame($result, array_merge_indexed($array1, $array2, $array3));
    //}
  }

  public static function providerArrayMergeIndexed()
  {
    $results = array(
      array( // Simple 2 array test with NULL
             array( // Result
                    1 => array('Test2' => 'Foo', 'Test3' => 'Bar'),
                    2 => array('Test2' => 'Qux'),
             ),
             NULL,
             array( // Array 2
                    1 => array('Test2' => 'Foo', 'Test3' => 'Bar'),
                    2 => array('Test2' => 'Qux'),
             ),
      ),
      array( // Simple 2 array test
        array( // Result
          1 => array('Test1' => 'Moo', 'Test2' => 'Foo', 'Test3' => 'Bar'),
          2 => array('Test1' => 'Baz', 'Test4' => 'Bam', 'Test2' => 'Qux'),
        ),
        array( // Array 1
          1 => array('Test1' => 'Moo'),
          2 => array('Test1' => 'Baz', 'Test4' => 'Bam'),
          ),
        array( // Array 2
          1 => array('Test2' => 'Foo', 'Test3' => 'Bar'),
          2 => array('Test2' => 'Qux'),
        ),
      ),
      array( // Simple 3 array test
        array( // Result
          1 => array('Test1' => 'Moo', 'Test2' => 'Foo'), //, 'Test3' => 'Bar'),
          2 => array('Test1' => 'Baz', 'Test4' => 'Bam', 'Test2' => 'Qux'),
        ),
        array( // Array 1
          1 => array('Test1' => 'Moo'),
          2 => array('Test1' => 'Baz', 'Test4' => 'Bam'),
          ),
        array( // Array 2
          1 => array('Test2' => 'Foo'),
          2 => array('Test2' => 'Qux'),
        ),
        //array( // Array 3
        //  1 => array('Test3' => 'Bar'),
        //  2 => array('Test2' => 'Qux'),
        //),
      array( // Partial overwrite by array 2
        array( // Result
          1 => array('Test1' => 'Moo', 'Test2' => 'Foo', 'Test3' => 'Bar'),
          2 => array('Test1' => 'Baz', 'Test4' => 'Bam', 'Test2' => 'Qux'),
        ),
        array( // Array 1
          1 => array('Test1' => 'Moo', 'Test2' => '000', 'Test3' => '666'),
          2 => array('Test1' => 'Baz', 'Test4' => 'Bam'),
          ),
        array( // Array 2
          1 => array('Test2' => 'Foo', 'Test3' => 'Bar'),
          2 => array('Test2' => 'Qux'),
        ),
      ),
      ),
    );

    return $results;
  }

    /**
     * @dataProvider providerArrayMergeMap
     * @group array
     */
    public function testArrayMergeMap($result, $array1, $array2, $map, $diff = []) {

        $this->assertSame($result, array_merge_map($array1, $array2, $map));
        $this->assertSame($diff, $array2);
    }

    public static function providerArrayMergeMap() {
        $results = [];

        $map = [
            'entPhysicalSerialNum'   => 'jnxContentsSerialNo',
            'entPhysicalHardwareRev' => 'jnxContentsRevision',
            'entPhysicalModelName'   => 'jnxContentsPartNo',
        ];

        $array1 = [
            1 =>  [
                'entPhysicalDescr'        => 'Juniper MX304 Edge Router',
                'entPhysicalVendorType'   => 'jnxChassisMX304',
                'entPhysicalContainedIn'  => '0',
                'entPhysicalClass'        => 'chassis',
                'entPhysicalParentRelPos' => '0',
                'entPhysicalName'         => 'JNP304-CHAS',
                'entPhysicalHardwareRev'  => '',
                'entPhysicalFirmwareRev'  => '',
                'entPhysicalSoftwareRev'  => '23.4R2-S3.9',
                'entPhysicalSerialNum'    => 'HA288',
                'entPhysicalMfgName'      => 'Juniper Networks',
                'entPhysicalModelName'    => '750-123404',
                'entPhysicalIsFRU'        => 'false',
            ],
            21 => [
                'entPhysicalDescr'        => 'Fan Tray 1',
                'entPhysicalVendorType'   => 'jnxFan',
                'entPhysicalContainedIn'  => '1',
                'entPhysicalClass'        => 'fan',
                'entPhysicalParentRelPos' => '1',
                'entPhysicalName'         => 'JNP-FAN-2RU',
                'entPhysicalHardwareRev'  => 'REV 08',
                'entPhysicalFirmwareRev'  => '',
                'entPhysicalSoftwareRev'  => '23.4R2-S3.9',
                'entPhysicalSerialNum'    => 'S/N BCFJ0620',
                'entPhysicalMfgName'      => 'Juniper Networks',
                'entPhysicalModelName'    => '760-126744',
                'entPhysicalIsFRU'        => 'false',
            ],
            23 => [
                'entPhysicalDescr'        => 'FPC: FPC-BUILTIN @ 0/*/*',
                'entPhysicalVendorType'   => 'jnxFPC',
                'entPhysicalContainedIn'  => '1',
                'entPhysicalClass'        => 'container',
                'entPhysicalParentRelPos' => '0',
                'entPhysicalName'         => '',
                'entPhysicalHardwareRev'  => '',
                'entPhysicalFirmwareRev'  => 'JUNOS 23.4R2-S3.10-EVO Linux (none) 5.2.60-yocto-standard-g07c4386 #1 SMP PREEMPT Mon Apr 15 13',
                'entPhysicalSoftwareRev'  => '23.4R2-S3.9',
                'entPhysicalSerialNum'    => 'BUILTIN',
                'entPhysicalMfgName'      => 'Juniper Networks',
                'entPhysicalModelName'    => 'BUILTIN',
                'entPhysicalIsFRU'        => 'true',
            ],
        ];

        $array2 = [
            '1.1.0.0' => [
                'jnxContentsContainerIndex'   => '1',
                'jnxContentsL1Index'          => '1',
                'jnxContentsL2Index'          => '0',
                'jnxContentsL3Index'          => '0',
                'jnxContentsType'             => 'jnxChassisMX304',
                'jnxContentsDescr'            => 'midplane',
                'jnxContentsSerialNo'         => 'S/N BCFH6322',
                'jnxContentsRevision'         => 'REV 41',
                'jnxContentsInstalled'        => '0:00:00.00',
                'jnxContentsPartNo'           => '750-123404',
                'jnxContentsChassisId'        => 'singleChassis',
                'jnxContentsChassisDescr'     => 'Single Chassis',
                'jnxContentsChassisCleiCode'  => 'INMKP00CRB',
                'jnxContentsModel'            => 'JNP304-CHAS',
            ],
            '4.2.1.0' => [
                'jnxContentsContainerIndex'   => '4',
                'jnxContentsL1Index'          => '2',
                'jnxContentsL2Index'          => '1',
                'jnxContentsL3Index'          => '0',
                'jnxContentsType'             => 'jnxFan',
                'jnxContentsDescr'            => 'Fan Tray 1 Fan 0',
                'jnxContentsSerialNo'         => 'S/N BCFJ0620',
                'jnxContentsRevision'         => 'REV 08',
                'jnxContentsInstalled'        => '0:00:00.00',
                'jnxContentsPartNo'           => '760-126744',
                'jnxContentsChassisId'        => 'singleChassis',
                'jnxContentsChassisDescr'     => 'Single Chassis',
                'jnxContentsChassisCleiCode'  => 'INCPAL6AAB',
                'jnxContentsModel'            => 'JNP-FAN-2RU',

                'jnxFruContentsIndex'         => '4',
                'jnxFruL1Index'               => '2',
                'jnxFruL2Index'               => '1',
                'jnxFruL3Index'               => '0',
                'jnxFruName'                  => 'Fan Tray 1 Fan 0',
                'jnxFruType'                  => 'fan',
                'jnxFruSlot'                  => '1',
                'jnxFruState'                 => 'online',
                'jnxFruTemp'                  => '0',
                'jnxFruOfflineReason'         => 'none',
                'jnxFruLastPowerOff'          => '0:00:00.00',
                'jnxFruLastPowerOn'           => '0:00:00.00',
                'jnxFruPowerUpTime'           => '283118852',
                'jnxFruChassisId'             => 'singleChassis',
                'jnxFruChassisDescr'          => 'Single Chassis',
                'jnxFruPsdAssignment'         => '0',
            ],
            '7.1.0.0' => [
                'jnxContentsContainerIndex'   => '7',
                'jnxContentsL1Index'          => '1',
                'jnxContentsL2Index'          => '0',
                'jnxContentsL3Index'          => '0',
                'jnxContentsType'             => 'jnxFPC',
                'jnxContentsDescr'            => 'FPC: FPC-BUILTIN @ 0/*/*',
                'jnxContentsSerialNo'         => 'BUILTIN',
                'jnxContentsRevision'         => '',
                'jnxContentsInstalled'        => '0:00:00.00',
                'jnxContentsPartNo'           => 'BUILTIN',
                'jnxContentsChassisId'        => 'singleChassis',
                'jnxContentsChassisDescr'     => 'Single Chassis',
                'jnxContentsChassisCleiCode'  => '',
                'jnxContentsModel'            => '',

                'jnxFruContentsIndex'         => '7',
                'jnxFruL1Index'               => '1',
                'jnxFruL2Index'               => '0',
                'jnxFruL3Index'               => '0',
                'jnxFruName'                  => 'FPC: FPC-BUILTIN @ 0/*/*',
                'jnxFruType'                  => 'flexiblePicConcentrator',
                'jnxFruSlot'                  => '0',
                'jnxFruState'                 => 'online',
                'jnxFruTemp'                  => '33',
                'jnxFruOfflineReason'         => 'none',
                'jnxFruLastPowerOff'          => '0:00:00.00',
                'jnxFruLastPowerOn'           => '0:00:00.00',
                'jnxFruPowerUpTime'           => '283115637',
                'jnxFruChassisId'             => 'singleChassis',
                'jnxFruChassisDescr'          => 'Single Chassis',
                'jnxFruPsdAssignment'         => '0',
            ],
        ];

        $result     = [];
        $result[1]  = $array1[1];
        $result[21] = array_merge($array1[21], $array2['4.2.1.0']);
        $result[23] = array_merge($array1[23], $array2['7.1.0.0']);

        $results[]  = [ $result, $array1, $array2, $map, [ '1.1.0.0' => $array2['1.1.0.0'] ] ];
        return $results;
    }

    /**
     * @dataProvider providerStringQuoted
     * @group string
     */
    public function testStringQuoted($string, $result, $quote = NULL) {
        if ($quote === NULL) {
            // Default, common
            $this->assertSame($result, is_string_quoted($string));
        } else {
            // Optional quote select
            $this->assertSame($result, is_string_quoted($string, $quote));
        }
    }
    public static function providerStringQuoted() {
        return array(
            array('\"sdfslfkm s\'fdsf" a;lm aamjn ',          FALSE),
            array('sdfslfkm s\'fdsf" a;lm aamjn \"',          FALSE),
            array('sdfslfkm s\'fdsf" a;lm aamjn ',            FALSE),
            array('"sdfslfkm s\'fdsf" a;lm aamjn "',          TRUE),
            array('"\"sdfslfkm s\'fdsf" a;lm aamjn \""',      TRUE),
            array('"""sdfslfkm s\'fdsf" a;lm aamjn """',      TRUE),
            array('"""sdfslfkm s\'fdsf" a;lm aamjn """"""""', TRUE),
            array('"""""""sdfslfkm s\'fdsf" a;lm aamjn """',  TRUE),
            // escaped quotes
            array('\"Mike Stupalov\" <mike@observium.org>',   FALSE),
            array('\"sdfslfkm s\'fdsf" a;lm aamjn \"',        TRUE, '\"'),
            array("\'\"sdfslfkm s\'fdsf\" a;lm aamjn \"\'",    TRUE, "\'"),
            //array('  \'\"sdfslfkm s\'fdsf" a;lm aamjn \"\' ', TRUE), // I forgot why it's TRUE
            array('\"Avenue Léon, België \"',                 TRUE, '\"'),
            // utf-8
            array('Avenue Léon, België ',                     FALSE),
            array('"Avenue Léon, België "',                   TRUE),
            array('"Винни пух и все-все-все "',               TRUE),
            // multilined
            array('"  \'\"\"sdfslfkm s\'fdsf"
                  a;lm aamjn \"\"\' "',                       TRUE),
            array('  \'\"\"sdfslfkm s\'fdsf"
                  a;lm aamjn \"\"\' ',                        FALSE),
            // not allowed quote char (always false)
            array('`sdfslfkm s\'fdsf" a;lm aamjn `',          FALSE, '`'),
            // not string
            array(NULL,                     FALSE),
            array(TRUE,                     FALSE),
            array(1,                        FALSE),
            array([],                       FALSE),
        );
    }

    /**
    * @dataProvider providerStringSimilar
    * @group string
    */
    public function testStringSimilar($result, $string1, $string2) {
        $this->assertSame($result, str_similar($string1, $string2));
        $this->assertSame($result, str_similar($string2, $string1));
    }

    public static function providerStringSimilar() {
        return [
            [ 'Intel Xeon E5430 @ 2.66GH', '0/0/0 Intel Xeon E5430 @ 2.66GH', '0/1/0 Intel Xeon E5430 @ 2.66GH' ],
            [ 'Intel Xeon E5430 @',        '0/0/0 Intel Xeon E5430 @ 2.66GH', '0/1/0 Intel Xeon E5430 @ 2.66G' ],
            [ 'ControlPlane',              'ControlPlane_03', 'ControlPlane_02' ],
            [ 'Network Processor',         'Network Processor CPU8', 'Network Processor CPU31' ],
            [ '',                          'Network Processor CPU8', 'Supervisor Card CPU' ],
        ];
    }

    /**
    * @dataProvider providerFindSimilar
    * @group string
    */
    public function testFindSimilar($result, $result_flip, $array) {
        shuffle($array); // Randomize array for more natural test

        $this->assertSame($result,      find_similar($array));
        $this->assertSame($result_flip, find_similar($array, TRUE));
    }

    public static function providerFindSimilar() {
        $array1 = [ '0/0/0 Intel Xeon E5430 @ 2.66GHz', '0/1/0 Intel Xeon E5430 @ 2.66GHz', '0/10/0 Intel Xeon E5430 @ 2.66GHz',
                    'Supervisor Card CPU',
                    'Network Processor CPU8', 'Network Processor CPU31' ];
        $array2 = [ '0/0/0 Intel Xeon E5430 @ 2.66GH', '0/1/0 Intel Xeon E5430 @ 2.66GH', '0/10/0 Intel Xeon E5430 @ 2.66G' ];
        $array3 = [ 'Slot 1 BR-MLX-10Gx8-X [1]', 'Slot 2 BR-MLX-10Gx8-X [1]',
                    'Slot 4 BR-MLX-1GFx24-X [1]',
                    'Slot 5 BR-MLX-MR2-X [1]', 'Slot 6 BR-MLX-MR2-X [1]' ];
        $array4 = [ 'ControlPlane_03', 'ControlPlane_01', 'ControlPlane_02' ];
        $array5 = [ 'Core 1', 'Core 2', 'Core 3', 'Core 4', 'Core 5', 'Core 6', 'Core 7', 'Core 8', 'Core 9', 'Core 10', 'Core 11', 'Core 12' ];

        return [
            [
                [
                    'Intel Xeon E5430 @ 2.66GHz' => [ '0/0/0 Intel Xeon E5430 @ 2.66GHz', '0/1/0 Intel Xeon E5430 @ 2.66GHz', '0/10/0 Intel Xeon E5430 @ 2.66GHz' ],
                    'Network Processor'          => [ 'Network Processor CPU8', 'Network Processor CPU31' ],
                    'Supervisor Card CPU'        => [ 'Supervisor Card CPU' ]
                ],
                [
                    '0/0/0 Intel Xeon E5430 @ 2.66GHz' => 'Intel Xeon E5430 @ 2.66GHz',
                    '0/1/0 Intel Xeon E5430 @ 2.66GHz' => 'Intel Xeon E5430 @ 2.66GHz',
                    '0/10/0 Intel Xeon E5430 @ 2.66GHz' => 'Intel Xeon E5430 @ 2.66GHz',
                    'Network Processor CPU8' => 'Network Processor',
                    'Network Processor CPU31' => 'Network Processor',
                    'Supervisor Card CPU' => 'Supervisor Card CPU'
                ],
                $array1
            ],
            [
                [
                    'Intel Xeon E5430 @ 2.66GH' => [ '0/0/0 Intel Xeon E5430 @ 2.66GH', '0/1/0 Intel Xeon E5430 @ 2.66GH', '0/10/0 Intel Xeon E5430 @ 2.66G' ]
                ],
                [
                    '0/0/0 Intel Xeon E5430 @ 2.66GH' => 'Intel Xeon E5430 @ 2.66GH',
                    '0/1/0 Intel Xeon E5430 @ 2.66GH' => 'Intel Xeon E5430 @ 2.66GH',
                    '0/10/0 Intel Xeon E5430 @ 2.66G' => 'Intel Xeon E5430 @ 2.66GH'
                ],
                $array2
            ],
            [
                [
                    'Slot BR-MLX-10Gx8-X [1]'    => [ 'Slot 1 BR-MLX-10Gx8-X [1]', 'Slot 2 BR-MLX-10Gx8-X [1]' ],
                    'Slot 4 BR-MLX-1GFx24-X [1]' => [ 'Slot 4 BR-MLX-1GFx24-X [1]' ],
                    'Slot BR-MLX-MR2-X [1]'      => [ 'Slot 5 BR-MLX-MR2-X [1]', 'Slot 6 BR-MLX-MR2-X [1]' ]
                ],
                [
                    'Slot 1 BR-MLX-10Gx8-X [1]'  => 'Slot BR-MLX-10Gx8-X [1]',
                    'Slot 2 BR-MLX-10Gx8-X [1]'  => 'Slot BR-MLX-10Gx8-X [1]',
                    'Slot 4 BR-MLX-1GFx24-X [1]' => 'Slot 4 BR-MLX-1GFx24-X [1]',
                    'Slot 5 BR-MLX-MR2-X [1]'    => 'Slot BR-MLX-MR2-X [1]',
                    'Slot 6 BR-MLX-MR2-X [1]'    => 'Slot BR-MLX-MR2-X [1]'
                ],
                $array3
            ],
            [
                [
                    'ControlPlane'    => [ 'ControlPlane_01', 'ControlPlane_02', 'ControlPlane_03' ],
                ],
                [
                    'ControlPlane_01'  => 'ControlPlane',
                    'ControlPlane_02'  => 'ControlPlane',
                    'ControlPlane_03'  => 'ControlPlane',
                ],
                $array4
            ],
            [
                [
                    'Core'    => [ 'Core 1', 'Core 2', 'Core 3', 'Core 4', 'Core 5', 'Core 6', 'Core 7', 'Core 8', 'Core 9', 'Core 10', 'Core 11', 'Core 12' ],
                ],
                [
                    'Core 1'  => 'Core',
                    'Core 2'  => 'Core',
                    'Core 3'  => 'Core',
                    'Core 4'  => 'Core',
                    'Core 5'  => 'Core',
                    'Core 6'  => 'Core',
                    'Core 7'  => 'Core',
                    'Core 8'  => 'Core',
                    'Core 9'  => 'Core',
                    'Core 10' => 'Core',
                    'Core 11' => 'Core',
                    'Core 12' => 'Core'
                ],
                $array5
            ],
        ];
    }

    /**
    * @dataProvider providerIsPingable
    * @group network
    */
    public function testIsPingable($hostname, $result, $try_a = TRUE) {
        if (!is_executable($GLOBALS['config']['fping'])) {
            // CentOS 6.8
            $GLOBALS['config']['fping']  = '/usr/sbin/fping';
            $GLOBALS['config']['fping6'] = '/usr/sbin/fping6';
        }

        if ($try_a) {
            $ping = is_pingable($hostname);
        } else {
            $ping = is_pingable($hostname, 'ipv6');
        }
        $ping = is_numeric($ping) && $ping > 0; // Function returns random float number
        $this->assertSame($result, $ping);
    }

    public static function providerIsPingable() {
        $array = [
            [ 'localhost',             TRUE ],
            [ '127.0.0.1',             TRUE ],
            [ 'yohoho.i.butylka.roma', FALSE ],
            [ '127.0.0.1',             FALSE, FALSE ], // Try ping IPv4 with IPv6 only
        ];

        $cmd = $GLOBALS['config']['fping6'] . " -c 1 -q ::1 2>&1";
        exec($cmd, $output, $return); // Check if we have IPv6 support in a current system
        if ($return === 0) {
            // IPv6 only
            $array[] = [ '::1',     TRUE, FALSE ];
            $array[] = [ '::ffff', FALSE, FALSE ];
            foreach ([ 'localhost', 'ip6-localhost' ] as $hostname) {
                // Debian used ip6-localhost instead localhost.. lol
                if ($ip = gethostbyname6($hostname, 'ipv6')) {
                    $array[] = [ $hostname, TRUE, FALSE ];
                    //var_dump($hostname);
                    break;
                }
            }
        }

        return $array;
    }

  /**
  * @dataProvider providerCalculateMempoolProperties
  * @group numbers
  */
  public function testCalculateMempoolProperties($scale, $used, $total, $free, $perc, $result)
  {
    $this->assertSame($result, calculate_mempool_properties($scale, $used, $total, $free, $perc));
  }

  public static function providerCalculateMempoolProperties()
  {
    $results = array(
      array(  1, 123456789, 234567890, NULL, NULL, array('used' => 123456789,  'total' => 234567890,   'free' => 111111101,  'perc' => 52.63, 'units' => 1,   'scale' => 1,   'valid' => TRUE)), // Used + Total known
      array( 10, 123456789, 234567890, NULL, NULL, array('used' => 1234567890, 'total' => 2345678900,  'free' => 1111111010, 'perc' => 52.63, 'units' => 10,  'scale' => 10,  'valid' => TRUE)), // Used + Total known, scale factor 10
      array(0.5, 123456789, 234567890, NULL, NULL, array('used' => 61728394.5, 'total' => 117283945.0, 'free' => 55555550.5, 'perc' => 52.63, 'units' => 0.5, 'scale' => 0.5, 'valid' => TRUE)), // Used + Total known, scale factor 0.5

      array(  1, NULL, 1234567890, 1597590, NULL, array('used' => 1232970300,   'total' => 1234567890,   'free' => 1597590,   'perc' => 99.87, 'units' => 1,   'scale' => 1,   'valid' => TRUE)), // Total + Free known
      array(100, NULL, 1234567890, 1597590, NULL, array('used' => 123297030000, 'total' => 123456789000, 'free' => 159759000, 'perc' => 99.87, 'units' => 100, 'scale' => 100, 'valid' => TRUE)), // Total + Free known, scale factor 10
      array(0.5, NULL, 1234567890, 1597590, NULL, array('used' => 616485150.0,  'total' => 617283945.0,  'free' => 798795.0,  'perc' => 99.87, 'units' => 0.5, 'scale' => 0.5, 'valid' => TRUE)), // Total + Free known, scale factor 0.5

      array(  1, 13333337, 23333337, 10000000, NULL, array('used' => 13333337,  'total' => 23333337,   'free' => 10000000,    'perc' => 57.14, 'units' => 1,   'scale' => 1,   'valid' => TRUE)), // All known
      array( 10, 13333337, 23333337, 10000000, NULL, array('used' => 133333370, 'total' => 233333370,  'free' => 100000000,   'perc' => 57.14, 'units' => 10,  'scale' => 10,  'valid' => TRUE)), // All known, scale factor 10
      array(0.5, 13333337, 23333337, 10000000, NULL, array('used' => 6666668.5, 'total' => 11666668.5, 'free' => 5000000.0,   'perc' => 57.14, 'units' => 0.5, 'scale' => 0.5, 'valid' => TRUE)), // All known, scale factor 0.5

      array(  1, 123456789, NULL, 163840, NULL, array('used' => 123456789,   'total' => 123620629,   'free' => 163840,        'perc' => 99.87, 'units' => 1,   'scale' => 1,   'valid' => TRUE)), // Used + Free known
      array(100, 123456789, NULL, 163840, NULL, array('used' => 12345678900, 'total' => 12362062900, 'free' => 16384000,      'perc' => 99.87, 'units' => 100, 'scale' => 100, 'valid' => TRUE)), // Used + Free known, scale factor 100
      array(0.5, 123456789, NULL, 163840, NULL, array('used' => 61728394.5,  'total' => 61810314.5,  'free' => 81920.0,       'perc' => 99.87, 'units' => 0.5, 'scale' => 0.5, 'valid' => TRUE)), // Used + Free known, scale factor 0.5

      array(   1, NULL, 600000000, NULL, 30, array('used' => 180000000,    'total' => 600000000,    'free' => 420000000,      'perc' => 30, 'units' => 1,    'scale' => 1,    'valid' => TRUE)),    // Total + Percentage known
      array(1000, NULL, 600000000, NULL, 30, array('used' => 180000000000, 'total' => 600000000000, 'free' => 420000000000,   'perc' => 30, 'units' => 1000, 'scale' => 1000, 'valid' => TRUE)),    // Total + Percentage known, scale factor 1000
      array( 0.5, NULL, 600000000, NULL, 30, array('used' => 90000000.0,   'total' => 300000000.0,  'free' => 210000000.0,    'perc' => 30, 'units' => 0.5,  'scale' => 0.5,  'valid' => TRUE)),    // Total + Percentage known, scale factor 0.5

      array(  1, 1597590, 1234567890, NULL, NULL, array('used' => 1597590,  'total' => 1234567890,  'free' => 1232970300,     'perc' => 0.13, 'units' => 1,   'scale' => 1,   'valid' => TRUE)),  // Used + Total known
      array( 10, 1597590, 1234567890, NULL, NULL, array('used' => 15975900, 'total' => 12345678900, 'free' => 12329703000,    'perc' => 0.13, 'units' => 10,  'scale' => 10,  'valid' => TRUE)),  // Used + Total known, scale factor 10
      array(0.5, 1597590, 1234567890, NULL, NULL, array('used' => 798795.0, 'total' => 617283945.0, 'free' => 616485150.0,    'perc' => 0.13, 'units' => 0.5, 'scale' => 0.5, 'valid' => TRUE)),  // Used + Total known, scale factor 0.5

      array(  1, NULL, NULL, NULL, 57, array('used' => 57, 'total' => 100, 'free' => 43, 'perc' => 57, 'units' => 1,   'scale' => 1,   'valid' => TRUE)),    // Only percentage known
      array( 40, NULL, NULL, NULL, 23, array('used' => 23, 'total' => 100, 'free' => 77, 'perc' => 23, 'units' => 40,  'scale' => 40,  'valid' => TRUE)),   // Only percentage known, scale factor 40
      array(0.1, NULL, NULL, NULL, 16, array('used' => 16, 'total' => 100, 'free' => 84, 'perc' => 16, 'units' => 0.1, 'scale' => 0.1, 'valid' => TRUE)),  // Only percentage known, scale factor 0.1
    );
    return $results;
  }

  /**
  * @dataProvider providerCalculateMempoolPropertiesScale
  * @group numbers
  */
  public function testCalculateMempoolPropertiesScale($scale, $used, $total, $free, $perc, $options, $result)
  {
    $this->assertSame($result, calculate_mempool_properties($scale, $used, $total, $free, $perc, $options));
  }

  public static function providerCalculateMempoolPropertiesScale()
  {
    $scale1 = array('scale_total' => 1024);
    $scale2 = array('scale_used'  => 2048);
    $scale3 = array('scale_free'  => 4096);

    $results = array(
      array(  1, 123456789, 234567890, NULL, NULL, $scale1, array('used' => 123456789,    'total' => 240197519360,  'free' => 240074062571,  'perc' =>     0.05, 'units' => 1,   'scale' => 1, 'valid' => TRUE)),   // Used + Total known
      array( 10, 123456789, 234567890, NULL, NULL, $scale2, array('used' => 252839503872, 'total' => 2345678900,    'free' => -250493824972, 'perc' => 10778.95, 'units' => 10,  'scale' => 10, 'valid' => FALSE)),  // Used + Total known, scale factor 10
      array(0.5, 123456789, 234567890, NULL, NULL, $scale3, array('used' => 61728394.5,   'total' => 117283945.0,   'free' => 55555550.5,    'perc' =>    52.63, 'units' => 0.5, 'scale' => 0.5, 'valid' => TRUE)), // Used + Total known, scale factor 0.5

      array(  1, NULL, 1234567890, 1597590, NULL, $scale1, array('used' => 1264195921770, 'total' => 1264197519360, 'free' => 1597590,       'perc' =>    100.0, 'units' => 1,   'scale' => 1, 'valid' => TRUE)),   // Total + Free known
      array(100, NULL, 1234567890, 1597590, NULL, $scale2, array('used' => 123297030000,  'total' => 123456789000,  'free' => 159759000,     'perc' =>    99.87, 'units' => 100, 'scale' => 100, 'valid' => TRUE)), // Total + Free known, scale factor 10
      array(0.5, NULL, 1234567890, 1597590, NULL, $scale3, array('used' => -5926444695.0, 'total' => 617283945.0,   'free' => 6543728640,    'perc' =>  -960.08, 'units' => 0.5, 'scale' => 0.5, 'valid' => FALSE)), // Total + Free known, scale factor 0.5
    );
    return $results;
  }

    /**
     * @group sql
     */
    public function testGenerateWhereClauseWithValidConditions()
    {
        $conditions = [
            'column1 = "value1"',
            '',
            '  ',
            'column2 > 10'
        ];
        $additional_conditions = [
            'column3 < 50',
            'column4 LIKE "%example%"'
        ];

        $expected = ' WHERE column1 = "value1" AND column2 > 10 AND column3 < 50 AND column4 LIKE "%example%"';
        $result = generate_where_clause($conditions, $additional_conditions);
        $this->assertEquals($expected, $result);
    }

    /**
     * @group sql
     */
    public function testGenerateWhereClauseWithOnlyEmptyConditions()
    {
        $conditions = [
            '',
            '  ',
            "\t",
            "\n"
        ];

        $result = generate_where_clause($conditions);
        //$this->assertNull($result);
        $this->assertEquals('', $result);
    }

    /**
     * @group sql
     */
    public function testGenerateWhereClauseWithSingleCondition()
    {
        $conditions = [
            'column1 = "value1"'
        ];

        $expected = ' WHERE column1 = "value1"';
        $result = generate_where_clause($conditions);
        $this->assertEquals($expected, $result);
    }

    /**
     * @group sql
     */
    public function testGenerateWhereClauseWithNoConditions()
    {
        $conditions = [];

        $result = generate_where_clause($conditions);
        //$this->assertNull($result);
        $this->assertEquals('', $result);
    }

    /**
     * @group sql
     */
    public function testGenerateWhereClauseWithOnlyAdditionalConditions()
    {
        $conditions = [];
        $additional_conditions = [
            'column1 = "value1"',
            'column2 > 10'
        ];

        $expected = ' WHERE column1 = "value1" AND column2 > 10';
        $result = generate_where_clause($conditions, $additional_conditions);
        $this->assertEquals($expected, $result);
    }
}



// EOF
