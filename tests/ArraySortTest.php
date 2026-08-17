<?php

use PHPUnit\Framework\TestCase;

class ArraySortTest extends TestCase
{
    /**
     * @group sort
     * @dataProvider providerArraySort
     */
    public function testArraySort($data, $on, $order, $expected, ?array $expectedKeys = null): void
    {
        $sorted = array_sort($data, $on, $order);

        if ($expectedKeys !== null) {
            $this->assertSame($expectedKeys, array_keys($sorted));
        }

        $this->assertSame($expected, $sorted);
    }

    /**
     * Data provider for testArraySortSimple().
     *
     * Each dataset:
     *  - $data         Input array.
     *  - $on           Field name or index to sort by.
     *  - $order        Sort order: 'SORT_ASC' or 'SORT_DESC'.
     *  - $expected     Expected sorted array.
     *  - $expectedKeys Optional expected keys after sorting.
     *
     * @return array
     */
    public static function providerArraySort(): array
    {
        return [

            'numeric array ascending' => [
                'data' => [
                    10 => 30,
                    5  => 10,
                    7  => 20,
                ],
                'on'   => 0,               // ignored for scalars, value itself is used
                'order'=> 'SORT_ASC',
                'expected' => [
                    5  => 10,
                    7  => 20,
                    10 => 30,
                ],
                'expectedKeys' => [5, 7, 10],
            ],

            'numeric array descending' => [
                'data' => [
                    10 => 30,
                    5  => 10,
                    7  => 20,
                ],
                'on'   => 0,
                'order'=> 'SORT_DESC',
                'expected' => [
                    10 => 30,
                    7  => 20,
                    5  => 10,
                ],
                'expectedKeys' => [10, 7, 5],
            ],

            'array of arrays ascending by field' => [
                'data' => [
                    10 => ['time' => 3.0, 'id' => 10],
                    5  => ['time' => 1.5, 'id' => 5],
                    7  => ['time' => 2.0, 'id' => 7],
                ],
                'on'   => 'time',
                'order'=> 'SORT_ASC',
                'expected' => [
                    5  => ['time' => 1.5, 'id' => 5],
                    7  => ['time' => 2.0, 'id' => 7],
                    10 => ['time' => 3.0, 'id' => 10],
                ],
                'expectedKeys' => [5, 7, 10],
            ],

            'array of arrays descending by field' => [
                'data' => [
                    10 => ['time' => 3.0, 'id' => 10],
                    5  => ['time' => 1.5, 'id' => 5],
                    7  => ['time' => 2.0, 'id' => 7],
                ],
                'on'   => 'time',
                'order'=> 'SORT_DESC',
                'expected' => [
                    10 => ['time' => 3.0, 'id' => 10],
                    7  => ['time' => 2.0, 'id' => 7],
                    5  => ['time' => 1.5, 'id' => 5],
                ],
                'expectedKeys' => [10, 7, 5],
            ],

            'missing field in some rows are skipped' => [
                'data' => [
                    1 => ['time' => 5, 'id' => 1],
                    2 => ['id' => 2],         // no 'time'
                    3 => ['time' => 1, 'id' => 3],
                ],
                'on'   => 'time',
                'order'=> 'SORT_ASC',
                'expected' => [
                    3 => ['time' => 1, 'id' => 3],
                    1 => ['time' => 5, 'id' => 1],
                    // key 2 is skipped because 'time' is missing
                ],
                'expectedKeys' => [3, 1],
            ],

            'non-array row sorted by its own value' => [
                'data' => [
                    1 => ['name' => 'alpha'],
                    2 => 'bravo',
                    3 => ['name' => 'charlie'],
                ],
                'on'   => 'name',
                'order'=> 'SORT_ASC',
                // For key 1 and 3: used 'name'; for key 2: value 'bravo'
                // Sort values: 'alpha', 'bravo', 'charlie'
                'expected' => [
                    1 => ['name' => 'alpha'],
                    2 => 'bravo',
                    3 => ['name' => 'charlie'],
                ],
                'expectedKeys' => [1, 2, 3],
            ],

            'empty array returns empty array' => [
                'data' => [],
                'on'   => 'time',
                'order'=> 'SORT_ASC',
                'expected' => [],
                'expectedKeys' => [],
            ],
        ];
    }

    /**
     * @group sort
     * @dataProvider providerArraySortBy
     */
    public function testArraySortBy($data, array $args, $expected, ?array $expectedKeys = null): void
    {
        // Direct call: array_sort_by($data, ...$args)
        $sorted = array_sort_by($data, ...$args);

        if ($expectedKeys !== null) {
            $this->assertSame($expectedKeys, array_keys($sorted));
        }

        $this->assertSame($expected, $sorted);
    }

