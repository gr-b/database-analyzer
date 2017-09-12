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

namespace Datto\DatabaseAnalyzer\Scanner;

use Datto\DatabaseAnalyzer\Database\Queries;
use Datto\DatabaseAnalyzer\Utility\Logger;
use Exception;

class Mapper
{
    /** @var Queries */
    private $queries;

    /** @var string */
    private $tableCatalog;

    /** @var string */
    private $tableSchema;

    /** @var Logger */
    private $logger;

    public function __construct(Queries $queries, $tableCatalog, Logger $logger)
    {
        $this->queries = $queries;
        $this->tableCatalog = $tableCatalog;
        $this->logger = $logger;
    }

    /**
     * Maps all schemas in the database.
     *
     * If there exists a "whitelist" file,
     * only map the schemas located in the file.
     *
     * @param array $whitelist
     * If a blank whitelist is given, output all schemas.
     *
     * @throws Exception
     *
     * @return Scan
     */
    public function map($whitelist)
    {
        // Check if there is any schema in the whitelist
        // that isn't also in the schema list.
        $this->logger->info("Getting schema list...");
        $schemaList = $this->getSchemaList();
        if (empty($whitelist)) {
            $whitelist = $schemaList;
            $this->logger->info("Whitelist is empty: scanning all schemas.");
        }

        foreach ($whitelist as $schema) {
            if (!in_array($schema, $schemaList)) {
                $this->logger->warn("Whitelisted schema {$schema} not found in the database, ignoring it.");
            }
        }

        $this->logger->info("Mapping schemas ".json_encode($whitelist));

        $map = array();

        foreach ($whitelist as $schema) {
            $this->logger->info("Started mapping {$schema}...");
            $map[$schema] = $this->mapSchema($schema);
            if (empty($map[$schema])) {
                throw new Exception("Mapper Error: schema {$schema} has no tables.");
            }
        }

        return new Scan($map);
    }

    private function mapSchema($schemaName)
    {
        $this->tableSchema = $schemaName;

        $map = array();

        $this->getColumns($map);
        $this->getIndices($map);
        $this->getCardinalities($map);
        $this->getUniquenessConstraints($map);
        $this->getTableComments($map);
        $this->getForeignKeys($map);

        return $map;
    }

    private function getSchemaList()
    {
        $schemas = $this->queries->getSchemas();

        $schemaList = array();

        foreach ($schemas as $row) {
            $schema = $row['schema'];
            $schemaList[] = $schema;
        }

        if (empty($schemaList)) {
            throw new Exception("Mapper error: Database has no schemas.");
        }

        return $schemaList;
    }

    private function getForeignKeys(&$map)
    {
        $keys = $this->queries->queryForeignKeys($this->tableSchema);

        foreach ($keys as $key) {
            $table = $key['table_name'];
            $column = $key['column_name'];
            $referenced_table = $key['referenced_table_name'];
            $referenced_column = $key['referenced_column_name'];

            $foreign_keys = &$map[$table]['foreign_keys'];
            if (!isset($foreign_keys)) {
                $foreign_keys = array();
            }

            $foreign_keys[] = array(
                'column' => $column,
                'referenced_table' => $referenced_table,
                'referenced_column' => $referenced_column,
            );
        }
    }

    private function getColumns(&$map)
    {
        $rows = $this->queries->selectColumns($this->tableSchema, $this->tableCatalog);

        foreach ($rows as $row) {
            $table = $row['table'];
            $name = $row['name'];
            $type = $row['type'];
            $values = self::getKnownValues($row['detailedType']);
            $isNullable = (bool)$row['isNullable'];
            $default = $row['default'];
            $comment = self::getNonEmptyString($row['comment']);
            $extra = self::getNonEmptyString($row['extra']);

            $map[$table]['columns'][$name] = array(
                'type' => $type,
                'values' => $values,
                'isNullable' => $isNullable,
                'default' => $default,
                'comment' => $comment,
                'extra' => $extra
            );
        }
    }

    public static function getKnownValues($type)
    {
        $pattern = '~^(?:enum|set)\\(\'(.*)\'\\)$~';

        if (preg_match($pattern, $type, $matches) !== 1) {
            return null;
        }

        // TODO: Parse MySQL string literals
        // See: http://dev.mysql.com/doc/refman/5.7/en/string-literals.html
        $values = explode("','", $matches[1]);
        sort($values, SORT_NATURAL);

        return $values;
    }

    private function getIndices(&$map)
    {
        $rows = $this->queries->selectIndices($this->tableSchema, $this->tableCatalog);

        foreach ($rows as $row) {
            $table = $row['table'];
            $index = $row['index'];
            $column = $row['column'];
            
            $indices = &$map[$table]['indices'];
            if (!isset($indices)) {
                $indices = array();
            }

            $keys = &$indices[$index];
            if(!isset($keys)) {
                $keys = array();
            }
            $keys[] = $column;
        }
    }

    private function getCardinalities(&$map)
    {
        $rows = $this->queries->selectCardinalities($this->tableSchema, $this->tableCatalog);

        foreach ($rows as $row) {
            $table = $row['table'];
            $cardinality = intval($row['cardinality']);

            $cardinalityReference = &$map[$table]['cardinality'];
            if (!isset($cardinalityReference)) {
                $cardinalityReference = $cardinality;
            } elseif ($cardinalityReference < $cardinality) {
                $cardinalityReference = $cardinality;
            }
        }
    }

    private function getUniquenessConstraints(&$map)
    {
        $rows = $this->queries->selectUniquenessConstraints($this->tableSchema, $this->tableCatalog);

        foreach ($rows as $row) {
            $id = $row['id'];
            $table = $row['table'];
            $column = $row['column'];

            $map[$table]['unique'][$id][] = $column;
        }

        foreach ($map as $table => &$definition) {
            if (isset($definition['unique'])) {
                $definition['unique'] = array_values($definition['unique']);
            }
        }
    }

    private static function getNonEmptyString($input)
    {
        if (!is_string($input)) {
            return null;
        }

        $input = trim($input);

        if (strlen($input) === 0) {
            return null;
        }

        return $input;
    }

    private function getTableComments(&$map)
    {
        $rows = $this->queries->selectTableComments($this->tableSchema, $this->tableCatalog);

        foreach ($rows as $row) {
            $table = $row['name'];
            $map[$table]['comment'] = $row['comment'];
        }
    }
}
