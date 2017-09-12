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

class Filesystem
{
    public function readDirectory($directoryPath)
    {
        @$directoryHandle = opendir($directoryPath);

        if (!is_resource($directoryHandle)) {
            return null;
        }

        $files = array();

        while (false !== ($childName = readdir($directoryHandle))) {
            if (($childName === '.') || ($childName === '..')) {
                continue;
            }

            $childPath = "{$directoryPath}/{$childName}";

            if (is_dir($childPath)) {
                $value = $this->readDirectory($childPath);
            } else {
                $value = $childName;
            }

            $files[] = $value;
        }

        closedir($directoryHandle);

        return $files;
    }

    public function relative($path)
    {
        if (!isset($path)) {
            return $path;
        }

        if ($path[0] == '.') {
            return __DIR__ . 'Filesystem.php/' .$path;
        } else {
            return $path;
        }
    }

    public function read($path)
    {
        $contents = @file_get_contents($path);

        if (!is_string($contents)) {
            return null;
        }

        return $contents;
    }

    public function write($path, $data)
    {
        $directory = dirname($path);

        if (!file_exists($directory)) {
            mkdir($directory, 0775, true);
        }

        $lengthWritten = file_put_contents($path, $data);

        return $lengthWritten === strlen($data);
    }
}