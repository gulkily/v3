<?php

declare(strict_types=1);

// Seeds an isolated instance for tests/browser/nojs_basic_access.mjs: enough
// threads for a second board page. Served by image_browser_router.php, which
// reads flags.json (members-only on/off).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/autoload.php';
$root = $argv[1];
$threads = (int) ($argv[2] ?? 55);
file_put_contents($root . '/unused.php', '<?php return [];');
file_put_contents($root . '/flags.json', json_encode(['members' => false]));
foreach (['public', 'templates', 'docs', 'prompts'] as $name) { symlink(dirname(__DIR__, 2) . '/' . $name, $root . '/' . $name); }
mkdir($root . '/sessions', 0700);
\ForumRewrite\Support\LocalRepositoryBootstrap::initializeLocalRepository(dirname(__DIR__, 2), $root . '/repository');
$repo = new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository');
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repo))->rebuild();
$writer = new \ForumRewrite\Write\LocalWriteService($root . '/repository', $root . '/read.sqlite3', $root . '/static', $repo);
for ($n = 1; $n <= $threads; $n++) {
    $writer->createThread(['subject' => sprintf('Seeded thread %02d', $n), 'body' => 'Seeded body ' . $n, 'board_tags' => 'general']);
}
echo json_encode(['threads' => $threads], JSON_THROW_ON_ERROR);
