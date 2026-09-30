<?php

declare(strict_types=1);

final class OfflineNavigationWorkerTest
{
    /** @return array<string, bool> */
    private function navigationPolicy(): array
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
global.self = {
  location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' },
  addEventListener() {}
};
const paths = [
  '/', '/threads', '/threads/?view=liked&sort=top', '/threads/root-001',
  '/tags', '/tags/', '/tags/general', '/tags/bug-fix',
  '/compose/thread', '/profiles/alice', '/search/', '/tags/Bad', '/tags/bug/extra'
];
vm.runInThisContext(source);
const policy = Object.fromEntries(paths.map((path) => [
  path,
  supportsOfflineNavigation(new URL(path, 'https://forum.test'))
]));
process.stdout.write(JSON.stringify(policy));
NODE;

        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/service_worker.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Worker policy helper failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testOfflineNavigationPolicyAdmitsOnlySupportedReadRoutes(): void
    {
        $policy = $this->navigationPolicy();

        foreach (['/', '/threads', '/threads/?view=liked&sort=top', '/threads/root-001', '/tags', '/tags/', '/tags/general', '/tags/bug-fix'] as $path) {
            assertSame(true, $policy[$path]);
        }
        foreach (['/compose/thread', '/profiles/alice', '/search/', '/tags/Bad', '/tags/bug/extra'] as $path) {
            assertSame(false, $policy[$path]);
        }
    }
}
