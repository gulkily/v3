<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Feedback\BacklogPrompt;
use ForumRewrite\Feedback\BacklogText;
use ForumRewrite\Feedback\FeedbackBacklog;
use ForumRewrite\Feedback\FeedbackItem;
use ForumRewrite\Feedback\FeedbackItemCheck;
use ForumRewrite\ReadModel\ReadModelMetadata;
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

    public function __construct(private readonly RouteServices $routeServices, private readonly string $projectRoot) {}

    /** @param array<string, mixed> $query */
    public function list(array $query): string
    {
        $words = FeedbackBacklog::words($query);
        $all = $this->items();
        $open = FeedbackBacklog::select($all, 'open', 'all');
        $items = FeedbackBacklog::sort(FeedbackBacklog::select($all, $words['show'], $words['kind']), $words['sort'], $words['order']);
        $repository = SourceRepository::fromProjectRoot($this->projectRoot);
        $fileBase = $repository !== null && str_starts_with($repository, 'https://github.com/') ? $repository . '/blob/main/docs/feedback/items/' : null;

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
            ], $items),
            'backlogOpenCount' => count($open),
            'backlogUnknownWords' => $words['unknown'],
            'backlogVersion' => substr(ReadModelMetadata::repositoryHead($this->projectRoot), 0, 12),
            'backlogSortOptions' => $this->sortOptions($words),
            'backlogShowOptions' => $this->options('show', self::SHOW_LABELS, $words),
            'backlogKindOptions' => $this->options('kind', self::KIND_LABELS, $words),
            'backlogTextHref' => self::href($words, [], '/backlog.txt'),
        ], 'Backlog', 'tools');
    }

    /**
     * The tidy address for a start request, when the request is not already in
     * that form. A plain form sends items[]=A&items[]=B; the page lives at
     * ?items=A,B, with the mode named only when it is not one-shot.
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

    /** @param array<string, mixed> $query a tidy start request: at least one ID-shaped word */
    public function start(array $query): string
    {
        $ids = BacklogPrompt::ids($query['items'] ?? null);
        $mode = BacklogPrompt::mode($query['mode'] ?? null);
        $picked = BacklogPrompt::pick($this->items(), $ids);
        $openIds = array_map(static fn (FeedbackItem $item): string => $item->id(), $picked['items']);
        $modes = [];
        foreach (['one-shot' => 'One-shot', 'staged' => 'Staged cycle'] as $key => $label) {
            $modes[] = ['key' => $key, 'label' => $label, 'href' => self::startHref($ids, $key), 'is_active' => $mode === $key];
        }

        return $this->routeServices->renderPageTemplate('backlog_start.php', [
            'backlogStartItems' => array_map(static fn (FeedbackItem $item): array => ['id' => $item->id(), 'title' => $item->header('Title')], $picked['items']),
            'backlogStartUnknown' => $picked['unknown'],
            'backlogStartClosed' => $picked['closed'],
            'backlogStartModes' => $modes,
            'backlogStartPrompt' => $picked['items'] === [] ? null : [
                'id' => 'backlog-start',
                'label' => 'Prompt',
                'text' => BacklogPrompt::build($picked['items'], $mode, SourceRepository::fromProjectRoot($this->projectRoot)),
            ],
            'backlogStartTextHref' => $openIds === [] ? null : self::startHref($openIds, $mode, '/backlog/start.txt'),
        ], 'Start', 'tools');
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
        $items = FeedbackBacklog::sort(FeedbackBacklog::select($this->items(), $words['show'], $words['kind']), $words['sort'], $words['order']);
        return BacklogText::render($items, $words, ReadModelMetadata::repositoryHead($this->projectRoot));
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
            return ['status' => 400, 'text' => "Name at least one item and, if not one-shot, the mode:\n/backlog/start.txt?items=PF-YYYYMMDD-F01,PF-YYYYMMDD-B02&mode=staged\nThe list is at /backlog.txt.\n", 'headers' => []];
        }
        $picked = BacklogPrompt::pick($this->items(), $ids);
        $leftOut = array_merge($picked['unknown'], $picked['closed']);
        $headers = $leftOut === [] ? [] : ['X-Left-Out: ' . implode(',', $leftOut)];
        if ($picked['items'] === []) {
            return ['status' => 404, 'text' => "Nothing to start: no open item among " . implode(', ', $ids) . ".\nThe list is at /backlog.txt.\n", 'headers' => $headers];
        }
        return ['status' => 200, 'text' => BacklogPrompt::build($picked['items'], $mode, SourceRepository::fromProjectRoot($this->projectRoot)), 'headers' => $headers];
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
