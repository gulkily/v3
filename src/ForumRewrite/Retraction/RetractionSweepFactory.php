<?php

declare(strict_types=1);
namespace ForumRewrite\Retraction;

use ForumRewrite\Llm\LlmExchangeDatabaseConfig;
use ForumRewrite\Support\PrivateConfig;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

final class RetractionSweepFactory
{
    public static function create(string $projectRoot, string $repositoryRoot, string $staticRoot, ?string $exchangePath = null, ?string $statePath = null): RetractionSweep
    {
        $exchangePath ??= LlmExchangeDatabaseConfig::path($projectRoot, PrivateConfig::load($projectRoot));
        $publicRoot = getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: $projectRoot . '/public';
        $key = hash('sha256', json_encode([$repositoryRoot, $exchangePath, $staticRoot, $publicRoot], JSON_THROW_ON_ERROR));
        $statePath ??= getenv('FORUM_RETRACTION_SWEEP_STATE_PATH') ?: $projectRoot . '/state/private/retraction-sweep-' . substr($key, 0, 20) . '.json';
        return new RetractionSweep($repositoryRoot, $exchangePath, $statePath, [$staticRoot, $publicRoot]);
    }

    public static function reconcile(RetractionSweep $sweep, SqliteTaskQueueStore $store): void
    {
        // Do not resurrect terminal failures on every cron tick. An operator can enqueue a retry.
        $state = $sweep->state();
        if (($state['status'] ?? '') === 'failed') return;
        if (($state['status'] ?? '') === 'complete' && time() - (strtotime($state['updated_at'] ?? '') ?: 0) < 300) return;
        if (!$sweep->hasRetractions()) return;
        $store->enqueue(SqliteTaskQueueStore::RETRACTION_SWEEP, 'retraction-sweep');
    }
}
