<?php

declare(strict_types=1);

final class OfflineSnapshotPresentationTest
{
    /** @return array<int, array<string, mixed>> */
    private function tagGroups(): array
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
global.window = {};
global.document = { addEventListener() {} };
vm.runInThisContext(source);
const rows = [
  ['t1', 'One', '', 0, '2026-01-01T00:00:00Z', 'A', '2026-01-01T00:00:00Z', '["topic","topic"]', '["general","topic"]', 0],
  ['t2', 'Two', '', 0, '2026-01-02T00:00:00Z', 'B', '2026-01-02T00:00:00Z', '["topic"]', '["general"]', 0],
  ['t3', 'Three', '', 0, '2026-01-03T00:00:00Z', 'C', '2026-01-03T00:00:00Z', '["bug"]', 'not json', 0],
  ['t4', 'Four', '', 0, '2026-01-04T00:00:00Z', 'D', '2026-01-04T00:00:00Z', '[]', '["general"]', 0],
  ['t5', 'Five', '', 0, '2026-01-05T00:00:00Z', 'E', '2026-01-05T00:00:00Z', '[]', '["general"]', 0],
  ['t6', 'Six', '', 0, '2026-01-06T00:00:00Z', 'F', '2026-01-06T00:00:00Z', '[]', '["general"]', 0],
  ['t7', 'Seven', '', 0, '2026-01-07T00:00:00Z', 'G', '2026-01-07T00:00:00Z', '[]', '["general"]', 0]
];
const database = { exec() { return [{ values: rows }]; } };
const groups = window.forumOfflineSnapshot.tagGroups(database).map((group) => ({
  tag: group.tag, count: group.count, ids: group.threads.map((thread) => thread.id),
  previewIds: group.previewThreads.map((thread) => thread.id), hasMore: group.hasMore, href: group.href
}));
process.stdout.write(JSON.stringify(groups));
NODE;

        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/offline_reader.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Snapshot presentation helper failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testTagGroupsMatchSavedBoardTagAndLabelSemantics(): void
    {
        $groups = $this->tagGroups();

        assertSame('general', $groups[0]['tag']);
        assertSame(6, $groups[0]['count']);
        assertSame(['t7', 't6', 't5', 't4', 't2', 't1'], $groups[0]['ids']);
        assertSame(['t7', 't6', 't5', 't4', 't2'], $groups[0]['previewIds']);
        assertSame(true, $groups[0]['hasMore']);
        assertSame('/tags/general', $groups[0]['href']);
        assertSame('topic', $groups[1]['tag']);
        assertSame(2, $groups[1]['count']);
        assertSame(['t2', 't1'], $groups[1]['ids']);
        assertSame('bug', $groups[2]['tag']);
        assertSame(1, $groups[2]['count']);
    }

    public function testBoardUrlsPreserveNormalRouteAndSelectedState(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
global.window = {};
global.document = { addEventListener() {} };
vm.runInThisContext(source);
const api = window.forumOfflineSnapshot;
process.stdout.write(JSON.stringify({
  root: api.normalBoardUrl(api.boardPathFromPathname('/'), { view: 'liked', sort: 'top' }),
  threads: api.normalBoardUrl(api.boardPathFromPathname('/threads/'), { view: 'all', sort: 'oldest' }),
  noSlash: api.normalBoardUrl(api.boardPathFromPathname('/threads'), { view: 'liked', sort: 'newest' }),
  unsupported: api.boardPathFromPathname('/tags/'),
  thread: api.normalThreadUrl('thread id')
}));
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/offline_reader.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Board URL helper failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('/?view=liked&sort=top', $result['root']);
        assertSame('/threads/?view=all&sort=oldest', $result['threads']);
        assertSame('/threads?view=liked&sort=newest', $result['noSlash']);
        assertSame('', $result['unsupported']);
        assertSame('/threads/thread%20id', $result['thread']);
    }

    public function testReaderRevisionUsesCachedShellFingerprintWithSafeFallback(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
global.window = {};
global.document = { addEventListener() {} };
vm.runInThisContext(source);
const api = window.forumOfflineSnapshot;
const shell = {
  querySelector(selector) {
    return selector === '[data-offline-reader]' ? {
      getAttribute(name) { return name === 'data-reader-revision' ? '/assets/offline_reader.123456789abc.js' : ''; }
    } : null;
  }
};
process.stdout.write(JSON.stringify({
  revision: api.readerRevisionFromShell(shell),
  absent: api.readerRevisionFromShell({ querySelector() { return null; } }),
  invalid: api.readerRevisionFromShell(null)
}));
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/offline_reader.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Reader revision helper failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('/assets/offline_reader.123456789abc.js', $result['revision']);
        assertSame('unknown', $result['absent']);
        assertSame('unknown', $result['invalid']);
    }

