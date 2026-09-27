<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use PDO;

final class FastmodHistoricalAuditService
{
    public function __construct(
        private readonly PDO $readPdo,
        private readonly SqliteFastScoreStore $scoreStore,
        private readonly string $rubricRevision,
    ) {
        $this->readPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * @return array{counts:array<string, int>, candidates:list<array{post_id:string,content_hash:string}>}
     */
    public function audit(): array
    {
        $counts = ['total' => 0, 'scored' => 0, 'excluded' => 0, 'pending' => 0, 'failed' => 0, 'unrated' => 0];
        $candidates = [];
        $contextFactory = new FastScoreContextFactory(static fn (string $_): ?array => null);
        $posts = $this->readPdo->query('SELECT post_id, thread_id, parent_id, subject, body FROM posts ORDER BY post_id')->fetchAll();

        foreach ($posts as $post) {
            $counts['total']++;
            $postId = (string) $post['post_id'];
            $contentHash = $contextFactory->contentHashForPost($post);
            $score = $this->scoreStore->find($postId, $contentHash, $this->rubricRevision);
            if ($score !== null && in_array($score['status'], ['scored', 'excluded'], true)) {
                $counts[(string) $score['status']]++;
                continue;
            }

            $work = $this->scoreStore->findWork($postId, $contentHash, $this->rubricRevision);
            if ($work !== null && in_array($work['state'], ['pending', 'running'], true)) {
                $counts['pending']++;
                continue;
            }
            if ($work !== null && $work['state'] === 'failed') {
                $counts['failed']++;
                continue;
            }

            $counts['unrated']++;
            $candidates[] = ['post_id' => $postId, 'content_hash' => $contentHash];
        }

        return ['counts' => $counts, 'candidates' => $candidates];
    }
}
