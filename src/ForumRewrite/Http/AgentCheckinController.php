<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\Support\Tagsets;
use ForumRewrite\Tools\MarkedPostsQuery;
use ForumRewrite\View\TemplateRenderer;

/**
 * GET /api/checkin: one read-only request that tells an agent what needs attention now,
 * each section ending with the exact next request. Plain text; reading it moves no mark.
 * The route is gated by FORUM_AGENT_CHECKIN_ENABLED before this controller runs.
 */
final class AgentCheckinController
{
    public const LIMIT = 10;

    /**
     * @param \Closure(array<string, mixed>): string $displayThreadTitle
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $displayThreadTitle,
        private readonly string $appVersion,
        private readonly bool $tagUnlocksEnabled,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function show(string $method, array $query = []): void
    {
        if ($method !== 'GET' && $method !== 'HEAD') {
            $this->routeServices->sendText("error=Only GET is supported.\n", 405, ['Allow: GET, HEAD', ...$this->routeServices->noStoreHeaders()]);
            return;
        }
        $origin = TemplateRenderer::requestOrigin();
        $lines = [
            'Check-in: ' . preg_replace('#^https?://#', '', $origin),
            'Version: ' . $this->appVersion,
            'Viewer: anonymous',
            '',
        ];
        foreach ($this->sections($origin) as $section) {
            $lines[] = $section['title'] . ': ' . count($section['items']);
            foreach ($section['items'] as $item) {
                $lines[] = '- ' . $item['line'];
                $lines[] = '  Read: ' . $item['read'];
            }
            if ($section['note'] !== '') $lines[] = $section['note'];
            if ($section['items'] !== [] && $section['next'] !== '') $lines[] = 'Next: ' . $section['next'];
            $lines[] = '';
        }
        $this->routeServices->sendText(implode("\n", $lines), 200, $this->routeServices->noStoreHeaders());
    }

    /**
     * Public sections, in the order an agent should act on them.
     *
     * @return list<array{key:string,title:string,items:list<array{line:string,read:string}>,next:string,note:string}>
     */
    public function sections(string $origin): array
    {
        $pdo = $this->routeServices->pdo();
        $sections = [];

        $parents = $this->tagUnlocksEnabled ? MarkedPostsQuery::workParents(Tagsets::forApplication()->all()) : [];
        if ($parents !== []) {
            $items = [];
            foreach (MarkedPostsQuery::open($pdo, $parents, self::LIMIT) as $post) {
                $where = $post['is_root'] ? 'opening post of' : 'in';
                $claimed = $post['state'] === 'claimed' ? ' (claimed by ' . ($post['set_by'] !== '' ? $post['set_by'] : 'someone') . ')' : '';
                $items[] = [
                    'line' => $post['post_id'] . ' ' . $where . ' "' . self::oneLine($post['thread_subject']) . '"' . $claimed . ': ' . $post['excerpt'],
                    'read' => 'GET /api/get_post?post_id=' . rawurlencode($post['post_id']),
                ];
            }
            $sections[] = [
                'key' => 'open_items',
                'title' => 'Open #' . implode(' #', $parents) . ' items',
                'items' => $items,
                'next' => 'claim one before working on it: POST /api/apply_post_tag with post_id=POST_ID and tag=claimed; mark it resolved when done. See /llms.txt, "Reply resolution".',
                'note' => '',
            ];
        }

        $threads = ThreadRepository::fetchThreads($pdo);
        usort($threads, static fn (array $a, array $b): int => [(string) $b['root_post_created_at'], (string) $b['root_post_id']] <=> [(string) $a['root_post_created_at'], (string) $a['root_post_id']]);
        $items = [];
        foreach (array_slice($threads, 0, self::LIMIT) as $thread) {
            $replies = (int) $thread['reply_count'];
            $items[] = [
                'line' => $thread['root_post_id'] . ' "' . self::oneLine(($this->displayThreadTitle)($thread)) . '" (' . $replies . ($replies === 1 ? ' reply' : ' replies') . ')',
                'read' => 'GET /api/get_thread?thread_id=' . rawurlencode((string) $thread['root_post_id']),
            ];
        }
        $sections[] = [
            'key' => 'newest_threads',
            'title' => 'Newest threads',
            'items' => $items,
            'next' => 'reply with python3 quill.py --site ' . $origin . ' reply --thread THREAD_ID --body-file reply.txt',
            'note' => '',
        ];

        return $sections;
    }

    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace('"', "'", $text)));
    }
}
