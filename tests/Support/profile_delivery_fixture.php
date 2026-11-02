<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/autoload.php';
$root = $argv[1]; $project = dirname(__DIR__, 2);
symlink($project.'/templates', $root.'/templates');
symlink($project.'/public', $root.'/public');
mkdir($root.'/sessions', 0700, true);
mkdir($root.'/repository');
$source = dirname(__DIR__).'/fixtures/parity_minimal_v1';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($iterator as $item) {
    $target = $root.'/repository/'.$iterator->getSubPathName();
    $item->isDir() ? mkdir($target,0777,true) : copy($item->getPathname(),$target);
}
(new \ForumRewrite\ReadModel\ReadModelBuilder($root.'/repository',$root.'/read.sqlite3',new \ForumRewrite\Canonical\CanonicalRecordRepository($root.'/repository')))->rebuild();
$pdo = new PDO('sqlite:'.$root.'/read.sqlite3');
echo json_encode(['slug'=>$pdo->query('SELECT profile_slug FROM profiles LIMIT 1')->fetchColumn(), 'themes'=>array_values(array_unique(array_merge(array_filter(\ForumRewrite\SiteProfileRegistry::active()['permittedThemes'],fn($v)=>$v!=='auto'),['qdb','chouse','mitrapclub'])))]);
