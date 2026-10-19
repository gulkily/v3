<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

final class PublicMarkdownRenderer
{
    public static function render(string $markdown): string
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

        return (string) (new MarkdownConverter($environment))->convert($markdown);
    }
}
