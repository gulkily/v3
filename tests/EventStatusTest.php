<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Calendar\EventStatus;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Support\EventStatusDirective;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Write\LocalWriteService;

final class EventStatusTest
{
    /** The fixture's committed root-approved operator. */
    private const OPERATOR = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    /** An approved member who is not an operator; given a read-model profile by the test. */
    private const MEMBER = 'openpgp:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testExtractReadsOnlyUnquotedConfirmedOrDeclinedLines(): void
    {
        eventStatusAssertSame('confirmed', EventStatusDirective::extract("Works for me.\nevent: confirmed\nsee you"));
        eventStatusAssertSame('declined', EventStatusDirective::extract("  EVENT:   Declined  "));
        eventStatusAssertSame('confirmed', EventStatusDirective::extract("event: confirmed\nevent: declined"));
        eventStatusAssertSame('declined', EventStatusDirective::extract("> event: confirmed\nevent: declined"));
        eventStatusAssertSame(null, EventStatusDirective::extract('> event: confirmed'));
        eventStatusAssertSame(null, EventStatusDirective::extract('event: maybe'));
        eventStatusAssertSame(null, EventStatusDirective::extract('the event: confirmed'));
        eventStatusAssertSame(null, EventStatusDirective::extract('event: confirmed, probably'));
    }

    public function testLatestOperatorLineWinsAndOtherLinesAreIgnored(): void
    {
        [$root, $database, $service, $application] = $this->environment();
        $plain = $this->event($service, 'Plain', 'No settlement yet.');
        $byRoot = $this->event($service, 'Operator root', "Arranged offline.\nevent: confirmed", self::OPERATOR);
        $flipped = $this->event($service, 'Flipped', 'Couch offer');
        $this->reply($service, $flipped, 'event: confirmed', self::OPERATOR);
        $this->reply($service, $flipped, "Plans changed.\nevent: declined", self::OPERATOR);
        $quoted = $this->event($service, 'Quoted', 'Couch offer');
        $this->reply($service, $quoted, "> event: confirmed\nagreed", self::OPERATOR);
        $byMember = $this->event($service, 'Member', 'Couch offer');
        $memberReply = $this->reply($service, $byMember, 'event: confirmed', self::OPERATOR);
        $retracted = $this->event($service, 'Retracted', 'Couch offer');
        $retractedReply = $this->reply($service, $retracted, 'event: confirmed', self::OPERATOR);
        $unverified = $this->event($service, 'Unverified', 'Couch offer');
        $unverifiedReply = $this->reply($service, $unverified, 'event: confirmed', self::OPERATOR);
        $guest = $this->event($service, 'Guest', 'Couch offer');
        $this->reply($service, $guest, 'event: confirmed');

        $pdo = new PDO('sqlite:' . $database);
        $this->addApprovedMember($pdo);
        $pdo->prepare('UPDATE posts SET author_identity_id = ? WHERE post_id = ?')->execute([self::MEMBER, $memberReply]);
        $pdo->prepare("UPDATE posts SET retracted_at = '2026-11-04T12:00:00Z' WHERE post_id = ?")->execute([$retractedReply]);
        $pdo->prepare('UPDATE posts SET attribution_verified = 0 WHERE post_id = ?')->execute([$unverifiedReply]);

        $states = EventStatus::forThreads($pdo, $root, [$plain, $byRoot, $flipped, $quoted, $byMember, $retracted, $unverified, $guest]);
        eventStatusAssertSame([$byRoot => 'confirmed', $flipped => 'declined'], $states);
        eventStatusAssertSame([], EventStatus::forThreads($pdo, $root, []));

        $page = $this->render($application, '/threads/' . rawurlencode($flipped));
        eventStatusAssertContains('<p class="meta event-state" data-event-state="declined">Event: declined</p>', $page);
        eventStatusAssertNotContains('data-event-state', $this->render($application, '/threads/' . rawurlencode($plain)));
        eventStatusAssertNotContains('data-event-state', $this->render($application, '/threads/root-001'));
    }

    public function testOperatorLinesStopCountingOnceTheOperatorIsNoLongerApproved(): void
    {
        [$root, $database, $service] = $this->environment();
        $thread = $this->event($service, 'Couch', 'Couch offer');
        $this->reply($service, $thread, 'event: confirmed', self::OPERATOR);
        $pdo = new PDO('sqlite:' . $database);
        eventStatusAssertSame('confirmed', EventStatus::forThread($pdo, $root, $thread));

        $pdo->prepare('UPDATE profiles SET is_approved = 0 WHERE identity_id = ?')->execute([self::OPERATOR]);
        eventStatusAssertSame(null, EventStatus::forThread($pdo, $root, $thread));
    }

    /** @return array{string,string,LocalWriteService,Application} */
    private function environment(): array
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-event-status-repo-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $root);
        file_put_contents($root . '/records/instance/feature-flags.txt', "Schema: site-feature-flags-v1\n\nFORUM_EVENT_SUPPORT_ENABLED: true\n");
        foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git -c commit.gpgsign=false commit -m init'] as $command) {
            $this->run($root, $command);
        }
        $database = sys_get_temp_dir() . '/forum-rewrite-event-status-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifacts = sys_get_temp_dir() . '/forum-rewrite-event-status-public-' . bin2hex(random_bytes(6));
        mkdir($artifacts, 0777, true);
        $application = new Application(dirname(__DIR__), $root, $database, $artifacts);
        $this->render($application, '/');
        $service = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root), featureFlags: FeatureFlagEvaluator::forRepository($root));

        return [$root, $database, $service, $application];
    }

    private function event(LocalWriteService $service, string $subject, string $body, ?string $author = null): string
    {
        $input = ['board_tags' => 'couches', 'subject' => $subject, 'body' => $body, 'event_date' => '2026-11-10'];
        if ($author !== null) {
            $input['author_identity_id'] = $author;
        }

        return (string) $service->createThread($input)['thread_id'];
    }

    private function reply(LocalWriteService $service, string $threadId, string $body, ?string $author = null): string
    {
        $input = ['thread_id' => $threadId, 'parent_id' => $threadId, 'body' => $body];
        if ($author !== null) {
            $input['author_identity_id'] = $author;
        }

        return (string) $service->createReply($input)['post_id'];
    }

    private function addApprovedMember(PDO $pdo): void
    {
        $pdo->exec('CREATE TEMP TABLE member AS SELECT * FROM profiles WHERE identity_id = ' . $pdo->quote(self::OPERATOR));
        $columns = array_column($pdo->query('PRAGMA table_info(profiles)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ($columns as $column) {
            if ($column === 'identity_id') {
                $pdo->exec('UPDATE member SET identity_id = ' . $pdo->quote(self::MEMBER));
            } elseif (preg_match('/slug|username|token|fingerprint/', $column) === 1) {
                $pdo->exec("UPDATE member SET {$column} = 'member-' || {$column} WHERE {$column} IS NOT NULL");
            }
        }
        $pdo->exec('INSERT INTO profiles SELECT * FROM member');
    }

    private function render(Application $application, string $path): string
    {
        ob_start();
        $application->handle('GET', $path);

        return (string) ob_get_clean();
    }

    private function run(string $directory, string $command): void
    {
        exec('cd ' . escapeshellarg($directory) . ' && ' . $command . ' 2>&1', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $target = $destination . '/' . $items->getSubPathName();
            $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
        }
    }
}

function eventStatusAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Failed asserting identical values. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function eventStatusAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output contains: ' . $needle);
    }
}

function eventStatusAssertNotContains(string $needle, string $haystack): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output does not contain: ' . $needle);
    }
}
