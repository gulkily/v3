<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Llm\LlmExchangeDatabaseConfig;
use ForumRewrite\Llm\LlmExchangeRecorder;
use ForumRewrite\Scoring\FastmodCostEstimator;
use ForumRewrite\Scoring\FastmodHistoricalAuditService;
use ForumRewrite\Scoring\FastScoreContextFactory;
use ForumRewrite\Scoring\FastScoreDatabaseConfig;
use ForumRewrite\Scoring\FastScoreWorkflowFactory;
use ForumRewrite\Scoring\FastScoringConfig;
use ForumRewrite\Scoring\FastScoringRubricRevision;
use ForumRewrite\Scoring\SqliteFastScoreStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\PrivateConfig;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\TaskQueue\TaskQueueDatabaseConfig;

$projectRoot = dirname(__DIR__);
$command = $argv[1] ?? '';
$options = fastScoreOptions(array_slice($argv, 2));
$privateConfig = PrivateConfig::load($projectRoot);
$scorePath = FastScoreDatabaseConfig::path($projectRoot, $privateConfig);

try {
    if ($command === 'audit') {
        if (($options['include-existing'] ?? false) !== true) {
            throw new InvalidArgumentException('--include-existing is required for historical audit.');
        }
        $databasePath = (string) ($options['database-path'] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
        if (!is_file($databasePath)) {
            throw new InvalidArgumentException('Read-model database not found.');
        }
        $config = FastScoringConfig::fromPrivateConfig($privateConfig);
        $audit = (new FastmodHistoricalAuditService(
            new PDO('sqlite:' . $databasePath),
            fastmodAuditScoreStore($scorePath),
            FastScoringRubricRevision::fromConfig($config, $projectRoot),
        ))->audit();
        [$inputUsdPerMillion, $outputUsdPerMillion] = fastmodPricing($privateConfig, $config->provider->model, $options);
        $estimate = (new FastmodCostEstimator(
            fastmodAuditExchangeDatabase(LlmExchangeDatabaseConfig::path($projectRoot, $privateConfig)),
            $config->provider->model,
            $inputUsdPerMillion,
            $outputUsdPerMillion,
        ))->estimate($audit);

        fwrite(STDOUT, "Fastmod historical audit\n");
        fwrite(STDOUT, 'Read-model database: ' . $databasePath . "\n");
        fwrite(STDOUT, 'Score database: ' . $scorePath . "\n");
        fwrite(STDOUT, 'Selected model: ' . $config->provider->model . "\n");
        fwrite(STDOUT, 'Candidate states: ' . json_encode($audit['counts'], JSON_THROW_ON_ERROR) . "\n");
        fwrite(STDOUT, sprintf(
            "Cost estimate: candidates=%d samples=%d input_tokens_per_post=%.1f output_tokens_per_post=%.1f input_usd_per_million=%.4f output_usd_per_million=%.4f estimated_usd=%.6f assumption=%s\n",
            $estimate['candidate_count'],
            $estimate['sample_count'],
            $estimate['input_tokens_per_post'],
            $estimate['output_tokens_per_post'],
            $inputUsdPerMillion,
            $outputUsdPerMillion,
            $estimate['estimated_cost_usd'],
            $estimate['assumption'],
        ));
        fwrite(STDOUT, "Audit is read-only: no score, work, task-queue, or exchange records were written.\n");
        exit(0);
    }

    $scoreDirectory = dirname($scorePath);
    if (!is_dir($scoreDirectory) && !mkdir($scoreDirectory, 0777, true) && !is_dir($scoreDirectory)) {
        throw new RuntimeException('Fastmod database directory is not writable.');
    }
    $store = new SqliteFastScoreStore(new PDO('sqlite:' . $scorePath));
    if ($command === 'status') {
        $config = FastScoringConfig::fromPrivateConfig($privateConfig);
        $queuePath = TaskQueueDatabaseConfig::path($projectRoot, $options['queue-database-path'] ?? null);
        $queueCounts = is_file($queuePath) ? (new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath)))->counts() : [];
        fwrite(STDOUT, 'Fastmod status' . "\n");
        fwrite(STDOUT, 'Score database: ' . $scorePath . "\n");
        fwrite(STDOUT, 'Active rubric revision: ' . FastScoringRubricRevision::fromConfig($config, $projectRoot) . "\n");
        fwrite(STDOUT, 'Work counts: ' . json_encode($store->workCounts(), JSON_THROW_ON_ERROR) . "\n");
        fwrite(STDOUT, 'Score counts: ' . json_encode($store->scoreCounts(), JSON_THROW_ON_ERROR) . "\n");
        fwrite(STDOUT, 'Score source counts: ' . json_encode($store->scoreCountsBySource(), JSON_THROW_ON_ERROR) . "\n");
        fwrite(STDOUT, 'Queue counts: ' . json_encode($queueCounts, JSON_THROW_ON_ERROR) . "\n");
        $lastFailure = $store->lastFailure();
        if ($lastFailure !== null) {
            fwrite(STDOUT, sprintf("Last failure: post=%s code=%s message=%s updated=%s\n", $lastFailure['post_id'], $lastFailure['failure_code'], $lastFailure['failure_message'], $lastFailure['updated_at']));
        }
        foreach ($store->recentWork((int) ($options['limit'] ?? 10)) as $work) {
            $probability = $work['probability'] === null ? 'none' : (string) $work['probability'];
            fwrite(STDOUT, sprintf("Work post=%s state=%s probability=%s source=%s attempts=%d failure=%s content_hash=%s rubric_revision=%s updated=%s\n", $work['post_id'], $work['state'], $probability, $work['source'] ?? 'none', $work['attempt_count'], $work['failure_category'] ?? 'none', $work['content_hash'], $work['rubric_revision'], $work['updated_at']));
        }
        exit(0);
    }

    if (in_array($command, ['retry', 'invalidate'], true)) {
        [$postId, $contentHash, $rubricRevision] = fastScoreTarget($options);
        $work = $command === 'retry'
            ? $store->retryWork($postId, $contentHash, $rubricRevision)
            : $store->invalidateWork($postId, $contentHash, $rubricRevision);
        if ($command === 'retry') {
            $queuePath = TaskQueueDatabaseConfig::path($projectRoot, $options['queue-database-path'] ?? null);
            $directory = dirname($queuePath);
            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException('Task queue directory is not writable.');
            }
            (new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath)))->enqueue(SqliteTaskQueueStore::FAST_SCORE_SWEEP, 'fast-score-sweep');
        }
        fwrite(STDOUT, json_encode($work, JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    }

    if ($command === 'prune') {
        $cutoff = isset($options['before']) ? strtotime((string) $options['before']) : strtotime('-1 year');
        if ($cutoff === false) {
            throw new InvalidArgumentException('Invalid --before timestamp.');
        }
        $cutoffValue = gmdate('c', $cutoff);
        $deleted = $store->pruneBefore($cutoffValue);
        $exchangePath = LlmExchangeDatabaseConfig::path($projectRoot, $privateConfig);
        if (is_file($exchangePath)) {
            $exchange = new PDO('sqlite:' . $exchangePath);
            $stmt = $exchange->prepare('DELETE FROM llm_exchanges WHERE call_type = :call_type AND occurred_at < :cutoff');
            $stmt->execute(['call_type' => 'fast_post_score', 'cutoff' => $cutoffValue]);
            $deleted += $stmt->rowCount();
        }
        fwrite(STDOUT, "Pruned {$deleted} private Fastmod records before {$cutoffValue}.\n");
        exit(0);
    }

    if ($command === 'smoke') {
        $postId = trim((string) ($options['post-id'] ?? ''));
        if ($postId === '') {
            throw new InvalidArgumentException('--post-id is required for smoke.');
        }
        $databasePath = (string) ($options['database-path'] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
        $readPdo = new PDO('sqlite:' . $databasePath);
        $fetchPost = static function (string $id) use ($readPdo): ?array {
            $stmt = $readPdo->prepare('SELECT post_id, thread_id, parent_id, subject, body FROM posts WHERE post_id = :post_id');
            $stmt->execute(['post_id' => $id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            return $post === false ? null : $post;
        };
        $post = $fetchPost($postId);
        if ($post === null) {
            throw new InvalidArgumentException('Post not found.');
        }
        $repositoryRoot = (string) ($options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: ($projectRoot . '/state/local_repository')));
        $recorder = fastScoreRecorder($projectRoot, $repositoryRoot, $privateConfig);
        $result = FastScoreWorkflowFactory::fromPrivateConfig($privateConfig, $projectRoot, $fetchPost, $recorder)->scorePost($post);
        fwrite(STDOUT, json_encode([
            'post_id' => $postId,
            'provider' => FastScoringConfig::fromPrivateConfig($privateConfig)->provider->provider,
            'model' => FastScoringConfig::fromPrivateConfig($privateConfig)->provider->model,
            'structured_output' => true,
            'result' => $result,
            'exchange_recording_enabled' => $recorder !== null,
        ], JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    }

    fastScoreUsage(STDERR);
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, 'error=' . $error->getMessage() . "\n");
    exit(1);
}

/** @return array<string, string|bool> */
function fastScoreOptions(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if (!str_starts_with($argument, '--')) {
            throw new InvalidArgumentException('Unknown argument: ' . $argument);
        }
        $parts = explode('=', substr($argument, 2), 2);
        $options[$parts[0]] = $parts[1] ?? true;
    }
    return $options;
}

/** @return array{string, string, string} */
function fastScoreTarget(array $options): array
{
    $values = [trim((string) ($options['post-id'] ?? '')), trim((string) ($options['content-hash'] ?? '')), trim((string) ($options['rubric-revision'] ?? ''))];
    if (in_array('', $values, true)) {
        throw new InvalidArgumentException('--post-id, --content-hash, and --rubric-revision are required.');
    }
    return $values;
}

/** @param array<string, mixed> $privateConfig */
function fastScoreRecorder(string $projectRoot, string $repositoryRoot, array $privateConfig): ?LlmExchangeRecorder
{
    if (!FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot)->isEnabled(FeatureFlagRegistry::LLM_CONVERSATION_RECORDING_ENABLED)) {
        return null;
    }
    $path = LlmExchangeDatabaseConfig::path($projectRoot, $privateConfig);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('LLM exchange database directory is not writable.');
    }
    return new LlmExchangeRecorder(new PDO('sqlite:' . $path));
}

