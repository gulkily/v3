<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\View\TemplateRenderer;

final class ForteBoardReaderTest
{
    /** @return array<string, mixed> */
    private function runScript(string $asset, string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/' . $asset),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Forte board reader helper failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testStandaloneLayoutResolvesColorSchemeFromSavedThemeOrSystem(): void
    {
        $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');
        $html = $renderer->renderStandalonePage('message.php', ['heading' => 'Test', 'message' => 'Test'], 'Title');
        assertSame(true, str_contains($html, '<meta name="color-scheme" content="light dark">'));

        $htmlPath = tempnam(sys_get_temp_dir(), 'forte-scheme-');
        file_put_contents($htmlPath, $html);
        try {
            $command = sprintf(
                'node -e %s %s',
                escapeshellarg(<<<'NODE'
const fs = require("fs");
const html = fs.readFileSync(process.argv[1], "utf8");
const source = html.match(/<script>\s*\(function \(\) \{[\s\S]*?<\/script>/)[0].replace(/<\/?script>/g, "");
function resolve(stored, osDark) {
  let scheme = null;
  global.localStorage = { getItem() { return stored; } };
  global.window = { matchMedia() { return { matches: osDark }; } };
  global.document = { documentElement: { setAttribute(name, value) { if (name === "data-forte-scheme") scheme = value; } } };
  eval(source);
  return scheme;
}
console.log(JSON.stringify({
  autoDarkSystem: resolve(null, true),
  autoLightSystem: resolve(null, false),
  lightOnDarkSystem: resolve("light", true),
  darkOnLightSystem: resolve("dark", false),
  darkNamedTheme: resolve("console", false),
  lightNamedTheme: resolve("whitehot", true),
  unknownStoredTheme: resolve("not-a-theme", false)
}));
NODE),
                escapeshellarg($htmlPath),
            );
            exec($command . ' 2>&1', $output, $exitCode);
        } finally {
            unlink($htmlPath);
        }
        if ($exitCode !== 0) {
            throw new RuntimeException('Forte scheme helper failed: ' . implode("\n", $output));
        }

        assertSame([
            'autoDarkSystem' => 'dark',
            'autoLightSystem' => 'light',
            'lightOnDarkSystem' => 'light',
            'darkOnLightSystem' => 'dark',
            'darkNamedTheme' => 'dark',
            'lightNamedTheme' => 'light',
            'unknownStoredTheme' => 'light',
        ], json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testSelectingThreadFetchesItsDetailPane(): void
    {
        $result = $this->runScript('paned_board_reader.js', <<<'NODE'
const fs = require("fs");
const vm = require("vm");
const source = fs.readFileSync(process.argv[1], "utf8");
const documentListeners = {};
const fetches = [];

function createElement(attributes) {
  const listeners = {};
  const values = Object.assign({}, attributes || {});
  return {
    hidden: false,
    listeners,
    classList: {
      values: {},
      contains(name) { return !!this.values[name]; },
      toggle(name, enabled) { this.values[name] = !!enabled; },
      remove(name) { delete this.values[name]; }
    },
    getAttribute(name) { return Object.prototype.hasOwnProperty.call(values, name) ? values[name] : null; },
    setAttribute(name, value) { values[name] = String(value); },
    addEventListener(name, listener) { listeners[name] = listener; },
    querySelector() { return null; },
    querySelectorAll() { return []; },
    insertAdjacentHTML() {}
  };
}

const row = createElement({ "data-paned-thread-id": "root-002", "data-paned-thread-tags": "general" });
row.closest = function(selector) { return selector === ".paned-list-row" ? row : null; };
const folderTree = createElement();
const listBody = createElement();
listBody.querySelectorAll = function(selector) { return selector === ".paned-list-row" ? [row] : []; };
const placeholder = createElement();
const contentPane = createElement();
contentPane.querySelector = function(selector) {
  return selector === "[data-paned-board-content-placeholder]" ? placeholder : null;
};

global.window = {
  location: { pathname: "/forte", search: "" },
  addEventListener() {},
  ForumThreadReactions: { bindWithin() {} }
};
global.location = window.location;
global.history = { pushState() {}, replaceState() {} };
global.document = {
  addEventListener(name, listener) { documentListeners[name] = listener; },
  querySelector(selector) {
    if (selector === "[data-paned-folder-tree]") return folderTree;
    if (selector === "[data-paned-board-list-body]") return listBody;
    if (selector === "[data-paned-board-content-pane]") return contentPane;
    return null;
  }
};
global.fetch = function(url) {
  fetches.push(String(url));
  return new Promise(function() {});
};

vm.runInThisContext(source);
documentListeners.DOMContentLoaded();
listBody.listeners.click({ target: row });
process.stdout.write(JSON.stringify({ fetches, selected: row.classList.contains("paned-list-row--selected"), placeholderHidden: placeholder.hidden }));
NODE);

        assertSame(['/api/forte_thread_detail?thread_id=root-002'], $result['fetches']);
        assertSame(true, $result['selected']);
        assertSame(true, $result['placeholderHidden']);
    }

    public function testThreadReactionBinderSupportsInjectedPaneRoots(): void
    {
        $result = $this->runScript('thread_reactions.js', <<<'NODE'
const fs = require("fs");
const vm = require("vm");
const source = fs.readFileSync(process.argv[1], "utf8");
const documentListeners = {};

function createElement(attributes) {
  const values = Object.assign({}, attributes || {});
  let listenerCount = 0;
  return {
    getAttribute(name) { return Object.prototype.hasOwnProperty.call(values, name) ? values[name] : null; },
    setAttribute(name, value) { values[name] = String(value); },
    addEventListener() { listenerCount++; },
    querySelector() { return null; },
    querySelectorAll() { return []; },
    matches() { return false; },
    listenerCount() { return listenerCount; }
  };
}

const post = createElement({ "data-post-id": "reply-001" });
post.matches = function(selector) { return selector === ".post-card[data-post-id]"; };
const article = createElement({ "data-thread-id": "root-001" });
article.matches = function(selector) { return selector === "[data-thread-reactions-root]"; };
article.querySelectorAll = function(selector) { return selector === ".post-card[data-post-id]" ? [post] : []; };

global.window = {};
global.document = { addEventListener(name, listener) { documentListeners[name] = listener; } };
vm.runInThisContext(source);
window.ForumThreadReactions.bindWithin(article);
window.ForumThreadReactions.bindWithin(article);
process.stdout.write(JSON.stringify({ threadListeners: article.listenerCount(), postListeners: post.listenerCount() }));
NODE);

        assertSame(1, $result['threadListeners']);
        assertSame(1, $result['postListeners']);
    }

    public function testPaneCacheUsesByteBoundedLruEviction(): void
    {
        $result = $this->runScript('paned_board_reader.js', <<<'NODE'
const fs = require("fs");
const vm = require("vm");
const source = fs.readFileSync(process.argv[1], "utf8");
const documentListeners = {};
function element() {
  return {
    hidden: false,
    classList: { contains() { return false; }, toggle() {}, remove() {} },
    getAttribute() { return null; }, setAttribute() {}, addEventListener() {},
    querySelector() { return null; }, querySelectorAll() { return []; }, insertAdjacentHTML() {}
  };
}
const folderTree = element();
const listBody = element();
const contentPane = element();
global.window = { location: { pathname: "/forte", search: "" }, addEventListener() {}, setTimeout() {} };
global.location = window.location;
global.history = { pushState() {}, replaceState() {} };
global.document = {
  addEventListener(name, listener) { documentListeners[name] = listener; },
  querySelector(selector) {
    if (selector === "[data-paned-folder-tree]") return folderTree;
    if (selector === "[data-paned-board-list-body]") return listBody;
    if (selector === "[data-paned-board-content-pane]") return contentPane;
    return null;
  }
};
vm.runInThisContext(source);
documentListeners.DOMContentLoaded();
const cache = window.ForteBoardReader.createPaneCache(2, 12);
cache.set("alpha", "aaa", "alpha");
cache.set("beta", "bbb", "alpha");
cache.get("alpha");
cache.set("gamma", "ccc", "alpha");
const oversized = cache.set("large", "1234567", "alpha");
process.stdout.write(JSON.stringify({ alpha: cache.get("alpha"), beta: cache.get("beta"), gamma: cache.get("gamma"), oversized, snapshot: cache.snapshot() }));
NODE);

        assertSame('aaa', $result['alpha']);
        assertSame(null, $result['beta']);
        assertSame('ccc', $result['gamma']);
        assertSame(false, $result['oversized']);
        assertSame(2, $result['snapshot']['count']);
        assertSame(12, $result['snapshot']['bytes']);
    }
}
