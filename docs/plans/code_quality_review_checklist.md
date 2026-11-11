# v3 Code Quality Review — Checklist

Status (2026-11-10): first tooling pass and all low-risk follow-ups done. Results and decisions: [code_quality_review_findings_v1.md](code_quality_review_findings_v1.md).

Merged so far: #139 (OpenPGP docs), #141 (this checklist + findings), #143 (RuntimeException import, escaped notices), #146 (dead code removed), #147 (high-traffic refresh bug), #148 (keep-notes, gpg status warning), #150 (git helpers take arrays), #151 (shared script helpers).

`[x]` done · `[~]` partly done or waiting on a decision (see the note) · `[ ]` not started

Scope: a dedicated pass over `gulkily/v3` (snapshot `cdec870`, 2026-11-10) focused on **dead code** first and **obvious quality issues** second. Each item is a check to run; findings get logged in a results file before anything is deleted.

Codebase at a glance: ~426 PHP files in `src/ForumRewrite` (32 subsystems), 360 PHP test files, 66 scripts, ~85 JS assets, 42 CSS files, 516 plan docs.

---

## Ground rules

Standing rules for every PR in this review; followed so far, so they stay unticked.

- [ ] One branch per PR; no behavior changes mixed into deletions.
- [ ] Before deleting anything, confirm it isn't reached **dynamically**: string-built class/file names, `theme-{$name}.css`, route tables, feature-flag registries, `template('…')` calls, `sendPrompt`/JS `data-*` hooks, the `./v3` command dispatcher.
- [ ] Remember the deployed sites (zenmemes.com, qdb.us, mitrapclub, chouse): something unused in core may be used only by one site profile.
- [ ] Each removal batch: smoke set + tests for touched areas (`./v3 test --smoke <Tests…>`), not the full suite.
- [ ] Log every finding as *remove / keep (with reason) / defer*.
- [ ] **Search everywhere before calling something unused.** The first pass missed live code because it skipped these places:
  - `docs/plans/`, including acceptance docs and runbooks. Some tools are documented only there (the Markdown audit scripts were).
  - `scripts/README.md`, which says what runs each script. `./v3` calls scripts by `${ROOT}/scripts/...` path, so a bare file-name search misses them (the "one-time" migration scripts were live commands).
  - `templates/`, for classes and values used only from a template.
  - `public/assets/*.PROVENANCE` and code comments, for things kept on purpose (the OpenPGP source map).
  - `git log -S<name>`, to see whether a caller was removed on purpose and when.
