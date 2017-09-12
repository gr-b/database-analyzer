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

use Exception;

/**
 * Class Scan
 *
 * This class validates a given scan1 associative array,
 * ensuring that the structure of the array matches the
 * expected format. This does not guarantee that the
 * relations between columns and tables are valid,
 * just that the structure of each schema, table, and
 * column are correct.
 *
 * @package Datto\DatabaseAnalyzer\Scanner
 */
class Scan
{
    /** @var array */
    private $scan;

    /** @var  array */
    private $currentColumnDefinition;

    /** @var string */
    private $currentColumn;

    public function __construct(array $scan)
    {
        $this->validate($scan);
        $this->scan = $scan;
    }

    public function getScan()
    {
        return $this->scan;
    }

    public function getSchemas()
    {
        return array_keys($this->scan);
    }

    public function getTables($schema)
    {
        if(!array_key_exists($schema, $this->scan)) {
            throw new Exception("Schema not found in scan1: {$schema}");
        }
        return array_keys($this->scan[$schema]);
    }

    /**
     * Validates the object's scan1 array.
     * Throws an exception if the object is invalid.
     *
     * A valid scan1 array is of the form:
     * array(
     *      schema => array(
     *              table => array(
     *                  'columns' => array(
     *                      column => array(
     *                          'type' => (string),
     *                          'values' => array( ... ),
     *                          'isNullable' => (boolean),
     *                          'default' => (string|null),
     *                          'comment' => (string|null),
     *                          'extra' => (string|null)
     *                      ),
     *                      ...
     *                  ),
     *                  'indices' => array(
     *                      array( (string)... )
     *                      ...
     *                  ),
     *                  'cardinality' => (int),
     *                  'unique' => array(
     *                      array( (string)... )
     *                      ...
     *                  )
     *                  'foreign_keys' => array(
     *                      array(
     *                          'column' => (string)
     *                          'referenced_column' => (string)
     *                          'referenced_table' => (string)
     *                      ),
     *                      ...
     *                  )
     *              )
     *              ...
     *      )
     *      ...
     * );
     * @param array $scan
     * @throws Exception
     */
    private function validate(array $scan)
    {
        if (!isset($scan) || !is_array($scan) || count($scan) == 0) {
            throw new Exception("Invalid scan1: Scan must be an array with at least one schema.");
        }

        foreach ($scan as $schema => $schemaDefinition) {
            if (!is_array($schemaDefinition) || count($schemaDefinition) == 0) {
                throw new Exception("Invalid scan1: Schema {$schema} must have at least one table.");
            }

            $this->validateTables($scan, $schema, $schemaDefinition);
        }
    }

    private function validateTables($scan, $schema, $schemaDefinition)
    {
        foreach ($schemaDefinition as $table => $tableDefinition) {
            $tableDefinition = &$scan[$schema][$table];
            $this->validateColumns($scan, $schema, $table, $tableDefinition['columns']);
            unset($tableDefinition['columns']);

            $this->validateIndices($schema, $table, $tableDefinition['indices']);
            unset($tableDefinition['indices']);

            $this->validateKey($schema, $table, $tableDefinition, 'cardinality', 'integer');
            unset($tableDefinition['cardinality']);

            $this->validateUniques($schema, $table, $tableDefinition);
            unset($tableDefinition['unique']);

            $keys = &$tableDefinition['foreign_keys'];
            $this->validateForeignKeys($schema, $table, $keys);
            unset($tableDefinition['foreign_keys']);

            if (isset($tableDefinition['comment'])) {
                if (gettype($tableDefinition['comment']) != 'string'){
                    throw new Exception("Invalid scan1: table {$schema}=>{$table} has an invalid comment");
                }
                unset($tableDefinition['comment']);
            }

            if (count($tableDefinition) > 0) {
                throw new Exception("Invalid scan1: table {$schema}=>{$table} has unnecessary keys defined: " .
                    json_encode(array_keys($tableDefinition)));
            }
        }
    }

    private function validateColumns($scan, $schema, $table, &$columns)
    {
        if (!isset($columns) || !is_array($columns) || count($columns) == 0) {
            throw new Exception("Invalid scan1: {$schema}=>{$table} must have key " .
                "'columns' defined and have at least one column.");
        }

        foreach ($columns as $column => $columnDefinition) {
            $this->currentColumn = $column;
            $this->currentColumnDefinition = $columnDefinition;

            if (!isset($columnDefinition)) {
                throw new Exception("Invalid scan1: {$schema}=>{$table}=>'columns'=>{$column} has no definition");
            }

            $type = &$scan[$schema][$table]['columns'][$column]['type'];
            $this->isValidType($schema, $table, $column, $type);


            $this->validateColumnField('values', array('array', 'NULL'));
            $this->validateColumnField('isNullable', array('boolean'));
            $this->validateColumnField('default', array('string', 'NULL'));
            $this->validateColumnField('comment', array('string', 'NULL'));
            $this->validateColumnField('extra', array('string', 'NULL'));
            $this->currentColumn = null;
            $this->currentColumnDefinition = null;
        }
    }

