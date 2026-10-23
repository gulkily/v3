#!/usr/bin/env python3
"""Classify measured nested-list documents using the canonical renderer, without saving HTML."""
import argparse
from collections import Counter
import hashlib
from html.parser import HTMLParser
import json
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]


class ListAudit(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.stack = []
        self.roots = self.nested = self.maximum_depth = self.ordered_items = 0
        self.fallbacks = []
        self.source = None

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag in ('ul', 'ol'):
            assert not self.stack or self.stack[-1] == 'li', 'Nested list must belong to an item'
            if self.stack:
                self.nested += 1
            else:
                self.roots += 1
            self.stack.append(tag)
            depth = sum(t in ('ul', 'ol') for t in self.stack)
            self.maximum_depth = max(self.maximum_depth, depth)
            assert depth <= 8, 'Unsupported depth was rendered structurally'
        elif tag == 'li':
            assert self.stack and self.stack[-1] in ('ul', 'ol'), 'Item must belong to a list'
            if self.stack[-1] == 'ol':
                assert re.fullmatch(r'[0-9]+', attrs.get('value', '')), 'Ordered item needs an explicit number'
                self.ordered_items += 1
            self.stack.append(tag)
        if tag == 'pre' and attrs.get('class') == 'docs-list-source':
            self.source = ''

    def handle_endtag(self, tag):
        if tag in ('ul', 'ol', 'li'):
            assert self.stack and self.stack.pop() == tag, 'List tree is not balanced'
        if tag == 'pre' and self.source is not None:
            self.fallbacks.append(self.source)
            self.source = None

    def handle_data(self, data):
        if self.source is not None:
            self.source += data


def indicators(text):
    """Source indicators aid review; they are not exhaustive semantic failure diagnoses."""
    reasons = []
    if '\t' in text:
        reasons.append('tab characters')
    if text.startswith(' '):
        reasons.append('indented root marker')
    if re.search(r'(?:^|\n)\s*(?:(?:[-+*]|[0-9]+[.)])\s+)?(?:`{3,}|~{3,})', text):
        reasons.append('embedded fence')
    if re.search(r'(?:^|\n)\s+(?:(?:[-+*]|[0-9]+[.)])\s+)?[|>]', text):
        reasons.append('embedded table or quote')
    if re.search(r'(?:^|\n)\s*[0-9]{10,}[.)]', text):
        reasons.append('oversized numeric marker')
    return reasons or ['unsupported block or ambiguous indentation/nesting']


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--metrics', required=True, type=Path, help='Frozen render report from markdown_metrics.py')
    ap.add_argument('--output', required=True, type=Path)
    args = ap.parse_args()
    metrics = json.loads(args.metrics.read_text())
    assert metrics['render_errors'] == 0
    chosen = [r for r in metrics['documents'] if r['syntax'].get('list_nested_item')]
    inputs = []
    for row in chosen:
        text = subprocess.check_output(['git', 'show', metrics['corpus_revision'] + ':' + row['path']], cwd=ROOT).decode('utf-8', errors='replace')
        assert hashlib.sha256(text.encode()).hexdigest() == row['source_sha256'], row['path']
        inputs.append({'path': row['path'], 'text': text})
    # The same canonical comment boundary is used only for source-equality checks.
    # Reader safety and ownership are independently covered by the regression/browser fixtures.
    php = r'''
require 'autoload.php';
$filter = new ReflectionMethod(ForumRewrite\Docs\PublicMarkdownRenderer::class, 'withoutProseComments');
$rows = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
foreach ($rows as &$row) {
    $row['html'] = ForumRewrite\Docs\PublicMarkdownRenderer::render($row['text'], $row['path']);
    $text = preg_replace('/\A(?:\xEF\xBB\xBF)?---\r?\n(.*?)\r?\n(?:---|\.\.\.)[ \t]*(?:\r?\n|$)/s', '', $row['text'], 1);
    $row['filtered_source'] = implode("\n", $filter->invoke(null, preg_split('/\r\n|\r|\n/', $text)));
    unset($row['text']);
}
echo json_encode($rows, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
'''
    rendered = json.loads(subprocess.check_output(['php', '-r', php], input=json.dumps(inputs).encode(), cwd=ROOT))
    documents = []
    for row, output in zip(chosen, rendered):
        audit = ListAudit()
        try:
            audit.feed(output['html'])
            assert not audit.stack, 'Unclosed list nodes'
            for source in audit.fallbacks:
                assert source in output['filtered_source'], 'Fallback differs from safety-filtered source'
        except AssertionError as error:
            raise AssertionError(row['path'] + ': ' + str(error)) from error
        status = ('mixed' if audit.nested else 'source_only') if audit.fallbacks else ('structured' if audit.nested else 'no_visible_nesting')
        documents.append({'path': row['path'], 'source_sha256': row['source_sha256'],
                          'nested_item_candidates': row['syntax']['list_nested_item'],
                          'root_lists': audit.roots, 'nested_lists': audit.nested,
                          'maximum_depth': audit.maximum_depth, 'ordered_items_with_values': audit.ordered_items,
                          'source_blocks': len(audit.fallbacks), 'classification': status,
                          'source_indicators': dict(Counter(reason for text in audit.fallbacks for reason in indicators(text))),
                          'fallback_sha256': [hashlib.sha256(text.encode()).hexdigest() for text in audit.fallbacks]})
    report = {'source_revision': metrics['corpus_revision'], 'renderer_revision': metrics['renderer_revision'],
              'document_count': len(documents), 'classifications': dict(Counter(r['classification'] for r in documents)),
              'nested_item_candidates': sum(r['nested_item_candidates'] for r in documents),
              'nested_lists': sum(r['nested_lists'] for r in documents),
              'source_blocks': sum(r['source_blocks'] for r in documents),
              'maximum_rendered_depth': max((r['maximum_depth'] for r in documents), default=0),
              'method': 'Select all source-metric nested-item documents at the frozen Git revision; verify source hashes, render with PublicMarkdownRenderer and inspect actual HTML list ancestry/balance/depth and explicit ordered item values. Verify every list-source region against canonical comment-filtered source (metadata excluded). Source indicators are nonexclusive clues, not complete parser diagnoses. No document bodies or HTML retained.',
              'limitations': 'Structural coverage of all measured documents; representative visual/ownership fixtures only. Candidate occurrences are not semantic successes or failures. A mixed/source-only document can still contain other readable structured lists. no_visible_nesting candidates may occur in hidden comments or other non-list contexts. Comment policy itself is verified by independent safety fixtures.',
              'documents': documents}
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps({k: v for k, v in report.items() if k not in ('documents', 'method', 'limitations')}, indent=2))


if __name__ == '__main__':
    main()
