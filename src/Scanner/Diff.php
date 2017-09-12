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

/**
 * Class Diff
 * @package Datto\DatabaseAnalyzer\Database
 *
 * Responsible for denoting which columns of the given scan1 must be resampled.
 */
class Diff
{
    /**
     * Transfers any values in columns in the given old output
     * to the values fields of the new output.
     * @param Scan $old
     * @param Scan $new
     * @return Scan
     */
    public static function transferOldValues(Scan $old, Scan $new)
    {
        $old = $old->getScan();
        $new = $new->getScan();

        // Look through $new. Wherever we find a null value, try to get that from $old
        foreach ($new as $schema => $schemaDefinition) {
            foreach ($schemaDefinition as $table => $tableDefinition) {
                foreach ($tableDefinition['columns'] as $column => $columnDefinition) {
                    if($columnDefinition['values'] == null) {

                        $oldColumn = &$old[$schema][$table]['columns'][$column];

                        if(isset($oldColumn['values']) && !self::columnsDifferent($oldColumn, $columnDefinition)) {
                            $new[$schema][$table]['columns'][$column]['values'] = $oldColumn['values'];
                        }

                    }
                }
            }

        }


        return new Scan($new);
    }

    /**
     * Determines whether, apart from the "values" field,
     * the given associative arrays are different.
     * @param array $oldColumn
     * @param array $newColumn
     * @return bool
     */
    private static function columnsDifferent($oldColumn, $newColumn)
    {
        if ($oldColumn == null || $newColumn == null) {
            return true;
        }
        unset($oldColumn['values']);
        unset($newColumn['values']);
        $diff = self::array_diff_recursive($oldColumn, $newColumn);
        return $diff != array();
    }

    /**
     * Produces any part of of $a that is not in $b.
     *
     * @param array $a
     * @param array $b
     *
     * @return array
     */
    public static function array_diff_recursive(&$a, &$b)
    {
        if (!is_array($a)) {
            return ($a !== $b ? (is_null($a) && is_null($b) ? array() : $a) : array());
        }
        $diff = array();
        foreach ($a as $key => $value) {
            if (!isset($b[$key]) && !is_null($value)) { // If $a has an element, but $b does not
                $diff[$key] = $value;
            } else { // If $a has an element that $b also has.
                $innerDiff = self::array_diff_recursive($a[$key], $b[$key]);
                if (is_array($innerDiff)) {
                    $values = array_values($innerDiff);
                    if (!empty($values)) {
                        $diff[$key] = $innerDiff;
                    }
                } else {
                    $diff[$key] = $innerDiff;
                }
            }
        }
        return $diff;
    }
}
