<?php

declare(strict_types=1);

final class UrlHintsTest
{
    public function testOneTimeHintsStayOnFirstLoadAndAreRemovedOnReload(): void
    {
        $result = $this->runScript(<<<'NODE'
const source = require('fs').readFileSync(process.argv[1], 'utf8');
const vm = require('vm');
function load(path, redirecting, type = 'reload') {
  const replacements = [];
  const state = {saved: 'history'};
  const window = {
    location: {href: 'https://example.test' + path},
    history: {state, replaceState(value, title, url) {replacements.push({value, url});}},
    performance: {getEntriesByType: () => [{type}]}
  };
  const document = {documentElement: {hasAttribute: (name) => redirecting === true && name === 'data-version-redirect'}};
  vm.runInNewContext(source, {window, document, URL});
  return replacements;
}
process.stdout.write(JSON.stringify({
  created: load('/threads/thread-001?created_post_id=thread-001&__v=abc'),
  reply: load('/threads/thread-001?page=2&created_post_id=reply-002#post-reply-002'),
  versioned: load('/tools/user-access/pending/?__v=abc'),
  clean: load('/threads/thread-001#post-reply-002'),
  forte: load('/forte?selected=thread-001&created_post_id=reply-002'),
  redirecting: load('/threads/thread-001?created_post_id=thread-001&__v=abc', true),
  firstLoad: load('/threads/thread-001?created_post_id=thread-001&__v=abc', false, 'navigate'),
  backForward: load('/threads/thread-001?created_post_id=thread-001', false, 'back_forward')
}));
NODE);
        assertSame('https://example.test/threads/thread-001', $result['created'][0]['url']);
        assertSame(['saved' => 'history'], $result['created'][0]['value']);
        assertSame('https://example.test/threads/thread-001?page=2#post-reply-002', $result['reply'][0]['url']);
        assertSame('https://example.test/tools/user-access/pending/', $result['versioned'][0]['url']);
        assertSame([], $result['clean']);
        assertSame([], $result['forte']);
        assertSame([], $result['redirecting']);
        assertSame([], $result['firstLoad']);
        assertSame([], $result['backForward']);
    }

    /**
     * @return array<string, mixed>
     */
    private function runScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/url_hints.js'),
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
