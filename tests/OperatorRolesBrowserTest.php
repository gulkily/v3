<?php

declare(strict_types=1);

final class OperatorRolesBrowserTest
{
    private function runScenario(string $scenario): array
    {
        $script = <<<'JS'
const fs = require('fs'), vm = require('vm');
const scenario = process.argv[2];
const calls = [], prompts = [];
let submit, signed = 0, reloaded = 0;
const feedback = { textContent: '' }, button = { disabled: false };
const form = { dataset: {}, querySelector(s) { return s === 'button' ? button : feedback; } };
global.FormData = class { entries() { return [['action', 'revoke'], ['grant_id', 'grant-a']]; } };
global.document = { addEventListener(type, cb) { if (type === 'submit') submit = cb; } };
global.window = {
  confirm(message) { prompts.push(message); return scenario !== 'cancel'; },
  location: { reload() { reloaded++; } },
  ForumBrowserSigning: {
    ensureActionIdentity() { return Promise.resolve(); },
    signCanonicalRecord(text) { signed++; return Promise.resolve('signed:' + text); }
  },
  fetch(path, options) {
    calls.push({ path, fields: Object.fromEntries(new URLSearchParams(options.body)) });
    const prepared = { status: 'ok', action: 'revoke', account: 'bob', affected_accounts: ['bob','carol'],
      prepare_token: 'token', post_id: 'post', record_path: 'record', canonical_record: 'canonical' };
    const result = path.includes('prepare_') ? prepared
      : (scenario === 'stale' ? { status: 'error', error: 'Operator or account state changed. Review the action again.' } : { status: 'ok' });
    return Promise.resolve({ ok: result.status === 'ok', json() { return Promise.resolve(result); } });
  }
};
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
submit({ target: { closest() { return form; } }, preventDefault() {} });
setTimeout(() => process.stdout.write(JSON.stringify({ calls, prompts, signed, reloaded, feedback: feedback.textContent, disabled: button.disabled, pending: !!form.dataset.pending })), 20);
JS;
        $command = 'node -e ' . escapeshellarg($script) . ' ' . escapeshellarg(__DIR__ . '/../public/assets/operator_roles.js') . ' ' . escapeshellarg($scenario);
        exec($command . ' 2>&1', $output, $code);
        assertSame(0, $code, implode("\n", $output));
        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testCancellationNeverSignsOrFinalizesAndShowsCascade(): void
    {
        $result = $this->runScenario('cancel');
        assertSame(1, count($result['calls']));
        assertSame(0, $result['signed']);
        assertSame(0, $result['reloaded']);
        assertStringContains('bob, carol', $result['prompts'][0]);
        assertStringContains('Cancelled', $result['feedback']);
        assertSame(false, $result['disabled']);
        assertSame(false, $result['pending']);
    }
    public function testConfirmedChangeSignsExactPreparedRecordAndReloads(): void
    {
        $result = $this->runScenario('confirm');
        assertSame(2, count($result['calls']));
        assertSame('canonical', $result['calls'][1]['fields']['canonical_record']);
        assertSame('signed:canonical', $result['calls'][1]['fields']['detached_signature']);
        assertSame(1, $result['reloaded']);
    }
    public function testStaleConfirmationOffersReviewAndAllowsRetry(): void
    {
        $result = $this->runScenario('stale');
        assertSame(0, $result['reloaded']);
        assertStringContains('Review the action again', $result['feedback']);
        assertSame(false, $result['disabled']);
        assertSame(false, $result['pending']);
    }
}
