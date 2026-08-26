<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Codex\CodexHandoffStore;

final class CodexHandoffStoreTest
{
    public function testRequestForPostCreatesDurableHandoff(): void
    {
        $store = new CodexHandoffStore(new PDO('sqlite::memory:'));

        $handoff = $store->requestForPost($this->post(), $this->viewer());
        $hydrated = $store->findByHandoffId($handoff['handoff_id']);

        assertSame('requested', $handoff['status']);
        assertSame(true, $handoff['requested']);
        assertStringContains('codex-handoff-', $handoff['handoff_id']);
        assertSame('root-001', $hydrated['origin_post_id']);
        assertSame('root-001', $hydrated['origin_thread_id']);
        assertSame('openpgp:requester', $hydrated['requester_identity_id']);
        assertSame('requester', $hydrated['requester_profile_slug']);
        assertSame('requester', $hydrated['requester_username']);
        assertSame(true, is_string($hydrated['requested_at']));
    }

    public function testRequestForPostDeduplicatesCurrentPostContent(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new CodexHandoffStore($pdo);

        $first = $store->requestForPost($this->post(), $this->viewer());
        $second = $store->requestForPost($this->post(), [
            'identity_id' => 'openpgp:other',
            'profile_slug' => 'other',
            'username' => 'other',
        ]);

        assertSame($first['handoff_id'], $second['handoff_id']);
        assertSame(false, $second['requested']);
        assertSame('openpgp:requester', $second['requester_identity_id']);
        assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM codex_handoffs')->fetchColumn());
    }

    public function testRequestForPostAllowsChangedContent(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new CodexHandoffStore($pdo);

        $first = $store->requestForPost($this->post(), $this->viewer());
        $changed = $this->post();
        $changed['body'] = 'Changed feature request body.';
        $second = $store->requestForPost($changed, $this->viewer());

        assertSame(false, $first['handoff_id'] === $second['handoff_id']);
        assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM codex_handoffs')->fetchColumn());
    }

    public function testUpdateStatusPersistsDraftAndRefreshesFromNewStore(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new CodexHandoffStore($pdo);
        $handoff = $store->requestForPost($this->post(), $this->viewer());

        $updated = $store->updateStatus($handoff['handoff_id'], 'draft_ready', [
            'user_story' => 'As an approved user, I want a handoff so that work can start clearly.',
            'fdp_step1' => '# Step 1 draft',
            'confidence_summary' => 'Medium confidence: local execution needs a runner.',
            'draft_text' => "User story\n\n# Step 1 draft",
        ]);
        $fresh = (new CodexHandoffStore($pdo))->findByHandoffId($handoff['handoff_id']);

        assertSame('draft_ready', $updated['status']);
        assertSame('As an approved user, I want a handoff so that work can start clearly.', $fresh['user_story']);
        assertSame('# Step 1 draft', $fresh['fdp_step1']);
        assertSame('Medium confidence: local execution needs a runner.', $fresh['confidence_summary']);
        assertStringContains('# Step 1 draft', $fresh['draft_text']);
        assertSame(true, is_string($fresh['draft_ready_at']));
        assertSame('Medium confidence: local execution needs a runner.', $fresh['status_context']['draft_ready']['confidence_summary']);
    }

    public function testUpdateStatusRejectsInvalidTransitions(): void
    {
        $store = new CodexHandoffStore(new PDO('sqlite::memory:'));
        $handoff = $store->requestForPost($this->post(), $this->viewer());

        try {
            $store->updateStatus($handoff['handoff_id'], 'completed');
            throw new RuntimeException('Expected invalid transition failure.');
        } catch (RuntimeException $exception) {
            assertSame('Invalid Codex handoff status transition: requested to completed', $exception->getMessage());
        }

        try {
            $store->updateStatus($handoff['handoff_id'], 'unknown');
            throw new RuntimeException('Expected unknown status failure.');
        } catch (RuntimeException $exception) {
            assertSame('Unknown Codex handoff status: unknown', $exception->getMessage());
        }
    }

    public function testApprovedRunningCompletedLifecycle(): void
    {
        $store = new CodexHandoffStore(new PDO('sqlite::memory:'));
        $handoff = $store->requestForPost($this->post(), $this->viewer());
        $draft = $store->updateStatus($handoff['handoff_id'], 'draft_ready', [
            'user_story' => 'As an approved user, I want a handoff so that work can start clearly.',
            'fdp_step1' => '# Step 1 draft',
            'confidence_summary' => 'High confidence.',
            'draft_text' => "User story\n\n# Step 1 draft",
        ]);
        $approved = $store->updateStatus($draft['handoff_id'], 'approved', ['approved_by' => 'requester']);
        $running = $store->updateStatus($approved['handoff_id'], 'running', ['runner' => 'codex exec']);
        $completed = $store->updateStatus($running['handoff_id'], 'completed', ['exit_code' => 0]);

        assertSame('approved', $approved['status']);
        assertSame('running', $running['status']);
        assertSame('completed', $completed['status']);
        assertSame(0, $completed['status_context']['completed']['exit_code']);
        assertSame(true, is_string($completed['completed_at']));
    }

    /**
     * @return array<string, mixed>
     */
    private function post(): array
    {
        return [
            'post_id' => 'root-001',
            'thread_id' => 'root-001',
            'subject' => 'Local Codex handoff',
            'body' => 'Approved users should be able to hand off feature requests to Codex.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function viewer(): array
    {
        return [
            'identity_id' => 'openpgp:requester',
            'profile_slug' => 'requester',
            'username' => 'requester',
        ];
    }
}
