<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

/**
 * Twenty-sixth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md
 * and docs/plans/activity_subsystem_extraction_plan_v1.md): the classic
 * /activity page and its /activity.rss feed - the last remaining piece of
 * the activity subsystem. An earlier dependency-graph investigation (see
 * the sub-plan doc) predicted this would be the most entangled of the four
 * page-shell handlers, needing several closures unrelated to activity data
 * itself; re-reading the actual current code found that prediction stale -
 * renderActivity()/renderActivityRss() only ever touched
 * normalizeActivityView()/fetchActivity() (now on ActivityService) and
 * renderPageTemplate()/sendXml() (already on RouteServices) plus RssFeed,
 * an already-standalone static class in this same namespace. Needed zero
 * closures - the cheapest of the four activity page-shell slices, not the
 * most expensive.
 */
final class ActivityPageController
{
    public function __construct(
        private readonly RouteServices $routeServices,
    ) {
    }

    public function board(string $view): string
    {
        $activityService = $this->routeServices->activityService();
        $view = $activityService->normalizeActivityView($view);

        return $this->routeServices->renderPageTemplate(
            'activity.php',
            [
                'view' => $view,
                'viewOptions' => [
                    [
                        'label' => 'All Activity',
                        'href' => '/activity/?view=all',
                        'is_active' => $view === 'all',
                    ],
                    [
                        'label' => 'Visible Content',
                        'href' => '/activity/?view=content',
                        'is_active' => $view === 'content',
                    ],
                    [
                        'label' => 'Identity',
                        'href' => '/activity/?view=identity',
                        'is_active' => $view === 'identity',
                    ],
                    [
                        'label' => 'Bootstraps',
                        'href' => '/activity/?view=bootstrap',
                        'is_active' => $view === 'bootstrap',
                    ],
                    [
                        'label' => 'Approvals',
                        'href' => '/activity/?view=approval',
                        'is_active' => $view === 'approval',
                    ],
                    [
                        'label' => 'RSS',
                        'href' => '/activity/?view=' . rawurlencode($view) . '&format=rss',
                        'is_active' => false,
                    ],
                ],
                'items' => $activityService->fetchActivity($view, 'date', 'desc')['items'],
            ],
            'Activity',
            'activity',
        );
    }

    public function rss(string $view): string
    {
        $activityService = $this->routeServices->activityService();
        $view = $activityService->normalizeActivityView($view);
        $items = [];
        foreach ($activityService->fetchActivity($view, 'date', 'desc')['items'] as $item) {
            $link = match ($item['kind']) {
                'thread_label_add' => '/threads/' . $item['thread_id'],
                'site_feature_flag' => '/tools/feature-flags/',
                default => '/posts/' . $item['post_id'],
            };
            $items[] = RssFeed::item($item['label'], $link, $item['kind'], (string) $item['created_at']);
        }

        return RssFeed::feed('Activity ' . $view, '/activity/?view=' . rawurlencode($view) . '&format=rss', $items);
    }
}
