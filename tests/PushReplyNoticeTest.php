<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Inbox\InboxMarkStore;
use ForumRewrite\Push\P256Key;
use ForumRewrite\Push\PushNotices;
use ForumRewrite\Push\PushSubscription;
use ForumRewrite\Push\PushSubscriptionStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Write\LocalWriteService;

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

/** A new reply records a push notice for the parent's author, under Unread's rule, and skipped kinds record none. */
final class PushReplyNoticeTest
{
    private const MEMBER = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    private string $tmp = '';
    private string $repository = '';
    private string $database = '';
    private PushSubscriptionStore $store;

    public function testAGuestReplyToAMembersPostRecordsOneNotice(): void
    {
        $writer = $this->environment();
        try {
            $thread = $writer->createThread(['board_tags' => 'general', 'subject' => 'Notice test', 'body' => 'Opening post.', 'author_identity_id' => self::MEMBER]);
            assertSame([], $this->store->pending(), 'a new thread notifies nobody');
            $writer->createReply(['thread_id' => $thread['thread_id'], 'parent_id' => $thread['thread_id'], 'body' => "## **Hello** from a [guest](https://example.com)\nSecond line."]);

            $pending = $this->store->pending();
            assertSame(1, count($pending));
            assertSame(['reply', 'ilyag', 'thread:' . $thread['thread_id'], 'guest', 'Hello from a guest', 'https://zenmemes.com'],
                [$pending[0]['kind'], $pending[0]['recipient'], $pending[0]['tag'], $pending[0]['actor'], $pending[0]['body'], $pending[0]['site']]);
            assertSame('/threads/' . $thread['thread_id'] . '#post-' . $pending[0]['source_id'], $pending[0]['url']);
        } finally {
            $this->cleanup();
        }
    }

    public function testOwnRepliesTagOnlyRepliesAndRepliesToGuestsRecordNone(): void
    {
        $writer = $this->environment();
        try {
            $thread = $writer->createThread(['board_tags' => 'general', 'subject' => 'Notice test', 'body' => 'Opening post.', 'author_identity_id' => self::MEMBER]);
            $writer->createReply(['thread_id' => $thread['thread_id'], 'parent_id' => $thread['thread_id'], 'body' => 'My own follow-up.', 'author_identity_id' => self::MEMBER]);
            $writer->createReply(['thread_id' => $thread['thread_id'], 'parent_id' => $thread['thread_id'], 'body' => '#like']);
            // reply-001 is a guest's: nobody to notify.
            $writer->createReply(['thread_id' => 'root-001', 'parent_id' => 'reply-001', 'body' => 'Answering a guest.']);
            assertSame([], $this->store->pending());
        } finally {
            $this->cleanup();
        }
    }

    public function testNoDeviceUnapprovedParentAndOldRepliesRecordNone(): void
    {
        $writer = $this->environment();
        try {
            $thread = $writer->createThread(['board_tags' => 'general', 'subject' => 'Notice test', 'body' => 'Opening post.', 'author_identity_id' => self::MEMBER]);
            $notices = $this->notices(fn (): int => time());
            $pdo = new PDO('sqlite:' . $this->database);

            $this->store->deleteForSession('session-1');
            $writer->createReply(['thread_id' => $thread['thread_id'], 'parent_id' => $thread['thread_id'], 'body' => 'Nobody is listening.']);
            assertSame([], $this->store->pending(), 'no device turned on');

            $this->device();
            $pdo->exec("UPDATE profiles SET is_approved = 0");
            $reply = $writer->createReply(['thread_id' => $thread['thread_id'], 'parent_id' => $thread['thread_id'], 'body' => 'An unapproved key does not speak for ilyag.']);
            assertSame([0, []], [$notices->recordReply($reply['post_id']), $this->store->pending()]);
            $pdo->exec("UPDATE profiles SET is_approved = 1");

            // A reply that is more than an hour old (a format change or a repair) never notifies.
            assertSame(0, $this->notices(fn (): int => time() + 7200)->recordReply($reply['post_id']));
            assertSame(1, $notices->recordReply($reply['post_id']));
            assertSame(0, $notices->recordReply($reply['post_id']), 'the same reply twice is one notice');
        } finally {
            $this->cleanup();
        }
    }

