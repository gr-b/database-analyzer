<?php

namespace Datto\DatabaseAnalyzer\Scanner;

require LENS . 'autoload.php';

use Datto\DatabaseAnalyzer\Database\QueriesMock;
use Datto\DatabaseAnalyzer\Utility\Logger;
use Exception;

// Assume we input a valid scan file: Scan validation is tested in tests/Scan.php

$database = null;
$whitelist = null;
$logger = new Logger('datto.database-analyzer-mapper-tester');


// Test
$queries = new QueriesMock($database);
$mapper = new Mapper($queries, 'def', $logger);
$output = $mapper->map($whitelist);

// Input
$whitelist = array();
$database = array();

// Output
echo "Getting schema list...\n";
throw new Exception('Mapper error: Database has no schemas.');


// Database has no schemas but we have one in whitelist
// Input
$whitelist = array('schema1');
$database = array();

// Output
echo "Getting schema list...\n";
throw new Exception('Mapper error: Database has no schemas.');


// Requested schema has no tables
// Input
$whitelist = array('schema1');
$database = array('schema1' => array());

// Should scan the one schema.
// Output
echo "Getting schema list...\n";
echo "Mapping schemas [\"schema1\"]\n";
echo "Started mapping schema1...\n";
throw new Exception('Mapper Error: schema schema1 has no tables.');


// Table has no columns nor metadata defined.
// Input
$whitelist = array('schema1');
$database = array(
    'schema1' => array(
        'table1' => array(),
    )
);

// Should scan the one schema.
// Output
echo "Getting schema list...\n";
echo "Mapping schemas [\"schema1\"]\n";
echo "Started mapping schema1...\n";
throw new Exception('Mapper Error: schema schema1 has no tables.');


// Table has columns and some metadata defined.
// Input
$whitelist = array('schema1');
$database = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                ),
            ),
            'cardinality' => 999
        ),
    )
);

// Output
echo "Getting schema list...\n";
echo "Mapping schemas [\"schema1\"]\n";
echo "Started mapping schema1...\n";
$output = new Scan($database);


// Table has columns and blank metadata fields
// Input
$whitelist = array('schema1');
$database = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                ),
            ),
            'indices' => array(),
            'unique' => array(),
            'foreign_keys' => array(),
            'cardinality' => 999
        ),
    )
);

// Output
echo "Getting schema list...\n";
echo "Mapping schemas [\"schema1\"]\n";
echo "Started mapping schema1...\n";
$output = new Scan(array( // Mapper removes blank metadata
        'schema1' => array(
            'table1' => array(
                'columns' => array(
                    'column1' => array(
                        'type' => 'INT',
                        'values' => null,
                        'isNullable' => false,
                        'default' => null,
                        'comment' => 'comment1',
                        'extra' => null,
                    ),
                ),
                'cardinality' => 999
            ),
        )
    )
);


// Table has columns and metadata
// Input
$whitelist = array('schema1');
$database = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                ),
            ),
            'indices' => array(
                'index1' => array('column1')
            ),
            'cardinality' => 999,
            'unique' => array(
                array('column1')
            ),
            'comment' => 'table comment 1'
        ),
    )
);

// Output
echo "Getting schema list...\n";
echo "Mapping schemas [\"schema1\"]\n";
echo "Started mapping schema1...\n";
$output = new Scan($database);


// Whitelist and mapper can support multiple schemas
// Input
$whitelist = array('schema1', 'schema2');
$database = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                ),
            )
        )
    ),
    'schema2' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                ),
            )
        )
    ),
);

// Output
echo "Getting schema list...\n";
echo "Mapping schemas [\"schema1\",\"schema2\"]\n";
echo "Started mapping schema1...\n";
echo "Started mapping schema2...\n";
$output = new Scan($database);


// Assert that the mapper will not confuse tables in different schemas
// Input
$whitelist = array('schema1', 'schema2');
$database = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                ),
            )
        )
    ),
    'schema2' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                ),
            ),
            'foreign_keys' => array(
                array(
                    'column' => 'column1',
                    'referenced_table' => 'table2',
                    'referenced_column' => 'column2'
                )
            )
        )
    ),
);

// Output
echo "Getting schema list...\n";
echo "Mapping schemas [\"schema1\",\"schema2\"]\n";
echo "Started mapping schema1...\n";
echo "Started mapping schema2...\n";
$output = new Scan($database);


// Schemas can have multiple tables and tables can have multiple columns.
// Input
$whitelist = array('schema1');
$database = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                ),
                'column2' => array(
                    'type' => 'TEXT',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                ),
            ),
            'indices' => array(
                'index1' => array('column1')
            ),
            'cardinality' => 999,
            'unique' => array(
                array('column1')
            ),
        ),
        'table2' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                ),
            ),
            'indices' => array(
                'index1' => array('column1')
            ),
            'cardinality' => 999,
            'unique' => array(
                array('column1')
            )
        ),
    )
);

// Output
echo "Getting schema list...\n";
echo "Mapping schemas [\"schema1\"]\n";
echo "Started mapping schema1...\n";
$output = new Scan($database);


// Tables can have multiple indices, uniqueness constraints, and foreign keys.
// Input
$whitelist = array('schema1');
$database = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                ),
            ),
            'indices' => array(
                'index1' => array('column1', 'column2', 'column3'),
                'index2' => array('column4', 'column5')
            ),
            'cardinality' => 999,
            'unique' => array(
                array('column1', 'column2'),
                array('column1', 'column3')
            ),
            'comment' => 'table comment 1',
            'foreign_keys' => array(
                array(
                    'column' => 'column1',
                    'referenced_table' => 'table2',
                    'referenced_column' => 'column2'
                ),
                array(
                    'column' => 'column2',
                    'referenced_table' => 'table3',
                    'referenced_column' => 'column3'
                )
            )
        ),
    )
);

// Output
echo "Getting schema list...\n";
echo "Mapping schemas [\"schema1\"]\n";
echo "Started mapping schema1...\n";
$output = new Scan($database);


// Get values for enums and sets
// Input
$whitelist = array('schema1');
$database = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'ENUM',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                    'detailedType' => 'enum(\'1\',\'2\')'
                ),
                'column2' => array(
                    'type' => 'SET',
                    'values' => null,
                    'isNullable' => false,
                    'default' => null,
                    'comment' => 'comment1',
                    'extra' => null,
                    'detailedType' => 'set(\'3\',\'4\')'
                )
            ),
        ),
    )
);

// Output
echo "Getting schema list...\n";
echo "Mapping schemas [\"schema1\"]\n";
echo "Started mapping schema1...\n";
$output = new Scan(
    array(
        'schema1' => array(
            'table1' => array(
                'columns' => array(
                    'column1' => array(
                        'type' => 'ENUM',
                        'values' => array('1','2'),
                        'isNullable' => false,
                        'default' => null,
                        'comment' => 'comment1',
                        'extra' => null,
                    ),
                    'column2' => array(
                        'type' => 'SET',
                        'values' => array('3','4'),
                        'isNullable' => false,
                        'default' => null,
                        'comment' => 'comment1',
                        'extra' => null,
                    )
                ),
            ),
        )
    )
);


