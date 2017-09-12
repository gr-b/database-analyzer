<?php

namespace Datto\DatabaseAnalyzer\Documentation;

use Datto\DatabaseAnalyzer\Utility\Configuration;
use Datto\DatabaseAnalyzer\Utility\Filesystem;
use Datto\DatabaseAnalyzer\Scanner\Scan;
use Exception;

require dirname(__DIR__) . '/autoload.php';

$filesystem = new Filesystem();

$path = dirname(__DIR__) . '/configuration.ini';
$settings = new Configuration($filesystem->read($path));

$documentationDirectory = $settings->getSetting('outputPath');
$generator = new Generator($filesystem, $documentationDirectory, $settings);

$path = $settings->getSetting('cachePath');
$content = $filesystem->read($path);
$content = json_decode($content, true);
if ($content == null) {
    throw new Exception("Cached scan file not found at path: {$path}. Make sure the configured path is relative to this file.");
}
$scan = new Scan($content);

$generator->generate($scan);
