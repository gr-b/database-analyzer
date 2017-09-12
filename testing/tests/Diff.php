<?php

namespace Datto\DatabaseAnalyzer\Scanner;

require LENS . 'autoload.php';

$scan = array(
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


$old = null;
$new = null;

// Test
$newScan = Diff::transferOldValues($old, $new);

// Input
$old = $scan;
$old['schema1']['table1']['columns']['column1']['values'] = array('SOME', 1, 2, 3);
$old['schema2']['table1']['columns']['column1']['values'] = array('ALL', 1, 2);
$old = new Scan($old);
$new = new Scan($scan);

// Output
$newScan = $scan;
$newScan['schema1']['table1']['columns']['column1']['values'] = array('SOME', 1, 2, 3);
$newScan['schema2']['table1']['columns']['column1']['values'] = array('ALL', 1, 2);
$newScan = new Scan($newScan);


// Diff transfers the values field regardless of what it is
// (note: class Scan guarantees that the values field is an array)
// Input
$old = $scan;
$old['schema1']['table1']['columns']['column1']['values'] = array();
$old['schema2']['table1']['columns']['column1']['values'] = array();
$old = new Scan($old);
$new = new Scan($scan);

// Output
$newScan = $scan;
$newScan['schema1']['table1']['columns']['column1']['values'] = array();
$newScan['schema2']['table1']['columns']['column1']['values'] = array();
$newScan = new Scan($newScan);


// If the column are different, do not substitute values
// Input
$old = $scan;
$old['schema1']['table1']['columns']['column1']['values'] = array();
$old['schema1']['table1']['columns']['column1']['type'] = 'TEXT';
$old = new Scan($old);
$new = new Scan($scan);

// Output
$newScan = new Scan($scan);


// Test
$output = Diff::array_diff_recursive($a, $b);

// Input
$a = array('a');
$b = array('a');


// Output
$output = array();


// Input
$a = array('a');
$b = array('b');


// Output
$output = array('a');

// Input
$a = array('a');
$b = array('b', 'c');


// Output
$output = array('a');


// Input
$a = array(
    'a' => array('a','b')
);
$b = array(
    'a' => array('a','b')
);


// Output
$output = array();


// Input
$a = array(
    'a' => array('c','b')
);
$b = array(
    'a' => array('a','b')
);


// Output
$output = array(
    'a' => array('c')
);


// Input
$a = array(
    'a' => array('c','b'),
    'b'
);
$b = array(
    'a' => array('a','b'),
    'b'
);


// Output
$output = array(
    'a' => array('c')
);


// Input
$a = array(
    'a' => array('c','b'),
    'c'
);
$b = array(
    'a' => array('a','b'),
    'b'
);


// Output
$output = array(
    'a' => array('c'),
    'c'
);
