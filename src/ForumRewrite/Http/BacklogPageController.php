<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Feedback\BacklogClaims;
use ForumRewrite\Feedback\BacklogPrompt;
use ForumRewrite\Feedback\BacklogText;
use ForumRewrite\Feedback\FeedbackBacklog;
use ForumRewrite\Feedback\FeedbackItem;
use ForumRewrite\Feedback\FeedbackItemCheck;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\SourceRepository;

/**
 * Tools → Backlog: the open feedback items in this checkout's
 * docs/feedback/items, sorted and filtered by readable words in the address.
 * The same words give the same list from /backlog.txt and ./v3 feedback list.
 */
final class BacklogPageController
{
    private const SORT_LABELS = ['salient' => 'Salient', 'entered' => 'Entered', 'touched' => 'Touched', 'effort' => 'Effort', 'impact' => 'Impact'];
    private const SHOW_LABELS = ['can-start' => 'Can start', 'untriaged' => 'Untriaged', 'ready' => 'Ready', 'waiting' => 'Waiting on you', 'open' => 'All open'];
    private const KIND_LABELS = ['all' => 'All kinds', 'bug' => 'Bugs', 'feature' => 'Features', 'other' => 'Other'];

    /** What a page needs to sign and send a claim or a release: the signing set the guestbook uses, then the claim script. */
    private const CLAIM_SCRIPTS = [
        '/assets/openpgp_loader.js',
        '/assets/browser_signing.js',
        '/assets/outbox_store.js',
        '/assets/outbox_storage.js',
        '/assets/outbox_compose.js',
        '/assets/outbox_sender.js',
        '/assets/private_site_auth.js',
        '/assets/backlog_claim.js',
    ];

    public function __construct(private readonly RouteServices $routeServices, private readonly string $projectRoot) {}

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $viewer as for start(): it only decides where Release is drawn
     */
    public function list(array $query, ?array $viewer = null): string
    {
        $words = FeedbackBacklog::words($query);
        $all = $this->items();
        $open = FeedbackBacklog::select($all, 'open', 'all');
        $claims = $this->claims($all);
        $items = FeedbackBacklog::sort($this->withoutClaimed(FeedbackBacklog::select($all, $words['show'], $words['kind']), $words, $claims), $words['sort'], $words['order']);
        $repository = SourceRepository::fromProjectRoot($this->projectRoot);
        $fileBase = $repository !== null && str_starts_with($repository, 'https://github.com/') ? $repository . '/blob/main/docs/feedback/items/' : null;
        [$viewerId, $viewerIsOperator] = $this->viewer($viewer, $claims);

        return $this->routeServices->renderPageTemplate('backlog.php', [
            'backlogItems' => array_map(static fn (FeedbackItem $item): array => [
                'id' => $item->id(),
                'title' => $item->header('Title'),
                'kind' => $item->header('Kind'),
                'workflow' => $item->header('Workflow'),
                'effort' => $item->number('Estimate') !== null ? $item->header('Estimate') : null,
                'impact' => $item->number('Impact') !== null ? $item->header('Impact') : null,
                'touched' => $item->touched(),
                'href' => $fileBase !== null ? $fileBase . rawurlencode($item->id()) . '.txt' : null,
                'claim_lines' => BacklogClaims::lines($claims[$item->id()] ?? null),
                'release_post' => BacklogClaims::releasable($claims[$item->id()] ?? null, $viewerId, $viewerIsOperator),
            ], $items),
            'backlogOpenCount' => count($open),
            'backlogUnknownWords' => $words['unknown'],
            'backlogVersion' => substr(ReadModelMetadata::repositoryHead($this->projectRoot), 0, 12),
            'backlogSortOptions' => $this->sortOptions($words),
            'backlogShowOptions' => [
                ...$this->options('show', self::SHOW_LABELS, $words),
                // One more word on the Show row, only where claims exist: a switch, so the active link turns it off.
                ...($claims === null ? [] : [[
                    'key' => 'claimed', 'label' => 'Hide claimed', 'is_active' => $words['claimed'] === 'hide',
                    'href' => self::href($words, ['claimed' => $words['claimed'] === 'hide' ? 'show' : 'hide']),
                ]]),
            ],
            'backlogKindOptions' => $this->options('kind', self::KIND_LABELS, $words),
            'backlogTextHref' => self::href($words, [], '/backlog.txt'),
            'backlogClaimsThread' => $claims === null ? null : SiteProfileRegistry::backlogClaimsThread(),
        ], 'Backlog', 'tools', $claims === null ? [] : self::CLAIM_SCRIPTS);
    }

