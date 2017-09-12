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

namespace Datto\DatabaseAnalyzer\Scanner\Parallel;

use Datto\DatabaseAnalyzer\Database\Database;
use Datto\DatabaseAnalyzer\Database\DatabaseQueries;
use Datto\DatabaseAnalyzer\Database\Mysql;
use Datto\DatabaseAnalyzer\Utility\Configuration;
use Datto\DatabaseAnalyzer\Scanner\Sampler;

class SamplerJob extends Job
{
    /** @var Configuration */
    private $settings;

    /** @var string */
    private $schema;

    /** @var string */
    private $table;

    /** @var string */
    private $column;

    /** @var string */
    private $type;

    /** @var string|null */
    private $index;

    /** @var array */
    private $sample;

    public function __construct($settings, $schema, $table, $column, $type, $index)
    {
        $this->settings = $settings;

        $this->schema = $schema;
        $this->table = $table;
        $this->column = $column;
        $this->type = $type;
        $this->index = $index;
    }

    public function start()
    {
        $mysql = new Mysql($this->settings);
        $pdo = $mysql->getPdo();
        $sampler = new Sampler(new DatabaseQueries(new Database($pdo)), $this->settings);

        if ($this->index == null) {
            $this->sample = $sampler->sample($this->schema, $this->table, $this->column, $this->type);
        } else {
            $this->sample = $sampler->sampleLarge($this->schema, $this->table, $this->column, $this->index);
        }
        unset($sampler);
    }

    public function __toString()
    {
        return "[Sampler|`{$this->schema}`.`{$this->table}`.`{$this->column}`]";
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getColumn()
    {
        return $this->column;
    }

    public function getSample()
    {
        return $this->sample;
    }
}
