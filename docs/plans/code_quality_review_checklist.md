# v3 Code Quality Review — Checklist

Status: first tooling pass done; results in [code_quality_review_findings_v1.md](code_quality_review_findings_v1.md).

Scope: a dedicated pass over `gulkily/v3` (snapshot `cdec870`, 2026-11-10) focused on **dead code** first and **obvious quality issues** second. Each item is a check to run; findings get logged in a results file before anything is deleted.

Codebase at a glance: ~426 PHP files in `src/ForumRewrite` (32 subsystems), 360 PHP test files, 66 scripts, ~85 JS assets, 42 CSS files, 516 plan docs.

---

## Ground rules

- [ ] Work on a branch (`chore/quality-review`); no behavior changes mixed into deletions.
- [ ] Before deleting anything, confirm it isn't reached **dynamically**: string-built class/file names, `theme-{$name}.css`, route tables, feature-flag registries, `template('…')` calls, `sendPrompt`/JS `data-*` hooks, the `./v3` command dispatcher.
- [ ] Remember the deployed sites (zenmemes.com, qdb.us, mitrapclub, chouse): something unused in core may be used only by one site profile.
- [ ] Each removal batch: smoke set + tests for touched areas (`./v3 test --smoke <Tests…>`), not the full suite.
- [ ] Log every finding as *remove / keep (with reason) / defer*.
- [ ] Start from the earlier audit, `docs/plans/codebase_cleanup_audit_findings_v1.md`. It already resolved some false leads, including the OpenPGP pair.
- [ ] **Intentional-duplicate rule.** If something looks unused or duplicated but is kept on purpose, the reason has to be visible *at the file*, not just in a plan doc:
  - Vendored third-party files get a `<name>.PROVENANCE` sidecar (upstream, version, integrity, SHA-256, modifications, why it's here, how to update) and a `<name>.LICENSE`. Examples: `qrcode-generator.PROVENANCE`, `openpgp.PROVENANCE` (PR #139).
  - First-party code that is only reached dynamically, or that is a deliberate twin, gets a short header comment saying what loads it and why.
  - When a finding turns out to be "keep", add the missing note in the same pass, so the next review doesn't trip over it again.

---

## Part 1 — Dead code

### 1.1 PHP classes and methods
- [ ] List classes in `src/` with no reference outside their own file and tests. A naive scan found only one candidate:
  - `Activity/AccessHistoryPresenter` — **false positive**, used from templates (`access_history_event.php`, `paned_activity_item_row.php`). Scans must include `templates/`.
- [ ] Classes referenced **only by tests** (code kept alive by its own tests).
- [ ] Public methods never called outside their class (needs a real tool — see "Tooling" below).
- [ ] Private methods / private properties never used.
- [ ] Constants and enum cases never read.
- [ ] Unused `use` imports.
- [ ] Unreachable branches: code after `return`/`throw`, conditions on values that can no longer occur (e.g. old schema versions, removed record types).

### 1.2 Feature flags
- [ ] For each flag in `Support/FeatureFlags/FeatureFlagRegistry`: is it permanently on everywhere? If so, remove the flag and the "off" path.
- [ ] Flags referenced in code but not in the registry, or registered but never checked.

### 1.3 Routes, templates, assets
- [ ] Routes in `Application` / `Http/RouteServices` / `Host/FrontController` with no link anywhere in templates, JS, or docs (candidates, then confirm against access logs if available).
- [ ] Templates in `templates/` never rendered.
- [ ] JS files in `public/assets/` never loaded by a template or other JS.
- [ ] CSS: theme files all came up "unreferenced" by name — confirm they're loaded via `ThemeRegistry` string building, then check each theme is still listed in the registry.
- [x] **Two OpenPGP bundles**: both are intentional. v6 is the default; v5 is the fallback for plain HTTP, where v6 can't run without WebCrypto. This is now documented in `openpgp.PROVENANCE` (PR #139). Still open: whether the 1.8 MB source map should ship.
- [ ] Other vendored files in `public/assets/` with no `.PROVENANCE` / `.LICENSE` sidecar.
- [ ] Orphaned CSS selectors inside large stylesheets (lower priority).

### 1.4 Scripts (`scripts/`, 66 files)
- [ ] Scripts not mentioned by `./v3`, docs, or other code: `markdown_list_corpus_audit.py`, `markdown_task_corpus_audit.py` (one-off audits?).
- [ ] Triplicated implementations: `publish_text.{php,pl,py}` and `stress_probe.{php,pl,py}` — intentional multi-language examples, or leftovers? Keep one, or document why all three exist.
- [ ] One-shot migrations/backfills that have already run everywhere: `backfill_unicode_risk.php`, `bake_legacy_timestamps.php`, `inject_approval.php`, the four `qdb_archive_import_*` scripts.
- [ ] Probes/benchmarks still worth keeping: `benchmark_profile_registration.php`, `markdown_render_probe.php`, `qdb_capacity_probe.js`.

### 1.5 Repo clutter
- [ ] Root-level notes that look like working scratch: `claude_request.txt`, `second_brain.txt`, `todo-scraped.txt`, `todo.txt` (308 lines). Move to `docs/` or remove; reconcile `todo.txt` with `docs/feedback`.
- [ ] `docs/plans/` (516 files): fine to keep as history, but check for plans marked in-progress whose work was abandoned.
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
- [ ] Methods over ~100 lines anywhere in `src/`.

### 2.2 Duplication
- [ ] Copy-pasted logic between subsystems (signature checks, path building, timestamp formatting, HTML escaping, JSON response helpers).
- [ ] Server/browser rule pairs that must agree (e.g. `UnicodeTextPolicy` ↔ compose-time JS validator): are they generated from one source or hand-synced?
- [ ] Near-duplicate JS: `outbox_*` (6 files), `private_message_*` (11 files), `key_backup*` (3) — check for repeated helpers that belong in one module.
- [ ] The `Sqlite*Store` classes: shared connection/busy-timeout/transaction boilerplate.

### 2.3 Error handling
- [ ] Empty `catch` blocks and catches that swallow exceptions without logging.
- [ ] `@` error suppression.
- [ ] Functions that return `false`/`null`/`''` for errors instead of throwing, with callers not checking.
- [ ] Shell-outs (`git`, `gpg`, `git fast-import`) whose exit codes or stderr are ignored.

### 2.4 Security-adjacent quick checks
- [ ] Every template output goes through the escaper (`$e(...)`); grep for raw `<?= $` in `templates/`.
- [ ] Shell commands built with string concatenation instead of `escapeshellarg` / argument arrays.
- [ ] SQL built by string interpolation rather than bound parameters.
- [ ] File paths derived from request input (path traversal).

### 2.5 Type and API hygiene
- [ ] Files missing `declare(strict_types=1)`.
- [ ] Missing parameter/return types; `mixed` or untyped arrays used as ad-hoc structs (`$item['view_security'] ?? false`) where a small value object would help.
- [ ] Static-call "presenters" and service locators called directly from templates.

### 2.6 Markers and comments
- [x] `TODO` / `FIXME` markers: none in `src/` (the earlier 257 was a loose substring count; see findings §7).
- [ ] Commented-out code blocks.
- [ ] Docblocks that contradict the code.

### 2.7 Tests
- [ ] Tests for code that no longer exists, or tests that assert nothing meaningful.
- [ ] Known pre-existing failures (e.g. the `BoardListTest` failure seen during the Unicode fix) — fix or mark explicitly.
- [ ] Slowest tests; whether the smoke set still covers the hot paths.

---

## Tooling to set up first

- [x] **PHPStan** (level 5 to start) with `--error-format=table`, and the dead-code extension (`shipmonk/dead-code-detector`) for unused classes/methods/constants. Run without committing a baseline at first; just collect output.
- [x] **phpcpd** or **jscpd** for duplication across PHP and JS.
- [ ] **knip** or a simple import graph for unused JS assets/exports.
- [x] A small script that cross-checks `public/assets/*`, `templates/*`, and `scripts/*` against every string reference in the repo (the quick version used to seed this list).
- [ ] Keep tool configs out of the runtime dependency footprint (dev-only, or run ad hoc) to stay within the minimal-dependency principle.

---

## Suggested order

1. Tooling + raw reports (no changes).
2. Repo clutter and obviously dead scripts/assets (lowest risk, quick wins).
3. Dead PHP classes/methods and stale feature flags.
4. Error-handling and security quick checks (fix as found).
5. God-class splits — each as its own Railspike cycle, not part of this review.

## Output

- `docs/quality/2026-11_review_findings.md` — every finding with file, category, decision, and PR link.
