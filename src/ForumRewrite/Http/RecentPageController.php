<?php

declare(strict_types=1);
namespace ForumRewrite\Http;

use ForumRewrite\Images\ImageStore;
use ForumRewrite\Images\ListThumbnails;
use ForumRewrite\Pagination\PageNavigation;
use ForumRewrite\ReadModel\RecentPostRepository;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\ThreadTitle;

final class RecentPageController
{
    private const GALLERY_PAGE_SIZE = 48;

    public function __construct(private readonly RouteServices $routeServices) {}

    public function board(mixed $page = null): string
    {
        $number = PageNavigation::pageNumber($page);
        $page = $this->page($number, 50, false);
        $page['items'] = ListThumbnails::attach(
            $this->routeServices->pdo(),
            $this->routeServices->repositoryRoot(),
            $this->routeServices->featureFlags()->isEnabled(FeatureFlagRegistry::LIST_THUMBNAILS_ENABLED),
            $page['items'],
            'post_id',
        );
        $pagination = PageNavigation::numbered('/recent/', [], $number, $page['has_next'], $page['items'] === []);
        return $this->routeServices->renderPageTemplate('recent.php', ['items'=>$page['items'], 'pagination'=>$pagination, 'paginationError'=>null], 'Recent', 'recent');
    }

    /**
     * Posts with an attached image, newest first, as image tiles. Same posts as
     * Recent; a post whose image file is missing is left out.
     */
    public function gallery(mixed $page = null): string
    {
        $number = PageNavigation::pageNumber($page);
        $page = $this->page($number, self::GALLERY_PAGE_SIZE, true);
        $pdo = $this->routeServices->pdo();
        try {
            $images = ListThumbnails::forPosts($pdo, new ImageStore($this->routeServices->repositoryRoot()), array_column($page['items'], 'post_id'));
        } catch (\RuntimeException) {
            $images = [];
        }
        $replyCounts = [];
        $threadIds = array_values(array_unique(array_column($page['items'], 'thread_id')));
        if ($threadIds !== []) {
            $query = $pdo->prepare('SELECT root_post_id, reply_count FROM threads WHERE root_post_id IN (' . implode(',', array_fill(0, count($threadIds), '?')) . ')');
            $query->execute($threadIds);
            foreach ($query->fetchAll(\PDO::FETCH_ASSOC) as $row) $replyCounts[(string) $row['root_post_id']] = (int) $row['reply_count'];
        }
        $tiles = [];
        foreach ($page['items'] as $item) {
            $image = $images[$item['post_id']] ?? null;
            if ($image === null) continue;
            $tiles[] = $item + ['image' => $image, 'reply_count' => $replyCounts[$item['thread_id']] ?? 0];
        }
        $pagination = PageNavigation::numbered('/gallery/', [], $number, $page['has_next'], $page['items'] === []);
        return $this->routeServices->renderPageTemplate('gallery.php', ['tiles'=>$tiles, 'pagination'=>$pagination, 'paginationError'=>null], 'Gallery', 'gallery');
    }

    /** The page holding a `with` post on Recent or Gallery; null shows the list normally. */
    public function withLocation(string $list, mixed $with): ?string
    {
        $id = PageNavigation::withId($with);
        if ($id === null) return null;
        $gallery = $list === 'gallery';
        $page = RecentPostRepository::pageOf($this->routeServices->pdo(), $id, $gallery ? self::GALLERY_PAGE_SIZE : 50, $gallery);
        return $page === null ? null : PageNavigation::withLocation($gallery ? '/gallery/' : '/recent/', [], $page, $id);
    }

    /** @return array{items:array,has_next:bool} */
    private function page(int $number, int $limit, bool $imagesOnly): array
    {
        $page = RecentPostRepository::page($this->routeServices->pdo(), $number, $limit, $imagesOnly);
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
        return $page;
    }
}
