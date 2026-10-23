#!/usr/bin/env python3
"""Audit rendered read-only tasks against a frozen source-candidate inventory; retain no HTML."""
import argparse
from collections import Counter
import hashlib
import json
from pathlib import Path
import re
import subprocess

from markdown_list_corpus_audit import ListAudit, ROOT, indicators
from markdown_metrics import scan


class TaskAudit(ListAudit):
    def __init__(self):
        super().__init__()
        self.task = None
        self.controls = []

    def handle_starttag(self, tag, attrs):
        super().handle_starttag(tag, attrs)
        attrs = dict(attrs)
        if tag == 'label' and 'docs-task-label' in attrs.get('class', '').split():
            assert self.task is None, 'Nested task label'
            self.task = {'text': '', 'controls': []}
        elif tag == 'input':
            assert self.task is not None, 'Control outside its task label'
            assert attrs.get('type') == 'checkbox' and 'disabled' in attrs, 'Task is not a disabled checkbox'
            assert not any(k in attrs for k in ('id', 'name', 'value', 'onclick', 'onchange')), 'Task acquired identity/submission/action attributes'
            self.task['controls'].append('checked' in attrs)
        elif self.task is not None:
            assert tag not in ('ul', 'ol', 'li', 'p'), 'Task label captured descendants or another paragraph'

    def handle_endtag(self, tag):
        super().handle_endtag(tag)
        if tag == 'label' and self.task is not None:
            assert len(self.task['controls']) == 1, 'Task must have exactly one associated checkbox'
            name = re.sub(r'\s+', ' ', self.task['text']).strip()
            assert name, 'Task lacks visible label text'
            self.controls.append({'checked': self.task['controls'][0], 'label_text_sha256': hashlib.sha256(name.encode()).hexdigest()})
            self.task = None
        if tag == 'p':
            assert self.task is None, 'Task label not closed within the first paragraph'

    def handle_data(self, data):
        super().handle_data(data)
        if self.task is not None:
            self.task['text'] += data


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--baseline', required=True, type=Path, help='Frozen markdown_task_lists_metrics.json inventory')
    ap.add_argument('--output', required=True, type=Path)
    args = ap.parse_args()
    baseline = json.loads(args.baseline.read_text())
    inputs = []
    for row in baseline['documents']:
        text = subprocess.check_output(['git', 'show', baseline['revision'] + ':' + row['path']], cwd=ROOT).decode('utf-8', errors='replace')
        assert hashlib.sha256(text.encode()).hexdigest() == row['source_sha256'], row['path']
        inputs.append({'path': row['path'], 'text': text})
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
    outputs = json.loads(subprocess.check_output(['php', '-r', php], input=json.dumps(inputs).encode(), cwd=ROOT))
    documents = []
    for row, output in zip(baseline['documents'], outputs):
        audit = TaskAudit()
        try:
            audit.feed(output['html'])
            assert not audit.stack and audit.task is None, 'Unclosed list/label structure'
            for source in audit.fallbacks:
                assert source in output['filtered_source'], 'Source recovery differs from safety-filtered Markdown'
        except AssertionError as error:
            raise AssertionError(row['path'] + ': ' + str(error)) from error
        checked = sum(c['checked'] for c in audit.controls)
        unchecked = len(audit.controls) - checked
        retained = Counter()
        for text in audit.fallbacks:
            counts, _ = scan(text)
            retained.update({'checked': counts['task_checked'], 'unchecked': counts['task_unchecked']})
        delta = row['checked'] + row['unchecked'] - len(audit.controls) - sum(retained.values())
        status = 'displayed' if audit.controls and not sum(retained.values()) and delta == 0 else ('mixed' if audit.controls else 'literal_or_source')
        documents.append({'path': row['path'], 'source_sha256': row['source_sha256'],
                          'candidate_checked': row['checked'], 'candidate_unchecked': row['unchecked'],
                          'displayed_checked': checked, 'displayed_unchecked': unchecked,
                          'source_checked_candidates': retained['checked'], 'source_unchecked_candidates': retained['unchecked'],
                          'other_candidate_delta': delta, 'classification': status,
                          'source_blocks': len(audit.fallbacks),
                          'source_indicators': dict(Counter(reason for text in audit.fallbacks for reason in indicators(text))),
                          'controls': audit.controls})
    report = {'source_revision': baseline['revision'],
              'renderer_revision': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
              'document_count': len(documents), 'classifications': dict(Counter(r['classification'] for r in documents)),
              'candidate_checked': baseline['checked'], 'candidate_unchecked': baseline['unchecked'],
              'displayed_checked': sum(r['displayed_checked'] for r in documents),
              'displayed_unchecked': sum(r['displayed_unchecked'] for r in documents),
              'source_checked_candidates': sum(r['source_checked_candidates'] for r in documents),
              'source_unchecked_candidates': sum(r['source_unchecked_candidates'] for r in documents),
              'other_candidate_delta': sum(r['other_candidate_delta'] for r in documents),
              'method': 'Render every frozen task-candidate document after source-hash verification. Inspect actual checkbox type/disabled/no-submission attributes, exactly one nonempty label per control, absence of descendant/paragraph capture, valid bounded list trees and unchanged safety-filtered source regions. Candidate counts are independent source heuristics. Raw source, HTML and label text are not retained; controls carry state and text hashes.',
              'limitations': 'Labels here are parsed text, not a full accessibility engine. Browser name/state/immutability and representative visual checks are separate; physical assistive technology and every document visually reviewed are not claimed. Delta records candidates outside displayed controls/source candidates, not an assumed syntax-conformance failure.',
              'documents': documents}
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps({k: v for k, v in report.items() if k not in ('documents', 'method', 'limitations')}, indent=2))


if __name__ == '__main__':
    main()
