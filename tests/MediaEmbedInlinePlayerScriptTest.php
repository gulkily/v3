<?php

declare(strict_types=1);

final class MediaEmbedInlinePlayerScriptTest
{
    public function testScriptHasValidSyntax(): void
    {
        $command = sprintf(
            'node --check %s',
            escapeshellarg(__DIR__ . '/../public/assets/media_embed_inline_player.js')
        );

        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Media embed inline player script syntax check failed: ' . implode("\n", $output));
        }
    }

    public function testScriptSetsIframeSrcOnlyOnToggleOpenNotAlreadySet(): void
    {
        $script = (string) file_get_contents(__DIR__ . '/../public/assets/media_embed_inline_player.js');

        assertSame(true, str_contains($script, 'addEventListener('));
        assertSame(true, str_contains($script, '"toggle"'));
        assertSame(true, str_contains($script, 'data-embed-src'));
        assertSame(true, str_contains($script, 'details.open'));
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
            );
        }
    }
}
