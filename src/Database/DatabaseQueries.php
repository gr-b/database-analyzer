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


class DatabaseQueries implements Queries
{
    /** @var Database */
    private $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    // Begin Mapper Queries

    public function queryForeignKeys($schema)
    {
        $query = <<< 'EOS'
SELECT  `table_name`,
        `column_name`,
        `referenced_table_name`,
        `referenced_column_name`
FROM `information_schema`.`KEY_COLUMN_USAGE`
WHERE `REFERENCED_TABLE_SCHEMA` = :tableSchema
AND `REFERENCED_TABLE_NAME` is not null
ORDER BY `TABLE_NAME`,`COLUMN_NAME`;
EOS;
        $arguments = array(
            ':tableSchema' => $schema
        );

        return $this->database->select($query, $arguments);
    }

    public function getSchemas()
    {
        $query = <<< 'EOS'
SELECT DISTINCT
                `columns`.`TABLE_SCHEMA` AS `schema`
    FROM `information_schema`.`COLUMNS` AS `columns`
    INNER JOIN `information_schema`.`TABLES` AS `tables` ON
        (`tables`.`TABLE_CATALOG` <=> `columns`.`TABLE_CATALOG`) AND
        (`tables`.`TABLE_SCHEMA` <=> `columns`.`TABLE_SCHEMA`) AND
        (`tables`.`TABLE_NAME` <=> `columns`.`TABLE_NAME`)
    WHERE
        (`tables`.`TABLE_TYPE` <=> 'BASE TABLE')
EOS;

        $arguments = array();

        return $this->database->select($query, $arguments);
    }

    public function selectColumns($schema, $catalog)
    {
        $query = <<<'EOS'
SELECT
        `columns`.`TABLE_NAME` AS `table`,
        `columns`.`COLUMN_NAME` AS `name`,
        UPPER(`columns`.`DATA_TYPE`) AS `type`,
        `columns`.`COLUMN_TYPE` AS `detailedType`,
        IF(`columns`.`IS_NULLABLE` = 'NO', 0, 1) AS `isNullable`,
        `columns`.`COLUMN_DEFAULT` AS `default`,
        `columns`.`COLUMN_COMMENT` AS `comment`,
        UPPER(`columns`.`EXTRA`) AS `extra`
    FROM `information_schema`.`COLUMNS` AS `columns`
    INNER JOIN `information_schema`.`TABLES` AS `tables` ON
        (`tables`.`TABLE_CATALOG` <=> `columns`.`TABLE_CATALOG`) AND
        (`tables`.`TABLE_SCHEMA` <=> `columns`.`TABLE_SCHEMA`) AND
        (`tables`.`TABLE_NAME` <=> `columns`.`TABLE_NAME`)
    WHERE
        (`tables`.`TABLE_CATALOG` <=> :tableCatalog) AND
        (`tables`.`TABLE_SCHEMA` <=> :tableSchema) AND
        (`tables`.`TABLE_TYPE` <=> 'BASE TABLE')
EOS;

        $arguments = array(
            ':tableCatalog' => $catalog,
            ':tableSchema' => $schema
        );

        return $this->database->select($query, $arguments);
    }

    public function selectIndices($schema, $catalog)
    {
        $query = <<<'EOS'
SELECT DISTINCT
    `TABLE_NAME` AS `table`,
    `INDEX_NAME` AS `index`,
    `COLUMN_NAME` AS `column`
FROM `information_schema`.`STATISTICS`
WHERE
    (`TABLE_CATALOG` = :tableCatalog) AND
    (`TABLE_SCHEMA` = :tableSchema)
EOS;

        $arguments = array(
            ':tableCatalog' => $catalog,
            ':tableSchema' => $schema
        );

        return $this->database->select($query, $arguments);
    }

    public function selectCardinalities($schema, $catalog)
    {
        $query = <<<'EOS'
SELECT DISTINCT
    `TABLE_NAME` as `table`,
    `CARDINALITY` AS `cardinality`
FROM `information_schema`.`STATISTICS`
WHERE
    (`TABLE_CATALOG` = :tableCatalog) AND
    (`TABLE_SCHEMA` = :tableSchema)
EOS;

        $arguments = array(
            ':tableCatalog' => $catalog,
            ':tableSchema' => $schema
        );

        return $this->database->select($query, $arguments);
    }

