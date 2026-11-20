<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\Support\Tagsets;
use ForumRewrite\Tools\MarkedPostsQuery;
use ForumRewrite\View\TemplateRenderer;

/**
 * GET /api/checkin: one read-only request that tells an agent what needs attention now,
 * each section ending with the exact next request. Plain text, or JSON with ?format=json.
 * Reading it moves no mark. The route is gated by FORUM_AGENT_CHECKIN_ENABLED before this
 * controller runs; personal sections follow Unread's gate (a signed-in approved key).
 */
final class AgentCheckinController
{
    public const LIMIT = 10;

    /**
     * @param \Closure(array<string, mixed>): string $displayThreadTitle
     * @param \Closure(): (array<string, mixed>|null) $authenticatedViewerProfile
     * @param (\Closure(): InboxPageController)|null $inbox null when Unread is disabled
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $displayThreadTitle,
        private readonly string $appVersion,
        private readonly bool $tagUnlocksEnabled,
        private readonly \Closure $authenticatedViewerProfile,
        private readonly ?\Closure $inbox,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function show(string $method, array $query = []): void
    {
        $json = ($query['format'] ?? '') === 'json';
        if ($method !== 'GET' && $method !== 'HEAD') {
            $headers = ['Allow: GET, HEAD', ...$this->routeServices->noStoreHeaders()];
            $json
                ? $this->routeServices->sendJson(['status' => 'error', 'error' => 'Only GET is supported.'], 405, $headers)
                : $this->routeServices->sendText("error=Only GET is supported.\n", 405, $headers);
            return;
        }
        $origin = TemplateRenderer::requestOrigin();
        $viewer = $this->viewer($origin);
        $sections = [...$this->personalSections($viewer['profile'], $origin), ...$this->sections($origin)];

        if ($json) {
            $this->routeServices->sendJson([
                'status' => 'ok',
                'site' => $origin,
                'version' => $this->appVersion,
                'viewer' => $viewer['summary'],
                'notices' => $viewer['notices'],
                'sections' => array_map(static fn (array $section): array => [
                    'key' => $section['key'],
                    'title' => $section['title'],
                    'total' => $section['total'],
                    'items' => $section['items'],
                    'next' => $section['items'] !== [] ? $section['next'] : '',
                    'note' => $section['note'],
                ], $sections),
            ], 200, $this->routeServices->noStoreHeaders());
            return;
        }

        $lines = [
            'Check-in: ' . preg_replace('#^https?://#', '', $origin),
            'Version: ' . $this->appVersion,
            'Viewer: ' . $viewer['line'],
            ...$viewer['notices'],
            '',
        ];
        foreach ($sections as $section) {
            $shown = count($section['items']);
            $lines[] = $section['title'] . ': ' . ($section['total'] > $shown ? $shown . ' of ' . $section['total'] : $shown);
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
     * @return list<array{key:string,title:string,total:int,items:list<array{id:string,thread_id:string,line:string,read:string}>,next:string,note:string}>
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
                $items[] = self::item($post['post_id'], $post['thread_id'],
                    $post['post_id'] . ' ' . $where . ' "' . self::oneLine($post['thread_subject']) . '"' . $claimed . ': ' . $post['excerpt'], 'post');
            }
            $sections[] = self::section('open_items', 'Open #' . implode(' #', $parents) . ' items', $items, count($items),
                'claim one before working on it: POST /api/apply_post_tag with post_id=POST_ID and tag=claimed; mark it resolved when done. See /llms.txt, "Reply resolution".');
        }

        $threads = ThreadRepository::fetchThreads($pdo);
        usort($threads, static fn (array $a, array $b): int => [(string) $b['root_post_created_at'], (string) $b['root_post_id']] <=> [(string) $a['root_post_created_at'], (string) $a['root_post_id']]);
        $items = [];
        foreach (array_slice($threads, 0, self::LIMIT) as $thread) {
            $replies = (int) $thread['reply_count'];
            $id = (string) $thread['root_post_id'];
            $items[] = self::item($id, $id, $id . ' "' . self::oneLine(($this->displayThreadTitle)($thread)) . '" (' . $replies . ($replies === 1 ? ' reply' : ' replies') . ')', 'thread');
        }
        $sections[] = self::section('newest_threads', 'Newest threads', $items, count($threads),
            'reply with python3 quill.py --site ' . $origin . ' reply --thread THREAD_ID --body-file reply.txt');

        return $sections;
    }

    /**
     * Who is asking, as a text line, a JSON summary and notices that say how to see more.
     *
     * @return array{profile:?array<string,mixed>,line:string,summary:array<string,string>,notices:list<string>}
     */
    private function viewer(string $origin): array
    {
        $profile = ($this->authenticatedViewerProfile)();
        $checkin = 'python3 quill.py --site ' . $origin . ' checkin';
        if ($profile === null) {
            return [
                'profile' => null,
                'line' => 'anonymous',
                'summary' => ['status' => 'anonymous', 'username' => '', 'identity_id' => ''],
                'notices' => ['Sign-in: replies to you need a signed-in approved key. With a key, run: ' . $checkin],
            ];
        }
        $name = trim((string) ($profile['username'] ?? '')) !== '' ? (string) $profile['username'] : (string) ($profile['fallback_label'] ?? '');
        $identity = (string) ($profile['identity_id'] ?? '');
        if ((int) ($profile['is_approved'] ?? 0) !== 1) {
            return [
                'profile' => null,
                'line' => $name . ' (' . $identity . ', not approved)',
                'summary' => ['status' => 'unapproved', 'username' => $name, 'identity_id' => $identity],
                'notices' => ['Approval: replies to you need an approved key. Ask an approved member to approve ' . $identity . '; see /llms.txt, "Approving keys".'],
            ];
        }
        $notices = $this->inbox === null ? ['Unread: disabled on this server, so replies to you are not shown.'] : [];
        return [
            'profile' => $this->inbox === null ? null : $profile,
            'line' => $name . ' (approved)',
            'summary' => ['status' => 'approved', 'username' => $name, 'identity_id' => $identity],
            'notices' => $notices,
        ];
    }