    /**
     * Data provider for testArraySortBy().
     *
     * @return array
     */
    public static function providerArraySortBy(): array
    {
        return [

            'single field ascending' => [
                'data' => [
                    ['name' => 'delta',  'age' => 20],
                    ['name' => 'bravo',  'age' => 30],
                    ['name' => 'charlie','age' => 25],
                    ['name' => 'alpha',  'age' => 40],
                ],
                'args' => ['name'],
                'expected' => [
                    ['name' => 'alpha',  'age' => 40],
                    ['name' => 'bravo',  'age' => 30],
                    ['name' => 'charlie','age' => 25],
                    ['name' => 'delta',  'age' => 20],
                ],
                'expectedKeys' => null,
            ],

            'single field descending numeric' => [
                'data' => [
                    ['name' => 'a', 'age' => 30],
                    ['name' => 'b', 'age' => 20],
                    ['name' => 'c', 'age' => 40],
                ],
                'args' => ['age', SORT_DESC, SORT_NUMERIC],
                'expected' => [
                    ['name' => 'c', 'age' => 40],
                    ['name' => 'a', 'age' => 30],
                    ['name' => 'b', 'age' => 20],
                ],
                'expectedKeys' => null,
            ],

            'multiple fields' => [
                'data' => [
                    ['group' => 'B', 'name' => 'alpha'],
                    ['group' => 'A', 'name' => 'charlie'],
                    ['group' => 'A', 'name' => 'bravo'],
                    ['group' => 'B', 'name' => 'delta'],
                ],
                'args' => [
                    'group', SORT_ASC, SORT_STRING,
                    'name',  SORT_ASC, SORT_STRING,
                ],
                'expected' => [
                    ['group' => 'A', 'name' => 'bravo'],
                    ['group' => 'A', 'name' => 'charlie'],
                    ['group' => 'B', 'name' => 'alpha'],
                    ['group' => 'B', 'name' => 'delta'],
                ],
                'expectedKeys' => null,
            ],

            'missing field becomes null' => [
                'data' => [
                    ['name' => 'alpha',  'age' => 30],
                    ['name' => 'bravo'],                     // no 'age' field
                    ['name' => 'charlie','age' => 20],
                ],
                'args' => ['age', SORT_ASC, SORT_NUMERIC],
                // Actual behaviour: NULL is treated as 0 and sorted first
                'expected' => [
                    ['name' => 'bravo'],                    // age = NULL -> 0
                    ['name' => 'charlie', 'age' => 20],
                    ['name' => 'alpha',   'age' => 30],
                ],
                'expectedKeys' => null,
            ],

            'non-array row handled as null' => [
                'data' => [
                    ['name' => 'alpha', 'age' => 30],
                    'not-an-array',
                    ['name' => 'bravo', 'age' => 20],
                ],
                'args' => ['age', SORT_ASC, SORT_NUMERIC],
                // Actual behaviour: non-array row produces NULL column value and is sorted first
                'expected' => [
                    'not-an-array',
                    ['name' => 'bravo', 'age' => 20],
                    ['name' => 'alpha', 'age' => 30],
                ],
                'expectedKeys' => null,
            ],

            'numeric keys are reindexed' => [
                'data' => [
                    10 => ['name' => 'bravo'],
                    5  => ['name' => 'alpha'],
                    42 => ['name' => 'charlie'],
                ],
                'args' => ['name'],
                'expected' => [
                    ['name' => 'alpha'],
                    ['name' => 'bravo'],
                    ['name' => 'charlie'],
                ],
                'expectedKeys' => [0, 1, 2],
            ],

            'empty array returns empty array' => [
                'data' => [],
                'args' => ['name'],
                'expected' => [],
                'expectedKeys' => [],
            ],

            'non-array input casts to array' => [
                'data' => 'not-array',
                'args' => ['name'],
                'expected' => ['not-array'],
                'expectedKeys' => [0],
            ],
        ];
    }

    /**
     * @group sort
     * @dataProvider providerArraySortByOrder
     */
    public function testArraySortByOrder(array $data, array $sortOrder, string $sortKey, bool $strict, array $expected, ?array $expectedKeys = null): void
    {
        $sorted = array_sort_by_order($data, $sortOrder, $sortKey, $strict);

        if ($expectedKeys !== null) {
            $this->assertSame($expectedKeys, array_keys($sorted), 'Array keys do not match expected.');
        }

        $this->assertSame($expected, $sorted);
    }

