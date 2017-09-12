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

namespace Datto\DatabaseAnalyzer\Documentation;

use Datto\DatabaseAnalyzer\Scanner\Scan;
use Datto\DatabaseAnalyzer\Utility\Configuration;
use Datto\DatabaseAnalyzer\Utility\Filesystem;

class Generator
{
    /**
     * The maximum number of characters to display
     * before including a visibility toggle on the rest.
     */
    const LARGE_SAMPLE_PREVIEW_LENGTH = 432;

    /**
     * The number of sampled values below which
     * a tooltip message will be displayed on the
     * samples.
     */
    const TOOLTIP_DISPLAY_THRESHOLD = 6;

    const TYPE_NULL = 0;
    const TYPE_BOOLEAN = 1;
    const TYPE_INTEGER = 2;
    const TYPE_FLOAT = 3;
    const TYPE_STRING = 4;

    /** @var string */
    private $outputDirectory;

    /** @var Filesystem */
    private $filesystem;

    /** @var Integer $toggleID */
    private $toggleID;

    public function __construct(Filesystem $filesystem, $outputDirectory)
    {
        $this->outputDirectory = $outputDirectory;
        $this->filesystem = $filesystem;
    }

    public function generate(Scan $scan)
    {
        shell_exec("rm -rf {$this->outputDirectory}");

        // Generate Schema index page.
        $schemas = $scan->getSchemas();
        $this->generateSchemaIndex($schemas);

        foreach ($scan->getScan() as $schema => $schemaDefinition) {
            $outputDirectory = $this->outputDirectory."/{$schema}";

            $pages = array();
            // Table of contents
            $index = self::getIndexTwig($schemaDefinition, $schema);
            $styleHTML = $this->getStyleHtml('../resources/style/page.css');
            $index = $this->getPageHTML($schema, $styleHTML, '../index.html', $index);
            $this->filesystem->write($this->outputDirectory."/{$schema}/index.html", $index);


            // Table pages
            $this->getTablesTwig($schemaDefinition, $pages);

            foreach ($pages as $table => $twig) {
                $styleHTML = $this->getStyleHTML('../../resources/style/page.css');
                $styleHTML .= $this->getStyleHTML('../../resources/style/table.css');
                $styleHTML .= $this->getStyleHTML('../../resources/style/expander.css');

                $html = $this->getPageHTML($table, $styleHTML, '../../index.html', $twig);
                $this->filesystem->write($this->outputDirectory."/{$schema}/tables/{$table}.html", $html);
            }
        }

        //echo "Output directory: {$this->outputDirectory}\n";
        shell_exec("cp -r resources {$this->outputDirectory}/resources");
    }

    private function generateSchemaIndex($schemas)
    {
        $schemasList = self::getSchemaListTwig($schemas);
        $styleHTML = $this->getStyleHTML('resources/style/page.css');

        $html = $this->getPageHTML('Schema Index', $styleHTML, 'index.html', $schemasList);
        $this->filesystem->write($this->outputDirectory.'/index.html', $html);
    }

    private function getPageHTML($title, $styleHTML, $indexPath, $linksHTML)
    {
        $html = "<!DOCTYPE html>

<html lang=\"en\">

<head>
	<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
	<title>{$title}</title>
	{$styleHTML}
</head>

<body>

<ul id=\"menu\"><li id=\"home\" class=\"here\"><a href=\"{$indexPath}\"><span class=\"company\">Database-Analyzer</span><span class=\"project\"> | Documentation</span></a></li></ul>

<h1>{$title}</h1>

{$linksHTML}

</body>

</html>
";
        return $html;
    }

    private static function getStyleHTML($path)
    {
        return Html5::getElement('link', '', array(
            'href' => $path,
            'rel' => 'stylesheet',
            'type' => 'text/css'
        ));
    }

    private static function getSchemaListTwig($schemas)
    {
        $links = array();

        foreach ($schemas as $schema) {
            $links[] = Html5::getLi(self::getSchemaLink($schema));
        }

        return Html5::getUl($links);
    }

    private static function getSchemaLink($schema)
    {
        $attributes = array(
            'href' => self::getSchemaUri($schema)
        );

        return Html5::getA(
            Html5::getText($schema),
            $attributes
        );
    }

    private function getTablesTwig($schema, &$pages)
    {
        foreach ($schema as $table => $properties) {
            $this->getTableTwig($schema, $table, $pages);
        }
    }

