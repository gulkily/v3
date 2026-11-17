<?php
// Prints one page rendered by Application from the minimal fixture repository,
// with the "Usage reports" flag on, for browser checks that serve it from
// memory: php tests/Support/usage_app_page_fixture.php /tools/usage-log/
declare(strict_types=1);

$project = dirname(__DIR__, 2);
require $project . '/autoload.php';

putenv('FORUM_USAGE_REPORTS_ENABLED=1');
$databasePath = sys_get_temp_dir() . '/usage-browser-' . bin2hex(random_bytes(6)) . '.sqlite3';
$_COOKIE = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
try {
    (new ForumRewrite\Application($project, $project . '/tests/fixtures/parity_minimal_v1', $databasePath))->handle('GET', $argv[1] ?? '/tools/usage-log/');
} finally {
    @unlink($databasePath);
}
