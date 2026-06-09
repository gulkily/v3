<?php

declare(strict_types=1);

final class OpenPgpLoaderTest
{
    /**
     * @return array<string, mixed>
     */
    private function runLoader(bool $secureContext): array
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');

const appendedScripts = [];
global.window = { isSecureContext: process.argv[2] === '1' };
global.document = {
  createElement(tagName) {
    return {
      tagName,
      async: true,
      src: '',
      onload: null,
      onerror: null
    };
  },
  head: {
    appendChild(script) {
      appendedScripts.push({ src: script.src, async: script.async });
    }
  }
};

vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
process.stdout.write(JSON.stringify({
  selectedVersion: window.__forumOpenPgpLoader.selectedVersion,
  selectedPath: window.__forumOpenPgpLoader.selectedPath,
  appendedScripts
}));
NODE;

        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/openpgp_loader.js'),
            escapeshellarg($secureContext ? '1' : '0'),
        );

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper execution failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testSecureContextSelectsOpenPgpV6Bundle(): void
    {
        $result = $this->runLoader(true);

        assertSame('v6', $result['selectedVersion']);
        assertSame('/assets/openpgp.min.js', $result['selectedPath']);
        assertSame([['src' => '/assets/openpgp.min.js', 'async' => false]], $result['appendedScripts']);
    }

    public function testInsecureContextSelectsOpenPgpV5FallbackBundle(): void
    {
        $result = $this->runLoader(false);

        assertSame('v5', $result['selectedVersion']);
        assertSame('/assets/openpgp.v5.11.3.min.js', $result['selectedPath']);
        assertSame([['src' => '/assets/openpgp.v5.11.3.min.js', 'async' => false]], $result['appendedScripts']);
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}
