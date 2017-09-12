<?php

namespace Datto\DatabaseAnalyzer\Scanner;

require LENS . 'autoload.php';

use Exception;

$input = null;

$scan1 = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => null
            )
        )
    ),
);

$scan2 = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => null,
                    'isNullable' => true,
                    'default' => null,
                    'comment' => null,
                    'extra' => 'extra1'
                )
            )
        )
    ),
);

$scan3 = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => array("SOME", 1, 2, 3, 4),
                    'isNullable' => true,
                    'default' => 'default1',
                    'comment' => 'comment1',
                    'extra' => 'extra'
                )
            ),
            'indices' => array(
                'index1' =>
                    array(
                        'column1'
                    )
            ),
        )
    ),
);

$scan4 = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array(
                    'type' => 'INT',
                    'values' => array("SOME", 1, 2, 3, 4),
                    'isNullable' => true,
                    'default' => 'default1',
                    'comment' => 'comment1',
                    'extra' => 'extra'
                )
            ),
            'indices' => array(
                'index1' =>
                    array(
                        'column1'
                    )
            ),
            'unique' => array(
                array(
                    'column1'
                )
            ),
            'cardinality' => 0
        )
    ),
);

$scan5 = array(
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



// Test
$scan = new Scan($input);

// Input
$input = array();

// Output
throw new Exception('Invalid scan1: Scan must be an array with at least one schema.');


// Input
$input = array(
    'schema1' => array(),
);

// Output
throw new Exception('Invalid scan1: Schema schema1 must have at least one table.');


// Input
$input = array(
    'schema1' => array(
        'table1' => array()
    ),
);

// Output
throw new Exception('Invalid scan1: schema1=>table1 must have key \'columns\' defined and have at least one column.');


// Input
$input = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array()
        )
    ),
);

// Output
throw new Exception('Invalid scan1: schema1=>table1 must have key \'columns\' defined and have at least one column.');


// Input
$input = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => array()
            )
        )
    ),
);

// Output
throw new Exception('Invalid scan1: schema1=>table1=>\'columns\'=>column1=>\'type\' must be a valid MySQL type, got null');


// Input
$input = array(
    'schema1' => array(
        'table1' => array(
            'columns' => array(
                'column1' => null
            )
        )
    ),
);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'columns'=>column1 has no definition");


// Input
$input = $scan1;
$column = &$input['schema1']['table1']['columns']['column1'];
$column['type'] = 'INT';
unset($column);

// Output
throw new Exception("Invalid scan1: column 'column1' must have field 'isNullable' of type boolean");


// Input
$input = $scan1;
$column = &$input['schema1']['table1']['columns']['column1'];
$column['type'] = 'INT';
$column['values'] = false;
$column['isNullable'] = true;
unset($column);

// Output
throw new Exception("Invalid scan1: column 'column1' must have field 'values' of type array");


// Input
$input = $scan1;
$column = &$input['schema1']['table1']['columns']['column1'];
$column['type'] = 'INT';
$column['values'] = null;
$column['isNullable'] = true;
$column['default'] = 0;
unset($column);

// Output
throw new Exception("Invalid scan1: column 'column1' must have field 'default' of type string");


// Input
$input = $scan1;
$column = &$input['schema1']['table1']['columns']['column1'];
$column['type'] = 'INT';
$column['values'] = null;
$column['isNullable'] = true;
$column['default'] = null;
$column['comment'] = true;
unset($column);

// Output
throw new Exception("Invalid scan1: column 'column1' must have field 'comment' of type string");


// Input
$input = $scan1;
$column = &$input['schema1']['table1']['columns']['column1'];
$column['type'] = 'INT';
$column['values'] = null;
$column['isNullable'] = true;
$column['default'] = null;
$column['comment'] = null;
$column['extra'] = 0;
unset($column);

// Output
throw new Exception("Invalid scan1: column 'column1' must have field 'extra' of type string");


// Input
$input = $scan2;
$table = &$input['schema1']['table1'];
$table['comment'] = 0;
unset($table);

