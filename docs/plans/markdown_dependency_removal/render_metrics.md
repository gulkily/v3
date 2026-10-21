# Markdown render measurement baseline

Corpus: 1,312 tracked, eligible Markdown documents at `c3f3653a199997a1e2fc7bbcac7da21d95ce3649`. Only tracked eligible files from the catalog are included; untracked notes and non-Markdown formats are excluded. No render errors. Source is frozen through Git, identified by path/hash; reports retain no source or rendered HTML. Rerun before dependency removal:

```sh
python3 scripts/markdown_metrics.py --reference --output /tmp/render-baseline.json
python3 -B tests/markdown_metrics_test.py
```

After restoration, use `--baseline docs/plans/markdown_dependency_removal/render_baseline.json` and omit `--reference`. This reuses the original source bytes, not newly edited documents. The probe's optional reference mode requires the pre-removal bundle and is not part of the application. Archived baseline evidence remains readable without it.

## Count interpretation and validation

The JSON reports **semantic parser node counts** independently of **source candidate occurrences**. They are deliberately not interchangeable or additive. For example tables, rows, cells, list containers/items and inline code are distinct semantic units; nested lists overlap total lists. Source patterns retain delimiter/marker variants but have ambiguity: multiline/nested inline syntax, bare URLs, indentation, Setext headings, loose lists, math and shortcut references need review. They are labeled candidates, not asserted fully parsed uses. Zero matches do not prove an arbitrary unsupported form absent. HTML counts include escaped nodes; parsing does not imply output executes. Front matter is excluded by source scan but included by the independent parser, explaining some differences. Code bodies are counted as example lines and excluded from prose source matching; inline code is masked first.

The mixed hand-counted fixture in `tests/markdown_metrics_test.py` validates repeated uses on a line, nested/item overlap, tables/cells, reference definitions versus uses, code exclusion, zero-use output, source locations, front matter and unclosed fences. This validates representative units, not full Markdown grammar. The JSON includes all observed parser node classes, all declared source categories including zero use, errors, and per-document locations for manual review. No diagram/math/footnote engines were installed or executed.

## Semantic reference counts

| Parser node type | Uses |
| --- | ---: |
| BlockQuote | 657 |
| Code | 31151 |
| Document | 1312 |
| Emphasis | 314 |
| FencedCode | 323 |
| Heading | 10750 |
| HtmlBlock | 15 |
| HtmlInline | 6 |
| IndentedCode | 1 |
| Link | 3972 |
| ListBlock | 12541 |
| ListItem | 41482 |
| Newline | 8766 |
| Paragraph | 49556 |
| Strikethrough | 1 |
| Strong | 4926 |
| Table | 93 |
| TableCell | 2555 |
| TableRow | 759 |
| TableSection | 185 |
| TaskListItemMarker | 1012 |
| Text | 106506 |
| ThematicBreak | 18 |

## Source syntax candidates

| Syntax/variant | Occurrences | Documents | Rare (≤5 docs) |
| --- | ---: | ---: | --- |
| autolink_bare_candidate | 251 | 47 | no |
| autolink_email_candidate | 0 | 0 | yes |
| autolink_url | 1 | 1 | yes |
| blockquote_line | 689 | 630 | no |
| blockquote_nested_line | 0 | 0 | yes |
| code_example_line | 1238 | 85 | no |
| emphasis_asterisk_candidate | 267 | 78 | no |
| emphasis_underscore_candidate | 18 | 14 | no |
| fence_backtick | 323 | 85 | no |
| fence_language | 284 | 82 | no |
| fence_mermaid | 6 | 2 | yes |
| fence_tilde | 0 | 0 | yes |
| footnote_candidate | 0 | 0 | yes |
| front_matter | 1 | 1 | yes |
| gfm_alert_candidate | 0 | 0 | yes |
| hardbreak_backslash | 0 | 0 | yes |
| hardbreak_spaces | 2155 | 219 | no |
| heading_atx | 10748 | 1311 | no |
| heading_setext_candidate | 1147 | 100 | no |
| html_comment_start | 10 | 2 | yes |
| html_tag_candidate | 27 | 13 | no |
| image_markdown_candidate | 0 | 0 | yes |
| indented_code_candidate | 944 | 69 | no |
| inline_code | 31105 | 1171 | no |
| link_fragment_candidate | 170 | 53 | no |
| link_inline_candidate | 3864 | 723 | no |
| link_reference_explicit_candidate | 0 | 0 | yes |
| link_reference_shortcut_candidate | 1126 | 57 | no |
| list_continuation_candidate | 15607 | 590 | no |
| list_loose_candidate | 5657 | 855 | no |
| list_nested_item | 9670 | 451 | no |
| list_ordered_item | 2030 | 386 | no |
| list_unordered_item | 39463 | 1287 | no |
| math_candidate | 10 | 8 | no |
| reference_definition | 26 | 1 | yes |
| strikethrough_candidate | 1 | 1 | yes |
| strong_asterisk_candidate | 4907 | 884 | no |
| strong_underscore_candidate | 1 | 1 | yes |
| table_cell_candidate | 2563 | 55 | no |
| table_row_candidate | 761 | 55 | no |
| table_separator | 93 | 55 | no |
| task_checked | 573 | 33 | no |
| task_unchecked | 419 | 30 | no |
| thematic_break_candidate | 18 | 5 | yes |
| unclosed_fence | 0 | 0 | yes |
