<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\PostRecordParser;

require __DIR__ . '/../autoload.php';

/** Event-When: the poster's words, kept in the signed root record beside the dates they filled. */
final class EventWhenRecordTest
{
    public function testParserReadsThePhraseOnARootWithADate(): void
    {
        $record = (new PostRecordParser())->parse($this->root("Event-Date: 2026-11-13\nEvent-Time: 19:00\nEvent-When: Friday 7pm\n"));
        eventWhenAssertSame('Friday 7pm', $record->eventWhen);
        eventWhenAssertSame(null, (new PostRecordParser())->parse($this->root("Event-Date: 2026-11-13\n"))->eventWhen);
    }

    public function testParserRejectsAPhraseWithoutADateOnAReplyOrTooLong(): void
    {
        $this->assertRejected($this->root("Event-When: Friday 7pm\n"), 'Event-When requires Event-Date.');
        $this->assertRejected($this->root("Event-Date: 2026-11-13\nEvent-When: " . str_repeat('x', 121) . "\n"), 'Event-When must be 1 to 120 characters.');
        $this->assertRejected("Post-ID: r1\nCreated-At: 2026-11-04T12:00:00Z\nBoard-Tags: general\nThread-ID: t1\nParent-ID: t1\nEvent-When: Friday\n\nhi\n",
            'Replies must not include typed root header: Event-When');
    }

    public function testFormPostKeepsThePhraseWhenTheFlagIsOn(): void
    {
        [$root, $application] = $this->environment(true);
        $this->post($application, ['body' => 'Games', 'subject' => 'Games night', 'event_date' => '2027-01-08', 'event_time' => '19:00', 'event_when' => 'the first Friday in January, 7pm']);
        $record = $this->latestRecord($root);
        eventWhenAssertContains("Event-Date: 2027-01-08\nEvent-Time: 19:00\nEvent-When: the first Friday in January, 7pm\n", $record);
    }

    public function testFlagOffIgnoresThePhrase(): void
    {
        [$root, $application] = $this->environment(false);
        $this->post($application, ['body' => 'Games', 'event_date' => '2027-01-08', 'event_when' => 'Friday']);
        $record = $this->latestRecord($root);
        eventWhenAssertContains("Event-Date: 2027-01-08\n", $record);
        eventWhenAssertSame(false, str_contains($record, 'Event-When'));
    }

    public function testPhraseWithoutADateIsRefusedAndTheDraftKept(): void
    {
        [$root, $application] = $this->environment(true);
        $head = trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && git rev-parse HEAD'));
        $html = $this->post($application, ['body' => 'Keep me', 'event_when' => 'after the holidays']);
        eventWhenAssertSame(400, http_response_code());
        eventWhenAssertContains('Add a date for the event, or press Fill.', $html);
        eventWhenAssertContains('value="after the holidays"', $html);
        eventWhenAssertContains('Keep me', $html);
        eventWhenAssertSame($head, trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && git rev-parse HEAD')));
    }

    public function testReadModelKeepsThePhraseAcrossRebuildAndFromTheLastSchema(): void
    {
        [$root, $application] = $this->environment(true);
        $this->post($application, ['body' => 'Games', 'subject' => 'Games night', 'event_date' => '2027-01-08', 'event_time' => '19:00', 'event_when' => 'Friday 7pm']);
        $this->post($application, ['body' => 'Plain', 'subject' => 'Plain event', 'event_date' => '2027-01-09']);
        $database = $root . '.sqlite3';
        $columns = static function () use ($database): array {
            $rows = (new PDO('sqlite:' . $database))->query('SELECT subject, event_when FROM threads WHERE event_date IS NOT NULL ORDER BY subject')->fetchAll(PDO::FETCH_KEY_PAIR);
            return $rows;
        };
        $incremental = $columns();
        eventWhenAssertSame(['Games night' => 'Friday 7pm', 'Plain event' => null], $incremental);

        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, new \ForumRewrite\Canonical\CanonicalRecordRepository($root)))->rebuild();
        eventWhenAssertSame($incremental, $columns());

        $threadId = (string) (new PDO('sqlite:' . $database))->query("SELECT root_post_id FROM threads WHERE subject = 'Games night'")->fetchColumn();
        $page = $this->get($application, '/threads/' . rawurlencode($threadId));
        eventWhenAssertContains('📅 2027-01-08 at 19:00</span>', $page);
        eventWhenAssertContains('<span class="event-block__when"> · “Friday 7pm”</span>', $page);
        eventWhenAssertContains('event-block__when', $this->get($application, '/calendar/2027-01-08') . $this->get($application, '/threads/?view=all'));