    /**
     * Data provider for testArraySortByOrder().
     *
     * Each dataset:
     *  - $data         Input array
     *  - $sortOrder    Custom order for sortKey values
     *  - $sortKey      Key used for sorting
     *  - $strict       If true, items without sortKey are removed
     *  - $expected     Expected sorted array
     *  - $expectedKeys Optional expected array keys after sorting
     *
     * @return array
     */
    public static function providerArraySortByOrder(): array
    {
        return [

            'simple group order with preserved keys' => [
                'data' => [
                    10 => ['group' => 'DOWN'],
                    5  => ['group' => 'UP'],
                    7  => ['group' => 'DISABLED'],
                    3  => ['group' => 'UP'],
                ],
                'sortOrder' => ['', 'UP', 'DOWN', 'DISABLED'],
                'sortKey' => 'group',
                'strict'  => false,
                'expected' => [
                    5 => ['group' => 'UP'],
                    3 => ['group' => 'UP'],
                    10 => ['group' => 'DOWN'],
                    7 => ['group' => 'DISABLED'],
                ],
                'expectedKeys' => [5, 3, 10, 7],
            ],

            'missing sort key non strict treated as empty string' => [
                'data' => [
                    1 => ['name' => 'no-group'],
                    2 => ['group' => 'UP', 'name' => 'with-group'],
                ],
                'sortOrder' => ['', 'UP', 'DOWN', 'DISABLED'],
                'sortKey'   => 'group',
                'strict'    => false,
                'expected' => [
                    1 => ['name' => 'no-group'],                    // group => ''
                    2 => ['group' => 'UP', 'name' => 'with-group'],
                ],
                'expectedKeys' => [1, 2],
            ],

            'missing sort key strict removes item' => [
                'data' => [
                    1 => ['name' => 'no-group'],
                    2 => ['group' => 'UP', 'name' => 'with-group'],
                ],
                'sortOrder' => ['', 'UP', 'DOWN', 'DISABLED'],
                'sortKey'   => 'group',
                'strict'    => true,
                'expected' => [
                    2 => ['group' => 'UP', 'name' => 'with-group'],
                ],
                'expectedKeys' => [2],
            ],

            'unknown value goes last' => [
                'data' => [
                    1 => ['group' => 'UP'],
                    2 => ['group' => 'MAINT'],
                    3 => ['group' => 'DOWN'],
                ],
                'sortOrder' => ['UP', 'DOWN'],
                'sortKey'   => 'group',
                'strict'    => false,
                'expected' => [
                    1 => ['group' => 'UP'],
                    3 => ['group' => 'DOWN'],
                    2 => ['group' => 'MAINT'], // not in sortOrder -> PHP_INT_MAX -> last
                ],
                'expectedKeys' => [1, 3, 2],
            ],

            'custom sort key status' => [
                'data' => [
                    100 => ['status' => 'DONE',  'id' => 100],
                    200 => ['status' => 'NEW',   'id' => 200],
                    300 => ['status' => 'READY', 'id' => 300],
                ],
                'sortOrder' => ['NEW', 'READY', 'DONE'],
                'sortKey'   => 'status',
                'strict'    => false,
                'expected' => [
                    200 => ['status' => 'NEW',   'id' => 200],
                    300 => ['status' => 'READY', 'id' => 300],
                    100 => ['status' => 'DONE',  'id' => 100],
                ],
                'expectedKeys' => [200, 300, 100],
            ],

            'empty array returns empty array' => [
                'data' => [],
                'sortOrder' => ['', 'UP', 'DOWN', 'DISABLED'],
                'sortKey'   => 'group',
                'strict'    => false,
                'expected' => [],
                'expectedKeys' => [],
            ],
        ];
    }

    /**
     * @group sort
     * @dataProvider providerArraySortReal
     */
    public function testArraySortReal(array $data, array $sortOrder, string $sortKey, array $expected, ?array $expectedKeys = null): void
    {
        $sorted = array_sort_by_order($data, $sortOrder, $sortKey);

        if ($expectedKeys !== null) {
            $this->assertSame($expectedKeys, array_keys($sorted), 'Array keys do not match expected.');
        }

        $this->assertSame($expected, $sorted);
    }
    public static function providerArraySortReal() {
        return [
            'devices by group' => [
                'data' => [
                    1   => [
                        'group' => 'DOWN',
                        'class' => 'bg-danger',
                        'name'  => 'demo.observium.ru',
                    ],
                    97  => [
                        'group' => 'UP',
                        'class' => 'bg-info',
                        'name'  => 'dev.observium.ru',
                    ],
                    361 => [
                        'group' => 'UP',
                        'class' => 'bg-info',
                        'name'  => 'ilo.erpica.net',
                    ],
                    245 => [
                        'group' => 'DOWN',
                        'class' => 'bg-danger',
                        'name'  => 'sophos-utm.paid',
                    ],
                    185 => [
                        'group' => 'UP',
                        'class' => 'bg-info',
                        'name'  => 'win.observium.ru',
                    ],
                ],
                'sortOrder' => [ '', 'UP', 'DOWN', 'DISABLED' ],
                'sortKey'   => 'group',
                'expected' => [
                    97  => [
                        'group' => 'UP',
                        'class' => 'bg-info',
                        'name'  => 'dev.observium.ru',
                    ],
                    361 => [
                        'group' => 'UP',
                        'class' => 'bg-info',
                        'name'  => 'ilo.erpica.net',
                    ],
                    185 => [
                        'group' => 'UP',
                        'class' => 'bg-info',
                        'name'  => 'win.observium.ru',
                    ],
                    1   => [
                        'group' => 'DOWN',
                        'class' => 'bg-danger',
                        'name'  => 'demo.observium.ru',
                    ],
                    245 => [
                        'group' => 'DOWN',
                        'class' => 'bg-danger',
                        'name'  => 'sophos-utm.paid',
                    ],
                ],
                'expectedKeys' => [97, 361, 185, 1, 245],
            ],
        ];
    }
}
