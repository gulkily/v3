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
        private readonly SqliteFastScoreStore $scoreStore,
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
            throw new \InvalidArgumentException('Fastmod sweep post limit must be at least 1.');
        }

        $summary = [
            'processed' => 0,
            'scored' => 0,
            'excluded' => 0,
            'failed' => 0,
            'remaining' => false,
        ];

        $normalWork = $this->scoreStore->claimPendingWork($postLimit);
        $remainingLimit = $postLimit - count($normalWork);
        $backfillWork = $remainingLimit > 0 ? $this->scoreStore->claimPendingBackfillWork($remainingLimit) : [];
        $backfillBatchId = $backfillWork === [] ? null : (int) $backfillWork[0]['backfill_batch_id'];
        $backfillExcluded = 0;
        foreach (array_merge($normalWork, $backfillWork) as $work) {
            $summary['processed']++;
            $post = $this->post((string) $work['post_id']);
            try {
                if ($post === null) {
                    $result = FastScoreResult::excluded(['post_unavailable']);
                } else {
                    $context = $this->contextFactory->forPost($post);
                    $result = (string) $context['content_hash'] === (string) $work['content_hash']
                        ? $this->workflow->scorePost($post)
                        : FastScoreResult::excluded(['content_changed_before_score']);
                }
            } catch (Throwable $error) {
                $result = [
                    'status' => 'provider_error',
                    'probability' => null,
                    'source' => 'none',
                    'signals' => ['provider_exception'],
                    'failure_message' => 'Unexpected scoring worker error.',
                    'failure_code' => 'worker_error',
                ];
            }

            $this->scoreStore->completeWork($work, $result);

            if ($result['status'] === 'scored') {
                $summary['scored']++;
            } elseif ($result['status'] === 'excluded') {
                $summary['excluded']++;
                if (isset($work['backfill_batch_id'])) {
                    $backfillExcluded++;
                }
            } elseif (str_ends_with((string) $result['status'], '_error')) {
                $summary['failed']++;
            }
        }

        $this->scoreStore->refreshBackfillBatchStates();
        $summary['remaining'] = $this->scoreStore->hasOutstandingWork() || $this->scoreStore->hasOutstandingBackfillWork();
        if ($backfillWork !== []) {
            $summary['backfill_processed'] = count($backfillWork);
            $summary['backfill_excluded'] = $backfillExcluded;
            $summary['backfill'] = $this->scoreStore->backfillProgress($backfillBatchId);
        }

        return $summary;
    }

    /** @return array<string, mixed>|null */
    private function post(string $postId): ?array
    {
        $statement = $this->pdo->prepare('SELECT post_id, thread_id, parent_id, subject, body FROM posts WHERE post_id = :post_id');
        $statement->execute(['post_id' => $postId]);
        $post = $statement->fetch();
        return $post === false ? null : $post;
    }
}