        // A version 53 database (no column) is rebuilt at startup before the column is read.
        $pdo = new PDO('sqlite:' . $database);
        $pdo->exec('ALTER TABLE threads DROP COLUMN event_when');
        $pdo->exec("UPDATE metadata SET value = '53' WHERE key = 'schema_version'");
        $pdo = null;
        $fresh = new Application(dirname(__DIR__), $root, $database, $root . '-public');
        eventWhenAssertContains('“Friday 7pm”', $this->get($fresh, '/threads/' . rawurlencode($threadId)));
        eventWhenAssertSame(\ForumRewrite\ReadModel\ReadModelMetadata::SCHEMA_VERSION, (new PDO('sqlite:' . $database))->query("SELECT value FROM metadata WHERE key = 'schema_version'")->fetchColumn());
        eventWhenAssertSame($incremental, $columns());
    }

    public function testFlagOffHidesAStoredPhrase(): void
    {
        [$root, $application] = $this->environment(true);
        $this->post($application, ['body' => 'Games', 'subject' => 'Games night', 'event_date' => '2027-01-08', 'event_when' => 'Friday']);
        file_put_contents($root . '/records/instance/feature-flags.txt', "Schema: site-feature-flags-v1\n\nFORUM_EVENT_SUPPORT_ENABLED: true\nFORUM_EVENT_WHEN_ENABLED: false\n");
        $threadId = (string) (new PDO('sqlite:' . $root . '.sqlite3'))->query("SELECT root_post_id FROM threads WHERE subject = 'Games night'")->fetchColumn();
        $page = $this->get(new Application(dirname(__DIR__), $root, $root . '.sqlite3', $root . '-public'), '/threads/' . rawurlencode($threadId));
        eventWhenAssertContains('📅 2027-01-08</span>', $page);
        eventWhenAssertSame(false, str_contains($page, 'event-block__when'));
    }

    private function get(Application $application, string $path): string
    {
        http_response_code(200);
        ob_start();
        try {
            $application->handle('GET', $path);
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    private function root(string $eventHeaders): string
    {
        return "Post-ID: t1\nCreated-At: 2026-11-04T12:00:00Z\nBoard-Tags: general\nSubject: Games\n" . $eventHeaders . "\nhello\n";
    }

    private function assertRejected(string $contents, string $message): void
    {
        try {
            (new PostRecordParser())->parse($contents);
        } catch (CanonicalRecordParseException $exception) {
            eventWhenAssertSame($message, $exception->getMessage());
            return;
        }
        throw new RuntimeException('Expected rejection: ' . $message);
    }

    /** @return array{string, Application} */
    private function environment(bool $when): array
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-event-when-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/fixtures/parity_minimal_v1', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $target = $root . '/' . $items->getSubPathName();
            $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
        }
        file_put_contents($root . '/records/instance/feature-flags.txt', "Schema: site-feature-flags-v1\n\nFORUM_EVENT_SUPPORT_ENABLED: true\nFORUM_EVENT_WHEN_ENABLED: " . ($when ? 'true' : 'false') . "\n");
        foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git -c commit.gpgsign=false commit -m init'] as $command) {
            exec('cd ' . escapeshellarg($root) . ' && ' . $command . ' 2>&1', $output, $status);
            if ($status !== 0) {
                throw new RuntimeException('Command failed: ' . $command);
            }
        }
        $artifacts = $root . '-public';
        mkdir($artifacts, 0777, true);

        return [$root, new Application(dirname(__DIR__), $root, $root . '.sqlite3', $artifacts)];
    }

    /** @param array<string, string> $post */
    private function post(Application $application, array $post): string
    {
        $_POST = $post + ['board_tags' => 'general', 'return_to' => '', 'body_format' => 'plain'];
        http_response_code(200);
        ob_start();
        try {
            $application->handle('POST', '/compose/thread');
        } finally {
            $html = (string) ob_get_clean();
            $_POST = [];
        }

        return $html;
    }

    private function latestRecord(string $root): string
    {
        $latest = trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && git log -1 --name-only --format='));

        return (string) file_get_contents($root . '/' . strtok($latest, "\n"));
    }
}

function eventWhenAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function eventWhenAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Expected to find ' . var_export($needle, true) . ' in ' . substr($haystack, 0, 600));
    }
}
