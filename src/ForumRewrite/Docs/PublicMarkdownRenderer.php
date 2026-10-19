<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;

final class PublicMarkdownRenderer
{
    public static function render(string $markdown, ?string $sourcePath = null, ?string $projectRoot = null): string
    {
        $autoload = dirname(__DIR__, 3) . '/third_party/markdown/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new \RuntimeException('Markdown runtime missing; restore the complete third_party/markdown package.');
        }
        require_once $autoload;

        // Build a fresh environment so duplicate heading counters are document scoped.
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
            'max_delimiters_per_line' => 1000,
            'heading_permalink' => [
                'insert' => 'none',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'apply_id_to_heading' => true,
            ],
            'slug_normalizer' => ['unique' => 'document'],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->addRenderer(FencedCode::class, new DocsCodeRenderer(), 10);
        $environment->addRenderer(IndentedCode::class, new DocsCodeRenderer(), 10);
        $environment->addRenderer(Table::class, new DocsTableRenderer(), 10);
        $environment->addRenderer(HtmlBlock::class, new DocsHtmlRenderer(), 100);
        $environment->addRenderer(HtmlInline::class, new DocsHtmlRenderer(), 100);

        $projectRoot ??= dirname(__DIR__, 3);
        $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event) use ($sourcePath, $projectRoot): void {
            $resources = [];
            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Link || $node instanceof Image) {
                    $resources[] = $node;
                }
            }
            foreach ($resources as $node) {
                $url = $node instanceof Image ? null : PlatformDocsCatalog::resolveLink($node->getUrl(), $sourcePath, $projectRoot);
                if ($url !== null) {
                    $node->setUrl($url);
                    continue;
                }
                // Preserve formatted labels without emitting a clickable unsupported source.
                while ($child = $node->firstChild()) {
                    $node->insertBefore($child);
                }
                $node->insertBefore(new Text($node instanceof Image ? ' (image unavailable)' : ' (source unavailable)'));
                $node->detach();
            }
        });

        $metadata = '';
        if (preg_match('/\A(?:\xEF\xBB\xBF)?---\r?\n(.*?)\r?\n(?:---|\.\.\.)[ \t]*(?:\r?\n|$)/s', $markdown, $match) === 1) {
            $metadata = '<details class="docs-metadata"><summary>Document metadata</summary>' . "\n" . '<pre tabindex="0" role="region" aria-label="Document metadata"><code>'
                . htmlspecialchars($match[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . "</code></pre>\n</details>" . "\n";
            $markdown = substr($markdown, strlen($match[0]));
        }

        return $metadata . (string) (new MarkdownConverter($environment))->convert($markdown);
    }
}
