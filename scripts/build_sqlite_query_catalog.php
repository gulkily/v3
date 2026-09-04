<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Tools\SqliteQueryCatalog;

$projectRoot = dirname(__DIR__);
$sourceDirectory = $argv[1] ?? ($projectRoot . '/queries/sqlite');
$browserPath = $argv[2] ?? ($projectRoot . '/public/assets/sqlite_viewer.js');
$localPath = $argv[3] ?? ($projectRoot . '/public/assets/sqlite_query_catalog.sql');

$catalog = SqliteQueryCatalog::load($sourceDirectory);
$browserSource = file_get_contents($browserPath);
if ($browserSource === false) {
    throw new RuntimeException('Unable to read browser viewer asset: ' . $browserPath);
}

$browserEntries = array_map(static function (array $entry): array {
    return [
        'id' => $entry['id'],
        'label' => $entry['label'],
        'description' => $entry['description'],
        'category' => $entry['category'],
        'sql' => $entry['sql'],
    ];
}, $catalog);
$encoded = json_encode($browserEntries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($encoded === false) {
    throw new RuntimeException('Unable to encode SQLite query catalog.');
}
$replacement = "    // BEGIN GENERATED SQLITE QUERY CATALOG\n"
    . '    var presetQueries = ' . $encoded . ";\n"
    . '    // END GENERATED SQLITE QUERY CATALOG';
$pattern = '/    \/\/ BEGIN GENERATED SQLITE QUERY CATALOG.*?    \/\/ END GENERATED SQLITE QUERY CATALOG/s';
$updated = preg_replace($pattern, $replacement, $browserSource, 1, $count);
if ($count === 0) {
    $updated = preg_replace('/    var presetQueries = \[.*?^\];/ms', $replacement, $browserSource, 1, $count);
}
if ($updated === null || $count !== 1) {
    throw new RuntimeException('Could not find the generated query catalog block in ' . $browserPath);
}
if (file_put_contents($browserPath, $updated) === false) {
    throw new RuntimeException('Unable to write browser viewer asset: ' . $browserPath);
}

$pack = "-- SQLite Viewer Query Catalog\n"
    . "-- Generated from repository query sources.\n\n";
foreach ($catalog as $entry) {
    $source = file_get_contents($sourceDirectory . '/' . $entry['filename']);
    if ($source === false) {
        throw new RuntimeException('Unable to read query source for local pack: ' . $entry['filename']);
    }
    $pack .= "-- -----------------------------------------------------------------------------\n"
        . '-- ' . $entry['label'] . "\n"
        . '-- ' . $entry['description'] . "\n"
        . "-- -----------------------------------------------------------------------------\n\n"
        . trim($source) . "\n\n";
}
if (file_put_contents($localPath, $pack) === false) {
    throw new RuntimeException('Unable to write local SQLite query pack: ' . $localPath);
}

fwrite(STDOUT, sprintf("Generated %d SQLite queries in %s and %s\n", count($catalog), $browserPath, $localPath));
