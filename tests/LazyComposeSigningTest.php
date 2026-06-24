<?php

declare(strict_types=1);

final class LazyComposeSigningTest
{
    /**
     * @return array<string, mixed>
     */
    private function runScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/lazy_compose_signing.js'),
        );

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper execution failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testFirstComposeIntentLoadsSigningAssetsAndInitializesComposer(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');

(async function () {
  const listeners = {};
  const appended = [];
  const root = {
    addEventListener(type, handler) {
      listeners[type] = handler;
    }
  };
  const bodyField = {
    matches(selector) {
      return selector.includes('textarea[name="body"]');
    }
  };
  global.window = {
    ForumBrowserSigning: {
      init(rootArg) {
        this.initCalled = true;
        this.initRootMatched = rootArg === root;
      }
    }
  };
  global.document = {
    querySelector(selector) {
      if (selector === '[data-compose-root]') {
        return root;
      }
      if (selector.startsWith('script[src*=')) {
        return appended.find((entry) => entry.src.includes(selector.slice(13, -2))) || null;
      }
      return null;
    },
    createElement(tagName) {
      return { tagName, src: '', defer: false, onload: null, onerror: null };
    },
    head: {
      appendChild(script) {
        appended.push(script);
      }
    }
  };

  vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
  listeners.focusin({ target: bodyField });
  listeners.input({ target: bodyField });
  await Promise.resolve();
  const afterDuplicateIntent = appended.map((script) => script.src);
  appended[0].onload();
  await Promise.resolve();
  appended[1].onload();
  await Promise.resolve();
  await Promise.resolve();
  await Promise.resolve();
  await Promise.resolve();
  await Promise.resolve();

  process.stdout.write(JSON.stringify({
    afterDuplicateIntent,
    appended: appended.map((script) => ({ src: script.src, defer: script.defer })),
    initCalled: window.ForumBrowserSigning.initCalled === true,
    initRootMatched: window.ForumBrowserSigning.initRootMatched === true
  }));
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
NODE;

        $result = $this->runScript($script);

        assertSame(['/assets/openpgp_loader.js'], $result['afterDuplicateIntent']);
        assertSame(
            [
                ['src' => '/assets/openpgp_loader.js', 'defer' => true],
                ['src' => '/assets/browser_signing.js', 'defer' => true],
            ],
            $result['appended']
        );
        assertSame(true, $result['initCalled']);
        assertSame(true, $result['initRootMatched']);
    }
}
