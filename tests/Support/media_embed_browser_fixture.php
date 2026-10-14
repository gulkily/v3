<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/autoload.php';

$root = $argv[1];
foreach (['public', 'templates', 'docs', 'prompts'] as $name) {
    symlink(dirname(__DIR__, 2) . '/' . $name, $root . '/' . $name);
}
file_put_contents($root . '/unused.php', '<?php return [];');
mkdir($root . '/sessions', 0700);
\ForumRewrite\Support\LocalRepositoryBootstrap::initializeLocalRepository(dirname(__DIR__, 2), $root . '/repository');
$repository = new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository');
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repository))->rebuild();
$previews = \ForumRewrite\View\MediaEmbedPreviewCacheStore::openAt($root);
$previews->put('instagram', 'CachedPreview', 'A cached Instagram preview', '/test-thumbnail.svg');
$previews->put('instagram', 'FailedPreview', null, null);
echo json_encode(['themes' => array_column(\ForumRewrite\View\ThemeRegistry::all(), 'name')], JSON_THROW_ON_ERROR);
