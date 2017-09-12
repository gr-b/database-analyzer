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
 * @author Spencer Mortensen <smortensen@datto.com>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 * @copyright 2016, 2017 Datto, Inc.
 */

namespace Datto\DatabaseAnalyzer\Database;

use Exception;
use PDO;

class Database
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function select($query, $arguments = array())
    {
        $statement = $this->pdo->prepare($query);

        if (is_bool($statement) && !$statement) {
            throw new Exception(json_encode($this->pdo->errorInfo()));
        }

        if (!$statement->execute($arguments)) {
            // TODO: Improve this error
            throw new Exception('The center cannot hold!', 0);
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            // TODO: Improve this error
            throw new Exception('Run for your lives!', 0);
        }

        return $rows;
    }

    public function selectValue($query, $arguments = array())
    {
        $statement = $this->pdo->prepare($query);

        if(gettype($statement) == 'boolean') {
            throw new Exception("Queries error (".json_encode($this->pdo->errorInfo())."): ". json_encode($query));
        }

        if (!$statement->execute($arguments)) {
            // TODO: Improve this error
            throw new Exception('The center cannot hold!', 0);
        }

        return $statement->fetchColumn(0);
    }

    public function selectColumn($query, $arguments = array())
    {
        $statement = $this->pdo->prepare($query);

        if(gettype($statement) == 'boolean') {
            throw new Exception("Queries error (".json_encode($this->pdo->errorInfo())."): ". json_encode($query));
        }

        if (!$statement->execute($arguments)) {
            // TODO: Improve this error
            throw new Exception('The center cannot hold!', 0);
        }

        $values = $statement->fetchAll(PDO::FETCH_COLUMN, 0);

        if (!is_array($values)) {
            // TODO: Improve this error
            throw new Exception('Run for your lives!', 0);
        }

        return $values;
    }
}