    /**
     * The tidy address for a start request, when the request is not already in
     * that form. A plain form sends items[]=A&items[]=B; the page lives at
     * ?items=A,B, with the mode named only when it is not the default (the agent decides).
     *
     * @param array<string, mixed> $query
     */
    public static function startRedirect(array $query, string $requestUri, string $path = '/backlog/start'): ?string
    {
        $ids = BacklogPrompt::ids($query['items'] ?? null);
        if ($ids === []) {
            return '/backlog';
        }
        $tidy = self::startHref($ids, BacklogPrompt::mode($query['mode'] ?? null), $path);
        return $requestUri === $tidy ? null : $tidy;
    }

    /** @param list<string> $ids */
    public static function startHref(array $ids, string $mode, string $path = '/backlog/start'): string
    {
        return $path . '?items=' . implode(',', $ids) . ($mode === BacklogPrompt::MODES[0] ? '' : '&mode=' . $mode);
    }

    /**
     * @param array<string, mixed> $query a tidy start request: at least one ID-shaped word
     * @param array<string, mixed>|null $viewer the viewer the thread page would recognise (session, else identity hint);
     *     it only decides whether a Claim form is drawn, since the claim itself is a signed post
     */
    public function start(array $query, ?array $viewer = null): string
    {
        $ids = BacklogPrompt::ids($query['items'] ?? null);
        $mode = BacklogPrompt::mode($query['mode'] ?? null);
        $all = $this->items();
        $picked = BacklogPrompt::pick($all, $ids);
        $openIds = array_map(static fn (FeedbackItem $item): string => $item->id(), $picked['items']);
        $claims = $this->claims($all);
        [$viewerId, $viewerIsOperator] = $this->viewer($viewer, $claims);
        $modes = [];
        foreach (['auto' => 'Agent decides', 'one-shot' => 'One-shot', 'staged' => 'Staged cycle'] as $key => $label) {
            $modes[] = ['key' => $key, 'label' => $label, 'href' => self::startHref($ids, $key), 'is_active' => $mode === $key];
        }

        return $this->routeServices->renderPageTemplate('backlog_start.php', [
            'backlogStartItems' => array_map(static fn (FeedbackItem $item): array => [
                'id' => $item->id(),
                'title' => $item->header('Title'),
                'claim' => $claims[$item->id()] ?? null,
                'release_post' => BacklogClaims::releasable($claims[$item->id()] ?? null, $viewerId, $viewerIsOperator),
            ], $picked['items']),
            // Null on a site with no claims thread: the page then says nothing about claims.
            'backlogClaimsThread' => $claims === null ? null : SiteProfileRegistry::backlogClaimsThread(),
            'backlogViewerId' => $viewerId,
            'backlogStartUnknown' => $picked['unknown'],
            'backlogStartClosed' => $picked['closed'],
            'backlogStartModes' => $modes,
            'backlogStartPrompt' => $picked['items'] === [] ? null : [
                'id' => 'backlog-start',
                'label' => 'Prompt',
                'text' => BacklogPrompt::build($picked['items'], $mode, SourceRepository::fromProjectRoot($this->projectRoot), $this->claimsAddress($claims, $openIds)),
            ],
            'backlogStartTextHref' => $openIds === [] ? null : self::startHref($openIds, $mode, '/backlog/start.txt'),
        ], 'Start', 'tools', $claims === null ? [] : self::CLAIM_SCRIPTS);
    }

    /**
     * /backlog.txt: the list as Key: value text, byte for byte what
     * ./v3 feedback list prints for the same words.
     *
     * @param array<string, mixed> $query
     */
    public function listText(array $query): string
    {
        $words = FeedbackBacklog::words($query);
        $all = $this->items();
        $claims = $this->claims($all);
        $items = FeedbackBacklog::sort($this->withoutClaimed(FeedbackBacklog::select($all, $words['show'], $words['kind']), $words, $claims), $words['sort'], $words['order']);
        return BacklogText::render($items, $words, ReadModelMetadata::repositoryHead($this->projectRoot), $claims);
    }

