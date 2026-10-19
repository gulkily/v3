<?php
declare(strict_types=1);
require __DIR__ . '/../../autoload.php';
$renderer = new ForumRewrite\View\TemplateRenderer(__DIR__ . '/../../templates');
echo '<!doctype html><html><head><title>Query fixture</title><link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="/assets/sqlite.css"></head><body><input aria-label="Background draft" value="keep me">';
if (($argv[1] ?? '') === 'standalone') {
    echo $renderer->renderFragment('pages/sqlite_viewer.php', ['toolNavOptions' => []]);
} else {
    if (str_starts_with($argv[1] ?? '', 'board-')) {
        [, $view, $sort] = explode('-', $argv[1]);
        echo $renderer->renderFragment('partials/query_action.php', ForumRewrite\Tools\BoardListingQuery::context($view, $sort));
    }
    foreach (['one' => 'SELECT 42 AS answer;', 'two' => 'SELECT 7 AS other;'] as $key => $sql) {
        echo $renderer->renderFragment('partials/query_action.php', ['queryKey' => $key, 'queryLabel' => $key, 'querySql' => $sql]);
    }
    echo $renderer->renderFragment('partials/sqlite_dialog.php', []);
}
echo '<p id="background-selection">Remember this selection</p><div style="height:1800px"></div>';
echo '<script src="/assets/sql-wasm.js"></script><script src="/assets/sqlite_viewer.js"></script><script src="/assets/sqlite_dialog.js"></script></body></html>';
