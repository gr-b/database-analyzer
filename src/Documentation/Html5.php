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

namespace Datto\DatabaseAnalyzer\Documentation;

class Html5
{
    /// BEGIN DEPRECATED ///

    public static function getElement($element, $innerHtml, $attributes = array())
    {
        $attributesText = '';

        foreach ($attributes as $name => $value) {
            $safeValue = self::html5EncodeAttribute($value);
            $attributesText .= " {$name}=\"{$safeValue}\"";
        }

        return "<{$element}{$attributesText}>{$innerHtml}</{$element}>";
    }

    public static function getUlFlat($elementsHtml)
    {
        $html = "<ul>\n";

        foreach ($elementsHtml as $elementInnerHtml) {
            $html .= "\t<li>{$elementInnerHtml}</li>\n";
        }

        $html .= "</ul>";

        return $html;
    }

    /// END DEPRECATED ///

    public static function getText($string)
    {
        if ($string === null) {
            return '&nbsp;';
        }

        return htmlspecialchars($string, ENT_HTML5 | ENT_NOQUOTES | ENT_DISALLOWED, 'UTF-8');
    }

    public static function getA($innerHtml, $attributes = array())
    {
        return self::getElement('a', $innerHtml, $attributes);
    }

    public static function getUl($elementsHtml, $attributes = array())
    {
        return self::getListElement('ul', $elementsHtml, $attributes);
    }

    public static function getLi($innerHtml, $attributes = array())
    {
        return self::getElement('li', $innerHtml, $attributes);
    }

    public static function getDl($elementsHtml, $attributes = array())
    {
        return self::getListElement('dl', $elementsHtml, $attributes);
    }

    public static function getDt($innerHtml, $attributes = array())
    {
        return self::getElement('dt', $innerHtml, $attributes);
    }

    public static function getDd($innerHtml, $attributes = array())
    {
        return self::getElement('dd', $innerHtml, $attributes);
    }

    private static function getListElement($name, $elementsHtml, $attributes)
    {
        $innerHtml = "\n\t" . implode("\n\t", $elementsHtml) . "\n";

        return self::getElement($name, $innerHtml, $attributes);
    }

    private static function html5EncodeAttribute($string)
    {
        return htmlspecialchars($string, ENT_HTML5 | ENT_COMPAT | ENT_DISALLOWED, 'UTF-8');
    }
}
