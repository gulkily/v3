# Restored-render comparison

The exact frozen corpus (1,312 files at the planning revision, per-file hashes verified) renders with **zero errors** after removal. Rendering uses the changed worktree recorded in `render_restored.json`; stage commit is recorded in the implementation summary. Every document's HTML hash changes; this is expected from changed serialization and formatting, not evidence of 1,312 independent semantic failures.

The reference AST and source counts remain in [render metrics](render_metrics.md); the [rare-syntax table](rare_syntax_decisions.md) leaves extension choices open. No new syntax extension has been implemented. Existing Docs integration is retained: current render signature/error boundary, catalog URL checks, heading IDs, document-scoped duplicate IDs, metadata safety, code captions/keyboard regions and no image fetching. Pipe tables use scrollable escaped-source fallback; source step numbers are preserved across list continuations to avoid presenting every procedural step as step 1.

Known differences: nested lists flatten; loose lists/continuations split; tasks, reference links, autolinks, underscore emphasis, strikethrough, Setext headings and unsupported fences remain readable source rather than equivalent formatted output. Table cell links remain source within the fallback. Critical inline document links and the retraction-to-CLI fragment remain clickable. General Markdown compatibility is deferred, not claimed by zero render errors.

| HTML opening tag | Reference total | Restored total |
| --- | ---: | ---: |
| a | 3566 | 3399 |
| blockquote | 657 | 689 |
| br | 2 | 0 |
| code | 31476 | 30969 |
| del | 1 | 0 |
| details | 1 | 1 |
| div | 93 | 0 |
| em | 314 | 267 |
| figcaption | 284 | 284 |
| figure | 284 | 284 |
| h1 | 1003 | 1003 |
| h2 | 8236 | 8235 |
| h3 | 1506 | 1506 |
| h4 | 4 | 4 |
| hr | 17 | 18 |
| input | 1012 | 0 |
| li | 41482 | 41483 |
| ol | 482 | 764 |
| p | 8583 | 10597 |
| pre | 325 | 419 |
| strong | 4926 | 4867 |
| summary | 1 | 1 |
| table | 93 | 0 |
| tbody | 92 | 0 |
| td | 2246 | 0 |
| th | 309 | 0 |
| thead | 93 | 0 |
| tr | 759 | 0 |
| ul | 12059 | 10013 |

Tag totals quantify structural differences only. Browser and release acceptance, including critical guide review, are recorded separately in the implementation summary.