// Output
throw new Exception('Invalid scan1: table schema1=>table1 has an invalid comment');


// Input
$input = $scan2;
$table = &$input['schema1']['table1'];
$table['comment'] = 'table comment1';
$table['indices'] = 0;
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1 must have key 'indices' defined as an array.");


// Input
$input = $scan2;
$table = &$input['schema1']['table1'];
$table['comment'] = 'table comment1';
$table['indices'] = array(
    'index1' => 0
);
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'indices' must only hold arrays, got '0'.");


// Input
$input = $scan2;
$table = &$input['schema1']['table1'];
$table['comment'] = 'table comment1';
$table['indices'] = array(
    'index1' =>
        array(
            0
        )
);
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'indices' field must hold an array of arrays of strings.");


// Input
$input = $scan3;
$table = &$input['schema1']['table1'];
$table['cardinality'] = '0';
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1 must have key 'cardinality' defined and be type integer.");


// Input
$input = $scan3;
$table = &$input['schema1']['table1'];
$table['cardinality'] = 0;
$table['unique'] = 0;
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1 must have key 'unique' defined and be type array.");


// Input
$input = $scan3;
$table = &$input['schema1']['table1'];
$table['cardinality'] = 0;
$table['unique'] = array(
    0
);
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'unique' field must hold an array of arrays of strings.");


// Input
$input = $scan3;
$table = &$input['schema1']['table1'];
$table['cardinality'] = 0;
$table['unique'] = array(
    array()
);
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'unique' field must hold an array of arrays of strings.");


// Input
$input = $scan3;
$table = &$input['schema1']['table1'];
$table['cardinality'] = 0;
$table['unique'] = array(
    array(
        0
    )
);
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'unique' field must hold an array of arrays of strings.");


// Input
$input = $scan4;
$table = &$input['schema1']['table1'];
$table['foreign_keys'] = 0;
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'foreign_keys' must be an array of arrays.");


// Input
$input = $scan4;
$table = &$input['schema1']['table1'];
$table['foreign_keys'] = array();
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'foreign_keys' must be an array of arrays.");


// Input
$input = $scan4;
$table = &$input['schema1']['table1'];
$table['foreign_keys'] = array(
    0
);
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'foreign_keys' must be an array of arrays.");


// Input
$input = $scan4;
$table = &$input['schema1']['table1'];
$table['foreign_keys'] = array(
    array()
);
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'foreign_keys' must have key 'column' which must be of type string");


// Input
$input = $scan4;
$table = &$input['schema1']['table1'];
$table['foreign_keys'] = array(
    array(
        'column' => 0
    )
);
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'foreign_keys' must have key 'column' which must be of type string");


// Input
$input = $scan4;
$table = &$input['schema1']['table1'];
$table['foreign_keys'] = array(
    array(
        'column' => 'column1'
    )
);
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'foreign_keys' must have key 'referenced_table' which must be of type string");


// Input
$input = $scan4;
$table = &$input['schema1']['table1'];
$table['foreign_keys'] = array(
    array(
        'column' => 'column1',
        'referenced_table' => 'table2'
    )
);
unset($table);

// Output
throw new Exception("Invalid scan1: schema1=>table1=>'foreign_keys' must have key 'referenced_column' which must be of type string");


// Input
$input = $scan4;
$table = &$input['schema1']['table1'];
$table['foreign_keys'] = array(
    array(
        'column' => 'column1',
        'referenced_table' => 'table2',
        'referenced_column' => 'column2'
    )
);
$table['unnecessary'] = 'value';
unset($table);

// Output
throw new Exception('Invalid scan1: table schema1=>table1 has unnecessary keys defined: ["unnecessary"]');



// Test
$scan = new Scan($input);
$output = $scan->getSchemas();

// Input
$input = $scan5;

// Output
$output = array('schema1', 'schema2');


// Test
$scan = new Scan($input);
$schema1Tables = $scan->getTables('schema1');
$schema2Tables = $scan->getTables('schema2');

// Input
$input = $scan5;

// Output
$schema1Tables = array("table1");
$schema2Tables = array("table1");

