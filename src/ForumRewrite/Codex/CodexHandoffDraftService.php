<?php

declare(strict_types=1);

namespace ForumRewrite\Codex;

use RuntimeException;

final class CodexHandoffDraftService
{
    /**
     * @param array<string, mixed> $handoff
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function prepare(array $handoff, array $post): array
    {
        $body = trim((string) ($post['body'] ?? ''));
        if ($body === '') {
            throw new RuntimeException('Codex handoff draft requires post body text.');
        }

        $subject = trim((string) ($post['subject'] ?? ''));
        $postId = (string) ($post['post_id'] ?? ($handoff['origin_post_id'] ?? ''));
        $title = $subject !== '' ? $subject : $this->titleFromBody($body);
        $requestSummary = $this->compactRequest($body);
        $userStory = 'As an approved local user, I want ' . lcfirst($requestSummary)
            . ' so that the request can be reviewed and implemented through the Railspike workflow.';
        $step1 = $this->stepOneAssessment($title, $requestSummary);
        $confidenceSummary = $this->confidenceSummary($body);
        $draftText = "# Codex Handoff Draft\n\n"
            . "Origin post: {$postId}\n\n"
            . "Workflow: docs/railspike/RAILSPIKE.md\n\n"
            . "## User Story\n\n{$userStory}\n\n"
            . "## Railspike Step 1\n\n{$step1}\n\n"
            . "## Implementation Confidence\n\n{$confidenceSummary}\n";

        return [
            'user_story' => $userStory,
            // Retain the persisted/API field name for existing handoffs and clients.
            'fdp_step1' => $step1,
            'confidence_summary' => $confidenceSummary,
            'draft_text' => $draftText,
        ];
    }

    private function stepOneAssessment(string $title, string $requestSummary): string
    {
        return '# ' . $this->heading($title) . " Step 1 Solution Assessment\n\n"
            . "## Problem Statement\n\n"
            . "Approved localhost users need {$requestSummary} while keeping local Codex execution behind an explicit review and approval gate.\n\n"
            . "## Option A: Draft only inside the forum UI\n\n"
            . "Pros:\n"
            . "- Safest first step because no local code execution starts.\n"
            . "- Gives approved users a reviewable user story, Railspike Step 1, and confidence summary.\n\n"
            . "Cons:\n"
            . "- Still requires a separate runner or operator action to perform the work.\n\n"
            . "## Option B: Queue approved handoffs for a local Codex runner\n\n"
            . "Pros:\n"
            . "- Preserves the UI approval gate while allowing local automation after approval.\n"
            . "- Fits `codex exec` and keeps execution out of public request handling.\n\n"
            . "Cons:\n"
            . "- Requires durable status tracking and local runner operations.\n\n"
            . "## Recommendation\n\n"
            . "Recommend Option B after validating the draft-only approval flow.\n";
    }

    private function confidenceSummary(string $body): string
    {
        $lower = strtolower($body);
        $riskSignals = 0;
        foreach (['database', 'schema', 'migration', 'security', 'auth', 'execute', 'codex', 'approval', 'approved'] as $signal) {
            if (str_contains($lower, $signal)) {
                $riskSignals++;
            }
        }

        if ($riskSignals >= 4) {
            return 'Medium confidence: the workflow is implementable, but local execution, approval, and audit boundaries need careful staged verification.';
        }

        if ($riskSignals >= 2) {
            return 'Medium-high confidence: the workflow is implementable with focused tests around authorization, durable state, and activity.';
        }

        return 'High confidence: the request is well suited to a small staged implementation with deterministic verification.';
    }

    private function titleFromBody(string $body): string
    {
        $line = trim((string) strtok($body, "\n"));
        if ($line === '') {
            return 'Codex Handoff';
        }

        return substr($line, 0, 80);
    }

    private function compactRequest(string $body): string
    {
        $line = $this->titleFromBody($body);
        $line = preg_replace('/\s+/', ' ', $line) ?? $line;
        $line = trim($line, " \t\n\r\0\x0B.?");
        if ($line === '') {
            return 'to prepare a local Codex handoff';
        }

        if (strlen($line) > 120) {
            $line = rtrim(substr($line, 0, 117)) . '...';
        }

        return 'to turn "' . $line . '" into a reviewed local Codex handoff';
    }

    private function heading(string $title): string
    {
        $title = preg_replace('/\s+/', ' ', trim($title)) ?? '';
        $title = trim($title, "# \t\n\r\0\x0B");

        return $title !== '' ? $title : 'Codex Handoff';
    }
}
