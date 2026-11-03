<?php

declare(strict_types=1);
namespace ForumRewrite\Http;

use ForumRewrite\Pagination\PageNavigation;
use ForumRewrite\ReadModel\RecentPostRepository;
use ForumRewrite\Support\ThreadTitle;

final class RecentPageController
{
    public function __construct(private readonly RouteServices $routeServices) {}

    public function board(mixed $position = null): string
    {
        $context = 'recent:created_at:post_id:desc';
        $error = null;
        try {
            if ($position !== null && !is_string($position)) throw new \InvalidArgumentException('This page position is invalid.');
            $cursor = $position === null ? null : PageNavigation::decode($position, $context);
            $page = RecentPostRepository::page($this->routeServices->pdo(), $cursor);
        } catch (\InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            $page = ['items'=>[], 'previous'=>null, 'next'=>null];
        }
        foreach ($page['items'] as &$item) {
            $item['is_reply'] = $item['post_id'] !== $item['thread_id'];
            // Open the thread, anchored to the reply's card; a post whose thread is unavailable keeps its own page.
            $item['href'] = $item['context_id'] === null ? '/posts/' . rawurlencode($item['post_id'])
                : '/threads/' . rawurlencode($item['thread_id']) . ($item['is_reply'] ? '#post-' . rawurlencode($item['post_id']) : '');
            $item['context_title'] = $item['context_id'] === null ? 'Unavailable thread'
                : ThreadTitle::displayTitle((string)$item['context_subject'], \ForumRewrite\Support\PostText::plainPreview((string)$item['context_body'], $item['context_body_format'] ?? null), '', 100);
            $item['excerpt'] = ThreadTitle::displayTitle('', \ForumRewrite\Support\PostText::plainPreview((string)$item['body'], $item['body_format'] ?? null), '', 280);
            if (trim((string)$item['body']) === '') {
                $item['excerpt'] = trim((string)$item['subject']) !== '' ? $item['subject'] : 'Post';
            }
        }
        unset($item);
        $pagination = PageNavigation::links('/recent/', [], $context, $page['previous'], $page['next'], $error !== null || ($position !== null && $page['items'] === []));
        return $this->routeServices->renderPageTemplate('recent.php', ['items'=>$page['items'], 'pagination'=>$pagination, 'paginationError'=>$error], 'Recent', 'recent');
    }
}
