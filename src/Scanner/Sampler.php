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
 * @author Spencer Mortensen <smortensen@datto.com>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 * @copyright 2016, 2017 Datto, Inc.
 */

namespace Datto\DatabaseAnalyzer\Scanner;

use Datto\DatabaseAnalyzer\Database\Queries;
use Datto\DatabaseAnalyzer\Utility\Configuration;

class Sampler
{
    /** @var integer
     * The number of data points to retrieve from each column.
     */
    private $SAMPLES_PER_COLUMN = 8;
    /** @var integer
     * The minimum number of data points to sample.
     * This is only applicable in certain cases where the column
     * being sampled is too large to sample normally.
     */
    private $MINIMUM_SAMPLE_SIZE = 4;

    private $TRIES_PER_SAMPLE = 4;

    /** @var Queries */
    private $queries;

    /** @var string */
    private $tableCatalog;

    /** @var string */
    private $tableSchema;

    public function __construct(Queries $queries, Configuration $settings)
    {
        $this->queries = $queries;
        $this->tableCatalog = $settings->getSetting('tableCatalog');

        $this->SAMPLES_PER_COLUMN = $settings->getSetting('samplesPerColumn');
        $this->TRIES_PER_SAMPLE = $settings->getSetting('triesPerSample');
    }

    /**
     * This function is called when the table has too many rows to do
     * a full table output. Our sampling methods are constrained by this:
     * For non-string values, we can get extrema.
     * For the rest of the values, we have to sample randomly.
     * @param string $schema
     * @param string $table
     * @param string $column
     * @param string $key
     * The primary key associated with the table in question.
     * The name of the primary key column
     * @return array
     */
    public function sampleLarge($schema, $table, $column, $key)
    {
        $this->tableSchema = $schema;

        $values = array();

        $tries = 0;
        while (count($values) < $this->SAMPLES_PER_COLUMN &&
            $tries < $this->TRIES_PER_SAMPLE*$this->SAMPLES_PER_COLUMN) {

            $selection = $this->queries->selectRandom($this->tableSchema, $table, $column, $key);
            if (!in_array($selection, $values)) {
                $values[] = $selection;
            }
            $tries++;
        }
        return $values;
    }

    public function sample($schema, $table, $column, $type)
    {
        $this->tableSchema = $schema;

        $count = $this->queries->countDistinctValues($this->tableSchema, $table, $column);

        if ($count <= $this->SAMPLES_PER_COLUMN) {
            return $this->selectAllKnownValues($table, $column, $type);
        } else {
            return $this->selectInterestingValues($table, $column, $type);
        }
    }

    private function selectAllKnownValues($table, $column, $type)
    {
        $values = $this->queries->selectDistinctValues($this->tableSchema, $table, $column);
        $values = self::castElements($values, $type);
        return self::getAllKnownValues($values);
    }

    private static function getAllKnownValues($values)
    {
        array_unshift($values, 'ALL');

        return $values;
    }

    private static function getSomeKnownValues($values)
    {
        array_unshift($values, 'SOME');

        return $values;
    }

    private static function castElements($array, $type)
    {
        switch ($type) {
            case 'integer':
                return array_map('self::toInteger', $array);

            case 'float':
                return array_map('self::toFloat', $array);

            default:
                return $array;
        }
    }

    protected static function toInteger($value)
    {
        if ($value === null) {
            return null;
        }

        return (int)$value;
    }

    protected static function toFloat($value)
    {
        if ($value === null) {
            return null;
        }

        return (float)$value;
    }

    private function selectInterestingValues($table, $column, $type)
    {
        if ($type === 'string') {
            return $this->selectInterestingStringValues($table, $column);
        } else {
            return $this->selectInterestingNumericValues($table, $column, $type);
        }
    }

    private function selectInterestingStringValues($table, $column)
    {
        $emptyValues = $this->queries->selectEmptyStringValues($this->tableSchema, $table, $column);
        sort($emptyValues, SORT_STRING);

        $exclude = $emptyValues;
        $limit = $this->SAMPLES_PER_COLUMN - count($emptyValues);
        if ($limit < 0) {
            $limit = $this->MINIMUM_SAMPLE_SIZE;
        }
        $randomValues = $this->queries->selectRandomValues($this->tableSchema, $table, $column, $limit, $exclude);
        sort($randomValues, SORT_NATURAL);

        $values = array_merge($emptyValues, $randomValues);

        return self::getSomeKnownValues($values);
    }

    private function selectInterestingNumericValues($table, $column, $type)
    {
        $emptyValues = $this->queries->selectEmptyNumericValues($this->tableSchema, $table, $column);
        $extrema = $this->queries->selectExtrema($this->tableSchema, $table, $column);
        $interestingValues = array_values(array_unique(array_merge($emptyValues, $extrema)));

        $exclude = $interestingValues;
        $limit = $this->SAMPLES_PER_COLUMN - count($exclude);
        $randomValues = $this->queries->selectRandomValues($this->tableSchema, $table, $column, $limit, $exclude);

        $values = array_merge($interestingValues, $randomValues);
        $values = self::castElements($values, $type);
        usort($values, 'self::compareNumbers');

        return self::getSomeKnownValues($values);
    }

    protected static function compareNumbers($a, $b)
    {
        if (($a === null) && ($b === null)) {
            return 0;
        }

        if ($a === null) {
            return -1;
        }

        if ($b === null) {
            return 1;
        }

        return $a - $b;
    }
}