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

use Datto\DatabaseAnalyzer\Utility\Configuration;
use PDO;
use PDOException;

class Mysql
{
    private $dbusername;
    private $dbpassword;
    private $host;
    private $port;

    public function __construct(Configuration $settings)
    {
        $this->dbusername = $settings->getSetting('dbusername');
        $this->dbpassword = $settings->getSetting('dbpassword');
        $this->host = $settings->getSetting('host');
        $this->port = $settings->getSetting('port');
    }

    /**
     * @return PDO
     */
    public function getPdo()
    {
        $access = $this->getAccess();
        $pdo = self::createPdo($access);

        return $pdo;
    }

    private function getAccess()
    {
        return array(
            'dsn' => "mysql:host={$this->host};port={$this->port};charset=utf8",
            'user' => $this->dbusername,
            'pass' => $this->dbpassword
        );
    }

    private static function createPdo($access)
    {
        if ($access === null) {
            return null;
        }

        try {
            $options = array(
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            );

            $pdo = new PDO($access['dsn'], $access['user'], $access['pass'], $options);

            if ($pdo === false) {
                return null;
            }
        } catch (PDOException $e) {
            throw new \Exception("Error connecting to database. "
            . "Are you sure the database information is correct in configuration.ini? "
            . "PDO Error Message: " . $e->getMessage());
        }

        // Protect against an open transaction inherited through the persistent connection
        if ((bool)$pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return $pdo;
    }
}