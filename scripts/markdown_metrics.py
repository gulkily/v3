#!/usr/bin/env python3
"""Frozen-corpus Markdown syntax candidates plus actual renderer metrics; archived reference AST counts.

No dependencies. Candidate counts are NOT a Markdown conformance parser: source and
AST totals are reported independently, with ambiguity explicit. Git freezes source
bytes; source locations and hashes survive removal without retaining vendor code.
"""
import argparse
from collections import Counter, defaultdict
import hashlib
import json
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PATTERNS = {
    'heading_atx': r'^ {0,3}#{1,6}\s+',
    'heading_setext_candidate': r'^ {0,3}(?:=+|-+)\s*$',
    'list_unordered_item': r'^\s*[-+*]\s+',
    'list_ordered_item': r'^\s*\d+[.)]\s+',
    'list_nested_item': r'^(?: {2,}|\t+)(?:[-+*]|\d+[.)])\s+',
    'list_continuation_candidate': r'^(?: {2,}|\t+)\S',
    'list_loose_candidate': r'^\s*$',
    'task_checked': r'^\s*[-+*]\s+\[[xX]\]\s',
    'task_unchecked': r'^\s*[-+*]\s+\[ \]\s',
    'blockquote_line': r'^ {0,3}>',
    'blockquote_nested_line': r'^ {0,3}>\s*>',
    'table_separator': r'^\s*\|?\s*:?-{3,}:?\s*\|(?:\s*:?-{3,}:?\s*\|?)+\s*$',
    'reference_definition': r'^ {0,3}\[[^\]]+\]:\s*\S+',
    'link_inline_candidate': r'(?<!!)\[[^\]\n]+\]\([^\n]+?\)',
    'link_reference_explicit_candidate': r'(?<!!)\[[^\]\n]+\]\[[^\]\n]*\]',
    'link_reference_shortcut_candidate': r'(?<!!)\[[^\]\n]+\](?![(:\[])',
    'link_fragment_candidate': r'\]\([^\s)]*#[^\s)]*\)',
    'image_markdown_candidate': r'!\[[^\]\n]*\]\([^\n]+?\)',
    'autolink_url': r'<https?://[^<>\s]+>',
    'autolink_email_candidate': r'<[^<>\s@]+@[^<>\s@]+>',
    'autolink_bare_candidate': r'(?<!["\w/<])(https?://|www\.)[^\s<>]+',
    'strong_asterisk_candidate': r'(?<!\\)\*\*[^*\n]+\*\*',
    'strong_underscore_candidate': r'(?<!\\)__[^_\n]+__',
    'emphasis_asterisk_candidate': r'(?<![\\*])\*(?!\*)([^*\n]+)\*(?!\*)',
    'emphasis_underscore_candidate': r'(?<![\\\w_])_(?!_)([^_\n]+)_(?![\w_])',
    'strikethrough_candidate': r'(?<!\\)~~[^~\n]+~~',
    'hardbreak_spaces': r' {2,}$',
    'hardbreak_backslash': r'(?<!\\)\\$',
    'html_tag_candidate': r'</?[A-Za-z][^>]*>',
    'html_comment_start': r'<!--',
    'footnote_candidate': r'\[\^[^\]]+\]',
    'gfm_alert_candidate': r'^>\s*\[!(?:NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]',
    'math_candidate': r'(?<![\\$])\$(?!\$)[^$\n]+\$',
    'thematic_break_candidate': r'^ {0,3}(?:(?:\*\s*){3,}|(?:-\s*){3,}|(?:_\s*){3,})$',
}
EXTRA = ['inline_code', 'fence_backtick', 'fence_tilde', 'fence_mermaid', 'fence_language',
         'indented_code_candidate', 'front_matter', 'table_row_candidate', 'table_cell_candidate',
         'unclosed_fence', 'code_example_line']

