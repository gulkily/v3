<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Qdb\QdbModeration;
use ForumRewrite\ReadModel\QdbPage;
use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\ReadModel\ThreadRepository;

final class QdbModerationTest
{
    private const START = '2026-11-17T12:00:00Z';
    private const OPERATOR = 'openpgp:' . 'a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1';
    private const VISITOR = 'openpgp:' . 'b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2';

    /** @return array{PDO, array<string, string>} */
    private function fixture(): array
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (ReadModelSchema::statements() as $sql) $pdo->exec($sql);
        $post = $pdo->prepare('INSERT INTO posts(post_id,thread_id,created_at,body,board_tags_json,author_identity_id,sequence_number) VALUES(?,?,?,?,?,?,?)');
        $thread = $pdo->prepare('INSERT INTO threads(root_post_id,root_post_created_at,last_activity_at,body_preview,reply_count,last_post_id,board_tags_json,thread_labels_json) VALUES(?,?,?,?,0,?,?,\'[]\')');
        $tag = $pdo->prepare("INSERT INTO viewer_tags(record_family,target_id,author_identity_id,tag) VALUES('thread-labels',?,?,?)");
        // name => [created_at, author, board tags, [tagger, tag] ...]
        $rows = [
            'archived' => ['2026-01-01T00:00:00Z', null, '["general"]', []],
            'anonymous' => ['2026-11-17T12:00:00Z', null, '["general"]', []],
            'visitor' => ['2026-11-17T13:00:00Z', self::VISITOR, '["general"]', []],
            'operator' => ['2026-11-17T13:00:00Z', self::OPERATOR, '["general"]', []],
            'news' => ['2026-11-17T13:00:00Z', self::VISITOR, '["news"]', []],
            'approved' => ['2026-11-17T13:00:00Z', null, '["general"]', [[self::OPERATOR, 'approved']]],
            'dashapproved' => ['2026-11-17T13:00:00Z', null, '["general"]', [['openpgp-' . substr(self::OPERATOR, 8), 'approved']]],
            'selfapproved' => ['2026-11-17T13:00:00Z', self::VISITOR, '["general"]', [[self::VISITOR, 'approved']]],
            'rejected' => ['2026-11-17T13:00:00Z', null, '["general"]', [[self::OPERATOR, 'rejected']]],
            'oldrejected' => ['2026-01-01T00:00:00Z', null, '["general"]', [[self::OPERATOR, 'rejected']]],
            'bothtags' => ['2026-11-17T13:00:00Z', null, '["general"]', [[self::OPERATOR, 'approved'], [self::OPERATOR, 'rejected']]],
        ];
        $ids = [];
        $number = 0;
        foreach ($rows as $name => [$createdAt, $author, $boardTags, $tags]) {
            $id = 'thread-20261117000000-qdb-' . ++$number;
            $ids[$name] = $id;
            $post->execute([$id, $id, $createdAt, 'Body ' . $name, $boardTags, $author, $number]);
            $thread->execute([$id, $createdAt, $createdAt, 'Preview ' . $name, $id, $boardTags]);
            foreach ($tags as [$tagger, $value]) $tag->execute([$id, $tagger, $value]);
        }
        // A non-quote thread on the same instance is never queued.
        $post->execute(['thread-plain', 'thread-plain', '2026-11-17T13:00:00Z', 'Plain', '["general"]', null, 99]);
        $thread->execute(['thread-plain', '2026-11-17T13:00:00Z', '2026-11-17T13:00:00Z', 'Plain', 'thread-plain', '["general"]']);
        $ids['plain'] = 'thread-plain';