    private static function getIndexTwig($schema, $schemaName)
    {
        $links = array();

        foreach ($schema as $class => $definition) {
            $links[] = Html5::getLi(self::getTableLink($class, $schemaName));
        }

        return Html5::getUl($links);
    }

    private function getTableTwig($schema, $table, &$pages)
    {
        $pageTwig = &$pages[$table];

        $columnsTwig = array();

        $this->toggleID = 0;

        $tableTwig = '';
        $comment = &$schema[$table]['comment'];
        if (isset($comment)) {
            $tableTwig .= Html5::getElement('p', Html5::getText($comment));
        }

        foreach ($schema[$table]['columns'] as $column => $definition) {
            $columnsTwig[] = $this->getColumnTwig($schema, $table, $column);
        }

        $cardinality = &$schema[$table]['cardinality'];
        if (isset($cardinality)) {
            $tableTwig .= Html5::getElement('p', 'Cardinality: '.$cardinality);
        }

        $tableTwig .= implode("\n\n\n", $columnsTwig);

        $tableTwig .= self::getTableFooterTwig($schema, $table);

        $pageTwig = $tableTwig;
    }

    private function getColumnTwig($schema, $table, $column)
    {
        $definition = $schema[$table]['columns'][$column];

        $isNullable = $definition['isNullable'];
        $type = $definition['type'];
        $values = &$definition['values'];

        $elements = array();
        self::addColumnElement('description: ', $definition['comment'], $elements);


        $elements[] = Html5::getDt('type: ');
        $typeDescription = $this->getTypeDescription($isNullable, $type, $values);
        $elements[] = Html5::getDd($typeDescription, array("class" => "type"));

        self::addColumnElement('default: ', $definition['default'], $elements);
        self::addColumnElement('extras: ', $definition['extra'], $elements);

        return
            Html5::getElement('h2', Html5::getText($column)) . "\n\n" .
            Html5::getDl(
                $elements,
                array(
                    'class' => 'property'
                )
            );
    }

    private function getTypeDescription($isNullable, $type, $values)
    {
        $nullText = ($isNullable ? 'NULL | ' : '');

        $typeHtml = Html5::getElement('b', $nullText . $type);
        $typeDescription =  $typeHtml . ' ';
        $typeDescription .= $this->getExpanderHtml($values);
        return $typeDescription;
    }

    /**
     * Adds the given content to the given array
     *
     * @param string $label
     * @param string &$content
     * @param array &$elements
     */
    private static function addColumnElement($label, &$content, &$elements)
    {
        if (isset($content)) {
            $elements[] = Html5::getDt($label);
            $elements[] = Html5::getDd($content);
        }
    }

    private static function getTableFooterTwig($schema, $table)
    {
        $indices = &$schema[$table]['indices'];
        $constraints = &$schema[$table]['unique'];
        $keys = &$schema[$table]['foreign_keys'];

        $twig = Html5::getElement('hr', '');

        $twig .= Html5::getElement('h1', Html5::getText('Indices'));
        $twig .= self::getIndicesFooter($indices, $table);

        $twig .= Html5::getElement('h1', Html5::getText('Uniqueness Constraints'))."\n";
        $twig .= self::getUniquenessFooter($constraints);

        $twig .= Html5::getElement('h1', Html5::getText('Foreign Keys'))."\n";
        $twig .= self::getForeignKeyFooter($keys);

        return $twig;
    }

    private static function getIndicesFooter($indices, $table)
    {
        if (!isset($indices)) {
            return Html5::getDl(
                array(Html5::getText("No indices in this table.")."\n"),
                array('class' => 'property')
            );
        }

        $indexDds = array();
        foreach ($indices as $name => $columns) {
            $text = Html5::getText(self::getIndexSQLString($table, $name, $columns));
            $indexDds[] = Html5::getDd($text);
        }

        $twig = Html5::getDl(
            $indexDds,
            array('class' => 'property')
        );

        return $twig;
    }

    private static function getIndexSQLString($table, $name, $columns)
    {
        $index = "(";
        foreach ($columns as $column) {
            $index .= "`{$column}`, ";
        }
        $index = substr($index, 0, -2);
        return $index.")";
    }

    private static function getUniquenessFooter($constraints)
    {
        if (!isset($constraints)) {
            return Html5::getDl(
                array(Html5::getText("No uniqueness constraints in this table.")."\n"),
                array('class' => 'property')
            );
        }

        $constraintDds = array();
        foreach ($constraints as $constraint) {
            $text = Html5::getText(self::getUniqueSQLString($constraint));
            $constraintDds[] = Html5::getDd($text);
        }

        $twig = Html5::getDl(
            $constraintDds,
            array('class' => 'property')
        );

        return $twig;
    }

