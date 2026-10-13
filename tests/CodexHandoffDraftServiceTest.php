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
        assertStringContains('## Railspike Step 1', $draft['draft_text']);
        assertStringContains('Workflow: docs/railspike/RAILSPIKE.md', $draft['draft_text']);
        assertStringContains('Railspike', $draft['fdp_step1']);
        assertStringNotContains('FDP', $draft['draft_text']);
    }

    public function testBrowserPreviewKeepsCompatibilityFieldForNewAndLegacyDrafts(): void
    {
        $draft = (new CodexHandoffDraftService())->prepare($this->handoff(), [
            'post_id' => 'root-001', 'body' => 'Prepare a reviewed handoff.',
        ]);
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const assert = require('assert/strict');
let source = fs.readFileSync(process.argv[1], 'utf8');
source = source.replace('function boot() {', 'window.previewTest = createCodexHandoffPreview; function boot() {');
function node(tag) {
  return { tag, children: [], attributes: {}, textContent: '',
    appendChild(child) { this.children.push(child); },
    setAttribute(name, value) { this.attributes[name] = value; }
  };
}
global.window = {};
global.document = { readyState: 'loading', addEventListener() {},
  createElement: node, createTextNode(text) { return { textContent: text, children: [] }; }
};
vm.runInThisContext(source);
function flatten(element) { return [element, ...element.children.flatMap(flatten)]; }
for (const text of [JSON.parse(process.argv[2]).fdp_step1, 'Legacy FDP <script> draft']) {
  const preview = window.previewTest({ handoff_id: 'handoff', handoff_status: 'draft_ready', fdp_step1: text });
  const elements = flatten(preview);
  assert.equal(elements.find(n => n.tag === 'pre').textContent, text);
  assert.equal(elements.filter(n => n.tag === 'button').length, 2);
  assert.deepEqual(elements.filter(n => n.tag === 'button').map(n => n.attributes['data-action']),
    ['approve-codex-handoff', 'reject-codex-handoff']);
  assert.equal(preview.open, true);
  const approved = window.previewTest({ handoff_status: 'approved', fdp_step1: text });
  assert.equal(flatten(approved).filter(n => n.tag === 'button').length, 0);
}
console.log('Compatibility previews passed');
NODE;
        $command = 'node -e ' . escapeshellarg($script) . ' '
            . escapeshellarg(dirname(__DIR__) . '/public/assets/post_analysis.js') . ' '
            . escapeshellarg(json_encode($draft, JSON_THROW_ON_ERROR));
        exec($command . ' 2>&1', $output, $exitCode);
        assertSame(0, $exitCode, implode("\n", $output));
        assertStringContains('Compatibility previews passed', implode("\n", $output));
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