        return [$pdo, $ids];
    }

    /** @param array<string, string> $ids @param list<array<string, mixed>> $threads @return list<string> */
    private static function names(array $ids, array $threads): array
    {
        $byId = array_flip($ids);
        $names = array_map(static fn (array $thread): string => $byId[$thread['root_post_id']], $threads);
        sort($names);

        return $names;
    }

    private static function active(): QdbModeration
    {
        return new QdbModeration(self::START, [self::OPERATOR]);
    }

    public function testListingsLeaveOutPendingAndRejectedQuotes(): void
    {
        [$pdo, $ids] = $this->fixture();
        QdbModeration::override(self::active());
        try {
            $listed = ['approved', 'archived', 'dashapproved', 'news', 'operator'];
            assertSame($listed, self::names($ids, QdbPage::page($pdo, 'latest', 1)['threads']));
            assertSame(count($listed), QdbPage::page($pdo, 'top', 1)['count']);
            assertSame([...$listed, 'plain'], self::names($ids, ThreadRepository::fetchThreads($pdo)));
            assertSame([], QdbPage::page($pdo, 'search', 1, 'Body anonymous')['threads']);
        } finally {
            QdbModeration::override(null);
        }
    }

    public function testQueuePageListsOnlyWaitingQuotes(): void
    {
        [$pdo, $ids] = $this->fixture();
        QdbModeration::override(self::active());
        try {
            $queue = QdbPage::page($pdo, 'queue', 1);
            assertSame(['anonymous', 'selfapproved', 'visitor'], self::names($ids, $queue['threads']));
            assertSame(3, $queue['count']);
            QdbModeration::override(QdbModeration::inactive());
            assertSame(0, QdbPage::page($pdo, 'queue', 1)['count']);
        } finally {
            QdbModeration::override(null);
        }
    }

    public function testStateOfEachQuote(): void
    {
        [$pdo, $ids] = $this->fixture();
        $moderation = self::active();
        $expected = [
            'archived' => 'approved', 'anonymous' => 'pending', 'visitor' => 'pending', 'operator' => 'approved',
            'news' => 'approved', 'approved' => 'approved', 'dashapproved' => 'approved', 'selfapproved' => 'pending',
            'rejected' => 'rejected', 'oldrejected' => 'rejected', 'bothtags' => 'rejected', 'plain' => 'approved',
        ];
        foreach ($expected as $name => $state) {
            assertSame($state, $moderation->state($pdo, $ids[$name]), $name);
        }
        assertSame('approved', $moderation->state($pdo, 'missing-qdb-1'));
    }

    public function testInactiveQueueListsEverything(): void
    {
        [$pdo, $ids] = $this->fixture();
        QdbModeration::override(QdbModeration::inactive());
        try {
            assertSame(count($ids) - 1, QdbPage::page($pdo, 'latest', 1)['count']);
            assertSame('approved', QdbModeration::inactive()->state($pdo, $ids['anonymous']));
            assertSame('', QdbModeration::inactive()->listedSqlSuffix());
            assertSame('0', QdbModeration::inactive()->queueSql());
        } finally {
            QdbModeration::override(null);
        }
    }

    public function testNoOperatorsMeansEveryNewQuoteWaits(): void
    {
        [$pdo, $ids] = $this->fixture();
        $moderation = new QdbModeration(self::START, []);
        assertSame('pending', $moderation->state($pdo, $ids['anonymous']));
        assertSame('pending', $moderation->state($pdo, $ids['approved']));
        assertSame('approved', $moderation->state($pdo, $ids['archived']));
    }

    public function testWithoutSignedSwitchOnNothingWaits(): void
    {
        $root = sys_get_temp_dir() . '/qdb-moderation-' . bin2hex(random_bytes(4));
        mkdir($root . '/records/feature-flag-changes', 0777, true);
        try {
            assertTrue(!QdbModeration::forRepository($root)->isActive());
            // An unsigned switch-on, even naming an operator, sets no start time.
            file_put_contents($root . '/records/feature-flag-changes/ffc-1.txt', "Record-ID: ffc-1\nCreated-At: 2026-11-17T12:00:00Z\nFlag-Key: FORUM_QDB_QUEUE_ENABLED\nValue: true\nOperator-Identity-ID: " . self::OPERATOR . "\n\n");
            assertSame(null, \ForumRewrite\Security\FeatureFlagSwitchOn::earliest($root, 'FORUM_QDB_QUEUE_ENABLED'));
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }
}
