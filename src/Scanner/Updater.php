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

use Datto\DatabaseAnalyzer\Scanner\Parallel\Processor;
use Datto\DatabaseAnalyzer\Scanner\Parallel\SamplerJob;
use Datto\DatabaseAnalyzer\Utility\Configuration;
use Datto\DatabaseAnalyzer\Utility\Filesystem;
use Datto\DatabaseAnalyzer\Utility\Logger;
use Exception;

class Updater
{
    /** @var integer
     * The maximum number of sampler processes that can be querying
     * the database at a given time.
     */
    private $MAXIMUM_OPEN_SAMPLERS = 4;

    /** @var integer
     * The columns with more than this number of rows will be sampled
     * using an alternative method that greatly increases speed while
     * sacrificing a small amount of randomness if the table has holes
     * in its primary key.
     */
    private $MAXIMUM_ROWS_IN_EASY_QUERY = 1000000;

    /** @var string */
    private $tableCatalog;

    /** @var string */
    private $tableSchema;

    /** @var Filesystem */
    private $filesystem;

    /** @var Configuration */
    private $settings;

    /** @var array */
    private $newScan;

    /** @var Processor */
    private $processor;

    /** @var integer */
    private $samplers;

    /** @var integer */
    private $samples;

    /** @var array */
    private $importantLargeTables;

    /** @var Logger */
    private $logger;

    /** @var array */
    private $largeTables;

    /** @var array */
    private $invalidTypes;

    /**
     * @param Filesystem $filesystem
     * @param Configuration $settings
     * @param Logger $logger
     */
    public function __construct(Filesystem $filesystem, Configuration $settings, Logger $logger)
    {
        $this->MAXIMUM_OPEN_SAMPLERS = $settings->getSetting('maximumOpenSamplers');
        $this->MAXIMUM_ROWS_IN_EASY_QUERY = $settings->getSetting('maximumRowsInEasyQuery');

        $this->filesystem = $filesystem;

        $this->tableCatalog = $settings->getSetting('tableCatalog');
        $this->whitelist = $settings->getSetting('whitelist');
        $this->importantLargeTables = $settings->getSetting('importantTables');
        $this->logger = $logger;

        $this->processor = new Processor();
        $this->samples = 0;

        $this->largeTables = array();
        $this->invalidTypes = array(
            'BINARY',
            'VARBINARY',
            'TINYBLOB',
            'MEDIUMBLOB',
            'BLOB',
            'LONGBLOB',
        );

        $this->settings = $settings;
    }

    /**
     * Updates the given old scan1 object to reflect the current status
     * of the database. Also samples 'values' fields from the given
     * scan1 object that are null.
     *
     * @param Scan $scan
     * An old scan1 that may differ from the current database.
     * Leave blank if no old output is available.
     * @throws Exception
     * @return Scan
     */
    public function update(Scan $scan)
    {
        $scan = $scan->getScan();
        $this->newScan = $scan;

        foreach ($this->whitelist as $schema) {
            $this->tableSchema = $schema;
            if (!isset($scan[$schema])) {
                throw new Exception("Updater error: whitelisted schema '{$schema}' not found in scan1");
            }
            $this->sample($scan[$schema]);
        }

        $this->writeLargeTables();

        return new Scan($this->newScan);
    }

    /**
     * Scans the database for every column where field 'values' is null
     *
     * @param array $schemaDefinition
     * Produces output into the class's newScan variable
     * with all 'values' field filled in.
     */
    private function sample(array $schemaDefinition)
    {
        $tables = 0;
        $this->samplers = 0;
        $total = count($schemaDefinition);
        $start = microtime(true);

        $this->logger->info("There are {$total} tables to be sampled.");

        foreach ($schemaDefinition as $table => $tableDefinition) {
            foreach ($tableDefinition['columns'] as $column => $columnDefinition) {
                $this->sampleColumn($table, $column, $tableDefinition, $columnDefinition);
                $this->samplers++;
            }

            $tables++;
            $elapsed = microtime(true) - $start;
            if ($this->samples > 0) {
                $this->logger->info("Sampled {$tables} tables | {$this->samples} columns | Elapsed time: {$elapsed} seconds" .
                "| Samplers open: {$this->samplers} | Just finished {$table} ");
            }

            // Save the incremental progress
            $this->filesystem->write($this->settings->getSetting('cachePath'), json_encode($this->newScan, JSON_PRETTY_PRINT));
        }

        $this->collectOpenSamples();
        $this->logger->info("Sampled {$this->samples} columns in total.");
    }