    public function testCompactOfflineIndicatorsUseStableLabelsAndFallbacks(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
global.window = {};
global.document = { addEventListener() {} };
vm.runInThisContext(source);
const api = window.forumOfflineSnapshot;
process.stdout.write(JSON.stringify({
  archive: api.compactArchiveIndicator('2026-09-30T05:33:24Z'),
  archiveFallback: api.compactArchiveIndicator(''),
  reader: api.compactReaderIndicator('/assets/offline_reader.7d25a6f66fa7.js'),
  readerFallback: api.compactReaderIndicator('unknown')
}));
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/offline_reader.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Compact offline indicator helper failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('archive 2026-09-30 05:33 UTC', $result['archive']);
        assertSame('archive unknown', $result['archiveFallback']);
        assertSame('reader 7d25a6f66fa7', $result['reader']);
        assertSame('reader unknown', $result['readerFallback']);
    }

    public function testTagsIndexRendersSavedGroupsAndEmptyState(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
function element(type) {
  return {
    type, children: [], className: '', dataset: {}, textContent: '', href: '', hidden: false,
    appendChild(child) { this.children.push(child); return child; },
    removeChild(child) { this.children.splice(this.children.indexOf(child), 1); },
    get firstChild() { return this.children[0] || null; }, setAttribute(name, value) { this[name] = value; }, addEventListener() {}
  };
}
global.window = {};
global.document = { addEventListener() {}, createElement: element };
vm.runInThisContext(source);
const rows = [
  ['t1', 'One', '', 0, '2026-01-01T00:00:00Z', 'A', '', '[]', '["general"]', 0],
  ['t2', 'Two', '', 0, '2026-01-02T00:00:00Z', 'B', '', '[]', '["general"]', 0],
  ['t3', 'Three', '', 0, '2026-01-03T00:00:00Z', 'C', '', '[]', '["general"]', 0],
  ['t4', 'Four', '', 0, '2026-01-04T00:00:00Z', 'D', '', '[]', '["general"]', 0],
  ['t5', 'Five', '', 0, '2026-01-05T00:00:00Z', 'E', '', '[]', '["general"]', 0],
  ['t6', 'Six', '', 0, '2026-01-06T00:00:00Z', 'F', '', '[]', '["general"]', 0]
];
const content = element('div');
window.forumOfflineSnapshot.renderTagsIndex({ content, database: { exec() { return [{ values: rows }]; } } });
const empty = element('div');
window.forumOfflineSnapshot.renderTagsIndex({ empty, content: empty, database: { exec() { return [{ values: [] }]; } } });
function collect(node, result) {
  if (node.type === 'a') result.links.push({ href: node.href, text: node.textContent });
  if (node.type === 'button') result.buttons.push({ disabled: node.disabled, text: node.textContent });
  if (node.textContent) result.text.push(node.textContent);
  node.children.forEach((child) => collect(child, result));
}
const rendered = { links: [], buttons: [], text: [] };
const emptyRendered = { links: [], buttons: [], text: [] };
collect(content, rendered);
collect(empty, emptyRendered);
process.stdout.write(JSON.stringify({ rendered, emptyRendered, hidden: content.hidden }));
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/offline_reader.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Tags index renderer failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame(false, $result['hidden']);
        assertSame([
            ['href' => '/tags/', 'text' => 'Tags'],
            ['href' => '/threads/?view=all&sort=newest', 'text' => 'All'],
            ['href' => '/threads/?view=liked&sort=newest', 'text' => 'Liked'],
            ['href' => '/threads/?view=all&sort=newest', 'text' => 'Newest'],
            ['href' => '/threads/?view=all&sort=oldest', 'text' => 'Oldest'],
            ['href' => '/threads/?view=all&sort=top', 'text' => 'Top'],
            ['href' => '/tags/general', 'text' => '#general'],
            ['href' => '/threads/t6', 'text' => 'Six'],
            ['href' => '/threads/t5', 'text' => 'Five'],
            ['href' => '/threads/t4', 'text' => 'Four'],
            ['href' => '/threads/t3', 'text' => 'Three'],
            ['href' => '/threads/t2', 'text' => 'Two'],
        ], $result['rendered']['links']);
        assertSame([['disabled' => true, 'text' => 'New Post']], $result['rendered']['buttons']);
        assertSame(true, in_array('showing 5 newest of 6', $result['rendered']['text'], true));
        assertSame(true, in_array('No tags were included in this saved snapshot. Reconnect to browse live tags.', $result['emptyRendered']['text'], true));
    }

