<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/TestViewerSession.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Canonical\RemovalReceipt;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Retraction\HistoryPurge;
use ForumRewrite\Retraction\HistoryPurgePlanner;

final class SourceRouteCommitMapTest
{
    private const AUTHOR = 'openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const GONE_ID = 'quote-20260101000000-eeee0001';
    private const KEPT_ID = 'quote-20260102000000-eeee0002';

    public function testOldSourceLinksResolveThroughTheMapAfterAPurge(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            $git = static fn (array $args): string => LabelRemoval::git($root, $args);
            $gone = CanonicalPathResolver::datedPost(self::GONE_ID, '2026-01-01T00:00:00Z');
            $kept = CanonicalPathResolver::datedPost(self::KEPT_ID, '2026-01-02T00:00:00Z');
            $workspace->put($root . '/' . $gone, "Post-ID: " . self::GONE_ID . "\nCreated-At: 2026-01-01T00:00:00Z\nAuthor-Identity-ID: " . self::AUTHOR . "\nBoard-Tags: general\nSubject: secret\n\nsecret prose\n");
            $git(['add', '-A']); $git(['commit', '-q', '-m', 'secret post']);
            $workspace->put($root . '/' . $kept, "Post-ID: " . self::KEPT_ID . "\nCreated-At: 2026-01-02T00:00:00Z\nAuthor-Identity-ID: " . self::AUTHOR . "\nBoard-Tags: general\nSubject: kept\n\nsurviving prose\n");
            $git(['add', '-A']); $git(['commit', '-q', '-m', 'kept post']);
            $oldKeptCommit = trim($git(['rev-parse', 'HEAD']));
            $keptBytes = file_get_contents($root . '/' . $kept);
            $source = file_get_contents($root . '/' . $gone);
            unlink($root . '/' . $gone);
            $workspace->put($root . '/records/post-removals/' . self::GONE_ID . '.txt', RemovalReceipt::encode(['Format' => 'removal-acceptance-v1',
                'Post-ID' => self::GONE_ID, 'Operation' => 'delete', 'Authority' => 'local-operator', 'Accepted-At' => '2026-10-30T00:00:00Z',
                'Actor' => 'local-operator', 'Source-SHA256' => hash('sha256', $source), 'Thread-ID' => self::GONE_ID, 'Parent-ID' => '',
                'Post-Created-At' => '2026-01-01T00:00:00Z', 'Author-Identity-ID' => self::AUTHOR, 'Thread-Type' => '',
                'Retraction-Class' => '', 'Retraction-Attribution' => '', 'Request-SHA256' => '', 'Signature-SHA256' => '']));
            $git(['add', '-A']); $git(['commit', '-q', '-m', 'delete secret']);
            $oldHead = trim($git(['rev-parse', 'HEAD']));
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();

            // Before the purge the old identities are live.
            assertSame($keptBytes, $this->render($root, $db, '/source/blob/' . $oldKeptCommit . '/' . $kept));
            assertSame(200, http_response_code());

            $result = HistoryPurge::run($root, $db, HistoryPurgePlanner::plan($root));
            assertSame(true, $result['verified']);
            $newKeptCommit = $result['map'][$oldKeptCommit];
            assertSame(true, $newKeptCommit !== $oldKeptCommit);

            // An old blob link for a surviving file resolves to the same bytes.
            assertSame($keptBytes, $this->render($root, $db, '/source/blob/' . $oldKeptCommit . '/' . $kept));
            assertSame(200, http_response_code());
            assertSame($keptBytes, $this->render($root, $db, '/source/blob/' . strtoupper($oldKeptCommit) . '/' . $kept));
            assertSame(200, http_response_code());
            // The new identity still works directly.
            assertSame($keptBytes, $this->render($root, $db, '/source/blob/' . $newKeptCommit . '/' . $kept));
            assertSame(200, http_response_code());
            // An old commit link shows the mapped commit and names the identity that was asked for.
            $details = $this->render($root, $db, '/source/commits/' . $oldHead);
            assertSame(200, http_response_code());
            assertSame(true, str_starts_with($details, 'Commit: ' . $result['map'][$oldHead] . "\nRequested-Commit: " . $oldHead . "\nAuthor-Date: "));
            assertSame(true, str_contains($details, "\nFiles:\n"));
            $direct = $this->render($root, $db, '/source/commits/' . $result['map'][$oldHead]);
            assertSame(200, http_response_code());
            assertSame(false, str_contains($direct, 'Requested-Commit:'));
            // A purged path returns the deletion notice at the old identity, the new identity and the current path, never prose.
            foreach (['/source/blob/' . $oldKeptCommit . '/' . $gone, '/source/blob/' . $newKeptCommit . '/' . $gone, '/source/current/' . $gone] as $url) {
                $body = $this->render($root, $db, $url);
                assertSame(410, http_response_code());
                assertSame(false, str_contains($body, 'secret prose'));
            }
            // Unknown and malformed identities are unchanged.
            $this->render($root, $db, '/source/blob/' . str_repeat('0', 40) . '/' . $kept);
            assertSame(404, http_response_code());
            $this->render($root, $db, '/source/commits/' . str_repeat('0', 40));
            assertSame(404, http_response_code());
            $this->render($root, $db, '/source/blob/not-a-sha/' . $kept);
            assertSame(400, http_response_code());
            $this->render($root, $db, '/source/commits/not-a-sha');
            assertSame(400, http_response_code());
            // The record itself is served as a canonical source file.
            $recordBody = $this->render($root, $db, '/source/current/' . $result['record_paths'][0]);
            assertSame(200, http_response_code());
            assertSame(true, str_starts_with($recordBody, "Format: history-rewrite-v1\n"));
        });
    }

    private function fixture(callable $test): void
    {
        $workspace = new ImportTestWorkspace();
        $root = $workspace->repository('repository', true);
        $db = $workspace->root . '/read.sqlite3';
        $flag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        $cookies = $_COOKIE;
        TestViewerSession::holdOpen();
        $_COOKIE = [];
        putenv('FORUM_APPROVED_MEMBERS_ONLY=false');
        try {
            $workspace->git($root);
            $test($workspace, $root, $db);
        } finally {
            TestViewerSession::reset(); $_COOKIE = $cookies;
            putenv($flag === false ? 'FORUM_APPROVED_MEMBERS_ONLY' : 'FORUM_APPROVED_MEMBERS_ONLY=' . $flag);
        }
    }

    private function render(string $root, string $db, string $url): string
    {
        http_response_code(200);
        ob_start();
        try {
            (new Application(dirname(__DIR__), $root, $db))->handle('GET', $url);
            return (string) ob_get_contents();
        } finally { ob_end_clean(); }
    }
}
