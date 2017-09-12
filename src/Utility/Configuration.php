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

namespace Datto\DatabaseAnalyzer\Utility;

use Exception;

class Configuration
{
    /** @var array */
    private $configuration;

    /**
     * @param string $raw
     */
    public function __construct($raw)
    {
        $this->getConfiguration($raw);
    }

    /**
     * Checks that the given configuration contents
     * are valid, and storing the results in
     * $this->configuration.
     *
     * @param string $contents
     * @throws Exception
     */
    private function getConfiguration($contents)
    {
        set_error_handler(array($this, 'handleWarnings'));
        try {
            $this->configuration = parse_ini_string($contents, false);
        } catch (Exception $exception) {
            $message = $exception->getMessage();

            throw new Exception('Invalid configuration file: ' . $message);
        }
        restore_error_handler();

        if (!is_array($this->configuration)) {
            $this->exception('error parsing configuration file.');
        }

        $this->expect('dbusername');
        $this->expect('dbpassword');
        $this->expect('host');
        $this->expect('port', 'integer');


        $this->expect('tableCatalog');
        $this->expect('cachePath');
        $this->expect('largeTablesPath');
        $this->expectArrayIfExists('whitelist');
        $this->expectArrayIfExists('importantTables');
        $this->expect('maximumOpenSamplers', 'integer');
        $this->expect('samplesPerColumn', 'integer');
        $this->expect('triesPerSample', 'integer');
        $this->expect('maximumRowsInEasyQuery', 'integer');

        $tables = &$this->configuration['importantTables'];
        if (!isset($tables)) {
            return;
        }
        foreach ($tables as $key => &$value) {
            $value = explode(',', $value);
        }
    }

    /**
     * Throws an error if the given key does not exist
     * in $this->configuration, or if the associated value
     * is not of the expected type.
     *
     * @param string $name
     * @param string $type
     */
    private function expect($name, $type = 'string')
    {
        $array = &$this->configuration;

        if (!key_exists($name, $array)) {
            $this->exception("setting \"{$name}\" not found");
        }

        $value = &$array[$name];
        if (!isset($value) || $value == "") {
            $this->exception("setting \"{$name}\" is not defined");
        }

        if ($type == 'integer') {
            if (!is_numeric($value)) {
                $this->exception("setting \"{$name}\" must be an integer");
            }
            try {
                $array[$name] = intval($value);
            } catch (Exception $exception) {
                $this->exception("setting \"{$name}\" must be an integer");
            }
        }

        if (gettype($value) != $type) {
            $this->exception("setting \"{$name}\" must be of type {$type}");
        }
    }

    private function expectArrayIfExists($name)
    {
        $array = &$this->configuration;

        if (array_key_exists($name, $array)) {
            if (gettype($array[$name]) != 'array') {
                throw new Exception("setting \"{$name}\" must be an array");
            }
        } else {
            $array[$name] = array();
        }
    }

    /**
     * Error handler function that raises warnings/errors into exceptions
     */
    private function handleWarnings($number, $string, $file, $line, array $context)
    {
        if ($number == E_WARNING) {
            throw new Exception($string);
        }
    }

    private function exception($message)
    {
        throw new Exception('Invalid configuration.ini file: ' . $message);
    }

    public function getSetting($name)
    {
        if (!array_key_exists($name, $this->configuration)) {
            throw new Exception("Setting {$name} not found");
        }

        return $this->configuration[$name];
    }
}