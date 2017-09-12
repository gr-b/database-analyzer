#!/usr/bin/env php
<?php

namespace Datto\DatabaseAnalyzer\Database;

use Datto\DatabaseAnalyzer\Utility\Configuration;
use Datto\DatabaseAnalyzer\Utility\Filesystem;
use Datto\DatabaseAnalyzer\Utility\Logger;
use Datto\DatabaseAnalyzer\Scanner\Diff;
use Datto\DatabaseAnalyzer\Scanner\Mapper;
use Datto\DatabaseAnalyzer\Scanner\Scan;
use Datto\DatabaseAnalyzer\Scanner\Updater;
use Datto\DatabaseAnalyzer\Documentation\Generator;

require __DIR__ . '/autoload.php';
require __DIR__ . '/vendor/autoload.php';

$configurationFilePath = __DIR__ . '/configuration.ini';

$logger = new Logger('database-analyzer');
$filesystem = new Filesystem();
$settings = new Configuration($filesystem->read($configurationFilePath));

$tableCatalog = $settings->getSetting('tableCatalog');
$whitelist = $settings->getSetting('whitelist');
$documentationDirectory = $settings->getSetting('outputPath');
$scanFile = $settings->getSetting('cachePath');

$mysql = new Mysql($settings);
$database = new Database($mysql->getPdo());
$queries = new DatabaseQueries($database);
$mapper = new Mapper($queries, $tableCatalog, $logger);
$updater = new Updater($filesystem, $settings, $logger);
$generator = new Generator($filesystem, $documentationDirectory);

$logger->info("Mapping the database...");
$scan = $mapper->map($whitelist);
$logger->info("Map complete.");

if (file_exists($scanFile)) {
	$logger->info("Transferring stored values...");
	// Sample only changed columns.
	$oldScan = $filesystem->read($scanFile);
	$oldScan = json_decode($oldScan, true);
	$oldScan = new Scan($oldScan);
	$scan = Diff::transferOldValues($oldScan, $scan);
}

$newScan = $updater->update($scan);
$newScanJson = json_encode($newScan->getScan(), JSON_PRETTY_PRINT);

$filesystem->write($scanFile, $newScanJson);

$logger->info("Generating documentation files...");
$generator->generate($newScan);
$logger->info("Documentation generation complete.");
