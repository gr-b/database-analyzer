<?php

/**
 * Copyright (C) 2016, 2017 Datto, Inc.
 *
 * This file is part of database-analyzer.
 *
 * Database-analyzer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Database-analyzer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with database-analyzer. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Griffin Bishop <gbishop@datto.com>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 * @copyright 2016, 2017 Datto, Inc.
 */

namespace Datto\DatabaseAnalyzer\Database;

class QueriesMock implements Queries
{

    /** @var array */
    private $scan;

    /**
     * Uses the given pre-made scan to answer mock
     * queries to the 'database'.
     * @param $scan
     */
    public function __construct($scan)
    {
        $this->scan = $scan;
    }

    // Begin Mapper Queries

    public function queryForeignKeys($schema)
    {
        $rows = array();
        if (!isset($this->scan[$schema])) {
            return array();
        }
        foreach ($this->scan[$schema] as $table => $tableDefinition) {
            if (!isset($tableDefinition['foreign_keys'])) {
                continue;
            }
            foreach ($tableDefinition['foreign_keys'] as $key) {
                $rows[] = array(
                    'table_name' => $table,
                    'column_name' => $key['column'],
                    'referenced_table_name' => $key['referenced_table'],
                    'referenced_column_name' => $key['referenced_column']
                );
            }
        }
        return $rows;
    }

    public function getSchemas()
    {
        $schemas = array_keys($this->scan);
        $rows = array();
        foreach ($schemas as $schema) {
            $rows[] = array('schema' => $schema);
        }
        return $rows;
    }

    public function selectColumns($schema, $catalog)
    {
        $rows = array();
        if (!isset($this->scan[$schema]) || empty($this->scan[$schema])) {
            return array();
        }
        foreach ($this->scan[$schema] as $table => $tableDefinition) {
            if (empty($tableDefinition) || empty($tableDefinition['columns'])) {
                continue;
            }
            foreach ($tableDefinition['columns'] as $column => $columnDefinition) {
                $type = $columnDefinition['type'];
                $values = $columnDefinition['values'];
                $isNullable = $columnDefinition['isNullable'];
                $default = $columnDefinition['default'];
                $comment = $columnDefinition['comment'];
                $extra = $columnDefinition['extra'];
                $detailedType = &$columnDefinition['detailedType'];
                $rows[] = array(
                    'table' => $table,
                    'name' => $column,
                    'type' => $type,
                    'values' => $values,
                    'isNullable' => $isNullable,
                    'default' => $default,
                    'comment' => $comment,
                    'extra' => $extra,
                    'detailedType' => (isset($detailedType) ? $detailedType : $type),
                );
            }
        }
        return $rows;
    }

    public function selectIndices($schema, $catalog)
    {
        $rows = array();
        if (!isset($this->scan[$schema])) {
            return array();
        }
        foreach ($this->scan[$schema] as $table => $tableDefinition) {
            if (!isset($tableDefinition['indices'])) {
                continue;
            }
            foreach ($tableDefinition['indices'] as $name => $columns) {
                $cardinality = &$tableDefinition['cardinality'];
                foreach ($columns as $column) {
                    $rows[] = array(
                        'table' => $table,
                        'index' => $name,
                        'column' => $column,
                        'cardinality' => $cardinality,
                    );
                }
            }
        }
        return $rows;
    }

    public function selectCardinalities($schema, $catalog)
    {
        $rows = array();
        if (!isset($this->scan[$schema])) {
            return array();
        }
        foreach ($this->scan[$schema] as $table => $tableDefinition) {
            if (!isset($tableDefinition['cardinality'])) {
                continue;
            }
            $rows[] = array(
                'table' => $table,
                'cardinality' => $this->scan[$schema][$table]['cardinality']
            );
        }
        return $rows;
    }

    public function selectUniquenessConstraints($schema, $catalog)
    {
        $rows = array();
        if (!isset($this->scan[$schema])) {
            return array();
        }
        foreach ($this->scan[$schema] as $table => $tableDefinition) {
            if (!isset($tableDefinition['unique'])) {
                continue;
            }
            foreach ($tableDefinition['unique'] as $id => $columns) {
                foreach ($columns as $column) {
                    $rows[] = array(
                        'id' => $id,
                        'table' => $table,
                        'column' => $column,
                    );
                }
            }
        }
        return $rows;
    }

    public function selectTableComments($schema, $catalog)
    {
        $rows = array();
        if (!isset($this->scan[$schema])) {
            return array();
        }
        foreach ($this->scan[$schema] as $table => $tableDefinition) {
            if (!isset($tableDefinition['comment'])) {
                continue;
            }
            $rows[] = array(
                'name' => $table,
                'comment' => $tableDefinition['comment']
            );
        }
        return $rows;
    }
    // End Mapper Queries


    // Begin Sampler Queries

    public function selectRandom($schema, $table, $column, $key)
    {
        echo "selectrandom\n";
    }

    public function countDistinctValues($schema, $table, $column)
    {
        return count($this->scan[$schema][$table]['columns'][$column]['values']);
    }

    public function selectDistinctValues($schema, $table, $column)
    {
        return array_unique($this->scan[$schema][$table]['columns'][$column]['values']);
    }

    public function selectEmptyStringValues($schema, $table, $column)
    {
        echo "selectemptystringvalues\n";
    }

    public function selectRandomValues($schema, $table, $column, $limit, $exclude = null)
    {
        echo "selectrandomvalues\n";
    }

    public function selectEmptyNumericValues($schema, $table, $column)
    {
        echo "Selectemptynumericvalues\n";
    }

    public function selectExtrema($schema, $table, $column)
    {
        echo "selectextrema\n";
    }

    // End Sampler Queries
}