    /**
     * Samples the given column
     *
     * @param string $table
     * @param string $column
     * @param array $tableDefinition
     * @param array $columnDefinition
     */
    private function sampleColumn($table, $column, array $tableDefinition, array $columnDefinition)
    {
        $values = &$columnDefinition['values'];
        $mysqlType = &$columnDefinition['type'];
        if (isset($values) || in_array($mysqlType, $this->invalidTypes)) {
            return;
        }

        $phpType = self::getPhpType($mysqlType);

        if ($this->samplers >= $this->MAXIMUM_OPEN_SAMPLERS) {
            $this->collectOpenSamples();
        }

        $cardinality = &$tableDefinition['cardinality'];
        if ($this->isHardQuery($cardinality)) {
            $this->openHardSampler($table, $column, $phpType, $tableDefinition);
        } else {
            $this->openSampler($table, $column, $phpType);
        }
    }

    /**
     * Determines whether the table has too many rows
     * to sample normally.
     * @param integer $cardinality
     * @return bool
     */
    private function isHardQuery($cardinality)
    {
        if (isset($cardinality) && $cardinality > $this->MAXIMUM_ROWS_IN_EASY_QUERY) {
            return true;
        }
        return false;
    }

    /**
     * Waits until all open samplers have completed, then collects
     * their output.
     * It would be faster to not wait until all samplers are
     * done and start more everytime we are below the cap.
     * However, this would create a situation where we have
     * all of our open samplers making very hard requests.
     * This would crashes the dbpool servers, depending on
     * the MAXIMUM_OPEN_SAMPLERS limit.
     */
    private function collectOpenSamples()
    {
        // Block until all Samplers are done.
        $results = $this->processor->collectAll();

        foreach ($results as $result) {
            /** @var SamplerJob $result */
            $schema = $result->getSchema();
            $table = $result->getTable();
            $column = $result->getColumn();
            $sample = $result->getSample();
            $this->newScan[$schema][$table]['columns'][$column]['values'] = $sample;
            $this->samples++;
        }
        $this->samplers = 0;
    }

    /**
     * Opens a process for a query on a table too large to sample
     * conventionally.
     *
     * @param string $table
     * @param string $column
     * @param string $phpType
     * @param array $tableDefinition
     */
    private function openHardSampler($table, $column, $phpType, array $tableDefinition)
    {
        $this->addLargeTable($this->tableSchema, $table);

        $importantTables = &$this->importantLargeTables[$this->tableSchema];
        if (isset($importantTables) && in_array($table, $importantTables)) {
            $this->logger->info("Sampling column {$column} from important table {$table} even though it is very large.");
            $this->openSampler($table, $column, $phpType);
            return;
        }

        $index = $this->getIndexColumn($tableDefinition);

        if ($index == null) {
            $this->logger->warn("No viable key found for large column `{$table}`.`{$column}` sampling normally");
            $this->logger->warn("Note: this may be very slow, and take up a large amount of block I/O");
            $this->openSampler($table, $column, $phpType); // Take the hit and sample normally.
            return;
        }


        $this->openSampler($table, $column, $phpType, $index);
    }

    /**
     * Opens a process that will sample the given
     * column.
     * @param string $table
     * @param string $column
     * @param string $phpType
     * @param string $index
     * For very large tables, an unique integer index
     * must be supplied in order to speed up the query.
     * Leave off for regular queries.
     */
    private function openSampler($table, $column, $phpType, $index = null)
    {
        $job = new SamplerJob($this->settings, $this->tableSchema, $table, $column, $phpType, $index);
        $this->processor->startJob($job);
    }