    private static function getForeignKeyFooter($keys)
    {
        if (!isset($keys)) {
            return Html5::getDl(
                array(Html5::getText("No foreign keys for this table.\n")),
                array('class' => 'property')
            );
        }

        $twig = '';

        $keyDds = array();
        foreach ($keys as $key) {
            $text = Html5::getText(self::getForeignKeySQLString($key));
            $keyDds[] = Html5::getDd($text);
        }

        $keysDl = Html5::getDl(
            $keyDds,
            array(
                'class' => 'property'
            )
        );

        $twig .= $keysDl;
        $twig .= "\n\n";

        return $twig;
    }

    private static function getForeignKeySQLString($key)
    {
        $column = $key['column'];
        $referenced_table = $key['referenced_table'];
        $referenced_column = $key['referenced_column'];

        return "FOREIGN KEY (`{$column}`) REFERENCES `{$referenced_table}` (`{$referenced_column}`)";
    }

    private static function getUniqueSQLString(array $columns)
    {
        $unique = 'UNIQUE(';
        foreach ($columns as $column) {
            $unique .= '`'.$column.'`, ';
        }
        $unique = substr($unique, 0, -2).')';

        return $unique;
    }

    private static function getTableUri($table, $schemaName)
    {
        return "tables/{$table}.html";
    }

    private static function getSchemaUri($schema)
    {
        return "{$schema}/index.html";
    }

    private static function getValuesTwig($values)
    {
        if ($values === null || !isset($values)) {
            return '';
        }

        $completeness = array_shift($values);

        $valuesPhp = array_map('self::phpEncode', $values);
        $valuesHtml = array_map('self::getText', $valuesPhp);

        switch ($completeness) {
            case 'SOME': # some of the existing values
                array_unshift($valuesHtml, '…');
                array_push($valuesHtml, '…');
                break;

            case 'ALL': # all of the existing values
                array_push($valuesHtml, '…');
                break;

            case 'ALL_POSSIBLE': # all possible values
                break;

            default: // The table was very large so the completeness of the array wasn't added.
                $value = self::phpEncode($completeness);
                array_push($valuesHtml, self::getText($value));
                array_unshift($valuesHtml, '…');
                array_push($valuesHtml, '…');
                break;
        }

        $valuesHtml = "(" . implode(', ', $valuesHtml) . ')';

        $valuesHtml = self::twigEncode($valuesHtml);
        return $valuesHtml;
    }

    private function getExpanderHtml($values)
    {
        $values = self::getValuesTwig($values);

        $cutOff = 800;
        if (strlen($values) < $cutOff) {
            return $values;
        }

        $beginning = substr($values, 0, $cutOff);
        $end = substr($values, $cutOff);

        $toggleID = $this->toggleID++;

        $end = Html5::getElement(
            'i',
            Html5::getElement(
                'span',
                Html5::getElement('input', '', array('id' => "toggle-{$toggleID}", 'type' => 'checkbox')) .
                         Html5::getElement('span', $end) .
                         Html5::getElement('label', '', array('for' => "toggle-{$toggleID}")),
                array('class' => 'toggle')
            )
        );

        return $beginning . $end;
    }

    private static function twigEncode($text)
    {
        return str_replace(array('{', '}'), array('&lbrace;', '&rbrace;'), $text);
    }

    private static function phpEncode($value)
    {
        if ($value === null) {
            return 'null';
        }

        if (is_string($value) && (preg_match('~[\t\n\f\r]~', $value) === 1)) {
            return json_encode($value);
        }

        if (is_float($value)) {
            return (string)$value;
        }

        return var_export($value, true);
    }

    private static function getTableLink($class, $schemaName)
    {
        $attributes = array(
            'href' => self::getTableUri($class, $schemaName)
        );

        return Html5::getA(
            Html5::getText($class),
            $attributes
        );
    }

    private static function getText($string)
    {
        if ($string === null) {
            return '&nbsp;';
        }

        return Html5::getText($string);
    }

    private static function getPageTwig($layout, $twig)
    {
        return <<<EOS
{% extends '{$layout}.twig' %}
{% block content %}
{$twig}
{% endblock %}
EOS;
    }

    private function writeTwig($title, $twig, $directory)
    {
        $this->filesystem->write("{$directory}/_.title", $title);
        $this->filesystem->write("{$directory}/_.twig", $twig);
    }
}
