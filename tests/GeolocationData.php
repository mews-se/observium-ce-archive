<?php

// NOTE. Currently, all tests failed!

/**
 * @backupGlobals disabled
 */
class GeolocationData extends \PHPUnit\Framework\TestCase {

    /**
     * @dataProvider providerGetGeolocation
     * @group        geo
     */
    public function testGetGeolocation($address, $result, $api = 'geocodefarm', $geo_db = [], $dns_only = FALSE) {
        if ($api === 'geocodefarm' || $api === 'openstreetmap' ||
            !empty($GLOBALS['config']['geo_api'][$api]['key'])) {
            $GLOBALS['config']['geocoding']['dns'] = $dns_only;
            $GLOBALS['config']['geocoding']['api'] = $api;

            $test = get_geolocation($address, $geo_db, $dns_only);
            unset($test['location_updated'], $test['location_status']);
            $this->assertSame($result, $test);
        }
    }

    public static function providerGetGeolocation() {
        $array = [];

        // DNS LOC (reverse)
        $location = 'qwerty';
        $api      = 'openstreetmap';
        $result   = [ 'location'      => $location, 'location_geoapi' => $api,
                      'location_lat'  => 37.7749289, 'location_lon' => -122.4194178,
                      'location_city' => 'San Francisco', 'location_county' => 'Unknown', 'location_state' => 'California', 'location_country' => 'United States' ];
        $array[]  = [ $location, $result, $api, [ 'hostname' => 'loc-degree.observium.dev' ], TRUE ]; // reverse, dns only
        $api      = 'geocodefarm';
        $result   = [ 'location'      => $location, 'location_geoapi' => $api,
                      'location_lat'  => 37.7749289, 'location_lon' => -122.4194178,
                      'location_city' => 'Mission District', 'location_county' => 'San Francisco', 'location_state' => 'CA', 'location_country' => 'United States' ];
        $array[]  = [ $location, $result, $api, [ 'hostname' => 'loc-degree.observium.dev' ], TRUE ]; // reverse, dns only
        $api      = 'yandex';
        $result   = [ 'location'         => $location, 'location_geoapi' => $api,
                      'location_lat'     => 37.7749289, 'location_lon' => -122.4194178,
                      'location_country' => 'United States', 'location_state' => 'California', 'location_county' => 'San Francisco', 'location_city' => 'SoMa' ];
        $array[]  = [ $location, $result, $api, [ 'hostname' => 'loc-degree.observium.dev' ], TRUE ]; // reverse, dns only
        $api      = 'mapquest';
        $result   = [ 'location'      => $location, 'location_geoapi' => $api,
                      'location_lat'  => 37.7749289, 'location_lon' => -122.4194178,
                      'location_city' => 'San Francisco', 'location_county' => 'San Francisco', 'location_state' => 'CA', 'location_country' => 'United States' ];
        $array[]  = [ $location, $result, $api, [ 'hostname' => 'loc-degree.observium.dev' ], TRUE ]; // reverse, dns only
        $api      = 'bing';
        $result   = [ 'location'      => $location, 'location_geoapi' => $api,
                      'location_lat'  => 37.7749289, 'location_lon' => -122.4194178,
                      'location_city' => 'Mission District', 'location_county' => 'San Francisco', 'location_state' => 'California', 'location_country' => 'United States' ];
        $array[]  = [ $location, $result, $api, [ 'hostname' => 'loc-degree.observium.dev' ], TRUE ]; // reverse, dns only

        // Location (reverse)
        $location = 'Some location [47.616380;-122.341673]';
        $api      = 'openstreetmap';
        $result   = [ 'location'      => $location, 'location_geoapi' => $api,
                      'location_lat'  => 47.61638, 'location_lon' => -122.341673,
                      'location_city' => 'Seattle', 'location_county' => 'King', 'location_state' => 'Washington', 'location_country' => 'United States' ];
        $array[]  = [ $location, $result, $api ];

        $location = 'Some location|\'47.616380\'|\'-122.341673\'';
        $api      = 'openstreetmap';
        $result   = [ 'location'      => $location, 'location_geoapi' => $api,
                      'location_lat'  => 47.61638, 'location_lon' => -122.341673,
                      'location_city' => 'Seattle', 'location_county' => 'King', 'location_state' => 'Washington', 'location_country' => 'United States' ];
        $array[]  = [ $location, $result, $api ];

        // First request (forward)
        $location = 'Badenerstrasse 569, Zurich, Switzerland';
        $api      = 'openstreetmap';
        $result   = [ 'location'      => $location, 'location_geoapi' => $api,
                      'location_lat'  => 47.3832766, 'location_lon' => 8.4955511,
                      'location_city' => 'Zurich', 'location_county' => 'District Zurich', 'location_state' => 'Zurich', 'location_country' => 'Switzerland' ];
        $array[]  = [ $location, $result, $api ];

        $location = 'Nikhef, Amsterdam, NL';
        $api      = 'yandex';
        $result   = [ 'location'         => $location, 'location_geoapi' => $api,
                      'location_lon'     => 4.892557, 'location_lat' => 52.373057,
                      'location_country' => 'Netherlands', 'location_state' => 'North Holland', 'location_county' => 'North Holland', 'location_city' => 'Amsterdam' ];
        $array[]  = [ $location, $result, $api ];

        $location = 'Korea_Seoul';
        $api      = 'mapquest';
        $result   = [ 'location'      => $location, 'location_geoapi' => $api,
                      'location_lat'  => 37.55886, 'location_lon' => 126.99989,
                      'location_city' => 'Seoul', 'location_county' => 'South Korea', 'location_state' => 'Unknown', 'location_country' => 'South Korea' ];
        $array[]  = [ $location, $result, $api ];

        // Second request (forward)
        $location = 'ZRH2, Badenerstrasse 569, Zurich, Switzerland';
        $api      = 'openstreetmap';
        $result   = [ 'location'      => $location, 'location_geoapi' => $api,
                      'location_lat'  => 47.3832766, 'location_lon' => 8.4955511,
                      'location_city' => 'Zurich', 'location_county' => 'District Zurich', 'location_state' => 'Zurich', 'location_country' => 'Switzerland' ];
        $array[]  = [ $location, $result, $api ];

        $location = 'Rack: NK-76 - Nikhef, Amsterdam, NL';
        $api      = 'yandex';
        $result   = [ 'location'         => $location, 'location_geoapi' => $api,
                      'location_lon'     => 4.892557, 'location_lat' => 52.373057,
                      'location_country' => 'Netherlands', 'location_state' => 'North Holland', 'location_county' => 'North Holland', 'location_city' => 'Amsterdam' ];
        $array[]  = [ $location, $result, $api ];

        $location = 'Korea_Seoul';
        $api      = 'bing';
        $result   = [ 'location'      => $location, 'location_geoapi' => $api,
                      'location_lat'  => 37.5682945, 'location_lon' => 126.9977875,
                      'location_city' => 'Seoul', 'location_county' => 'Unknown', 'location_state' => 'Seoul', 'location_country' => 'South Korea' ];
        $array[]  = [ $location, $result, $api ];

        // "Mohammed Bin Rashid Boulevard 1"
        // "Under The Sink, The Office, London, UK"
        // "1100 Congress Ave, Austin, TX 78701 [3rd floor cabinet]"

        return $array;
    }

}

// EOF
