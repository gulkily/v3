<?php

declare(strict_types=1);

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
process.stdout.write(JSON.stringify({ threadBound: article.getAttribute("data-thread-reactions-bound"), postBound: post.getAttribute("data-post-reactions-bound"), threadListeners: article.listenerCount(), postListeners: post.listenerCount() }));
NODE);

        assertSame('1', $result['threadBound']);
        assertSame('1', $result['postBound']);
        assertSame(1, $result['threadListeners']);
        assertSame(1, $result['postListeners']);
    }
}
