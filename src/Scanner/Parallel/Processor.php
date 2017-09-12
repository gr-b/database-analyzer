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

use SpencerMortensen\ParallelProcessor\Processor as OldProcessor;

class Processor
{
    /** @var Processor */
    private $processor;

    /** @var integer */
    private $nextId;

    public function __construct($maximumBlockingSeconds = null)
    {
        $this->processor = new OldProcessor($maximumBlockingSeconds);
        $this->nextId = 0;
    }

    public function startJob(Job $job)
    {
        $id = $this->nextId;
        $this->nextId++;

        $this->processor->startJob($id, $job);
    }

    public function collectAll()
    {
        $collected = array();

        while ($this->processor->getResult($id, $result)) {
        	$collected[] = unserialize($result);
        }

        return $collected;
    }
}