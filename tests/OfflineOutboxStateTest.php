<?php

declare(strict_types=1);

final class OfflineOutboxStateTest
{
    /** @return array<string, mixed> */
    private function runStateScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg(<<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
global.window = {};
vm.runInThisContext(source);
NODE
                . "\n" . $script),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_store.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox state helper failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testStateTransitionsAndPendingCountAreExplicit(): void
    {
        $result = $this->runStateScript(<<<'NODE'
const api = window.forumOutbox;
const draft = api.createItem({ id: 'intent-1', action: 'reply', createdAt: '2026-10-01T00:00:00Z', summary: 'Reply to Example', payload: { body: 'private local body' } });
const queued = api.transition(draft, 'queued', null, '2026-10-01T00:01:00Z');
const sending = api.transition(queued, 'sending', null, '2026-10-01T00:02:00Z');
const accepted = api.transition(sending, 'accepted', { message: 'Published' }, '2026-10-01T00:03:00Z');
let invalid = '';
try { api.transition(accepted, 'queued'); } catch (error) { invalid = error.message; }
process.stdout.write(JSON.stringify({
  actions: api.actionTypes,
  states: api.states,
  queued: queued.state,
  accepted: accepted.state,
  invalid,
  pending: api.pendingCount([draft, queued, accepted]),
  summary: api.safeSummary(draft)
}));
NODE);

        assertSame(['reaction', 'reply', 'thread'], $result['actions']);
        assertSame('queued', $result['queued']);
        assertSame('accepted', $result['accepted']);
        assertStringContains('cannot transition from accepted to queued', $result['invalid']);
        assertSame(2, $result['pending']);
        assertSame('Reply to Example', $result['summary']['summary']);
        assertFalse(array_key_exists('payload', $result['summary']));
    }

    public function testInvalidActionAndStateAreRejected(): void
    {
        $result = $this->runStateScript(<<<'NODE'
const api = window.forumOutbox;
const errors = [];
for (const candidate of [
  { id: 'intent-1', action: 'flag', state: 'draft' },
  { id: 'intent-2', action: 'reply', state: 'published' },
  { id: '', action: 'thread', state: 'draft' }
]) {
  try { api.createItem(candidate); } catch (error) { errors.push(error.message); }
}
process.stdout.write(JSON.stringify({ errors }));
NODE);

        assertSame([
            'Outbox item action is unsupported.',
            'Outbox item state is unsupported.',
            'Outbox items require a stable id.',
        ], $result['errors']);
    }
}