    /**
     * Unread replies and conversations for an approved viewer, most personal first.
     *
     * @param array<string, mixed>|null $profile
     * @return list<array{key:string,title:string,total:int,items:list<array{id:string,thread_id:string,line:string,read:string}>,next:string,note:string}>
     */
    private function personalSections(?array $profile, string $origin): array
    {
        if ($profile === null || $this->inbox === null) {
            return [];
        }
        $unread = ($this->inbox)()->unreadFor($profile, self::LIMIT);
        $items = [];
        foreach ($unread['replies'] as $reply) {
            $postId = (string) $reply['post_id'];
            $threadId = (string) ($reply['thread_id'] ?? '') !== '' ? (string) $reply['thread_id'] : $postId;
            $author = trim((string) ($reply['author_label'] ?? '')) !== '' ? (string) $reply['author_label'] : 'guest';
            $context = self::oneLine((string) ($reply['context_subject'] ?? ''));
            $items[] = self::item($postId, $threadId,
                $postId . ' by ' . self::oneLine($author) . ($context !== '' ? ' in "' . $context . '"' : '') . ': ' . self::excerpt((string) ($reply['body'] ?? '')), 'post');
        }
        $ids = implode(' ', array_column($items, 'id'));
        $sections = [self::section('replies_to_you', 'Replies to you', $items, max($unread['reply_count'], count($items)),
            'reply with python3 quill.py --site ' . $origin . ' reply --thread THREAD_ID --body-file reply.txt, then mark these caught up: python3 quill.py --site ' . $origin . ' caught-up --section replies ' . $ids
            . ' (or POST /api/inbox/catch_up; see /api/).',
            $unread['replies_failed'] ? 'Unavailable right now; try again shortly.' : '')];

        if ($unread['messages_enabled']) {
            $items = [];
            foreach ($unread['conversations'] as $conversation) {
                $count = (int) $conversation['unread_count'];
                $items[] = [
                    'id' => (string) $conversation['counterpart'],
                    'thread_id' => '',
                    'line' => 'with ' . self::oneLine((string) $conversation['counterpart']) . ' (' . $count . ' unread)',
                    'read' => 'open ' . $origin . '/messages/ in a browser',
                ];
            }
            $sections[] = self::section('unread_conversations', 'Unread conversations', $items, count($items),
                'messages are end-to-end encrypted; read and answer them at ' . $origin . '/messages/',
                $unread['messages_failed'] ? 'Unavailable right now; try again shortly.' : '');
        }

        return $sections;
    }

    /** @return array{id:string,thread_id:string,line:string,read:string} */
    private static function item(string $id, string $threadId, string $line, string $kind): array
    {
        return [
            'id' => $id,
            'thread_id' => $threadId,
            'line' => $line,
            'read' => $kind === 'thread' ? 'GET /api/get_thread?thread_id=' . rawurlencode($id) : 'GET /api/get_post?post_id=' . rawurlencode($id),
        ];
    }

    /**
     * @param list<array{id:string,thread_id:string,line:string,read:string}> $items
     * @return array{key:string,title:string,total:int,items:list<array{id:string,thread_id:string,line:string,read:string}>,next:string,note:string}
     */
    private static function section(string $key, string $title, array $items, int $total, string $next, string $note = ''): array
    {
        return ['key' => $key, 'title' => $title, 'total' => $total, 'items' => $items, 'next' => $next, 'note' => $note];
    }

    private static function excerpt(string $body): string
    {
        $text = self::oneLine($body);
        return mb_strlen($text) > 110 ? rtrim(mb_substr($text, 0, 109)) . '…' : $text;
    }

    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace('"', "'", $text)));
    }
}
