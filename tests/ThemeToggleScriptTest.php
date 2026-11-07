<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

final class ThemeToggleScriptTest
{
    public function testThemeCycleBehavior(): void
    {
        $this->assertScriptPasses('theme_cycle.cjs');
    }

    public function testThemeScriptsStartOnlyWhileActive(): void
    {
        $this->assertScriptPasses('theme_scripts.cjs');
    }

    private function assertScriptPasses(string $script): void
    {
        $command = 'cd ' . escapeshellarg(dirname(__DIR__)) . ' && node '
            . escapeshellarg('tests/browser/' . $script) . ' 2>&1';
        exec($command, $output, $status);

        assertSame(0, $status, implode("\n", $output));
    }
}
