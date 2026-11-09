<?php

declare(strict_types=1);

final class ThreadUrlHintsTest
{
    public function testCreatedPostHintIsRemovedWithoutTheVersionChecker(): void
    {
        $result = $this->runScript(<<<'NODE'
const source = require('fs').readFileSync(process.argv[1], 'utf8');
const vm = require('vm');
function load(path) {
  const replacements = [];
  const state = {saved: 'history'};
  const window = {
    location: {href: 'https://example.test' + path},
    history: {state, replaceState(value, title, url) {replacements.push({value, url});}}
  };
  vm.runInNewContext(source, {window, URL});
  return replacements;
}
process.stdout.write(JSON.stringify({
  created: load('/threads/thread-001?created_post_id=thread-001'),
  reply: load('/threads/thread-001?page=2&created_post_id=reply-002#post-reply-002'),
  clean: load('/threads/thread-001#post-reply-002'),
  forte: load('/forte?selected=thread-001&created_post_id=reply-002')
}));
NODE);
        assertSame('https://example.test/threads/thread-001', $result['created'][0]['url']);
        assertSame(['saved' => 'history'], $result['created'][0]['value']);
        assertSame('https://example.test/threads/thread-001?page=2#post-reply-002', $result['reply'][0]['url']);
        assertSame([], $result['clean']);
        assertSame([], $result['forte']);
    }

    /**
     * @return array<string, mixed>
     */
    private function runScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/thread_url_hints.js'),
        );

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper execution failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }
}
