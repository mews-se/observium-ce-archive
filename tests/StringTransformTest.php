<?php

class StringTransformTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider providerStringTransform
     * @group string
     */
    public function testStringTransform($result, $string, $transformations) {
        $this->assertSame($result, string_transform($string, $transformations));
    }

    public static function providerStringTransform()
    {
        return array(
            // Append
            array('Bananarama',     'Banana',          array(
                array('action' => 'append', 'string' => 'rama')
            )),
            array('Bananarama',     'Banana',          array(
                array('action' => 'append', 'string' => 'ra'),
                array('action' => 'append', 'string' => 'ma')
            )),
            // Prepend
            array('Benga boys',     'boys',            array(
                array('action' => 'prepend', 'string' => 'Benga ')
            )),
            // Replace
            array('Observium',      'ObserverNMS',     array(
                array('action' => 'replace', 'from' => 'erNMS', 'to' => 'ium')
            )),
            array('ObserverNMS',    'ObserverNMS',     array(
                array('action' => 'replace', 'from' => 'ernms', 'to' => 'ium')
            )),
            // Case Insensitive Replace
            array('Observium',      'ObserverNMS',     array(
                array('action' => 'ireplace', 'from' => 'erNMS', 'to' => 'ium')
            )),
            array('Observium',      'ObserverNMS',     array(
                array('action' => 'ireplace', 'from' => 'ernms', 'to' => 'ium')
            )),
            // Regex Replace
            array('1.46.82', 'CS141-SNMP V1.46.82 161207', array(
                array('action' => 'regex_replace', 'from' => '/CS1\d1\-SNMP V(\d\S+).*/', 'to' => '$1')
            )),
            // Regex Replace
            array('1.46.82', 'CS141-SNMP V1.46.82 161207', array(
                array('action' => 'preg_replace', 'from' => '/CS1\d1\-SNMP V(\d\S+).*/', 'to' => '$1')
            )),
            // Regex Replace (missed delimiters)
            array('1.46.82', 'CS141-SNMP V1.46.82 161207', array(
                array('action' => 'preg_replace', 'from' => 'CS1\d1\-SNMP V(\d\S+).*', 'to' => '$1')
            )),
            // Regex Replace (to empty)
            array('', 'FF FF FF FF FF FF FF FF FF FF FF FF FF FF FF FF', array(
                array('action' => 'preg_replace', 'from' => '/^FF( FF)*$/', 'to' => '')
            )),
            // Regex Replace (not match)
            array('CS141-SNMP', 'CS141-SNMP',          array(
                array('action' => 'preg_replace', 'from' => '/CS1\d1\-SNMP V(\d\S+).*/', 'to' => '$1')
            )),
            // Regex Match (with array_tag_replace_clean())
            array('11130141XXXXXX', 'PDU System v1.06 (SN 11130141XXXXXX)',          array(
                [ 'action' => 'preg_match', 'from' => '/v(?<version>.*?) \(SN (?<serial>\S+?)\)/s', 'to' => '%serial%' ]
            )),
            // Regex Match (with array_tag_replace_clean())
            array('1.06', 'PDU System v1.06 (SN 11130141XXXXXX)',          array(
                [ 'action' => 'preg_match', 'from' => '/v(?<version>.*?) \(SN (?<serial>\S+?)\)/s', 'to' => '%version%' ]
            )),
            // Regex Match (with array_tag_replace_clean(), not match)
            array('', 'PDU System v1.06 (SN 11130141XXXXXX)',          array(
                [ 'action' => 'preg_match', 'from' => '/v(?<version>.*?) \(SN (?<serial>\S+?)\)/s', 'to' => '%qwerty%' ]
            )),
            // Trim
            array('OOObservium',    'oooOOObserviumo', array(
                array('action' => 'trim', 'characters' => 'o')
            )),
            // LTrim
            array('OOObserviumo',   'oooOOObserviumo', array(
                array('action' => 'ltrim', 'characters' => 'o')
            )),
            // RTrim
            array('oooOOObservium', 'oooOOObserviumo', array(
                array('action' => 'rtrim', 'characters' => 'o')
            )),
            // MAP
            array('oooOOObserviumo', 'oooOOObservium', array(
                array('action' => 'map', 'map' => [ 'oooOOObservium' => 'oooOOObserviumo' ])
            )),
            array('oooOOO', 'oooOOO', array(
                array('action' => 'map', 'map' => [ 'oooOOObservium' => 'oooOOObserviumo' ])
            )),
            // MAp by regex
            array('oooOOObserviumo', 'ooo3748yhrfnhnd3', array(
                array('action' => 'map_match', 'map' => [ '/^ooo/' => 'oooOOObserviumo' ])
            )),
            array('ooo3748yhrfnhnd3', 'ooo3748yhrfnhnd3', array(
                array('action' => 'map_match', 'map' => [ '/^xoo/' => 'oooOOObserviumo' ])
            )),

            // Timeticks
            array(15462419, '178:23:06:59.03', array(
                array('action' => 'timeticks')
            )),

            // BGP 32bit ASdot
            array('327700', '5.20', array(
                array('action' => 'asdot')
            )),

            // Explode (defaults - delimiter: " ", index: first)
            array('1.6', '1.6 Build 13120415', array(
                array('action' => 'explode')
            )),
            array([ '1.6', 'Build', '13120415' ], '1.6 Build 13120415', array(
                array('action' => 'explode', 'index' => 'array')
            )),
            array('1.6', '1.6 Build 13120415', array(
                array('action' => 'explode', 'delimiter' => ' ', 'index' => 'first')
            )),
            array('1.6', '1.6 Build 13120415', array(
                array('action' => 'explode', 'delimiter' => ' ', 'index' => 0)
            )),
            array('13120415', '1.6 Build 13120415', array(
                array('action' => 'explode', 'delimiter' => ' ', 'index' => 'last')
            )),
            array('Build', '1.6 Build 13120415', array(
                array('action' => 'explode', 'delimiter' => ' ', 'index' => 'second')
            )),
            array('Build', '1.6 Build 13120415', array(
                array('action' => 'explode', 'delimiter' => ' ', 'index' => 1)
            )),
            array('6 Build 13120415', '1.6 Build 13120415', array(
                array('action' => 'explode', 'delimiter' => '.', 'index' => 1)
            )),
            // (unknown index)
            array('1.6 Build 13120415', '1.6 Build 13120415', array(
                array('action' => 'explode', 'delimiter' => '.', 'index' => 10)
            )),
            // Error, not string passed
            array(NULL, [], array(
                array('action' => 'explode')
            )),

            // Single action with less array nesting
            array('1.46.82', 'CS141-SNMP V1.46.82 161207', array('action' => 'preg_replace', 'from' => '/CS1\d1\-SNMP V(\d\S+).*/', 'to' => '$1')),
            array('327700', '5.20',                        array('action' => 'asdot')),

            // Combinations, to be done in exact order, including no-ops
            array('Observium',      'oooOOOKikkero',   array(
                array('action' => 'trim', 'characters' => 'o'),
                array('action' => 'ltrim', 'characters' => 'O'),
                array('action' => 'rtrim', 'characters' => 'F'),
                array('action' => 'replace', 'from' => 'Kikker', 'to' => 'ObserverNMS'),
                array('action' => 'replace', 'from' => 'erNMS', 'to' => 'ium')
            )),

            // base64
            array('T2JzZXJ2aXVt', 'Observium', array(
                array('action' => 'base64_encode')
            )),
            array('Observium', 'T2JzZXJ2aXVt', array(
                array('action' => 'base64_decode')
            )),

            // json_decode
            array(['a' => 1, 'b' => 2], '{"a":1,"b":2}', array(
                array('action' => 'json_decode')
            )),

            // hash
            array('c4ca4238a0b923820dcc509a6f75849b', '1', array(
                array('action' => 'hash', 'algo' => 'md5')
            )),

            // substring
            array('serv', 'Observium', array(
                array('action' => 'substring', 'start' => 2, 'length' => 4)
            )),

            // sprintf
            array('Observium-123', '123', array(
                array('action' => 'sprintf', 'format' => 'Observium-%s')
            )),
        );
    }
}
