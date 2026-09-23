<?php

declare(strict_types=1);

final class OpenPgpLoaderTest
{
    /**
     * @return array<string, mixed>
     */
    private function runLoader(bool $secureContext, bool $startLoad = false): array
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');

const appendedNodes = [];
global.window = { isSecureContext: process.argv[2] === '1' };
global.document = {
  createElement(tagName) {
    return {
      tagName,
      async: true,
      rel: '',
      as: '',
      href: '',
      src: '',
      onload: null,
      onerror: null
    };
  },
  head: {
    appendChild(node) {
      appendedNodes.push({ tagName: node.tagName, rel: node.rel, as: node.as, href: node.href, src: node.src, async: node.async });
    }
  }
};

vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
if (process.argv[3] === '1') {
  window.__forumOpenPgpLoader.load();
}
process.stdout.write(JSON.stringify({
  selectedVersion: window.__forumOpenPgpLoader.selectedVersion,
  selectedPath: window.__forumOpenPgpLoader.selectedPath,
  appendedNodes
}));
NODE;

        $command = sprintf(
            'node -e %s %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/openpgp_loader.js'),
            escapeshellarg($secureContext ? '1' : '0'),
            escapeshellarg($startLoad ? '1' : '0'),
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
        assertSame([[
            'tagName' => 'link',
            'rel' => 'preload',
            'as' => 'script',
            'href' => '/assets/openpgp.min.js',
            'src' => '',
            'async' => true,
        ]], $result['appendedNodes']);
    }

    public function testInsecureContextSelectsOpenPgpV5FallbackBundle(): void
    {
        $result = $this->runLoader(false);

        assertSame('v5', $result['selectedVersion']);
        assertSame('/assets/openpgp.v5.11.3.min.js', $result['selectedPath']);
        assertSame([[
            'tagName' => 'link',
            'rel' => 'preload',
            'as' => 'script',
            'href' => '/assets/openpgp.v5.11.3.min.js',
            'src' => '',
            'async' => true,
        ]], $result['appendedNodes']);
    }

    public function testEvaluationStartsOnlyWhenACallerRequestsIt(): void
    {
        $result = $this->runLoader(true, true);

        assertSame('link', $result['appendedNodes'][0]['tagName']);
        assertSame('script', $result['appendedNodes'][1]['tagName']);
        assertSame('/assets/openpgp.min.js', $result['appendedNodes'][1]['src']);
        assertSame(true, $result['appendedNodes'][1]['async']);
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
