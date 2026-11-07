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

    public function testForteKindViewsListFilterCountAndPageEachKind(): void
    {
        [$app, $pdo] = $this->environment();
        $pdo->exec('DELETE FROM activity');
        $insert = $pdo->prepare("INSERT INTO activity (created_at,kind,record_family,action_key,label,board_tags_json,author_label)
            VALUES (:at,:kind,:family,:key,:key,'[]','guest')");
        $kinds = ['post_reaction_add' => 45, 'thread' => 3, 'codex_handoff' => 2, 'profile_name_change' => 4];
        $i = 0;
        foreach ($kinds as $kind => $total) {
            for ($n = 0; $n < $total; $n++, $i++) {
                $insert->execute([
                    'at' => sprintf('2026-10-23T00:%02d:00Z', $i % 60),
                    'kind' => $kind,
                    'family' => $kind === 'profile_name_change' ? 'profile_name' : 'test',
                    'key' => sprintf('%s-%03d', $kind, $n),
                ]);
            }
        }

        $html = $this->render($app, '/forte/activity/?view=reaction');
        foreach (['All Activity', 'Threads', 'Replies', 'Reactions', 'Label Changes', 'Identity Keys', 'Invitations', 'Feature Flag Changes', 'Codex Handoffs', 'Bootstraps', 'Approvals'] as $label) {
            assertStringContains('<span>' . $label . '</span>', $html);
        }
        assertStringContains('<span>Reactions</span><span class="paned-folder-count">45</span>', $html);
        assertStringContains('<span>Threads</span><span class="paned-folder-count">3</span>', $html);
        assertStringContains('<span>Codex Handoffs</span><span class="paned-folder-count">2</span>', $html);
        assertStringContains('<span>All Activity</span><span class="paned-folder-count">50</span>', $html);
        assertSame(1, preg_match('/paned-folder-item--selected"\s+data-paned-activity-view="reaction"/', $html));
        assertSame(20, substr_count($html, 'data-paned-activity-view-reaction="1"'));
        assertStringNotContains('thread-000', $html);

        // Username changes are off by default: no entry, no leak, safe fallback.
        assertStringNotContains('Profile Changes', $html);
        assertStringNotContains('profile_name_change-', $this->render($app, '/forte/activity/?view=profile_name'));
        assertStringContains('aria-selected="true"', $this->render($app, '/forte/activity/?view=profile_name'));
        $leak = json_decode($this->render($app, '/api/forte_activity_page?rows_only=1&view=profile_name'), true);
        assertSame('', $leak['html']);

        $seen = [];
        $cursor = null;
        do {
            $page = json_decode($this->render($app, '/api/forte_activity_page?rows_only=1&view=reaction' . ($cursor === null ? '' : '&cursor=' . rawurlencode(json_encode($cursor)))), true);
            assertSame('ok', $page['status']);
            preg_match_all('/data-paned-activity-id="(\d+)"/', $page['html'], $ids);
            $seen = [...$seen, ...$ids[1]];
            assertStringNotContains('thread-0', $page['html']);
            $cursor = $page['next_cursor'];
        } while ($page['has_more']);
        assertSame(45, count(array_unique($seen)));

        $unknown = $this->render($app, '/forte/activity/?view=nonsense');
        assertStringContains('<span>All Activity</span>', $unknown);
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
        assertSame(100, count($service->fetchActivityPage('all', 1, 1000)['items']));
        assertSame('/activity/?view=content&page=2', $next);
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

    public function testTiedTimesTraverseInStableOrderAcrossReindexing(): void
    {
        [$app, $pdo, $service] = $this->environment();
        $pages = []; $number = 1;
        do { $pages[] = $page = $service->fetchActivityPage('all', $number); $number++; } while ($page['has_next']);
        assertSame(3, count($pages));
        $keys = array_merge(...array_map(static fn (array $page): array => array_column($page['items'], 'action_key'), $pages));
        assertSame(205, count($keys));
        assertSame(205, count(array_unique($keys)));
        $pdo->exec('UPDATE activity SET id=id+1000');
        assertSame(array_column($pages[1]['items'], 'action_key'), array_column($service->fetchActivityPage('all', 2)['items'], 'action_key'));
        $pdo->exec("INSERT INTO activity (created_at,kind,action_key,label,board_tags_json,author_label) VALUES ('2026-10-24T00:00:00Z','test','new-arrival','New arrival','[]','guest')");
        assertSame('new-arrival', $service->fetchActivityPage('all')['items'][0]['action_key']);
        assertSame([], $service->fetchActivityPage('all', 9)['items']);
    }

    public function testEmptyPagesAndMalformedNumbersHaveRecovery(): void
    {
        [$app, $pdo, $service] = $this->environment();
        $next = $this->nextLink($this->render($app, '/activity/?view=all'));
        assertSame('/activity/?page=2', $next, 'The default view is left out.');
        $pdo->exec("DELETE FROM activity WHERE action_key < 'record-105'");
        $empty = $this->render($app, $next);
        assertStringContains('No activity.', $empty);
        assertStringContains('>Previous</a>', $empty);
        assertStringContains('>First page</a>', $empty);
        foreach (['/activity/?page=garbage', '/activity/?page[]=x', '/activity/?position=old'] as $url) {
            $html = $this->render($app, $url);
            assertStringNotContains('role="alert"', $html);
            assertStringContains('record-204', $html, $url . ' shows page 1');
        }
        assertStringNotContains('?page=', $this->render($app, '/activity/?view=approval'));
    }

    public function testSourceLessEventsAreIncludedAndPagedWithoutExpiry(): void
    {
        [$app, $pdo, $service] = $this->environment();
        $pdo->exec("INSERT INTO activity (created_at,kind,label,board_tags_json,author_label) VALUES ('2026-10-24T00:00:00Z','codex_handoff','Local handoff','[]','guest')");
        $first = $service->fetchActivityPage('all');
        assertSame('Local handoff', $first['items'][0]['label']);
        $next = $this->nextLink($this->render($app, '/activity/'));
        assertTrue(is_string($next));
        $pdo->exec('UPDATE activity SET id=id+1000 WHERE action_key IS NULL');
        $later = $this->render($app, $next);
        assertStringNotContains('role="alert"', $later);
        assertStringContains('record-104', $later);
        assertStringContains('Local handoff', $this->render($app, '/activity/'));
    }

    public function testPagesSurviveFullRebuildWithDifferentRowIds(): void
    {
        $root = sys_get_temp_dir() . '/activity-rebuild-' . bin2hex(random_bytes(6));
        mkdir($root, 0700);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/fixtures/parity_minimal_v1', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $file) {
            $destination = $root . '/' . $iterator->getSubPathname();
            $file->isDir() ? mkdir($destination, 0700, true) : copy($file->getPathname(), $destination);
        }
        for ($i = 0; $i < 205; $i++) {
            $id = sprintf('page-%03d', $i);
            file_put_contents($root . '/records/posts/' . $id . '.txt', "Post-ID: $id\nCreated-At: 2026-10-23T00:00:00Z\nBoard-Tags: general\nSubject: $id\n\nPage body.\n");
        }
        $database = $root . '/read.sqlite3';
        $repository = new \ForumRewrite\Canonical\CanonicalRecordRepository($root);
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, $repository))->rebuild();
        $pdo = new PDO('sqlite:' . $database);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $service = new ActivityService(static fn (): PDO => $pdo, $root, $database);
        $pdo->exec('UPDATE activity SET id=id+1000');
        $expected = array_column($service->fetchActivityPage('all', 2)['items'], 'action_key');
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, $repository))->rebuild();
        assertSame($expected, array_column($service->fetchActivityPage('all', 2)['items'], 'action_key'));
    }

    public function testActualStaticPublicationAndPageRoutingDoNotSubstituteFirstPage(): void
    {
        [$app, $pdo, $service, $database] = $this->environment();
        $root = sys_get_temp_dir() . '/activity-serving-' . bin2hex(random_bytes(6));
        mkdir($root . '/public', 0700, true);
        mkdir($root . '/static', 0700, true);
        $repository = __DIR__ . '/fixtures/parity_minimal_v1';
        $builder = new \ForumRewrite\Host\StaticArtifactBuilder(dirname(__DIR__), $repository, $database, $root . '/public');
        assertTrue($builder->buildSingleRoute('/activity/'));
        $published = (string) file_get_contents($root . '/public/activity.html');
        assertStringContains('record-204', $published);
        $next = $this->nextLink($published);
        assertTrue(is_string($next));
        assertSame(false, $builder->buildSingleRoute($next));
        $controller = new \ForumRewrite\Host\FrontController(dirname(__DIR__), $repository, $database, $root . '/static', $root . '/public');
        $render = static function (string $url, array $cookies = []) use ($controller): string {
            ob_start();
            $controller->handle('GET', $url, $cookies);
            return (string) ob_get_clean();
        };
        assertStringContains('record-204', $render('/activity/'));
        foreach ([[], ['theme_hint' => 'light']] as $cookies) {
            $second = $render($next, $cookies);
            assertStringContains('record-104', $second);
            assertStringNotContains('record-204', $second);
        }
        assertStringContains('record-004', $render('/activity/?view=bootstrap'));
        $denied = $render('/activity/?view=security');
        assertStringContains('Sign in with your operator account to view access history.', $denied);
        assertStringNotContains('record-204', $denied);
        (new \ForumRewrite\Write\StaticArtifactInvalidator($root . '/public'))->invalidateReply('root-001', 'reply-001');
        assertSame(false, is_file($root . '/public/activity.html'));
        assertStringContains('record-204', $render('/activity/'));
    }

    public function testCurrentVisibilityAndSecurityEnrichmentApplyToStandardPages(): void
    {
        [$app, $pdo, $service, $database] = $this->environment();
        $pdo->exec("UPDATE activity SET post_id='root-001' WHERE action_key='record-204'");
        $pdo->exec("UPDATE posts SET retracted_at='2026-10-23T01:00:00Z' WHERE post_id='root-001'");
        assertSame('record-203', $service->fetchActivityPage('content')['items'][0]['action_key']);
        $pdo->exec("UPDATE activity SET kind='approval' WHERE action_key='record-203'");
        $secure = new ActivityService(static fn (): PDO => $pdo, __DIR__ . '/fixtures/parity_minimal_v1', $database, false, static fn (): bool => true);
        $securityPage = $secure->fetchActivityPage('security');
        assertSame(1, count($securityPage['items']));
        assertTrue(array_key_exists('security_review', $securityPage['items'][0]));
        try {
            $service->fetchActivityPage('security');
            throw new RuntimeException('Unauthenticated security page was allowed.');
        } catch (RuntimeException $error) {
            assertSame('Authenticated root-approved operator access required.', $error->getMessage());
        }
    }

    public function testLargeSharedCommitDoesNotExpandEveryStandardItemOrWarmFullManifest(): void
    {
        $root = sys_get_temp_dir() . '/activity-bulk-' . bin2hex(random_bytes(6));
        mkdir($root, 0700);
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/Support/standard_activity_pagination_fixture.php') . ' ' . escapeshellarg($root) . ' 2>&1', $output, $code);
        assertSame(0, $code, implode("\n", $output));
        $database = $root . '/read.sqlite3';
        $pdo = new PDO('sqlite:' . $database);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $service = new ActivityService(static fn (): PDO => $pdo, $root . '/repository', $database);
        $page = $service->fetchActivityPage('all');
        assertSame(100, count($page['items']));
        assertSame([], (new ReflectionProperty($service, 'activityCommitManifestCache'))->getValue($service));
        $full = $service->fetchActivity('all')['items'];
        foreach ($page['items'] as $index => $item) {
            assertSame([], $item['source_commit_files']);
            assertSame(1, count($item['relevant_files']));
            assertSame('added', $item['relevant_files'][0]['status']);
            assertSame($item['source_path'], $item['relevant_files'][0]['path']);
            assertSame($full[$index]['relevant_files'], $item['relevant_files']);
            assertTrue(count($full[$index]['source_commit_files']) > 2300);
        }
        $app = new Application(dirname(__DIR__), $root . '/repository', $database, $root . '/static');
        $html = $this->render($app, '/activity/');
        assertTrue(strlen($html) < 500000);
        assertSame(100, substr_count($html, '<li>'));
        assertStringNotContains('records/notes/unrelated-2299.txt', $html);
        assertStringContains('rel="next"', $html);
        assertStringContains('/source/blob/' . $page['items'][0]['source_commit_sha'] . '/' . $page['items'][0]['source_path'], $html);
        $commit = $this->render($app, '/source/commits/' . $page['items'][0]['source_commit_sha']);
        assertStringContains('records/notes/unrelated-2299.txt', $commit);
    }

    public function testMissingHistoryPreservesBootstrapAndUnsignedEvidence(): void
    {
        $root = sys_get_temp_dir() . '/activity-no-history-' . bin2hex(random_bytes(6));
        mkdir($root, 0700);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/fixtures/parity_minimal_v1', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $file) {
            $destination = $root . '/' . $iterator->getSubPathname();
            $file->isDir() ? mkdir($destination, 0700, true) : copy($file->getPathname(), $destination);
        }
        file_put_contents($root . '/records/posts/bootstrap-evidence.txt', "Post-ID: bootstrap-evidence\nCreated-At: 2026-10-23T00:00:00Z\nBoard-Tags: identity internal\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\nIdentity evidence.\n");
        $database = sys_get_temp_dir() . '/activity-evidence-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $app = new Application(dirname(__DIR__), $root, $database);
        $html = $this->render($app, '/activity/');
        $pdo = new PDO('sqlite:' . $database);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $service = new ActivityService(static fn (): PDO => $pdo, $root, $database);
        $page = $service->fetchActivityPage('all')['items'];
        $existing = $service->fetchActivity('all')['items'];
        $byKey = array_column($existing, null, 'action_key');
        $bootstrap = false;
        foreach ($page as $item) {
            assertSame($byKey[$item['action_key']]['relevant_files'], $item['relevant_files']);
            assertSame($byKey[$item['action_key']]['source_signature_status'], $item['source_signature_status']);
            if ($item['record_family'] === 'identity_bootstrap') {
                $bootstrap = true;
                assertTrue(in_array('identity record', array_column($item['relevant_files'], 'role'), true));
            }
        }
        assertTrue($bootstrap);
        assertStringContains('Record signature: anonymous unsigned', $html);
        assertStringContains('commit unavailable', $html);
        assertStringContains('Relevant files (', $html);
        assertStringContains('/source/current/records/posts/root-001.txt', $html);
    }

    public function testNavigationContractIsIndependentOfActivity(): void
    {
        $links = PageNavigation::numbered('/example/', ['filter' => 'a & b'], 1, true);
        parse_str((string) parse_url($links['next_href'], PHP_URL_QUERY), $query);
        assertSame(['filter' => 'a & b', 'page' => '2'], $query);
        assertSame(null, $links['previous_href']);
        assertSame(null, $links['restart_href']);
        assertSame('/example/?filter=a%20%26%20b', PageNavigation::numbered('/example/', ['filter' => 'a & b'], 2, false, true)['previous_href']);
    }
}