    public function selectUniquenessConstraints($schema, $catalog)
    {
        $query = <<<'EOS'
SELECT
    CONCAT_WS('.', QUOTE(`usage`.`CONSTRAINT_CATALOG`), QUOTE(`usage`.`CONSTRAINT_SCHEMA`), QUOTE(`usage`.`CONSTRAINT_NAME`)) AS `id`,
    `usage`.`TABLE_NAME` AS `table`,
    `usage`.`COLUMN_NAME` AS `column`
    FROM `information_schema`.`TABLE_CONSTRAINTS` AS `constraints`
    INNER JOIN `information_schema`.`KEY_COLUMN_USAGE` AS `usage` ON
        (`constraints`.`CONSTRAINT_CATALOG` = `usage`.`CONSTRAINT_CATALOG`) AND
        (`constraints`.`CONSTRAINT_SCHEMA` = `usage`.`CONSTRAINT_SCHEMA`) AND
        (`constraints`.`CONSTRAINT_NAME` = `usage`.`CONSTRAINT_NAME`) AND
        (`constraints`.`TABLE_SCHEMA` = `usage`.`TABLE_SCHEMA`) AND
        (`constraints`.`TABLE_NAME` = `usage`.`TABLE_NAME`)
    WHERE
        (`usage`.`TABLE_CATALOG` = :tableCatalog) AND
        (`usage`.`TABLE_SCHEMA` = :tableSchema) AND
        (`constraints`.`CONSTRAINT_TYPE` IN ('PRIMARY KEY', 'UNIQUE'))
EOS;

        $arguments = array(
            ':tableCatalog' => $catalog,
            ':tableSchema' => $schema
        );

        return $this->database->select($query, $arguments);
    }

    public function selectTableComments($schema, $catalog)
    {
        $query = <<<'EOS'
SELECT
    `table`.`TABLE_NAME` AS `name`,
    `table`.`TABLE_COMMENT` AS `comment`
    FROM `information_schema`.`TABLES` AS `table`
    WHERE
        (`table`.`TABLE_CATALOG` <=> :tableCatalog) AND
        (`table`.`TABLE_SCHEMA` <=> :tableSchema) AND
        (`table`.`TABLE_TYPE` <=> 'BASE TABLE') AND
        (`table`.`TABLE_COMMENT` IS NOT NULL) AND
        (`table`.`TABLE_COMMENT` <> '')
EOS;

        $arguments = array(
            ':tableCatalog' => $catalog,
            ':tableSchema' => $schema
        );

        return $this->database->select($query, $arguments);
    }

    // End Mapper Queries


    // Begin Sampler Queries

    /**
     * Quickly select a random value from the given column.
     * This method is used when the column would be too go
     * through each element individually (using ORDER BY
     * rand() and LIMIT).
     *
     * @param string $schema
     * @param string $table
     * @param string $column
     * @param string $key
     * The name of a column in $table that is both unique and an index.
     * @return array
     */
    public function selectRandom($schema, $table, $column, $key)
    {
        // What if there's a table named `temp1` or `temp2`? Or column named `column`?
        /** @link http://jan.kneschke.de/projects/mysql/order-by-rand/ */
        $query = <<<EOS
SELECT `{$column}`
FROM `{$schema}`.`{$table}` AS `temp1` JOIN
    (SELECT (RAND() *
        (SELECT MAX(`{$key}`) 
                FROM `{$schema}`.`{$table}`))
    AS `column`)
        AS `temp2`
WHERE `temp1`.`{$key}` >= `temp2`.`column`
ORDER BY `temp1`.`{$key}` ASC
LIMIT 1;
EOS;

        $rows = $this->database->select($query);
        return $rows[0][$column];
    }

