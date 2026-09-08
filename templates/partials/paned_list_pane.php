<?php
/**
 * @var array<int, array{post: array<string, mixed>, children: array}> $replyTree
 */
$countDescendants = null;
$countDescendants = function (array $node) use (&$countDescendants): int {
    $count = 0;
    foreach ($node['children'] as $child) {
        $count += 1 + $countDescendants($child);
    }

    return $count;
};

$fallbackSubject = static function (array $post): string {
    $subject = trim((string) ($post['subject'] ?? ''));
    if ($subject !== '') {
        return $subject;
    }

    $bodyText = trim((string) preg_replace('/\s+/u', ' ', (string) ($post['body'] ?? '')));
    if ($bodyText === '') {
        return (string) $post['post_id'];
    }

    if (mb_strlen($bodyText) <= 60) {
        return $bodyText;
    }

    return mb_substr($bodyText, 0, 60) . '...';
};

$renderNode = null;
$renderNode = function (array $node, int $depth) use (&$renderNode, $countDescendants, $fallbackSubject, $e, $author, $timestamp): string {
    $post = $node['post'];
    $postId = (string) $post['post_id'];
    $isAgentPost = (string) ($post['author_label'] ?? '') === 'reply-agent';
    $hasChildren = $node['children'] !== [];
    $descendantCount = $hasChildren ? $countDescendants($node) : 0;

    $html = '<div class="paned-list-row" data-paned-post-id="' . $e($postId) . '" data-paned-depth="' . $depth . '"'
        . ($hasChildren ? ' data-paned-has-children="1"' : '') . '>';
    $html .= '<span class="paned-list-toggle"' . ($hasChildren ? ' data-paned-toggle="' . $e($postId) . '"' : '') . '>'
        . ($hasChildren ? '&#9662;' : '') . '</span>';
    $html .= '<span class="paned-list-subject" style="padding-left:' . ($depth * 16) . 'px">';
    if ($isAgentPost) {
        $html .= '<span class="paned-agent-badge">AGENT</span> ';
    }
    if ($hasChildren) {
        $html .= '<span class="paned-collapse-count" data-paned-collapse-count="' . $e($postId) . '" hidden>[+' . $descendantCount . ']</span>';
    }
    $html .= '<span>' . $e($fallbackSubject($post)) . '</span>';
    $html .= '</span>';
    $html .= '<span class="paned-list-from">' . $author($post) . '</span>';
    $html .= '<span class="paned-list-date">' . $timestamp((string) ($post['created_at'] ?? '')) . '</span>';
    $html .= '</div>';

    foreach ($node['children'] as $child) {
        $html .= $renderNode($child, $depth + 1);
    }

    return $html;
};

$listRowsHtml = '';
foreach ($replyTree as $rootNode) {
    $listRowsHtml .= $renderNode($rootNode, 0);
}
?>
<div class="paned-list-pane">
  <div class="paned-list-head">
    <span class="paned-list-toggle-head"></span>
    <span class="paned-list-subject-head">Subject</span>
    <span class="paned-list-from-head">From</span>
    <span class="paned-list-date-head">Date</span>
  </div>
  <div class="paned-list-body" data-paned-list-body><?= $listRowsHtml ?></div>
</div>