    /**
     * /backlog/start.txt: the prompt for the named items, as ./v3 feedback
     * start prints it. Items left out are named in the X-Left-Out header.
     *
     * @param array<string, mixed> $query
     * @return array{status: int, text: string, headers: list<string>}
     */
    public function startText(array $query): array
    {
        $ids = BacklogPrompt::ids($query['items'] ?? null);
        $mode = $query['mode'] ?? BacklogPrompt::MODES[0];
        if ($ids === [] || !is_string($mode) || !in_array($mode, BacklogPrompt::MODES, true)) {
            return ['status' => 400, 'text' => "Name at least one item and, to set it, the mode (auto, one-shot or staged; auto lets the agent choose):\n/backlog/start.txt?items=PF-YYYYMMDD-F01,PF-YYYYMMDD-B02&mode=staged\nThe list is at /backlog.txt.\n", 'headers' => []];
        }
        $all = $this->items();
        $picked = BacklogPrompt::pick($all, $ids);
        $leftOut = array_merge($picked['unknown'], $picked['closed']);
        $headers = $leftOut === [] ? [] : ['X-Left-Out: ' . implode(',', $leftOut)];
        if ($picked['items'] === []) {
            return ['status' => 404, 'text' => "Nothing to start: no open item among " . implode(', ', $ids) . ".\nThe list is at /backlog.txt.\n", 'headers' => $headers];
        }
        // Held items are named, as left-out ones are, and the prompt is returned all the same.
        $claims = $this->claims($all);
        $openIds = array_map(static fn (FeedbackItem $item): string => $item->id(), $picked['items']);
        $held = [];
        foreach ($openIds as $id) {
            if (BacklogClaims::isHeld($claims[$id] ?? null)) {
                $held[] = $id . ' by ' . preg_replace('/[^\x20-\x7E]|[,;]/', '?', $claims[$id]['holder']['name']) . ' since ' . $claims[$id]['holder']['since'];
            }
        }
        if ($held !== []) {
            $headers[] = 'X-Claimed: ' . implode(', ', $held);
        }
        return ['status' => 200, 'text' => BacklogPrompt::build($picked['items'], $mode, SourceRepository::fromProjectRoot($this->projectRoot), $this->claimsAddress($claims, $openIds)), 'headers' => $headers];
    }

    /**
     * /backlog/claims.txt?items=A,B: who holds the named open items.
     *
     * @param array<string, mixed> $query
     * @return array{status: int, text: string, headers: list<string>}
     */
    public function claimsText(array $query): array
    {
        $all = $this->items();
        $claims = $this->claims($all);
        $threadId = SiteProfileRegistry::backlogClaimsThread();
        if ($claims === null || $threadId === null) {
            return ['status' => 404, 'text' => "This site keeps no backlog claims. Look for a branch or pull request that names the item instead.\nThe list is at /backlog.txt.\n", 'headers' => []];
        }
        $ids = BacklogPrompt::ids($query['items'] ?? null);
        if ($ids === []) {
            return ['status' => 400, 'text' => "Name at least one item:\n/backlog/claims.txt?items=PF-YYYYMMDD-F01,PF-YYYYMMDD-B02\nThe list, with every item's claim, is at /backlog.txt.\n", 'headers' => []];
        }
        $picked = BacklogPrompt::pick($all, $ids);
        $leftOut = array_merge($picked['unknown'], $picked['closed']);

        return [
            'status' => 200,
            'text' => BacklogText::claims(array_map(static fn (FeedbackItem $item): string => $item->id(), $picked['items']), $claims, $threadId),
            'headers' => $leftOut === [] ? [] : ['X-Left-Out: ' . implode(',', $leftOut)],
        ];
    }

    /**
     * The address a prompt names for these items' claims, or null where there is none to name.
     *
     * @param array<string, array<string, mixed>>|null $claims
     * @param list<string> $ids
     */
    private function claimsAddress(?array $claims, array $ids): ?string
    {
        return $claims === null || $ids === [] ? null : \ForumRewrite\View\TemplateRenderer::requestOrigin() . '/backlog/claims.txt?items=' . implode(',', $ids);
    }

