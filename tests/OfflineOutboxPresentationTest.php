<?php

declare(strict_types=1);

final class OfflineOutboxPresentationTest
{
    public function testOutboxUsesCompactExpandableRowsWithBothTimestampMeanings(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const source = fs.readFileSync(process.argv[1], 'utf8');
process.stdout.write(JSON.stringify({
  details: source.includes('document.createElement("details")'),
  summary: source.includes('document.createElement("summary")'),
  actionTime: source.includes('Action time (signed)'),
  integrationTime: source.includes('Integration time '),
  privatePayload: !source.includes('item.payload')
}));
NODE;
        $command = sprintf('node -e %s %s', escapeshellarg($script), escapeshellarg(__DIR__ . '/../public/assets/outbox.js'));
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox compact-presentation check failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame(['details' => true, 'summary' => true, 'actionTime' => true, 'integrationTime' => true, 'privatePayload' => true], $result);
    }

    public function testOutboxLinksEligibleOriginalTargetsWithoutLinkingIncompleteTargets(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
global.window = {};
global.document = { addEventListener() {} };
const source = fs.readFileSync(process.argv[1], 'utf8').replace(
  'document.addEventListener("DOMContentLoaded", function () {',
  'window.outboxTargetLink = targetLink; document.addEventListener("DOMContentLoaded", function () {'
);
vm.runInThisContext(source);
process.stdout.write(JSON.stringify([
  window.outboxTargetLink({ target: { kind: 'thread', id: 'thread id' } }),
  window.outboxTargetLink({ target: { kind: 'post', id: 'post id' } }),
  window.outboxTargetLink({ target: { kind: 'board' } }),
  window.outboxTargetLink({ target: { kind: 'thread' } })
]));
NODE;
        $command = sprintf('node -e %s %s', escapeshellarg($script), escapeshellarg(__DIR__ . '/../public/assets/outbox.js'));
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox target-link rendering check failed: ' . implode("\n", $output));
        }
        $links = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame([
            ['href' => '/threads/thread%20id', 'text' => 'View target thread'],
            ['href' => '/posts/post%20id', 'text' => 'View target post'],
            null,
            null,
        ], $links);
    }
}
