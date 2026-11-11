<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;

/**
 * The When box in the composer: hidden unless the flag is on, and Fill without
 * JavaScript returns the composer filled, with the draft kept and nothing posted.
 */
final class EventPhraseComposeTest
{
    public function testWhenBoxNeedsTheFlag(): void
    {
        [, $application] = $this->environment(false);
        eventPhraseComposeAssertNotContains('name="event_when"', $this->request($application, 'GET', '/compose/thread'));

        [, $application] = $this->environment(true);
        $html = $this->request($application, 'GET', '/compose/thread');
        eventPhraseComposeAssertContains('name="event_when"', $html);
        eventPhraseComposeAssertContains('name="event_when_fill"', $html);
    }

    public function testFillWithoutJavaScriptFillsFieldsAndKeepsTheDraft(): void
    {
        [$root, $application] = $this->environment(true);
        $head = $this->head($root);
        $tomorrow = gmdate('Y-m-d', time() + 86400);

        $html = $this->request($application, 'POST', '/compose/thread', [
            'body' => 'Board games', 'subject' => 'Games night', 'board_tags' => 'general',
            'event_when' => 'tomorrow', 'event_when_fill' => '1', 'event_location' => 'Kitchen',
        ]);

        eventPhraseComposeAssertContains('name="event_date" value="' . $tomorrow . '"', $html);
        eventPhraseComposeAssertContains('name="event_when" maxlength="120" autocomplete="off" placeholder="next Friday at 7" value="tomorrow"', $html);
        eventPhraseComposeAssertContains('Board games', $html);
        eventPhraseComposeAssertContains('value="Games night"', $html);
        eventPhraseComposeAssertContains('name="event_location" value="Kitchen"', $html);
        eventPhraseComposeAssertContains('Read with today as ' . gmdate('Y-m-d') . ' (UTC).', $html);
        eventPhraseComposeAssertContains('data-compose-disclosure="event" open', $html);
        eventPhraseComposeAssertSame($head, $this->head($root));
    }

    public function testUnreadPhraseChangesNothing(): void
    {
        [$root, $application] = $this->environment(true);
        $head = $this->head($root);

        $html = $this->request($application, 'POST', '/compose/thread', [
            'body' => 'Keep me', 'event_when' => 'whenever works', 'event_when_fill' => '1', 'event_date' => '2027-02-03',
        ]);

        eventPhraseComposeAssertContains('Could not read a date from that.', $html);
        eventPhraseComposeAssertContains('name="event_date" value="2027-02-03"', $html);
        eventPhraseComposeAssertContains('Keep me', $html);
        eventPhraseComposeAssertSame($head, $this->head($root));
    }

    public function testFixedDateStaysFixedAcrossFill(): void
    {
        [, $application] = $this->environment(true);

        $html = $this->request($application, 'POST', '/compose/thread', [
            'body' => 'Spare room', 'event_when' => '2027-05-01', 'event_when_fill' => '1',
            'event_date' => '2027-04-20', 'event_date_fixed' => '1',
        ]);

        eventPhraseComposeAssertContains('name="event_date" value="2027-04-20" readonly', $html);
        eventPhraseComposeAssertContains('The date stays 2027-04-20 on this page.', $html);
        eventPhraseComposeAssertContains('name="event_date_fixed" value="1"', $html);
    }

    /** @return array{string, Application} */
    private function environment(bool $when): array
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-event-phrase-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $root);
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
    private function request(Application $application, string $method, string $path, array $post = []): string
    {
        $_POST = $post;
        http_response_code(200);
        ob_start();
        try {
            $application->handle($method, $path);
        } finally {
            $html = (string) ob_get_clean();
            $_POST = [];
        }

        return $html;
    }

    private function head(string $root): string
    {
        return trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && git rev-parse HEAD'));
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

function eventPhraseComposeAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Expected to find ' . var_export($needle, true));
    }
}

function eventPhraseComposeAssertNotContains(string $needle, string $haystack): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException('Did not expect ' . var_export($needle, true));
    }
}

function eventPhraseComposeAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
