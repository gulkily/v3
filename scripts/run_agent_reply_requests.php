<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Agent\SqliteAgentReplyGenerationStore;
use ForumRewrite\Application;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$options = parseOptions(array_slice($argv, 1));
$repositoryRoot = (string) ($options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)));
$databasePath = (string) ($options['database-path'] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
$artifactRoot = getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: ($projectRoot . '/public');
$limit = max(1, (int) ($options['limit'] ?? 10));
$postId = isset($options['post-id']) ? trim((string) $options['post-id']) : '';
$dryRun = ($options['dry-run'] ?? false) === true;
$quiet = ($options['quiet'] ?? false) === true;
$startedAt = microtime(true);

try {
    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $store = new SqliteAgentReplyGenerationStore($pdo);

    if ($dryRun) {
        $count = countRequestedRows($pdo, $postId);
        emit($quiet, "Agent reply request dry run\n");
        emit($quiet, "Repository: {$repositoryRoot}\n");
        emit($quiet, "Database: {$databasePath}\n");
        emit($quiet, "Artifact root: {$artifactRoot}\n");
        emit($quiet, "Post filter: " . ($postId === '' ? '(none)' : $postId) . "\n");
        emit($quiet, "Limit: {$limit}\n");
        emit($quiet, "Queued requests: {$count}\n");
        exit(0);
    }

    $application = new Application($projectRoot, $repositoryRoot, $databasePath, $artifactRoot);
    $summary = [
        'claimed' => 0,
        'generated' => 0,
        'already_posted' => 0,
        'skipped' => 0,
        'failed' => 0,
        'in_progress' => 0,
    ];
    $reasons = [];
    $queuedBefore = countRequestedRows($pdo, $postId);

    emit($quiet, "Agent reply request fulfillment starting\n");
    emit($quiet, "Repository: {$repositoryRoot}\n");
    emit($quiet, "Database: {$databasePath}\n");
    emit($quiet, "Artifact root: {$artifactRoot}\n");
    emit($quiet, "Post filter: " . ($postId === '' ? '(none)' : $postId) . "\n");
    emit($quiet, "Limit: {$limit}\n");
    emit($quiet, "Queued before claim: {$queuedBefore}\n");

    $run = static function () use ($store, $application, $limit, $postId, $quiet, &$summary, &$reasons): void {
        $claimedRows = [];
        if ($postId !== '') {
            $claimed = $store->claimRequestedForPost($postId);
            $claimedRows = $claimed === null ? [] : [$claimed];
        } else {
            $claimedRows = $store->claimNextRequested($limit);
        }

        emit($quiet, 'Claimed rows: ' . count($claimedRows) . "\n");

        foreach ($claimedRows as $row) {
            $summary['claimed']++;
            $requestId = (string) ($row['id'] ?? '');
            $targetPostId = (string) ($row['target_post_id'] ?? '');
            $contentHash = (string) ($row['target_content_hash'] ?? '');
            emit($quiet, sprintf(
                "Processing request id=%s target_post_id=%s content_hash=%s\n",
                $requestId === '' ? '(unknown)' : $requestId,
                $targetPostId === '' ? '(unknown)' : $targetPostId,
                $contentHash === '' ? '(unknown)' : $contentHash
            ));

            $result = $application->fulfillAgentReplyRequest($row);
            $status = (string) ($result['generation_status'] ?? 'failed');
            $detail = [
                'request_id' => $requestId,
                'target_post_id' => $targetPostId,
                'status' => $status,
                'reason' => null,
                'agent_post_id' => isset($result['agent_post_id']) ? (string) $result['agent_post_id'] : null,
            ];
            if ($status === 'generated') {
                $summary['generated']++;
            } elseif ($status === 'already_posted') {
                $summary['already_posted']++;
            } elseif ($status === 'not_recommended') {
                $summary['skipped']++;
                $reason = (string) ($result['reason'] ?? 'not_recommended');
                $detail['reason'] = $reason;
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            } elseif ($status === 'failed') {
                $summary['failed']++;
                $reason = (string) ($result['failure_code'] ?? 'failed');
                $detail['reason'] = $reason;
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            } else {
                $summary['in_progress']++;
                $detail['reason'] = $status;
                $reasons[$status] = ($reasons[$status] ?? 0) + 1;
            }
            $parts = [
                'request_id=' . ($requestId === '' ? '(unknown)' : $requestId),
                'target_post_id=' . ($targetPostId === '' ? '(unknown)' : $targetPostId),
                'status=' . $status,
            ];
            if ($detail['reason'] !== null && $detail['reason'] !== '') {
                $parts[] = 'reason=' . $detail['reason'];
            }
            if ($detail['agent_post_id'] !== null && $detail['agent_post_id'] !== '') {
                $parts[] = 'agent_post_id=' . $detail['agent_post_id'];
            }
            emit($quiet, 'Result: ' . implode(' ', $parts) . "\n");
        }
    };

    try {
        (new ExecutionLock(dirname($databasePath) . '/forum-rewrite-agent-replies.lock', 0))->withExclusiveLock($run);
    } catch (RuntimeException $exception) {
        if (str_contains($exception->getMessage(), 'Timed out waiting for execution lock')) {
            emit($quiet, "Another agent reply request worker is already running.\n");
            exit(0);
        }

        throw $exception;
    }

    $queuedAfter = countRequestedRows($pdo, $postId);

    emit($quiet, "Agent reply request fulfillment complete\n");
    emit($quiet, sprintf(
        "Claimed: %d, generated: %d, already posted: %d, skipped: %d, failed: %d, in progress: %d\n",
        $summary['claimed'],
        $summary['generated'],
        $summary['already_posted'],
        $summary['skipped'],
        $summary['failed'],
        $summary['in_progress'],
    ));
    emit($quiet, "Queued after run: {$queuedAfter}\n");
    if ($reasons !== []) {
        ksort($reasons);
        emit($quiet, 'Reasons: ' . implode(', ', array_map(
            static fn (string $reason, int $count): string => $reason . '=' . $count,
            array_keys($reasons),
            array_values($reasons)
        )) . "\n");
    }
    emit($quiet, sprintf("Elapsed: %.3f seconds\n", microtime(true) - $startedAt));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n\n" . usageText());
    exit(1);
}

/**
 * @param list<string> $args
 * @return array<string, string|bool|int>
 */
function parseOptions(array $args): array
{
    $options = [];
    foreach ($args as $arg) {
        if ($arg === '--quiet') {
            $options['quiet'] = true;
            continue;
        }

        if ($arg === '--dry-run') {
            $options['dry-run'] = true;
            continue;
        }

        foreach (['limit', 'post-id', 'repository-root', 'database-path'] as $key) {
            $prefix = '--' . $key . '=';
            if (str_starts_with($arg, $prefix)) {
                $value = substr($arg, strlen($prefix));
                $options[$key] = $key === 'limit' ? max(1, (int) $value) : $value;
                continue 2;
            }
        }

        throw new InvalidArgumentException('Unknown option: ' . $arg);
    }

    return $options;
}

function emit(bool $quiet, string $message): void
{
    if ($quiet) {
        return;
    }

    fwrite(STDOUT, $message);
}

function countRequestedRows(PDO $pdo, string $postId): int
{
    if ($postId !== '') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM post_generated_responses WHERE status = :status AND target_post_id = :post_id');
        $stmt->execute([
            'status' => 'requested',
            'post_id' => $postId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM post_generated_responses WHERE status = :status');
    $stmt->execute(['status' => 'requested']);

    return (int) $stmt->fetchColumn();
}

function usageText(): string
{
    return "Usage: php scripts/run_agent_reply_requests.php [--limit=10] [--dry-run] [--quiet] [--post-id=<id>] [--repository-root=<path>] [--database-path=<path>]\n";
}
