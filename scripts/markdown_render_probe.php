<?php
/** Maintainer-only render/AST probe. The optional reference mode uses the existing bundle. */
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
use ForumRewrite\Docs\PlatformDocsCatalog;
use ForumRewrite\Docs\PublicMarkdownRenderer;
$root = dirname(__DIR__);
if (in_array('--inventory', $argv, true)) {
    echo json_encode(PlatformDocsCatalog::inventory($root), JSON_THROW_ON_ERROR);
    exit;
}
$reference = in_array('--reference', $argv, true);
if ($reference) {
    require $root . '/third_party/markdown/vendor/autoload.php';
    $environment = new League\CommonMark\Environment\Environment(['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $environment->addExtension(new League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
    $environment->addExtension(new League\CommonMark\Extension\GithubFlavoredMarkdownExtension());
    $parser = new League\CommonMark\Parser\MarkdownParser($environment);
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
        if ($reference) {
            foreach ($parser->parse($input['text'])->iterator() as $node) {
                $name = (new ReflectionClass($node))->getShortName();
                $row['nodes'][$name] = ($row['nodes'][$name] ?? 0) + 1;
            }
            ksort($row['nodes']);
        }
    } catch (Throwable $error) {
        $row['error'] = get_class($error) . ': ' . $error->getMessage();
    }
    $results[] = $row;
}
echo json_encode($results, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
