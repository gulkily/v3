<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Renderer\Block\IndentedCodeRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class DocsCodeRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if ($node instanceof IndentedCode) {
            return str_replace('<pre>', '<pre tabindex="0" role="region" aria-label="Code">', (string) (new IndentedCodeRenderer())->render($node, $childRenderer));
        }
        FencedCode::assertInstanceOf($node);
        $code = str_replace('<pre>', '<pre tabindex="0" role="region" aria-label="Code">', (string) (new FencedCodeRenderer())->render($node, $childRenderer));
        $language = $node->getInfoWords()[0] ?? '';
        if ($language === '') {
            return $code;
        }
        $label = $language === 'mermaid' ? 'Mermaid source' : $language;

        return '<figure class="docs-code"><figcaption>'
            . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . "</figcaption>\n" . $code . "\n</figure>";
    }
}
