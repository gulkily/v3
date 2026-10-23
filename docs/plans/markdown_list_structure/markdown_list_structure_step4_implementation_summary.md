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

## Stage 5 - Portable publication and recovery

- Changes: copied-package acceptance includes supported lists, source fallbacks, literal numbers and deep-list region parity; static-reader assertions check the actual single six-step procedure/paragraphs. Existing table/download/asset contracts remain covered.
- Verification — passed: **2/2 full static inventory/on-demand tests**. Copied complete dependency-free application on PHP 8.1.2, actual full/shared CLI publication, dynamic/generated list/table/fallback parity and destinations, stale/deleted-reader refresh; injected renderer failure returns 503 without exposing internal failure detail, keeps the published list/fallback release usable, and restoration/rebuild succeeds. No source-doc snapshot abort.
- Verification — passed: **8 generated reader + 8 static discovery browser scenarios**, desktop/390px × default/Word97/Chicago/Midnight. Generated readers exercise semantic parents/numbers, deep/source Tab/ArrowRight/focus, safe list/table link navigation, code bytes, alignment and no page overflow. Inspected all four generated phone list screenshots. Evidence: `release_recovery_evidence.json`, `static_browser_evidence.json`, `static_discovery_evidence.json`; fixture `/tmp/markdown-release-80msiqso`. Syntax/whitespace checks passed.
- Notes: Stage 4 commit `f9b4528d0`. Complete static inventory test takes several minutes; focused/source correctness is not inferred from duration. No production activation, full-suite or physical-device claim. Corpus acceptance/closeout remains Stage 6.

## Stage 6 - Corpus acceptance and closeout

- Changes: frozen/refreshed compact reports, a reproducible dependency-free maintainer list audit, all 453 measured nested-list document classifications and [human-readable acceptance](./list_render_acceptance.md). Updated runbook with content-column ownership, literal numbers, eight-level support, whole-block recovery and explicit padding/numeric guards. Wider Docs column and broader syntax extensions remain deferred.
- Verification — passed: all **1,405 frozen** source hashes match and render without errors; **1,411 refreshed tracked documents** at `4876f2337` render without errors. Frozen standalone table count remains 118. Audit covers **453 documents / 9,716 nested-item candidates**: 447 structured, five mixed, one source-only; 3,934 actual nested list containers and nine source regions. Every inspected list tree has valid parent/item balance, explicit ordered values and depth within the eight-level bound; source regions match canonical safety-filtered source. Observed corpus nesting reaches three levels; dedicated reader fixtures prove eight-level/over-limit behavior. Candidate counts are not semantic successes; visual review remains representative.
- Verification — passed: final **46/46** catalog/reader/list/table cases plus Stage 5 **2/2 static** cases; **48 distinct focused PHP cases**. Hand-counted metrics fixture, corpus audit, PHP/Node/Python syntax, artifact links, whitespace and dependency-delta checks passed. Dynamic/static/discovery evidence covers 24 distinct theme/viewport scenarios. Inspected phone fixtures across all themes and desktop samples, not every document or physical device.
- Notes: Stage 5 commit `4876f2337`; application renderer/CSS did not change afterward, so complete copied-package/recovery/browser evidence remains applicable. No full-suite, live deployment, merge/push, schema/navigation change, new dependency, todo.txt edit or recovery disposal. Stage 6 commit follows this summary.

## Study closeout and handoff

- Stable `v3/markdown_list_structure`, scope v1 clarified/R1/P2, Codex single exposed rater; baseline `b9d1cc874`. Planning `17fcec043`; Stages 1–5 `e27a0356d`, `dd24c3ad1`, `3bfe93666`, `f9b4528d0`, `4876f2337`; Stage 6 follows. Six planned/six completed stages, no new feature request or split. Corrections: newline-sensitive assertion replaced with semantic checks; ancestor paragraph return fixed from mixed fixture; browser checks strengthened for source/deep-region Tab/arrow access and initial-scroll screenshots. No remaining implementation inside approved supported/fallback boundaries.
- Projected **0.90 total preserved/reaffirmed**; separately assessed retrospective scope **0.95** for ownership/nesting, privacy-safe whole-block fallback, phone/keyboard containment, corpus classification and real publication/recovery evidence. All three frozen R1 hashes reverified, same exposed rater; no measured labor, independent rating or accepted-count increment. Maintainer comfortable-fit judgment and acceptance pending; parent history-removal retention acceptance remains separate/incomplete.
- Dependency delta **zero** external additions/upgrades/copied code; manifests/locks and third-party tree unchanged. Technically complete local handoff, with full suite, physical devices and deployment unverified. Recovery bundles and the recorded wider Docs follow-up remain preserved.

## Merge authorization — 2026-10-23T15:18:18.733723+00:00

- User performed UAT and said “Great, please merge, and what's next.” Accepted delivered list slice for merge/publication; preserve all six stage commits and the other work now on origin/main. Study comfortable-fit/sizing judgment remains unknown; functional acceptance does not invent that judgment.
- Next recommendation: read-only task-list presentation using existing literal checked/unchecked markers (626/542 candidates in the latest measured report), preserving source and no task-editing controls. This is a recommendation, not an implementation or new-cycle approval. Wider Docs column remains separately recorded and deferred.
- Integrate updated origin/main before publication and verify canonical renderer/CSS preservation plus focused Docs/link checks. No dependency, layout expansion, deployment or historical-storage disposal.

- Merge verification — passed: integrated origin/main `f82061406`; preserved both ledger histories in the sole content conflict and automatic README/test-runner merges. **47/47** focused PHP cases include Docs/catalog/list/table plus checkout real-Git acceptance; metrics fixture and whitespace checks passed. Canonical renderer/Docs CSS are byte-identical to the reviewed list branch, and newer origin history contains no target Markdown vendor path. Prior package/browser/recovery evidence remains scoped to the unchanged renderer; no new full-suite, production or live-deployment claim.
