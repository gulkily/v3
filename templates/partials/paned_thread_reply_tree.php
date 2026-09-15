<?php
/**
 * @var array<int, array{post: array<string, mixed>, children: array}> $replyTree
 */
$renderNode = null;
$renderNode = function (array $node, int $depth) use (&$renderNode, $e, $author, $timestamp, $br): string {
    $post = $node['post'];
    $isAgentPost = (string) ($post['author_label'] ?? '') === 'reply-agent';

    $postId = (string) $post['post_id'];
    $html = '<div class="paned-reply-node post-card paned-post-card" data-paned-reply-post-id="' . $e($postId) . '" data-post-id="' . $e($postId) . '" style="margin-left:' . ($depth * 1.25) . 'rem">';
    $html .= '<div class="paned-reply-meta">';
    if ($isAgentPost) {
        $html .= '<span class="paned-agent-badge">AGENT</span> ';
    }
    $html .= $author($post) . ' &middot; ' . $timestamp((string) ($post['created_at'] ?? ''));
    $html .= '</div>';
    $html .= '<div class="paned-reply-body">' . $br($post['body']) . '</div>';
    $html .= '<div class="paned-reaction-row"><button type="button" class="paned-reaction-button" data-action="apply-post-tag" data-tag="flag" data-post-id="' . $e($postId) . '" data-applied-label="Flagged" aria-pressed="false">Flag</button></div>';
    $html .= '<p class="paned-reaction-feedback" data-role="post-reaction-feedback" hidden></p>';
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