function fastScoreUsage($stream): void
{
    fwrite($stream, "Usage:\n  php scripts/fast_score.php status [--limit=10] [--queue-database-path=/private/tasks.sqlite3]\n  php scripts/fast_score.php audit --include-existing [--database-path=/path/read-model.sqlite3] [--input-usd-per-million=N --output-usd-per-million=N]\n  php scripts/fast_score.php retry --post-id=... --content-hash=... --rubric-revision=...\n  php scripts/fast_score.php invalidate --post-id=... --content-hash=... --rubric-revision=...\n  php scripts/fast_score.php smoke --post-id=... [--database-path=/path/read-model.sqlite3]\n  php scripts/fast_score.php prune [--before=ISO-8601]\n");
}

function fastmodAuditScoreStore(string $scorePath): SqliteFastScoreStore
{
    if (!is_file($scorePath)) {
        return new SqliteFastScoreStore(new PDO('sqlite::memory:'));
    }

    return new SqliteFastScoreStore(new PDO('sqlite:' . $scorePath), false);
}

function fastmodAuditExchangeDatabase(string $exchangePath): PDO
{
    if (is_file($exchangePath)) {
        return new PDO('sqlite:' . $exchangePath);
    }

    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('CREATE TABLE llm_exchanges (call_type TEXT, provider_model TEXT, status TEXT, response_json TEXT)');
    return $pdo;
}

/**
 * @param array<string, mixed> $privateConfig
 * @param array<string, string|bool> $options
 * @return array{float, float}
 */
function fastmodPricing(array $privateConfig, string $model, array $options): array
{
    $input = $options['input-usd-per-million'] ?? $privateConfig['FAST_SCORING_INPUT_USD_PER_MILLION'] ?? null;
    $output = $options['output-usd-per-million'] ?? $privateConfig['FAST_SCORING_OUTPUT_USD_PER_MILLION'] ?? null;
    if ($input !== null || $output !== null) {
        if (!is_numeric($input) || !is_numeric($output) || (float) $input < 0 || (float) $output < 0) {
            throw new InvalidArgumentException('Both non-negative --input-usd-per-million and --output-usd-per-million values are required.');
        }
        return [(float) $input, (float) $output];
    }

    if (in_array(strtolower($model), ['gpt-5-nano', 'openai/gpt-5-nano'], true)) {
        return [0.05, 0.40];
    }

    throw new InvalidArgumentException('Configure FAST_SCORING_INPUT_USD_PER_MILLION and FAST_SCORING_OUTPUT_USD_PER_MILLION, or pass both pricing options.');
}
