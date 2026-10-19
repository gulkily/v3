<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Support\HealthIndicators;

final class HealthIndicatorsTest
{
    public function testHealthyStatusIsAllOkWithNoFixes(): void
    {
        $indicators = HealthIndicators::fromStatus($this->status());

        assertSame([], array_values(array_filter($indicators, static fn (array $i): bool => $i['level'] !== 'OK')));
        assertSame([], HealthIndicators::fixes($indicators));
        assertSame(
            ['Frontend', 'Read model', 'Stale marker', 'Shared lock', 'Read-model DB', 'Queue DB', 'Rebuild task', 'Task executor', 'Task queue', 'Failed tasks'],
            array_column($indicators, 'label'),
        );
    }

    public function testQueuedTasksWithUnobservedExecutorWarn(): void
    {
        $indicators = $this->byLabel(HealthIndicators::fromStatus($this->status([
            'task_queue' => ['queued' => 130, 'executor_status' => 'not_observed', 'executor_last_completed_at' => 'none'],
        ])));

        assertSame('WARN', $indicators['Task executor']['level']);
        assertSame('WARN', $indicators['Task queue']['level']);
        assertStringContains('130 queued', $indicators['Task queue']['detail']);
        assertStringContains('start the task worker', (string) $indicators['Task executor']['fix']);
        assertSame('OK', $indicators['Read model']['level']);
    }

    public function testIdleExecutorWithEmptyQueueDoesNotWarn(): void
    {
        foreach (['not_observed', 'stale'] as $executor) {
            $indicators = $this->byLabel(HealthIndicators::fromStatus($this->status([
                'task_queue' => ['queued' => 0, 'executor_status' => $executor],
            ])));

            assertSame('OK', $indicators['Task executor']['level']);
            assertSame('OK', $indicators['Task queue']['level']);
        }
    }

    public function testFreshExecutorWithBacklogDoesNotWarn(): void
    {
        $indicators = $this->byLabel(HealthIndicators::fromStatus($this->status([
            'task_queue' => ['queued' => 5, 'executor_status' => 'fresh'],
        ])));

        assertSame('OK', $indicators['Task executor']['level']);
        assertSame('OK', $indicators['Task queue']['level']);
    }

    public function testFrontendVerdicts(): void
    {
        $cases = [
            'responding' => 'OK',
            'timed_out' => 'FAIL',
            'not_running' => 'FAIL',
            'error' => 'FAIL',
            'redirected' => 'FAIL',
            'ambiguous' => 'WARN',
            'unresolved' => 'WARN',
        ];
        foreach ($cases as $frontendStatus => $level) {
            $indicators = $this->byLabel(HealthIndicators::fromStatus($this->status([
                'frontend' => ['status' => $frontendStatus, 'detail' => 'detail'],
            ])));
            assertSame($level, $indicators['Frontend']['level']);
        }

        $notChecked = $this->byLabel(HealthIndicators::fromStatus($this->status(['frontend' => ['status' => 'not_checked']])));
        assertSame(false, isset($notChecked['Frontend']));
    }

    public function testLockedDatabaseFixDependsOnRebuild(): void
    {
        $locked = ['status' => 'locked', 'detail' => 'write lock held by another process; reads still work'];

        $stuck = $this->byLabel(HealthIndicators::fromStatus($this->status(['database_access' => ['read_model' => $locked]])));
        $rebuilding = $this->byLabel(HealthIndicators::fromStatus($this->status([
            'database_access' => ['read_model' => $locked],
            'task_queue' => ['rebuild_task_status' => 'running'],
        ])));

        assertSame('FAIL', $stuck['Read-model DB']['level']);
        assertStringContains('may be stuck', (string) $stuck['Read-model DB']['fix']);
        assertStringContains('rebuild is running', (string) $rebuilding['Read-model DB']['fix']);
    }

    public function testSharedLockStaleMarkerAndFailedTasksWarn(): void
    {
        $indicators = $this->byLabel(HealthIndicators::fromStatus($this->status([
            'read_model' => ['lock_status' => 'locked', 'stale_marker' => 'present', 'stale_reason' => 'commit'],
            'task_queue' => ['failed' => 2],
        ])));

        assertSame('WARN', $indicators['Shared lock']['level']);
        assertSame('WARN', $indicators['Stale marker']['level']);
        assertSame('WARN', $indicators['Failed tasks']['level']);
    }

    public function testUnavailableAndMissingReadModelFail(): void
    {
        $indicators = $this->byLabel(HealthIndicators::fromStatus($this->status([
            'read_model' => ['status' => 'unavailable'],
            'database_access' => ['read_model' => ['status' => 'missing', 'detail' => 'x'], 'task_queue' => ['status' => 'missing', 'detail' => 'x']],
        ])));

        assertSame('FAIL', $indicators['Read model']['level']);
        assertSame(false, isset($indicators['Read-model DB']));
        assertSame('OK', $indicators['Queue DB']['level']);
    }

    /**
     * @param list<array{level:string,label:string,detail:string,fix:?string}> $indicators
     * @return array<string, array{level:string,label:string,detail:string,fix:?string}>
     */
    private function byLabel(array $indicators): array
    {
        return array_combine(array_column($indicators, 'label'), $indicators);
    }

    /**
     * @param array<string, array<string, mixed>> $overrides
     * @return array<string, mixed>
     */
    private function status(array $overrides = []): array
    {
        $base = [
            'read_model' => ['status' => 'ready', 'schema_version' => '21', 'expected_schema_version' => '21', 'lock_status' => 'unlocked', 'stale_marker' => 'absent', 'stale_reason' => 'none'],
            'task_queue' => ['status' => 'available', 'queued' => 0, 'running' => 0, 'failed' => 0, 'rebuild_task_status' => 'absent', 'executor_status' => 'fresh', 'executor_last_completed_at' => 'now'],
            'frontend' => ['status' => 'responding', 'detail' => 'HTTP 200 in 5 ms', 'url' => 'http://127.0.0.1:8000', 'latency_ms' => 5],
            'database_access' => [
                'read_model' => ['status' => 'accessible', 'detail' => 'readable and writable'],
                'task_queue' => ['status' => 'accessible', 'detail' => 'readable and writable'],
            ],
        ];
        foreach ($overrides as $section => $values) {
            $base[$section] = $values + $base[$section];
        }

        return $base;
    }
}
