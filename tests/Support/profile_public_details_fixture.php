<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/autoload.php';
if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1];
$project = dirname(__DIR__, 2);
symlink($project . '/templates', $root . '/templates');
symlink($project . '/public', $root . '/public');
mkdir($root . '/sessions', 0700, true);
$source = __DIR__ . '/../fixtures/parity_minimal_v1';
mkdir($root . '/repository');
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
    $target = $root . '/repository/' . substr($file->getPathname(), strlen($source) + 1);
    if ($file->isDir()) mkdir($target); else copy($file->getPathname(), $target);
}
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository')))->rebuild();
$pdo = new PDO('sqlite:' . $root . '/read.sqlite3');
$profile = $pdo->query('SELECT * FROM profiles LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$pdo->exec('UPDATE profiles SET is_approved=0');
echo json_encode(['slug' => $profile['profile_slug'], 'key' => $profile['public_key'], 'themes' => array_values(array_filter(\ForumRewrite\SiteProfileRegistry::active()['permittedThemes'], static fn (string $theme): bool => $theme !== 'auto'))]);