    public function testTagResultRendersAllSavedThreadsAndMissingState(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
function element(type, text) {
  return {
    type, children: [], className: '', dataset: {}, textContent: text || '', href: '', hidden: false,
    appendChild(child) { this.children.push(child); return child; },
    removeChild(child) { this.children.splice(this.children.indexOf(child), 1); },
    get firstChild() { return this.children[0] || null; }, setAttribute(name, value) { this[name] = value; }, addEventListener() {}
  };
}
global.window = {};
global.document = { addEventListener() {}, createElement: element, createTextNode(text) { return element('#text', text); } };
vm.runInThisContext(source);
const rows = [
  ['t1', 'One', '', 0, '2026-01-01T00:00:00Z', 'A', '', '[]', '["general"]', 0],
  ['t2', 'Two', '', 0, '2026-01-02T00:00:00Z', 'B', '', '["topic"]', '[]', 0],
  ['t3', 'Three', '', 0, '2026-01-03T00:00:00Z', 'C', '', '["topic"]', '[]', 0]
];
const database = { exec() { return [{ values: rows }]; } };
const content = element('div');
const found = window.forumOfflineSnapshot.renderTagResult({ content, database, tag: 'topic' });
const missing = element('div');
const missingFound = window.forumOfflineSnapshot.renderTagResult({ content: missing, database, tag: 'absent' });
function collect(node, result) {
  if (node.type === 'a') result.links.push({ href: node.href, text: node.textContent });
  if (node.textContent) result.text.push(node.textContent);
  node.children.forEach((child) => collect(child, result));
}
const rendered = { links: [], text: [] };
const missingRendered = { links: [], text: [] };
collect(content, rendered);
collect(missing, missingRendered);
process.stdout.write(JSON.stringify({
  found, missingFound, rendered, missingRendered,
  tag: window.forumOfflineSnapshot.tagFromPathname('/tags/topic/'), invalid: window.forumOfflineSnapshot.tagFromPathname('/tags/Topic')
}));
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/offline_reader.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Tag-result renderer failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame(true, $result['found']);
        assertSame(false, $result['missingFound']);
        assertSame('topic', $result['tag']);
        assertSame('', $result['invalid']);
        assertSame([
            ['href' => '/tags/', 'text' => 'Back to Tags'],
            ['href' => '/', 'text' => 'Back to Board'],
            ['href' => '/threads/t3', 'text' => 'Three'],
            ['href' => '/threads/t2', 'text' => 'Two'],
        ], $result['rendered']['links']);
        assertSame(true, in_array('This tag is not included in the saved snapshot. Reconnect to browse live results.', $result['missingRendered']['text'], true));
    }

    public function testThreadDetailRendersPostBodyLineBreaksLikeOnlineView(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
function element(type) {
  return {
    type, children: [], className: '', dataset: {}, textContent: '', innerHTML: '', href: '', hidden: false,
    appendChild(child) { this.children.push(child); return child; },
    removeChild(child) { this.children.splice(this.children.indexOf(child), 1); },
    get firstChild() { return this.children[0] || null; }, setAttribute(name, value) { this[name] = value; }, addEventListener() {}
  };
}
global.window = {};
global.document = { addEventListener() {}, createElement: element };
vm.runInThisContext(source);
const rows = [
  ['p1', null, 'My Title', 'My Title\n\nFirst paragraph line.\nSecond line of paragraph.', 'A', '2026-01-01T00:00:00Z'],
  ['p2', 'p1', '', 'A reply with a <script> tag & an "quote".', 'B', '2026-01-02T00:00:00Z']
];
const database = {
  exec(sql) {
    if (sql.indexOf('FROM threads') !== -1) return [{ values: [['2026-01-02T00:00:00Z', 1]] }];
    return [{ values: rows }];
  }
};
const content = element('div');
window.forumOfflineSnapshot.renderThreadDetail({
  content, database, threadId: 't1', setStatus() {}
});
process.stdout.write(JSON.stringify({
  rootBody: content.children[0].children[1].innerHTML,
  replyBody: content.children[1].children[1].innerHTML
}));
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/offline_reader.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Thread detail renderer failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame("First paragraph line.<br>\nSecond line of paragraph.", $result['rootBody']);
        assertSame('A reply with a &lt;script&gt; tag &amp; an &quot;quote&quot;.', $result['replyBody']);
    }
}
