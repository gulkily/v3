<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Activity\ActivityService;
use ForumRewrite\Pagination\PageNavigation;

final class StandardActivityPaginationTest
{
    /** @return array{Application,PDO,ActivityService,string} */
    private function environment(): array
    {
        $root = __DIR__ . '/fixtures/parity_minimal_v1';
        $database = sys_get_temp_dir() . '/activity-pages-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $app = new Application(dirname(__DIR__), $root, $database);
        $this->render($app, '/api/read_model_status');
        $pdo = new PDO('sqlite:' . $database);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('DELETE FROM activity');
        $insert = $pdo->prepare("INSERT INTO activity (created_at,kind,record_family,action_key,label,board_tags_json,author_label)
            VALUES ('2026-10-23T00:00:00Z','test','test',:key,:key,:tags,'guest')");
        for ($i = 0; $i < 205; $i++) {
            $insert->execute(['key' => sprintf('record-%03d', $i), 'tags' => $i < 5 ? '["identity","internal"]' : '["general"]']);
        }
        return [$app, $pdo, new ActivityService(static fn (): PDO => $pdo, $root, $database), $database];
    }

    private function render(Application $app, string $url): string
    {
        ob_start();
        $app->handle('GET', $url);
        return (string) ob_get_clean();
    }

    private function nextLink(string $html): ?string
    {
        return preg_match('/rel="next" href="([^"]+)"/', $html, $match) === 1 ? html_entity_decode($match[1], ENT_QUOTES) : null;
    }

    public function testNormalStandardLinksReachBeyondFirstBatchAndKeepCategory(): void
    {
        [$app, $pdo, $service] = $this->environment();
        $first = $this->render($app, '/activity/?view=content');
        assertStringContains('record-204', $first);
        assertStringNotContains('record-104</p>', $first);
        $next = $this->nextLink($first);
        assertTrue(is_string($next));
        assertStringContains('view=content', $next);
        $second = $this->render($app, $next);
        assertStringContains('record-104', $second);
        assertStringContains('record-005', $second);
        assertSame(null, $this->nextLink($second));
        assertStringNotContains('record-004', $second);
        assertSame(100, count($service->fetchActivityPage('all', null, 'next', 1000)['items']));
    }

    public function testExistingForteAndRssContractsRemainUnchanged(): void
    {
        [$app, $pdo, $service] = $this->environment();
        assertSame(['items', 'has_more'], array_keys($service->fetchActivity('all')));
        assertSame(['items', 'has_more'], array_keys($service->fetchActivityRows('all', 'date', 'desc')));
        $rss = $this->render($app, '/activity/?view=all&format=rss');
        assertSame(100, substr_count($rss, '<item>'));
        assertStringContains('Load more', $this->render($app, '/forte/activity/'));
    }

    public function testNavigationContractIsIndependentOfActivity(): void
    {
        $links = PageNavigation::links('/example/', ['filter' => 'a & b'], 'example:a & b', null, ['key' => 'entry-9']);
        parse_str((string) parse_url($links['next_href'], PHP_URL_QUERY), $query);
        assertSame('a & b', $query['filter']);
        assertSame(['direction' => 'next', 'boundary' => ['key' => 'entry-9']], PageNavigation::decode($query['position'], 'example:a & b'));
        assertSame(null, $links['previous_href']);
    }
}
