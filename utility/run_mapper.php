<?php

namespace Datto\DatabaseAnalyzer\Database;

use Datto\DatabaseAnalyzer\Utility\Configuration;
use Datto\DatabaseAnalyzer\Utility\Filesystem;
use Datto\DatabaseAnalyzer\Utility\Logger;
use Datto\DatabaseAnalyzer\Scanner\Mapper;

require dirname(__DIR__) . '/autoload.php';

$settings = new Configuration(Filesystem::read(dirname(__DIR__).'/configuration.ini'));

$tableCatalog = $settings->getSetting('tableCatalog');


$mysql = new Mysql($settings);
$database = new Database($mysql->getPdo());
$queries = new DatabaseQueries($database);
$logger = new Logger('database-analyzer');
$mapper = new Mapper($queries, $tableCatalog, $logger);
$filesystem = new Filesystem();

$whitelist = $settings->getSetting('whitelist');

$map = $mapper->map($whitelist);
$map = $map->getScan();

$serialMap = json_encode($map, JSON_PRETTY_PRINT);

$logger->info("Writing map file...\n");
$path = __DIR__.'/map';
$filesystem->write($path, $serialMap);