    /**
     * Who holds what, read from the site's claims thread now. Null when the site
     * names no claims thread: the backlog then shows and prints nothing about
     * claims. A named thread that does not exist yet gives no claims.
     *
     * @param list<FeedbackItem> $items
     * @return array<string, array<string, mixed>>|null
     */
    public function claims(array $items): ?array
    {
        $threadId = SiteProfileRegistry::backlogClaimsThread();
        if ($threadId === null) {
            return null;
        }
        $pdo = $this->routeServices->pdo();
        $thread = ThreadRepository::byId($pdo, $threadId);
        if ($thread === null || ($thread['retracted_at'] ?? null) !== null) {
            return [];
        }
        $repositoryRoot = $this->routeServices->repositoryRoot();

        return BacklogClaims::fromPosts(
            ThreadRepository::replyPostsByThreadId($pdo, $threadId),
            $items,
            time(),
            static fn (string $identityId): bool => \ForumRewrite\Security\ApprovalPolicy::isActiveOperator($repositoryRoot, $identityId),
        );
    }

    /**
     * The viewer's identity and whether they are an operator, for drawing Claim
     * and Release. Nobody on a site with no claims thread.
     *
     * @param array<string, mixed>|null $viewer
     * @param array<string, array<string, mixed>>|null $claims
     * @return array{0: string, 1: bool}
     */
    private function viewer(?array $viewer, ?array $claims): array
    {
        $identity = $claims === null ? '' : (string) ($viewer['identity_id'] ?? '');

        return [$identity, $identity !== '' && \ForumRewrite\Security\ApprovalPolicy::isActiveOperator($this->routeServices->repositoryRoot(), $identity)];
    }

    /**
     * @param list<FeedbackItem> $items
     * @param array<string, mixed> $words
     * @param array<string, array<string, mixed>>|null $claims
     * @return list<FeedbackItem>
     */
    private function withoutClaimed(array $items, array $words, ?array $claims): array
    {
        if ($claims === null || $words['claimed'] !== 'hide') {
            return $items;
        }

        return array_values(array_filter($items, static fn (FeedbackItem $item): bool => !BacklogClaims::isHeld($claims[$item->id()] ?? null)));
    }

    /** @return list<FeedbackItem> */
    public function items(): array
    {
        return self::load($this->projectRoot);
    }

    /** @return list<FeedbackItem> items that pass the check; a checkout with no items folder gives none */
    public static function load(string $projectRoot): array
    {
        $directory = $projectRoot . '/docs/feedback/items';
        return is_dir($directory) ? FeedbackItemCheck::run($directory)->items : [];
    }

    /**
     * The address for these words, leaving out every word at its default.
     *
     * @param array<string, mixed> $words
     * @param array<string, string> $change
     */
    public static function href(array $words, array $change = [], string $path = '/backlog'): string
    {
        $query = [];
        foreach (FeedbackBacklog::DEFAULTS as $name => $default) {
            $value = $change[$name] ?? $words[$name] ?? $default;
            if ($value !== $default) {
                $query[] = $name . '=' . rawurlencode((string) $value);
            }
        }
        return $path . ($query === [] ? '' : '?' . implode('&', $query));
    }

    /**
     * A sort link switches to that sort, newest first. The active sort's link flips its order.
     *
     * @param array<string, mixed> $words
     * @return list<array{key: string, label: string, href: string, is_active: bool, order: string}>
     */
    private function sortOptions(array $words): array
    {
        $options = [];
        foreach (self::SORT_LABELS as $key => $label) {
            $active = $words['sort'] === $key;
            $options[] = [
                'key' => $key,
                'label' => $label,
                'href' => self::href($words, ['sort' => $key, 'order' => $active && $words['order'] === 'newest' ? 'oldest' : 'newest']),
                'is_active' => $active,
                'order' => $active ? (string) $words['order'] : 'newest',
            ];
        }
        return $options;
    }

    /**
     * @param array<string, string> $labels
     * @param array<string, mixed> $words
     * @return list<array{key: string, label: string, href: string, is_active: bool}>
     */
    private function options(string $name, array $labels, array $words): array
    {
        $options = [];
        // A workflow word outside the labelled ones (planned, in-progress, blocked) still gets a link while it is active.
        if (!isset($labels[$words[$name]])) {
            $labels[(string) $words[$name]] = ucfirst(str_replace('-', ' ', (string) $words[$name]));
        }
        foreach ($labels as $key => $label) {
            $options[] = ['key' => $key, 'label' => $label, 'href' => self::href($words, [$name => $key]), 'is_active' => $words[$name] === $key];
        }
        return $options;
    }
}