    private function validateColumnField($name, $types)
    {
        $field = &$this->currentColumnDefinition[$name];
        if (!in_array(gettype($field), $types)) {
            throw new Exception("Invalid scan1: column '{$this->currentColumn}' must have field '{$name}' of type {$types[0]}");
        }
    }

    private function validateIndices($schema, $table, &$indices)
    {
        if (!isset($indices)) {
            return; // Indices are optional
        }
        if (!is_array($indices)) {
            throw new Exception("Invalid scan1: {$schema}=>{$table} must have key 'indices' defined as an array.");
        }

        foreach ($indices as $name => $columns) {
            if (!is_array($columns)) {
                throw new Exception("Invalid scan1: {$schema}=>{$table}=>'indices' must only hold arrays, got '" . json_encode($columns) . "'.");
            }
            foreach ($columns as $column) {
                if (!is_string($column)) {
                    throw new Exception("Invalid scan1: {$schema}=>{$table}=>'indices' field must" .
                        " hold an array of arrays of strings.");
                }
            }
        }
    }

    private function validateKey($schema, $table, $tableDefinition, $key, $type)
    {
        $keyReference = &$tableDefinition[$key];
        if (!isset($keyReference)) {
            return;
        }
        if (gettype($keyReference) != $type) {
            throw new Exception("Invalid scan1: {$schema}=>{$table} must have key '{$key}' defined and be type {$type}.");
        }
    }


    private function validateUniques($schema, $table, $tableDefinition)
    {
        $keyReference = &$tableDefinition['unique'];
        if (!isset($keyReference)) {
            return; // Uniques are optional
        }

        $this->validateKey($schema, $table, $tableDefinition, 'unique', 'array');
        foreach ($tableDefinition['unique'] as $unique) {
            if (gettype($unique) != 'array' || empty($unique)) {
                throw new Exception("Invalid scan1: {$schema}=>{$table}=>'unique' field must" .
                    " hold an array of arrays of strings.");
            }
            foreach ($unique as $column) {
                if (!is_string($column)) {
                    throw new Exception("Invalid scan1: {$schema}=>{$table}=>'unique' field must" .
                        " hold an array of arrays of strings.");
                }
            }
        }
    }


    private function validateForeignKeys($schema, $table, $keys)
    {
        if (!isset($keys)) {
            return; // Foreign keys are optional
        }
        if (gettype($keys) != 'array'|| empty($keys)) {
            throw new Exception("Invalid scan1: {$schema}=>{$table}=>'foreign_keys' must be an array of arrays.");
        }

        foreach ($keys as $key) {
            if (gettype($key) != 'array') {
                throw new Exception("Invalid scan1: {$schema}=>{$table}=>'foreign_keys' must be an array of arrays.");
            }

            $this->validateForeignKeyField($schema, $table, $key, 'column', 'string');
            $this->validateForeignKeyField($schema, $table, $key, 'referenced_table', 'string');
            $this->validateForeignKeyField($schema, $table, $key, 'referenced_column', 'string');
        }
    }

    private function validateForeignKeyField($schema, $table, $key, $fieldname, $type)
    {
        if (!isset($key[$fieldname]) || gettype($key[$fieldname]) != $type) {
            throw new Exception("Invalid scan1: {$schema}=>{$table}=>'foreign_keys' must have key '{$fieldname}' which must be of type {$type}");
        }
    }


    private function isValidType($schema, $table, $column, $type)
    {
        if ($type == null) {
            throw new Exception("Invalid scan1: {$schema}=>{$table}=>'columns'=>{$column}=>'type' must be a valid MySQL type, got null");
        }
        if (gettype($type) != 'string') {
            throw new Exception("Invalid scan1: {$schema}=>{$table}=>'column'=>'type' must be a valid MySQL type, got {$type}");
        }

        $validTypes = array(
            'TINYINT',
            'SMALLINT',
            'MEDIUMINT',
            'INT',
            'BIGINT',
            'FLOAT',
            'DOUBLE',
            'BINARY',
            'VARBINARY',
            'TINYBLOB',
            'MEDIUMBLOB',
            'BLOB',
            'LONGBLOB',
            'CHAR',
            'VARCHAR',
            'TINYTEXT',
            'MEDIUMTEXT',
            'TEXT',
            'LONGTEXT',
            'BIT',
            'DECIMAL',
            'NUMERIC',
            'DATE',
            'DATETIME',
            'TIMESTAMP',
            'TIME',
            'YEAR',
            'ENUM',
            'SET',
            'DEC',
            'NUMERIC',
            'FIXED',
            'REAL',
            'BOOL',
            'BOOLEAN',
            'DOUBLE PRECISION'

        );

        if (!in_array($type, $validTypes)) {
            throw new Exception("Invalid scan1: {$schema}=>{$table}=>'column'=>'type' must be a valid MySQL type, got {$type}");
        }
    }
}