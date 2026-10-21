<?php
/** Maintainer-only render probe. Reference AST evidence was captured before removal. */
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
use ForumRewrite\Docs\PlatformDocsCatalog;
use ForumRewrite\Docs\PublicMarkdownRenderer;
$root = dirname(__DIR__);
if (in_array('--inventory', $argv, true)) {
    echo json_encode(PlatformDocsCatalog::inventory($root), JSON_THROW_ON_ERROR);
    exit;
}
$results = [];
foreach (json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR) as $input) {
    $row = ['path' => $input['path'], 'nodes' => [], 'error' => null];
    try {
        $html = PublicMarkdownRenderer::render($input['text'], $input['path'], $root);
        $row['html_sha256'] = hash('sha256', $html);
        $row['html_bytes'] = strlen($html);
        preg_match_all('~<([a-z][a-z0-9]*)\b[^>]*>~i', $html, $tags);
        $row['tags'] = array_count_values($tags[1]);
    } catch (Throwable $error) {
        $row['error'] = get_class($error) . ': ' . $error->getMessage();
    }
    $results[] = $row;
}
echo json_encode($results, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
