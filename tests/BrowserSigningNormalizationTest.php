<?php

declare(strict_types=1);

final class BrowserSigningNormalizationTest
{
    /**
     * @return array<string, mixed>
     */
    private function runHelper(string $text, bool $removeUnsupported = false): array
    {
        $script = <<<'NODE'
global.window = {};
global.localStorage = { getItem(){ return ''; }, setItem(){}, removeItem(){} };
global.document = {
  addEventListener(){},
  querySelector(){ return null; },
  createElement(){ return { setAttribute(){}, style:{}, select(){}, value:'' }; },
  body: { appendChild(){}, removeChild(){} },
};
global.navigator = {};
const fs = require('fs');
const vm = require('vm');
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
const helper = window.__forumComposeNormalization.normalizeComposeAscii;
process.stdout.write(JSON.stringify(helper(process.argv[2], { removeUnsupported: process.argv[3] === '1' })));
NODE;

        $command = sprintf(
            'node -e %s %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/browser_signing.js'),
            escapeshellarg($text),
            escapeshellarg($removeUnsupported ? '1' : '0'),
        );

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper execution failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testNormalizeComposeAsciiRewritesCommonPunctuation(): void
    {
        $result = $this->runHelper('“hello”… — test');

        assertSame('"hello"... - test', $result['text']);
        assertSame(true, $result['hadCorrections']);
        assertSame(0, $result['unsupportedCount']);
        assertSame(0, $result['removedUnsupportedCount']);
    }

    public function testNormalizeComposeAsciiReportsUnsupportedCharacters(): void
    {
        $result = $this->runHelper('emoji 🙂 test');

        assertSame('emoji 🙂 test', $result['text']);
        assertSame(false, $result['hadCorrections']);
        assertSame(1, $result['unsupportedCount']);
        assertSame(0, $result['removedUnsupportedCount']);
    }

    public function testNormalizeComposeAsciiCanRemoveUnsupportedCharacters(): void
    {
        $result = $this->runHelper('emoji 🙂 test', true);

        assertSame('emoji  test', $result['text']);
        assertSame(false, $result['hadCorrections']);
        assertSame(0, $result['unsupportedCount']);
        assertSame(1, $result['removedUnsupportedCount']);
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
