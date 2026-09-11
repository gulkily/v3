<?php
/**
 * @var array<int, array{post: array<string, mixed>, children: array}> $replyTree
 */
$renderNode = null;
$renderNode = function (array $node, int $depth) use (&$renderNode, $e, $author, $timestamp, $br): string {
    $post = $node['post'];
    $isAgentPost = (string) ($post['author_label'] ?? '') === 'reply-agent';

    $html = '<div class="paned-reply-node" style="margin-left:' . ($depth * 1.25) . 'rem">';
    $html .= '<div class="paned-reply-meta">';
    if ($isAgentPost) {
        $html .= '<span class="paned-agent-badge">AGENT</span> ';
    }
    $html .= $author($post) . ' &middot; ' . $timestamp((string) ($post['created_at'] ?? ''));
    $html .= '</div>';
    $html .= '<div class="paned-reply-body">' . $br($post['body']) . '</div>';
    $html .= '</div>';

    foreach ($node['children'] as $child) {
        $html .= $renderNode($child, $depth + 1);
    }

    return $html;
};

$treeHtml = '';
foreach ($replyTree as $rootNode) {
    $treeHtml .= $renderNode($rootNode, 0);
}
?>
<div class="paned-reply-tree"><?= $treeHtml ?></div>
