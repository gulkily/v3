<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Qdb\QdbBoardPolicy;

final class QdbBoardPolicyTest
{
    public function testPaginatesLatestAndLeavesOtherSectionsUnbounded(): void
    {
        $policy = new QdbBoardPolicy('/unused', static fn (): ?array => null);
        $threads = array_map(static fn (int $number): array => ['root_post_id' => 'thread-' . $number], range(1, 26));

        $firstPage = $policy->paginate($threads, 'latest', 1);
        assertSame(25, count($firstPage['threads']));
        assertSame('thread-1', $firstPage['threads'][0]['root_post_id']);
        assertSame(3, count($firstPage['pagination']));

        $finalPage = $policy->paginate($threads, 'top', 2);
        assertSame(1, count($finalPage['threads']));
        assertSame('thread-26', $finalPage['threads'][0]['root_post_id']);
        assertSame(null, $policy->paginate($threads, 'leetness', 1)['pagination']);
    }

    public function testUsesDistanceFrom1337ForLeetness(): void
    {
        $policy = new QdbBoardPolicy('/unused', static fn (): ?array => null);

        assertSame(-1, $policy->compareLeetness(['score_total' => 1336], ['score_total' => 1300], 1));
        assertSame(-1, $policy->compareLeetness(['score_total' => 1336], ['score_total' => 1338], -1));
    }

    public function testRetainsOnlyThreadsWithCanonicalQuoteIds(): void
    {
        $policy = new QdbBoardPolicy('/unused', static fn (): ?array => null);
        $threads = [
            ['root_post_id' => 'thread-20030613104735-qdb-42'],
            ['root_post_id' => 'root-001'],
            ['root_post_id' => 'thread-20030613104735-qdb-not-a-number'],
        ];

        $quotes = $policy->eligibleQuotes($threads);

        assertSame(['thread-20030613104735-qdb-42'], array_column($quotes, 'root_post_id'));
        assertSame(1, $policy->quoteCount($threads));
    }
}
