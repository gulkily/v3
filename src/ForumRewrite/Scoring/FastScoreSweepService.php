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
     * Handles bounded private work while independently limiting provider calls.
     *
     * @param null|callable(string, array<string,mixed>):void $progress
     * @return array<string, int|bool|array<string,mixed>|null>
     */
    public function run(int $providerCallLimit, int $workLimit = 250, ?callable $progress = null): array
    {
        if ($providerCallLimit < 1) {
            throw new \InvalidArgumentException('Fastmod provider-call limit must be at least 1.');
        }
        if ($workLimit < 1) {
            throw new \InvalidArgumentException('Fastmod work limit must be at least 1.');
        }

        $summary = [
            'examined' => 0,
            'processed' => 0,
            'provider_calls' => 0,
            'scored' => 0,
            'excluded' => 0,
            'failed' => 0,
            'remaining' => false,
        ];

        $backfillBatchId = null;
        $backfillProcessed = 0;
        $backfillExcluded = 0;
        while ($summary['examined'] < $workLimit) {
            $work = $this->claimNextWork();
            if ($work === null) {
                break;
            }
            $summary['examined']++;
            $postId = (string) $work['post_id'];
            if (isset($work['backfill_batch_id'])) {
                $backfillBatchId ??= (int) $work['backfill_batch_id'];
            }
            $post = $this->post($postId);
            try {
                if ($post === null) {
                    $result = FastScoreResult::excluded(['post_unavailable']);
                } else {
                    $context = $this->contextFactory->forPost($post);
                    if ((string) $context['content_hash'] !== (string) $work['content_hash']) {
                        $result = FastScoreResult::excluded(['content_changed_before_score']);
                    } else {
                        $result = $this->workflow->localResultForPost($post);
                        if ($result === null) {
                            if ($summary['provider_calls'] >= $providerCallLimit) {
                                $this->scoreStore->releaseClaimedWork($work);
                                $this->report($progress, 'deferred', $summary, $work, [
                                    'reason' => 'provider_call_limit_reached',
                                    'work_limit' => $workLimit,
                                    'provider_call_limit' => $providerCallLimit,
                                ]);
                                break;
                            }
                            if (isset($work['backfill_batch_id']) && !$this->scoreStore->reserveBackfillProviderAttempt($work)) {
                                $this->scoreStore->releaseClaimedWork($work);
                                $this->report($progress, 'deferred', $summary, $work, [
                                    'reason' => 'backfill_budget_exhausted',
                                    'work_limit' => $workLimit,
                                    'provider_call_limit' => $providerCallLimit,
                                ]);
                                break;
                            }
                            $summary['provider_calls']++;
                            $this->report($progress, 'provider_started', $summary, $work, [
                                'work_limit' => $workLimit,
                                'provider_call_limit' => $providerCallLimit,
                            ]);
                            $result = $this->workflow->scorePost($post);
                        }
                    }
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
            $summary['processed']++;
            if (isset($work['backfill_batch_id'])) {
                $backfillProcessed++;
            }

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
            $this->report($progress, 'completed', $summary, $work, [
                'work_limit' => $workLimit,
                'provider_call_limit' => $providerCallLimit,
                'status' => (string) $result['status'],
                'probability' => $result['probability'],
                'source' => (string) $result['source'],
                'failure_code' => $result['failure_code'] ?? null,
            ]);
        }

        $this->scoreStore->refreshBackfillBatchStates();
        $summary['remaining'] = $this->scoreStore->hasOutstandingWork() || $this->scoreStore->hasOutstandingBackfillWork();
        if ($backfillBatchId !== null) {
            $summary['backfill_processed'] = $backfillProcessed;
            $summary['backfill_excluded'] = $backfillExcluded;
            $summary['backfill'] = $this->scoreStore->backfillProgress($backfillBatchId);
        }

        return $summary;
    }

    /** @return array<string,mixed>|null */
    private function claimNextWork(): ?array
    {
        $normal = $this->scoreStore->claimPendingWork(1);
        if ($normal !== []) {
            return $normal[0];
        }

        $backfill = $this->scoreStore->claimPendingBackfillWork(1);
        return $backfill[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    private function post(string $postId): ?array
    {
        $statement = $this->pdo->prepare('SELECT post_id, thread_id, parent_id, subject, body FROM posts WHERE post_id = :post_id');
        $statement->execute(['post_id' => $postId]);
        $post = $statement->fetch();
        return $post === false ? null : $post;
    }

    /**
     * @param null|callable(string, array<string,mixed>):void $progress
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $work
     * @param array<string,mixed> $details
     */
    private function report(?callable $progress, string $event, array $summary, array $work, array $details): void
    {
        if ($progress === null) {
            return;
        }

        $progress($event, array_merge($details, [
            'post_id' => (string) $work['post_id'],
            'examined' => (int) $summary['examined'],
            'provider_calls' => (int) $summary['provider_calls'],
            'backfill_batch_id' => isset($work['backfill_batch_id']) ? (int) $work['backfill_batch_id'] : null,
        ]));
    }
}