    /**
     * Produces a column name from the given table in the scan1
     * that would be suitable to scan1 a large table with.
     *
     * @param array $tableDefinition
     * @return string
     */
    private function getIndexColumn(array $tableDefinition)
    {
        $uniqueNumericIndices = $this->getUniqueNumericIndices($tableDefinition);
        if (empty($uniqueNumericIndices)) {
            return null;
        }

        // Prefer single column/fewer column indices
        $this->sortArraysByLength($uniqueNumericIndices);

        $column = &$uniqueNumericIndices[0][0];
        return (isset($column) ? $column : null );
    }

    /**
     * @param array $tableDefinition
     * @return array
     */
    private function getUniqueNumericIndices($tableDefinition)
    {
        $indices = $tableDefinition['indices'];
        $uniques = $tableDefinition['unique'];

        if (empty($indices) || empty($uniques)) {
            return array();
        }

        $uniqueNumericIndices = array();
        foreach ($indices as $index) {
            if (in_array($index, $uniques)) {
                $integer = true;
                foreach ($index as $column) {
                    $type = $tableDefinition['columns'][$column]['type'];
                    if (self::getPhpType($type) != 'integer') {
                        $integer = false;
                    }
                }
                if ($integer) {
                    $uniqueNumericIndices[] = $index;
                }
            }
        }
        return $uniqueNumericIndices;
    }

    /**
     * @param array $arrays
     */
    private function sortArraysByLength(array &$arrays)
    {
        $byLength =  function (array $a, array $b) {
            if (count($a) < count($b)) {
                return -1;
            } elseif (count($a) == count($b)) {
                return 0;
            } else {
                return 1;
            }
        };

        usort($arrays, $byLength);
    }

    public function writeScan($scan, $file)
    {
        $contents = json_encode($scan);
        file_put_contents($file, $contents);
    }

    private function writeLargeTables()
    {
        $empty = true;
        foreach ($this->largeTables as $schema => $tables) {
            if (!empty($tables)) {
                $empty = false;
                break;
            }
        }

        if (!$empty) {
            $path = $this->settings->getSetting('largeTablesPath');
            $this->filesystem->write($path, json_encode($this->largeTables, JSON_PRETTY_PRINT));
        }
    }

    private function addLargeTable($schema, $table)
    {
        $tables = &$this->largeTables[$schema];
        if (!isset($tables)) {
            $tables = array();
        }

        if (!in_array($table, $tables)) {
            $tables[] = $table;
        }
    }

    /**
     * Adds the given path to the given associative array.
     * Every non terminal element of the given path will
     * be a key in the return array.
     *
     * @param array $array
     * @param array $path
     * @return array
     */
    protected static function addPath(array $array, array $path)
    {
        $start = &$array;

        $length = count($path);
        for ($i = 0; $i < $length - 1; $i++) {
            $key = &$array[$path[$i]];
            if ($i < $length - 2) {
                if (!isset($key)) {
                    $key = array();
                } elseif (!is_array($key)) {
                    $key = array($key);
                }
                $array = &$key;
            } else {
                $key[] = $path[$i + 1];
                break;
            }
        }
        return $start;
    }

    private static function getPhpType($mysqlType)
    {
        switch ($mysqlType) {
            case 'TINYINT':
            case 'SMALLINT':
            case 'MEDIUMINT':
            case 'INT':
            case 'BIGINT':
                return 'integer';

            case 'FLOAT':
            case 'DOUBLE':
                return 'float';

            case 'BINARY':
            case 'VARBINARY':
            case 'TINYBLOB':
            case 'MEDIUMBLOB':
            case 'BLOB':
            case 'LONGBLOB':
                return 'binary';

            case 'CHAR':
            case 'VARCHAR':
            case 'TINYTEXT':
            case 'MEDIUMTEXT':
            case 'TEXT':
            case 'LONGTEXT':
            case 'BIT':
            case 'DECIMAL':
            case 'NUMERIC':
            case 'DATE':
            case 'DATETIME':
            case 'TIMESTAMP':
            case 'TIME':
            case 'YEAR':
                return 'string';

            default:
                $mysqlTypeName = var_export($mysqlType, true);
                trigger_error("Unknown type: {$mysqlTypeName}", E_USER_ERROR);
                return null;
        }
    }


}
