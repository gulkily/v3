<?php

declare(strict_types=1);

namespace ForumRewrite\View;

/**
 * Replies arranged by Parent-ID. Shared by the Forte board's reply tree and the
 * thread page's nested view of a ported thread.
 */
final class ReplyTree
{
    /** Deeper replies stop indenting here so narrow screens stay readable. */
    public const MAX_INDENT_DEPTH = 6;

    /**
     * A reply whose parent is not in the list (the root, or a parent filtered
     * out) becomes a top-level node. Children keep the list's order.
     *
     * @param list<array<string, mixed>> $posts
     * @return list<array{post: array<string, mixed>, children: array}>
     */
    public static function build(array $posts): array
    {
        $nodesByPostId = [];
        foreach ($posts as $post) {
            $nodesByPostId[(string) $post['post_id']] = [
                'post' => $post,
                'children' => [],
            ];
        }

        $roots = [];
        foreach ($nodesByPostId as $postId => &$node) {
            $parentId = $node['post']['parent_id'] !== null ? (string) $node['post']['parent_id'] : null;
            if ($parentId !== null && $parentId !== $postId && isset($nodesByPostId[$parentId])) {
                $nodesByPostId[$parentId]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        unset($node);

        return $roots;
    }

    /**
     * The tree in reading order (each reply followed by its replies), with each
     * reply's depth: 0 for a reply to the root.
     *
     * @param list<array<string, mixed>> $posts
     * @return list<array{post: array<string, mixed>, depth: int}>
     */
    public static function flatten(array $posts): array
    {
        $out = [];
        $walk = static function (array $nodes, int $depth) use (&$walk, &$out): void {
            foreach ($nodes as $node) {
                $out[] = ['post' => $node['post'], 'depth' => $depth];
                $walk($node['children'], $depth + 1);
            }
        };
        $walk(self::build($posts), 0);
        return $out;
    }
}
