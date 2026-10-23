> **Feature plan:** [Step 1](./markdown_list_structure_step1_solution_assessment.md) · [Step 2](./markdown_list_structure_step2_feature_description.md) · [Step 3](./markdown_list_structure_step3_development_plan.md) · [Step 4](./markdown_list_structure_step4_implementation_summary.md)

Debug mode: enabled, inherited from approved Step 3.

## Execution state

- Steps 1–3 approved; branch `feature/markdown-list-structure`; baseline `b9d1cc874`, planning commit `17fcec043`. Six sequential stages, no merged-branch reuse or publication/deployment/disposal authorization.
- Study entry before implementation: `railspike-effort` / `v3/markdown_list_structure`, v1/R1/P2, Codex single exposed rater; **0.90 total reaffirmed**, prior scores/missing intake retained. Existing exact R1 verification retained. No measured labor, independent rating or accepted outcome.
- No external dependency, schema/navigation or wider-column changes. Preserve todo.txt and recovery storage.

## Stage 1 - Paragraph ownership and numbering

- Changes: canonical renderer collects adjacent flat items and content-column continuations, retains wrapped/loose paragraphs inside items, starts sibling lists on marker-kind changes and preserves every ordered source number with explicit values. Existing nested/unsupported blocks temporarily retain prior behavior pending Stages 2–3. Registered three focused list tests and updated procedure assertions to semantic numbers instead of six detached lists. Continuing-study planning records included here, outside the initial planning commit.
- Verification — passed: renderer lint, **29/29 list/reader/table tests**, retraction guide's single six-step list and all four standalone tables, repeated/gapped/restarted numbers, blank paragraphs, marker changes and following standalone content; whitespace check. One new assertion initially assumed no separator newline before following prose; separated semantic list and paragraph checks, then reran all 29.
- Notes: planning `17fcec043`. Stage 2 nesting, Stage 3 full recovery/safety, browser and portable publication remain pending. No full-suite/UI/deployment claim.

## Stage 2 - Nested list relationships

- Changes: bounded recursive list ownership through eight levels, mixed ordered/unordered children, sibling marker changes, established-column dedents and paragraph return to parents. Same canonical inline path, render signature and ordered values. Updated prior nested-list assertions to require structural nesting; unsupported cases remain for Stage 3 recovery.
- Verification — passed: **32/32** list/reader/table cases; mixed grandchildren, parent paragraph after children, wide-number content columns, eight levels and real account-key plan risk bullets. Initial mixed fixture exposed parent text aligned with a child's marker column; returning to the owning ancestor content column fixed it, then all cases passed. PHP lint/whitespace checks passed.
- Notes: Stage 1 commit `e27a0356d`. Ambiguous/unsupported constructs still need whole-block recovery and adversarial safety before browser/delivery work.

## Stage 3 - Unsupported-block recovery and safety

- Changes: replaced legacy flattening with complete escaped list-source recovery for tabs, unknown/dedented ownership, over-limit nesting, oversized numeric markers, indented code and embedded tables/quotes/fences. Fenced examples remain in their source block through closing delimiters, including unindented example lines; subsequent standalone headings/tables remain outside. Comment preprocessing preserves fenced example bytes while suppressing prose comments before either semantic or fallback rendering. No alternative inline/link parser.
- Verification — passed: **37/37** focused list/reader/table tests; whole-block source equality, nine-level fallback, ambiguous columns, tabs and marker widths, nested/optional-pipe tables, quotes, indented/fenced code and following standalone tables, code-comment bytes versus hidden prose comments, literal tasks, escaping, unsafe/traversal/symlink links, image alternatives, real procedure and nested-plan fixtures. PHP lint/whitespace checks passed.
- Notes: Stage 2 commit `dd24c3ad1`. Fallback is intentionally broad for unsupported embedded blocks. New fallbacks are keyboard-focusable source regions; actual phone/focus/deep-nesting presentation remains Stage 4. No dependency, source-document rewrite or broader syntax extension.

## Stage 4 - Accessible ordinary reader

- Changes: semantic six-step/paragraph browser assertions replace six detached lists. Compact list indentation and paragraph spacing reuse reader styles; five-to-eight-level lists get a keyboard-focusable local scroll region with readable minimum width. New isolated list fixture covers literal numbers, parent paragraphs, eight-level links, nine-level/source fallback, fenced example bytes and standalone tables. No wider-column/layout expansion.
- Verification — passed: **37/37 PHP cases** and **8 dynamic Chromium scenarios**, desktop/390px × default/Word97/Chicago/Midnight, JavaScript disabled. Normal Tools→Docs→guide entry, actual source numbers/paragraphs, parent/child ancestry, safe list/table link destinations, Tab access to deep-list and source regions, ArrowRight scrolling, visible focus, readable deep text, code bytes and no page-wide overflow. Node/PHP syntax and whitespace checks passed.
- Visual review — passed: all four phone list fixtures plus desktop Word97; deep text retains usable width, markers are visible at initial scroll, source recovery/adjacent tables stay readable. Screenshots reset local scroll only after real keyboard checks. Durable measurements: `dynamic_browser_evidence.json`; fixture `/tmp/markdown-lists-dynamic-teun5itn`.
- Notes: Stage 3 commit `3bfe93666`. Local Chromium only; copied/static release and recovery remain Stage 5. Accessibility labels add no explanatory visible copy; navigation/icons unchanged.
