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
            $item['href'] = '/posts/' . rawurlencode($item['post_id']);
            $item['is_reply'] = $item['post_id'] !== $item['thread_id'];
            $item['context_title'] = $item['context_id'] === null ? 'Unavailable thread'
                : ThreadTitle::displayTitle((string)$item['context_subject'], (string)$item['context_body'], '', 100);
            $item['excerpt'] = ThreadTitle::displayTitle('', (string)$item['body'], '', 280);
            if (trim((string)$item['body']) === '') {
                $item['excerpt'] = trim((string)$item['subject']) !== '' ? $item['subject'] : 'Post';
            }
        }
        unset($item);
        $pagination = PageNavigation::links('/recent/', [], $context, $page['previous'], $page['next'], $error !== null || ($position !== null && $page['items'] === []));
        return $this->routeServices->renderPageTemplate('recent.php', ['items'=>$page['items'], 'pagination'=>$pagination, 'paginationError'=>$error], 'Recent', 'recent');
    }
}