def scan(text):
    counts = Counter({key: 0 for key in list(PATTERNS) + EXTRA})
    locations = defaultdict(list)
    fence = None
    metadata = False
    lines = text.splitlines()
    def add(key, line, count=1):
        counts[key] += count
        if len(locations[key]) < 4:
            locations[key].append(line)
    for i, line in enumerate(lines, 1):
        if i == 1 and line.lstrip('\ufeff') == '---':
            metadata = True
            add('front_matter', i)
            continue
        if metadata:
            if line.strip() in ('---', '...'):
                metadata = False
            continue
        m = re.match(r'^\s*(?:>\s*)?(`{3,}|~{3,})(.*)$', line)
        if fence:
            if m and m[1][0] == fence[0] and len(m[1]) >= len(fence) and not m[2].strip():
                fence = None
            else:
                add('code_example_line', i)
            continue
        if m:
            fence = m[1]
            add('fence_backtick' if fence[0] == '`' else 'fence_tilde', i)
            if m[2].strip(): add('fence_language', i)
            if m[2].strip().split(' ')[0] == 'mermaid': add('fence_mermaid', i)
            continue
        if re.match(r'^(?: {4}|\t)\S', line): add('indented_code_candidate', i)
        spans = list(re.finditer(r'(`+)(.+?)\1(?!`)', line))
        add('inline_code', i, len(spans)) if spans else None
        masked = re.sub(r'(`+)(.+?)\1(?!`)', lambda m: ' ' * len(m[0]), line)
        for key, pattern in PATTERNS.items():
            if key == 'list_loose_candidate':
                matches = [1] if (not line.strip() and i < len(lines) and re.match(r'^\s*(?:[-+*]|\d+[.)])\s+', lines[i])) else []
            else:
                matches = list(re.finditer(pattern, masked))
            if matches: add(key, i, len(matches))
        if masked.lstrip().startswith('|') and masked.count('|') >= 2 and not re.search(PATTERNS['table_separator'], masked):
            add('table_row_candidate', i)
            add('table_cell_candidate', i, max(0, len(masked.strip().strip('|').split('|'))))
    if fence: add('unclosed_fence', len(lines))
    return dict(counts), dict(locations)

def php(args, data=None):
    return json.loads(subprocess.check_output(['php', str(ROOT / 'scripts/markdown_render_probe.php'), *args],
        input=None if data is None else json.dumps(data).encode(), cwd=ROOT))

def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--baseline', type=Path, help='Reuse frozen source corpus/revision from a previous report')
    ap.add_argument('--reference', action='store_true', help='Historical option; reference results were archived before removal')
    ap.add_argument('--output', type=Path, required=True)
    args = ap.parse_args()
    if args.reference:
        ap.error('Reference runtime removed; use the archived render_baseline.json evidence. No dependency installation is supported.')
    before = json.loads(args.baseline.read_text()) if args.baseline else None
    revision = before['corpus_revision'] if before else subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
    if before:
        paths = [r['path'] for r in before['documents']]
    else:
        tracked = set(subprocess.check_output(['git', 'ls-tree', '-r', '--name-only', revision], cwd=ROOT, text=True).splitlines())
        paths = sorted(r['path'] for r in php(['--inventory']) if r['format'] in ['md','markdown'] and r['available'] and r['path'] in tracked)
    corpus = []
    for path in paths:
        text = subprocess.check_output(['git', 'show', revision + ':' + path], cwd=ROOT).decode('utf-8', errors='replace')
        corpus.append({'path': path, 'text': text})
    rendered = {r['path']: r for r in php(['--reference'] if args.reference else [], corpus)}
    docs = []
    totals = Counter()
    doccounts = Counter()
    nodes = Counter()
    for doc in corpus:
        counts, locations = scan(doc['text'])
        row = dict(rendered[doc['path']], source_sha256=hashlib.sha256(doc['text'].encode()).hexdigest(),
                   syntax={k:v for k,v in counts.items() if v}, locations=locations)
        totals.update(counts)
        doccounts.update(k for k,v in counts.items() if v)
        nodes.update(row['nodes'])
        if before:
            prior = next(r for r in before['documents'] if r['path'] == row['path'])
            assert row['source_sha256'] == prior['source_sha256'], doc['path']
            row['html_changed'] = row.get('html_sha256') != prior.get('html_sha256')
        docs.append(row)
    report = {'corpus_revision': revision, 'renderer_revision': subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip(),
        'renderer_worktree_changes': subprocess.check_output(['git','diff','--name-only'],cwd=ROOT,text=True).splitlines(),
        'reference_ast': args.reference, 'rare_max_documents': 5,
        'method': 'Source candidate occurrences, document counts and reference AST node totals are independent. Candidates may overlap; nested items are also items. Table counts are candidate rows/cells; reference definitions are not link uses. Bare URL candidates overlap inline/autolinks. List continuation/loose, indented code, math, Setext and inline variants require review. Code bodies are excluded from prose matches and counted as example lines. Inline source patterns are same-line candidates, not complete Markdown parsing. Unknown forms are not proven absent. Front matter is excluded from prose; reference AST includes it. No vendor source or rendered HTML is retained.',
        'syntax': {k: {'occurrences':totals[k], 'documents':doccounts[k], 'rare': doccounts[k]<=5} for k in sorted(totals)},
        'reference_node_totals': dict(sorted(nodes.items())), 'document_count': len(docs), 'render_errors':sum(r['error'] is not None for r in docs), 'documents':docs}
    args.output.parent.mkdir(parents=True,exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2)+'\n')
    print(json.dumps({k:report[k] for k in ['corpus_revision','document_count','render_errors','reference_node_totals']},indent=2))

if __name__ == '__main__': main()
