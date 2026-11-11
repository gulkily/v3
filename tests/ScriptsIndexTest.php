<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

/**
 * scripts/README.md says what runs each script. ./v3 calls scripts by
 * ${ROOT}/scripts/... path, so without the index a live script can look
 * unused to a name search. Keep it complete in both directions.
 */
final class ScriptsIndexTest
{
    public function testEveryScriptHasARowInTheIndex(): void
    {
        $index = $this->index();
        $missing = [];
        foreach ($this->scripts() as $script) {
            if (!str_contains($index, '`' . $script . '`')) {
                $missing[] = $script;
            }
        }

        assertSame([], $missing);
    }

    public function testTheIndexNamesNoMissingScripts(): void
    {
        preg_match_all('/`((?:lib\/)?[A-Za-z0-9_.-]+\.(?:php|py|pl|sh|mjs|js|cjs))`/', $this->index(), $matches);
        $unknown = array_values(array_diff(array_unique($matches[1]), $this->scripts()));

        assertSame([], $unknown);
    }

    /**
     * Every script the ./v3 launcher runs is in the index's ./v3 section, and
     * says near its top which ./v3 command runs it.
     */
    public function testLauncherScriptsNameTheirCommand(): void
    {
        $root = dirname(__DIR__);
        preg_match_all('/scripts\/([A-Za-z0-9_.-]+\.(?:php|py|pl|sh|mjs|js|cjs))/', (string) file_get_contents($root . '/v3'), $launched);
        $launched = array_values(array_unique($launched[1]));
        sort($launched);

        $section = explode("\n## ", explode('## Behind `./v3` commands', $this->index(), 2)[1] ?? '', 2)[0];
        $problems = [];
        foreach ($launched as $script) {
            if (!str_contains($section, '`' . $script . '`')) {
                $problems[] = $script . ': not in the ./v3 section of scripts/README.md';
            }
            $head = implode("\n", array_slice(file($root . '/scripts/' . $script) ?: [], 0, 15));
            if (!str_contains($head, 'Run with ./v3 ')) {
                $problems[] = $script . ': no "Run with ./v3 ..." line near the top';
            }
        }

        assertSame([], $problems);
    }

    private function index(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/scripts/README.md');
    }

    /** @return list<string> */
    private function scripts(): array
    {
        $root = dirname(__DIR__) . '/scripts';
        $scripts = [];
        foreach ([$root => '', $root . '/lib' => 'lib/'] as $directory => $prefix) {
            foreach (scandir($directory) ?: [] as $name) {
                if ($name !== 'README.md' && is_file($directory . '/' . $name)) {
                    $scripts[] = $prefix . $name;
                }
            }
        }
        sort($scripts);

        return $scripts;
    }
}
