<?php

declare(strict_types=1);

final class OfflineOutboxStorageTest
{
    /** @return array<string, mixed> */
    private function runStorageScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg(<<<'NODE'
const fs = require('fs');
const vm = require('vm');
global.window = {};
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
NODE
                . "\n" . $script),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_store.js'),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_storage.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox storage helper failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testUnavailableAndQuotaStorageErrorsAreActionable(): void
    {
        $result = $this->runStorageScript(<<<'NODE'
(async function () {
  let unavailable = '';
  try { await window.forumOutboxStorage.list(); } catch (error) { unavailable = error.message; }
  process.stdout.write(JSON.stringify({
    unavailable,
    quota: window.forumOutboxStorage.errorMessage({ name: 'QuotaExceededError' }),
    security: window.forumOutboxStorage.errorMessage({ name: 'SecurityError' })
  }));
})();
NODE);

        assertSame('Outbox storage is unavailable on this device.', $result['unavailable']);
        assertSame('Outbox storage is full. Export or discard local work before adding more.', $result['quota']);
        assertSame('This browser does not allow local Outbox storage.', $result['security']);
    }
}
