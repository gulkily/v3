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
    __forumAssetPaths: {
      openpgpLoader: '/assets/openpgp_loader.fingerprint.js',
      browserSigning: '/assets/browser_signing.fingerprint.js'
    },
    ForumBrowserSigning: {
      init(rootArg) {
        this.initCalled = true;
        this.initRootMatched = rootArg === global.document;
      }
    }
  };
  global.document = {
    querySelectorAll(selector) {
      return selector === '[data-compose-root]' ? [root] : [];
    },
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
  await window.ForumLazyComposeSigning.load();
  await Promise.resolve();
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

        assertSame(['/assets/openpgp_loader.fingerprint.js'], $result['afterDuplicateIntent']);
        assertSame(
            [
                ['src' => '/assets/openpgp_loader.fingerprint.js', 'defer' => true],
                ['src' => '/assets/browser_signing.fingerprint.js', 'defer' => true],
            ],
            $result['appended']
        );
        assertSame(true, $result['initCalled']);
        assertSame(true, $result['initRootMatched']);
    }

    public function testReactionOnlyPageExposesTheLazyLoaderWithoutAComposeForm(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');

(async function () {
  const appended = [];
  global.window = {
    __forumAssetPaths: {
      openpgpLoader: '/assets/openpgp_loader.fingerprint.js',
      browserSigning: '/assets/browser_signing.fingerprint.js'
    }
  };
  global.document = {
    querySelectorAll() {
      return [];
    },
    querySelector(selector) {
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
  const beforeExplicitLoad = appended.length;
  const loading = window.ForumLazyComposeSigning.load();
  appended[0].onload();
  await Promise.resolve();
  appended[1].onload();
  await loading;

  process.stdout.write(JSON.stringify({
    hasLoader: typeof window.ForumLazyComposeSigning.load === 'function',
    beforeExplicitLoad,
    appended: appended.map((script) => script.src)
  }));
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
NODE;

        $result = $this->runScript($script);

        assertSame(true, $result['hasLoader']);
        assertSame(0, $result['beforeExplicitLoad']);
        assertSame([
            '/assets/openpgp_loader.fingerprint.js',
            '/assets/browser_signing.fingerprint.js',
        ], $result['appended']);
    }

    public function testStoredIdentityOnReactionPagePreloadsAndInitializesSigning(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');

(async function () {
  const appended = [];
  let idleCallback = null;
  global.window = {
    localStorage: {
      getItem(key) {
        return key === 'forum_pki_public_key' || key === 'forum_pki_private_key' ? 'stored-key' : '';
      }
    },
    requestIdleCallback(callback) { idleCallback = callback; },
    __forumAssetPaths: {
      openpgpLoader: '/assets/openpgp_loader.fingerprint.js',
      browserSigning: '/assets/browser_signing.fingerprint.js'
    },
    ForumBrowserSigning: {
      init(rootArg) { this.initRootMatched = rootArg === global.document; }
    }
  };
  global.document = {
    querySelectorAll() { return []; },
    querySelector(selector) {
      if (selector === '[data-thread-reactions-root], .post-card[data-post-id]') return {};
      if (selector.startsWith('script[src*=')) {
        return appended.find((entry) => entry.src.includes(selector.slice(13, -2))) || null;
      }
      return null;
    },
    createElement(tagName) { return { tagName, src: '', defer: false, onload: null, onerror: null }; },
    head: { appendChild(script) { appended.push(script); } }
  };

  vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
  const beforeIdle = appended.length;
  idleCallback();
  await Promise.resolve();
  appended[0].onload();
  await Promise.resolve();
  appended[1].onload();
  await window.ForumLazyComposeSigning.load();

  process.stdout.write(JSON.stringify({
    beforeIdle,
    appended: appended.map((script) => script.src),
    initialized: window.ForumBrowserSigning.initRootMatched === true
  }));
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
NODE;

        $result = $this->runScript($script);

        assertSame(0, $result['beforeIdle']);
        assertSame([
            '/assets/openpgp_loader.fingerprint.js',
            '/assets/browser_signing.fingerprint.js',
        ], $result['appended']);
        assertSame(true, $result['initialized']);
    }
}
