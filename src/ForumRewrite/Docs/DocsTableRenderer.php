<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

use League\CommonMark\Extension\Table\TableRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class DocsTableRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        return '<div class="docs-table-scroll" tabindex="0" role="region" aria-label="Table">'
            . (new TableRenderer())->render($node, $childRenderer) . '</div>';
    }
}
