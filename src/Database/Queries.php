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

namespace Datto\DatabaseAnalyzer\Database;

interface Queries
{
    // Begin Mapper Queries

    public function queryForeignKeys($schema);

    public function getSchemas();

    public function selectColumns($schema, $catalog);

    public function selectIndices($schema, $catalog);

    public function selectCardinalities($schema, $catalog);

    public function selectUniquenessConstraints($schema, $catalog);

    public function selectTableComments($schema, $catalog);
    // End Mapper Queries


    // Begin Sampler Queries

    public function selectRandom($schema, $table, $column, $key);

    public function countDistinctValues($schema, $table, $column);

    public function selectDistinctValues($schema, $table, $column);

    public function selectEmptyStringValues($schema, $table, $column);

    public function selectRandomValues($schema, $table, $column, $limit, $exclude = null);

    public function selectEmptyNumericValues($schema, $table, $column);

    public function selectExtrema($schema, $table, $column);

    // End Sampler Queries
}