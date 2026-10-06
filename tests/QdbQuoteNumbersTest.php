<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Qdb\QdbQuoteNumbers;

final class QdbQuoteNumbersTest
{
    public function testParsesAndBuildsPermalinksForQdbThreadIds(): void
    {
        assertSame('42', QdbQuoteNumbers::fromThreadId('thread-20030613104735-qdb-42'));
        assertSame(null, QdbQuoteNumbers::fromThreadId('thread-20030613104735-qdb-not-a-number'));
        assertSame(
            ['displayNumber' => '42', 'permalinkHref' => '/42'],
            QdbQuoteNumbers::displayPermalink('thread-20030613104735-qdb-42')
        );
        assertSame(
            ['displayNumber' => 'root-001', 'permalinkHref' => '/threads/root-001'],
            QdbQuoteNumbers::displayPermalink('root-001')
        );
    }

    public function testMintsPositiveNumbersAndRejectsZero(): void
    {
        assertSame('thread-20030613104735-qdb-42', QdbQuoteNumbers::mint('20030613104735', 42));

        try {
            QdbQuoteNumbers::mint('20030613104735', 0);
            throw new RuntimeException('Expected zero QDB quote number to be rejected.');
        } catch (InvalidArgumentException) {
            assertTrue(true);
        }
    }

    public function testAllocatesAndResolvesNumbersFromTheThreadReadModel(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE threads (root_post_id TEXT NOT NULL)');
        $pdo->exec("INSERT INTO threads (root_post_id) VALUES ('root-001'), ('thread-20030613104735-qdb-41'), ('thread-20030613104735-qdb-42')");

        assertSame(43, QdbQuoteNumbers::nextAvailable($pdo));
        assertSame('thread-20030613104735-qdb-42', QdbQuoteNumbers::resolve($pdo, 42));
        assertSame(null, QdbQuoteNumbers::resolve($pdo, 0));
        assertSame(null, QdbQuoteNumbers::resolve($pdo, 999));
    }
}