- [ ] **Check that new tests run.** `tests/run.php` lists test files explicitly; `TestFileRegistrationTest` now fails when a `*Test.php` file is missing from it.
- [ ] Start from the earlier audit, `docs/plans/codebase_cleanup_audit_findings_v1.md`. It already resolved some false leads, including the OpenPGP pair.
- [ ] **Intentional-duplicate rule.** If something looks unused or duplicated but is kept on purpose, the reason has to be visible *at the file*, not just in a plan doc:
  - Vendored third-party files get a `<name>.PROVENANCE` sidecar (upstream, version, integrity, SHA-256, modifications, why it's here, how to update) and a `<name>.LICENSE`. Examples: `qrcode-generator.PROVENANCE`, `openpgp.PROVENANCE` (PR #139).
  - First-party code that is only reached dynamically, or that is a deliberate twin, gets a short header comment saying what loads it and why.
  - When a finding turns out to be "keep", add the missing note in the same pass, so the next review doesn't trip over it again.

---

## Part 1 — Dead code

### 1.1 PHP classes and methods
- [x] List classes in `src/` with no reference outside their own file and tests. None found by the PHPStan dead-code pass. The naive scan's one candidate was a false positive:
  - `Activity/AccessHistoryPresenter` — **false positive**, used from templates (`access_history_event.php`, `paned_activity_item_row.php`). Scans must include `templates/`.
- [~] Classes referenced **only by tests** (code kept alive by its own tests). Members listed in findings §3; test hooks marked `@internal` (#148); the rest wait on a decision.
- [x] Public methods never called outside their class (needs a real tool — see "Tooling" below). Removed in #146.
- [x] Private methods / private properties never used. Methods removed in #146; unread constructor properties listed for review in findings §2.
- [x] Constants and enum cases never read. Two `SIZE` constants removed in #146; no dead enum cases.
- [~] Unused `use` imports. Only in files touched by #146; those are cleaned up. Not swept repo-wide.
- [ ] Unreachable branches: code after `return`/`throw`, conditions on values that can no longer occur (e.g. old schema versions, removed record types).

### 1.2 Feature flags
- [x] Flags that are on everywhere are **not** dead code; keep them. A flag lets an operator switch off a buggy feature without a deploy, and lets a future instance leave out a feature it doesn't want (Ilya, 2026-11-10). A flag is dead only if no code reads it.
- [x] Flags referenced in code but not in the registry, or registered but never checked. All 36 flags are read; none orphaned.

### 1.3 Routes, templates, assets
- [ ] Routes in `Application` / `Http/RouteServices` / `Host/FrontController` with no link anywhere in templates, JS, or docs (candidates, then confirm against access logs if available).
- [x] Templates in `templates/` never rendered. `pages/private_messages.php` removed in #146.
- [x] JS files in `public/assets/` never loaded by a template or other JS. None found apart from theme scripts, which load through the registry.
- [x] CSS: theme files all came up "unreferenced" by name — confirm they're loaded via `ThemeRegistry` string building, then check each theme is still listed in the registry. Confirmed; `ThemeRegistryTest` now fails on any `theme-*` file outside the registry (#148).
- [x] **Two OpenPGP bundles**: both are intentional. v6 is the default; v5 is the fallback for plain HTTP, where v6 can't run without WebCrypto. This is now documented in `openpgp.PROVENANCE` (PR #139). The 1.8 MB source map also stays: the v6 bundle points to it, browsers fetch it only with DevTools open, and it was added on purpose to stop a DevTools 404 (`firefox_console_warnings_causes_v1.md`).
- [x] Other vendored files in `public/assets/` with no `.PROVENANCE` / `.LICENSE` sidecar. None: OpenPGP and qrcode-generator are the only vendored files, and both have them.
- [ ] Orphaned CSS selectors inside large stylesheets (lower priority).

### 1.4 Scripts (`scripts/`, 66 files)
- [x] Scripts not mentioned by `./v3`, docs, or other code: `markdown_list_corpus_audit.py`, `markdown_task_corpus_audit.py`. **Keep**: Markdown renderer audit tools, still needed while the renderer is being extended; documented only in their plan acceptance docs, which the scan skipped. Docstrings now link there (#156).
- [x] Triplicated implementations: `publish_text.{php,pl,py}` and `stress_probe.{php,pl,py}`. Intentional and will grow; documented in `docs/reference/multi_language_scripts.md`, with a pointer in each file's header.
- [x] Suspected one-shot scripts are live `./v3` commands, so they stay: `inject_approval.php` (`./v3 approval seed|approve`), `backfill_unicode_risk.php` (`./v3 unicode-risk-backfill`, rerun when the Unicode-risk schema version changes), `bake_legacy_timestamps.php` (`./v3 bake-timestamps`). The scan missed them because `v3` calls them by `${ROOT}/scripts/...` path. The `qdb_archive_import_*` scripts are documented in the QDB import runbook.
- [ ] Probes/benchmarks still worth keeping: `benchmark_profile_registration.php`, `markdown_render_probe.php`, `qdb_capacity_probe.js`.

### 1.5 Repo clutter
- [x] Root-level notes: `claude_request.txt`, `second_brain.txt` and `todo-scraped.txt` moved into `docs/plans/` beside the work they belong to (#156). `todo.txt` stays at the root as the quick inbox.
- [ ] `docs/plans/` (516 files): fine to keep as history, but check for plans marked in-progress whose work was abandoned. Known stale descriptions after #146: `qdb_classic_urls_checklist.md` (QDB `/add` now goes through `QdbExperience`), `production_stall_busy_page_findings_v1.md` (the on-miss static build was removed on Sep 21), `site_feature_flags_slices_v1.md` (its compatibility wrappers were a migration step; nothing calls them now).
- [ ] `queries/`, `prompts/`, `resources/`: confirm each file is still read by code.

### 1.6 Database / read model
- [ ] SQLite tables, columns, indexes, and triggers created in the read-model schema but never queried (cross-check against `scripts/build_sqlite_query_catalog.php` output).
- [ ] Migration/upgrade code for schema versions far older than any live install.
- [ ] Record types or headers the parser still accepts but nothing writes anymore.

---

## Part 2 — Obvious quality issues

### 2.1 Oversized files (god classes)
- [ ] `Write/LocalWriteService.php` — **5,073 lines**. Map its responsibilities; identify natural splits (per record type? validation vs. git commit vs. read-model update?).
- [ ] `Application.php` — 2,951 lines. Is it mostly route wiring/DI that could move into `RouteServices`?
- [ ] `ReadModel/IncrementalReadModelUpdater.php` (2,238) vs `ReadModelBuilder.php` (1,700): look for logic duplicated between the incremental and full-rebuild paths — a real correctness risk, since they must stay equivalent.
- [ ] `Activity/ActivityService.php` (1,261), `View/TemplateRenderer.php` (982), `TaskQueue/SqliteTaskQueueStore.php` (969).
- [~] Methods over ~100 lines anywhere in `src/`. Measured: 32, led by the 1,083-line `Application::handle` (findings §6). Splitting is for separate Railspike cycles.

### 2.2 Duplication
- [~] Copy-pasted logic between subsystems (signature checks, path building, timestamp formatting, HTML escaping, JSON response helpers). jscpd: 0.88% overall. Script helpers shared in #151; card partials and LLM providers left for their own cycles; toggle JS kept on purpose (findings §5).
- [ ] Server/browser rule pairs that must agree (e.g. `UnicodeTextPolicy` ↔ compose-time JS validator): are they generated from one source or hand-synced?
- [ ] Near-duplicate JS: `outbox_*` (6 files), `private_message_*` (11 files), `key_backup*` (3) — check for repeated helpers that belong in one module.
- [ ] The `Sqlite*Store` classes: shared connection/busy-timeout/transaction boilerplate.

### 2.3 Error handling
- [x] Empty `catch` blocks and catches that swallow exceptions without logging. All six now say why the error is ignored (#148).
- [x] `@` error suppression. 155 uses checked; return values are checked. No action.
- [~] Functions that return `false`/`null`/`''` for errors instead of throwing, with callers not checking. Write-side done: the unchecked purge journal writes are fixed (#162). Read-side needs a higher PHPStan level.
- [x] Shell-outs (`git`, `gpg`, `git fast-import`) whose exit codes or stderr are ignored. None ignored where it matters (findings, correctness pass).

### 2.4 Security-adjacent quick checks
- [x] Every template output goes through the escaper (`$e(...)`); grep for raw `<?= $` in `templates/`. 38 bare echoes, all numbers, literals or pre-built HTML; `$notice` now escaped (#143).
- [x] Shell commands built with string concatenation instead of `escapeshellarg` / argument arrays. All traced; none unsafe. The last two string-argument git helpers now take arrays (#150).
- [x] SQL built by string interpolation rather than bound parameters. 58 checked; every value is a placeholder list, fixed fragment, int or allow-listed (findings, correctness pass).
- [x] File paths derived from request input (path traversal). All request-facing file reads are guarded (findings, correctness pass).

### 2.5 Type and API hygiene
- [x] Files missing `declare(strict_types=1)`. None in `src/`.
- [ ] Missing parameter/return types; `mixed` or untyped arrays used as ad-hoc structs (`$item['view_security'] ?? false`) where a small value object would help.
- [ ] Static-call "presenters" and service locators called directly from templates.

### 2.6 Markers and comments
- [x] `TODO` / `FIXME` markers: none in `src/` (the earlier 257 was a loose substring count; see findings §7).
- [ ] Commented-out code blocks.
- [ ] Docblocks that contradict the code.

### 2.7 Tests
- [ ] Tests for code that no longer exists, or tests that assert nothing meaningful.
- [x] Known pre-existing failures (e.g. the `BoardListTest` failure seen during the Unicode fix) — fix or mark explicitly. `BoardListTest` passes now. Five HighTraffic failures turned out to be a real bug, fixed in #147.
- [ ] Slowest tests; whether the smoke set still covers the hot paths.

---

## Tooling to set up first

- [x] **PHPStan** (run at level 0, so it only reports dead code and fatal errors; raising the level is a later step), and the dead-code extension (`shipmonk/dead-code-detector`) for unused classes/methods/constants. Run without committing a baseline at first; just collect output.
- [x] **phpcpd** or **jscpd** for duplication across PHP and JS.
- [ ] **knip** or a simple import graph for unused JS assets/exports.
- [x] A small script that cross-checks `public/assets/*`, `templates/*`, and `scripts/*` against every string reference in the repo (the quick version used to seed this list).
- [x] Keep tool configs out of the runtime dependency footprint (dev-only, or run ad hoc) to stay within the minimal-dependency principle. Run ad hoc from a scratch directory; nothing added to the repo. The commands are in the findings appendix.

---

## Suggested order

1. ~~Tooling + raw reports (no changes).~~ Done (#141).
2. Repo clutter and obviously dead scripts/assets (lowest risk, quick wins). Not started: §1.4 and §1.5 need keep/remove decisions.
3. Dead PHP classes/methods and stale feature flags. Dead members done (#146); test-only members and feature flags wait on decisions.
4. ~~Error-handling and security quick checks (fix as found).~~ Done (#143, #148, #150).
5. God-class splits — each as its own Railspike cycle, not part of this review.

## Output

- [code_quality_review_findings_v1.md](code_quality_review_findings_v1.md) — every finding with file, category and proposed decision.