    public function testAFailingNoticeStillSavesThePost(): void
    {
        $writer = $this->environment(fail: true);
        try {
            $result = $writer->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => 'Saved even when push breaks.']);
            assertSame('ok', $result['status']);
        } finally {
            $this->cleanup();
        }
    }

    public function testTheReadCheckNeverCreatesAnUnreadMark(): void
    {
        $marks = new InboxMarkStore(new PDO('sqlite::memory:'), static fn (): string => '2026-11-24T12:00:00Z');
        assertSame(false, $marks->isCaughtUp('ilyag', 'replies', 'reply-1', '2026-11-24T11:00:00Z'), 'Unread never opened: nothing is read');
        assertSame(false, $marks->isCaughtUp('ilyag', 'replies', 'reply-1', '2026-11-24T11:00:00Z'), 'and the check left no mark behind');
        $marks->mark('ilyag', 'replies');
        assertSame(true, $marks->isCaughtUp('ilyag', 'replies', 'reply-1', '2026-11-24T11:00:00Z'));
        assertSame(false, $marks->isCaughtUp('ilyag', 'replies', 'reply-2', '2026-11-24T11:58:00Z'), 'inside the late-arrival window');
        $marks->catchUp('ilyag', 'replies', ['reply-2' => '2026-11-24T11:58:00Z']);
        assertSame(true, $marks->isCaughtUp('ilyag', 'replies', 'reply-2', '2026-11-24T11:58:00Z'));
    }

    public function testTheFirstPlainLineDropsMarkdownAndIsCut(): void
    {
        assertSame('Quoted words', PushNotices::firstPlainLine("\n\n> Quoted `words`\nmore"));
        assertSame('item one', PushNotices::firstPlainLine("```\n- item one"));
        assertSame(PushNotices::BODY_CHARACTERS, mb_strlen(PushNotices::firstPlainLine(str_repeat('é', 300))));
    }

    private function environment(bool $fail = false): LocalWriteService
    {
        $this->tmp = sys_get_temp_dir() . '/v3-push-reply-' . bin2hex(random_bytes(6));
        $this->repository = $this->tmp . '/repo';
        $this->database = $this->tmp . '/read.sqlite3';
        mkdir($this->repository, 0777, true);
        mkdir($this->tmp . '/public', 0777, true);
        exec('cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1') . '/. ' . escapeshellarg($this->repository));
        exec('cd ' . escapeshellarg($this->repository) . ' && git init -q && git config user.name "Forum Rewrite" && git config user.email "forum-rewrite@example.invalid" && git add . && git -c commit.gpgsign=false commit -q -m init 2>&1');
        ob_start();
        (new Application(dirname(__DIR__), $this->repository, $this->database, $this->tmp . '/public'))->handle('GET', '/');
        ob_end_clean();
        // The fixture's one key is named "guest", a name shared by every guest; give it a member's name.
        (new PDO('sqlite:' . $this->database))->exec("UPDATE profiles SET username = 'ilyag', username_token = 'ilyag'");
        $this->store = new PushSubscriptionStore(new PDO('sqlite:' . $this->tmp . '/push.sqlite3'));
        $this->device();

        return new LocalWriteService(
            $this->repository,
            $this->database,
            $this->tmp . '/public',
            new CanonicalRecordRepository($this->repository),
            featureFlags: FeatureFlagEvaluator::forRepository($this->repository),
            onPostPublished: $fail
                ? static function (string $postId): void { throw new RuntimeException('push store unavailable'); }
                : fn (string $postId): int => $this->notices(fn (): int => time())->recordReply($postId),
        );
    }

    private function notices(Closure $clock): PushNotices
    {
        return new PushNotices(new PDO('sqlite:' . $this->database), fn (): PushSubscriptionStore => $this->store, 'https://zenmemes.com', null, $clock);
    }

    private function device(): void
    {
        $this->store->save(new PushSubscription('https://web.push.apple.com/a', P256Key::rawPublicKey(P256Key::generate()), random_bytes(16)), 'ilyag', self::MEMBER, 'session-1', time());
    }

    private function cleanup(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmp));
    }
}
