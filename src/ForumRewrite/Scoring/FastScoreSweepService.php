<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use PDO;
use Throwable;

final class FastScoreSweepService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly FastScoreContextFactory $contextFactory,
        private readonly FastScoreWorkflowService $workflow,
        private readonly FastScoreStore $scoreStore,
        private readonly string $rubricRevision,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * Scores at most $postLimit posts whose current content and rubric revision
     * do not already have a stored result.
     *
     * @return array{processed:int,scored:int,excluded:int,failed:int,remaining:bool}
     */
    public function run(int $postLimit): array
    {
        if ($postLimit < 1) {
            throw new \InvalidArgumentException('Fast score sweep post limit must be at least 1.');
        }

        $candidates = [];
        foreach ($this->postsInStableOrder() as $post) {
            $context = $this->contextFactory->forPost($post);
            if ($this->scoreStore->find(
                (string) $context['post_id'],
                (string) $context['content_hash'],
                $this->rubricRevision,
            ) !== null) {
                continue;
            }

            $candidates[] = [$post, $context];
        }

        $summary = [
            'processed' => 0,
            'scored' => 0,
            'excluded' => 0,
            'failed' => 0,
            'remaining' => count($candidates) > $postLimit,
        ];

        foreach (array_slice($candidates, 0, $postLimit) as [$post, $context]) {
            $summary['processed']++;

            try {
                $result = $this->workflow->scorePost($post);
            } catch (Throwable $error) {
                $result = [
                    'status' => 'provider_error',
                    'probability' => null,
                    'source' => 'none',
                    'signals' => ['provider_exception'],
                    'failure_message' => $error->getMessage(),
                ];
            }

            $this->scoreStore->save(
                (string) $context['post_id'],
                (string) $context['content_hash'],
                $this->rubricRevision,
                $result,
            );

            if ($result['status'] === 'scored') {
                $summary['scored']++;
            } elseif ($result['status'] === 'excluded') {
                $summary['excluded']++;
            } elseif (str_ends_with((string) $result['status'], '_error')) {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /** @return list<array<string, mixed>> */
    private function postsInStableOrder(): array
    {
        $statement = $this->pdo->query(
            "SELECT post_id, thread_id, parent_id, subject, body\n"
            . "FROM posts\n"
            . "WHERE TRIM(COALESCE(subject, '') || COALESCE(body, '')) <> ''\n"
            . 'ORDER BY sequence_number ASC, post_id ASC'
        );

        /** @var list<array<string, mixed>> $posts */
        $posts = $statement->fetchAll();
        return $posts;
    }
}