    public function countDistinctValues($schema, $table, $column)
    {
        $query = <<<EOS
SELECT
    COUNT(DISTINCT `{$column}`)
    FROM `{$schema}`.`{$table}`
EOS;

        return $this->database->selectValue($query);
    }

    public function selectDistinctValues($schema, $table, $column)
    {
        $query = <<<EOS
SELECT
    DISTINCT(`{$column}`)
    FROM `{$schema}`.`{$table}`
    ORDER BY `{$column}` ASC
EOS;

        return $this->database->selectColumn($query);
    }

    public function selectEmptyStringValues($schema, $table, $column)
    {
        $query = <<<EOS
SELECT
    DISTINCT `{$column}`
    FROM `{$schema}`.`{$table}`
    WHERE
        (`{$column}` <=> NULL) OR
        (`{$column}` <=> '') OR
        (`{$column}` <=> '0') OR
        (`{$column}` <=> '0000') OR
        (`{$column}` <=> '1901') OR
        (`{$column}` <=> '2155') OR
        (`{$column}` <=> '-838:59:59') OR
        (`{$column}` <=> '-838:59:59.000000') OR
        (`{$column}` <=> '00:00:00') OR
        (`{$column}` <=> '00:00:00.000000') OR
        (`{$column}` <=> '838:59:59') OR
        (`{$column}` <=> '838:59:59.000000') OR
        (`{$column}` <=> '0000-00-00') OR
        (`{$column}` <=> '1000-01-01') OR
        (`{$column}` <=> '1970-01-01') OR
        (`{$column}` <=> '9999-12-31') OR
        (`{$column}` <=> '0000-00-00 00:00:00') OR
        (`{$column}` <=> '1000-01-01 00:00:00') OR
        (`{$column}` <=> '1000-01-01 00:00:00.000000') OR
        (`{$column}` <=> '1970-01-01 00:00:00') OR
        (`{$column}` <=> '1970-01-01 00:00:00.000000') OR
        (`{$column}` <=> '1970-01-01 00:00:01') OR
        (`{$column}` <=> '1970-01-01 00:00:01.000000') OR
        (`{$column}` <=> '2038-01-19 03:14:07') OR
        (`{$column}` <=> '2038-01-19 03:14:07.999999') OR
        (`{$column}` <=> '9999-12-31 23:59:59') OR
        (`{$column}` <=> '9999-12-31 23:59:59.999999')
EOS;

        return $this->database->selectColumn($query);
    }

    public function selectRandomValues($schema, $table, $column, $limit, $exclude = null)
    {
        if (count($exclude) === 0) {
            $query = <<<EOS
SELECT
    DISTINCT(`{$column}`)
    FROM `{$schema}`.`{$table}`
    ORDER BY RAND()
    LIMIT {$limit}
EOS;
        } else {
            $conditions = array();

            foreach ($exclude as $value) {
                $escapedValue = json_encode($value);
                $conditions[] = "(`{$column}` <=> $escapedValue)";
            }

            $condition = 'NOT (' . implode(' OR ', $conditions) . ')';

            $query = <<<EOS
SELECT
    DISTINCT(`{$column}`)
    FROM `{$schema}`.`{$table}`
    WHERE {$condition}
    ORDER BY RAND()
    LIMIT {$limit}
EOS;
        }

        return $this->database->selectColumn($query);
    }

    public function selectEmptyNumericValues($schema, $table, $column)
    {
        $query = <<<EOS
SELECT
    DISTINCT `{$column}`
    FROM `{$schema}`.`{$table}`
    WHERE
        (`{$column}` IS NULL) OR
        (`{$column}` = 0)
EOS;

        return $this->database->selectColumn($query);
    }

    public function selectExtrema($schema, $table, $column)
    {
        $query = <<<EOS
SELECT
    MIN(`{$column}`) AS `min`,
    MAX(`{$column}`) AS `max`
    FROM `{$schema}`.`{$table}`
EOS;

        $rows = $this->database->select($query);

        $min = &$rows[0]['min'];
        $max = &$rows[0]['max'];

        return array($min, $max);
    }

    // End Sampler Queries
}