<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Codex\CodexHandoffDraftService;

final class CodexHandoffDraftServiceTest
{
    public function testPrepareBuildsUserStoryStepOneAndConfidenceSummary(): void
    {
        $service = new CodexHandoffDraftService();

        $draft = $service->prepare($this->handoff(), [
            'post_id' => 'root-001',
            'subject' => 'Localhost Codex handoff',
            'body' => 'Approved users should be able to hand off tasks to Codex from localhost.',
        ]);

        assertStringContains('As an approved local user, I want to turn "Approved users should be able to hand off tasks to Codex from localhost" into a reviewed local Codex handoff', $draft['user_story']);
        assertStringContains('# Localhost Codex handoff Step 1 Solution Assessment', $draft['fdp_step1']);
        assertStringContains('## Option A: Draft only inside the forum UI', $draft['fdp_step1']);
        assertStringContains('## Option B: Queue approved handoffs for a local Codex runner', $draft['fdp_step1']);
        assertStringContains('Recommend Option B', $draft['fdp_step1']);
        assertStringContains('Medium-high confidence', $draft['confidence_summary']);
        assertStringContains('Origin post: root-001', $draft['draft_text']);
        assertStringContains('## Implementation Confidence', $draft['draft_text']);
    }

    public function testPrepareReportsMediumConfidenceForExecutionAndAuditRisk(): void
    {
        $service = new CodexHandoffDraftService();

        $draft = $service->prepare($this->handoff(), [
            'post_id' => 'root-001',
            'body' => 'Add Codex execution with auth, approval, security, database schema, and activity audit support.',
        ]);

        assertStringContains('Medium confidence', $draft['confidence_summary']);
        assertStringContains('local execution, approval, and audit boundaries', $draft['confidence_summary']);
    }

    public function testPrepareRejectsEmptyPostBody(): void
    {
        $service = new CodexHandoffDraftService();

        try {
            $service->prepare($this->handoff(), ['post_id' => 'root-001', 'body' => '']);
            throw new RuntimeException('Expected empty body failure.');
        } catch (RuntimeException $exception) {
            assertSame('Codex handoff draft requires post body text.', $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function handoff(): array
    {
        return [
            'handoff_id' => 'codex-handoff-test',
            'origin_post_id' => 'root-001',
        ];
    }
}
