<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class DocsHtmlRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        $literal = $node->getLiteral();
        if (preg_match('/^\s*(?:<!--(?:(?!-->)[\s\S])*-->\s*)+$/', $literal) === 1) {
            return '';
        }
        // A standalone HTML image gets readable alt text; never fetch or execute it.
        if (preg_match('/^\s*<img\b[^>]*>\s*$/i', $literal) === 1
            && preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $literal, $match) === 1) {
            $label = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return htmlspecialchars($label . ' (image unavailable)', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return htmlspecialchars($literal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
