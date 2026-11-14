# AGENTS.md line in every source file

Cycle: `v3/agents_md_source_line`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on` present; no explicit request)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: enabled (new enrolled cycle default); no real alternatives or work units, so no unit estimates

**Cycle estimate: 0.15 (execution entry; intake missing)**

Status: implemented and verified locally; PR open, awaiting maintainer review and merge. Follow-up pending: railspike-dev todo note (see Outcome).

## Original request

Maintainer, 2026-11-14, in a Claude session: "as the maintainer, i want every source file in the repo to have one line that directs agents to never ignore agents.md and always follow its instructions." Repository named in a follow-up answer: `v3`. Feedback items: none match (`docs/feedback/items/` searched; PF-20261030-F41 is about documenting common tasks, not this).

## Authorization

The request itself authorizes the bounded change. On 2026-11-14 the maintainer chose "Convert to one-shot" when asked whether to run this as a Railspike one-shot cycle or leave it as Direct work. That covers this record, the planning and completion commits, pushing the branch and opening a PR. It does not cover merging or deploying, and it does not cover changing AGENTS.md itself.

## Chronology (historical omission)

The agent first classified the task as a small Direct change under AGENTS.md ("Small, straightforward changes can proceed directly") and drafted the whole implementation in the working tree before any plan existed. When the maintainer asked about one-shot guidance, the work was converted to this cycle. No commit had been made. This record is committed alone first, with the drafted implementation left out of that commit. It was written after the implementation, so it is not a true pre-implementation plan. The omission is reported here and at closeout rather than hidden.

## Outcome and work type

Application maintenance. Every tracked source file gets one comment line, in the file's own comment syntax: `Agents: never ignore AGENTS.md; always follow its instructions.`

In scope: `.php`, `.js`, `.mjs`, `.cjs`, `.css`, `.py`, `.sh`, `.pl`, `.sql`, `.html` and `.htm` files, plus the extensionless `v3` launcher, across `src/`, `templates/`, `public/`, `scripts/`, `queries/`, `tests/` (fixtures included), `docs/examples/` and `autoload.php`. Also `scripts/build_sqlite_query_catalog.php`, so the generated `public/assets/sqlite_query_catalog.sql` carries the line, and the regenerated catalog and `sqlite_viewer.js`.

Excluded:
- Vendored third-party files (`openpgp.min.js`, `openpgp.v5.11.3.min.js`, `qrcode-generator.js`, `sql-wasm.js`). Their PROVENANCE files pin them as unmodified upstream with SHA-256 hashes.
- The `docs/railspike/` subtree (vendored Railspike).
- `docs/plans/` mockups (planning artifacts).
- `resources/` (third-party scripture copyright text).
- JSON files, which have no comment syntax.

## Approach and risks

One approach: a throwaway script, kept outside the repository, that inserts the line where each format allows it and skips files that already have it.

- PHP: after `<?php`, or after the shebang and `<?php` line. Templates that start with HTML get a leading `<?php // … ?>` line. PHP drops the newline after `?>`, so rendered output does not change.
- JS/CSS/HTML/Python/shell/Perl: first line, but after a shebang, a Python coding line, `@charset` or `<!doctype>`.
- `queries/sqlite/*.sql`: `SqliteQueryCatalog::parseFile` reads leading `-- key: value` metadata, and the query body must start with `SELECT` or `WITH`. A plain comment line would break both. The line goes in as a metadata entry, `-- agents: never ignore AGENTS.md; always follow its instructions.`, which the parser ignores. This also follows the project's `Key: value` format rule.
- Risk: byte-exact checks on assets or on the working tree. The SQLite catalog generator and asset fingerprinting are covered by the tests listed below. Vendored hash-pinned files are excluded.
- Risk: new source files added later will not have the line. Out of scope here; listed under follow-ups.

## Reuse decisions

- Uses the existing comment syntax of each format and the existing `Key: value` metadata of the query files. Adds no component, helper or dependency.
- The generated catalog is rebuilt with the existing `scripts/build_sqlite_query_catalog.php` rather than edited by hand.

## Completion conditions

- Normal entry: an agent opening any in-scope source file sees the line near the top.
- End-to-end: every in-scope file has exactly one copy of the line. All changed files still parse, and the site, CLI and tests behave as before.
- Release: PR merged by the maintainer.

## Concepts touched

None.

## Verification and recovery plan

- Syntax: `php -l`, `node --check`, `python3 -m py_compile`, `bash -n` and `perl -c` on every changed file.
- Tests: the smoke set, plus `SqliteQueryCatalogTest`, `AssetFingerprintTest`, `OpenPgpAssetSmokeProbeTest`, `NavIconSetsTest`, `TemplateRendererMediaEmbedsScriptTest` and `ThemeCatalogPageTest`. The full suite is left to the maintainer, per AGENTS.md.
- Recovery: the change is one line per file and can be reverted with the PR.

Starting revisions and branch: `main` at `59d2d64609d8b3ff98b3484df20dd4648a067f8a`; branch `chore/agents_md_source_line`.

Planning commit: `f70fcd7` (record only; written after the drafted implementation, see Chronology).

## Study history

- 2026-11-14 intake: **missing**. The cycle started after the implementation was drafted.
- 2026-11-14T17:19Z execution entry: **0.15** whole-cycle projection, scope v1, R1, P2. It sits between "Suppress duplicate thread-card preview" (0.10) and "Profile-specific about-page copy" (0.20). The change is mechanical, but it covers about 1,370 files, needs per-format parser checks, and has a generated artifact. The rater (Claude) had already seen the full implementation. No measured labor.

## Dated execution history

- 2026-11-14: Direct-mode draft (before this record). The script added the line to 1,369 files. The query-catalog generator header was updated and the catalog regenerated. Every changed file passed a syntax check.
- 2026-11-14T17:20Z, scope v2 (an addition, not a correction). The maintainer asked why the agent chose Direct work, and the agent suggested AGENTS.md wording. The maintainer replied: "can you please make this change to agents.md and also note it down in gulkily/railspike-dev/todo.txt". Added to this cycle: the AGENTS.md Railspike paragraph now requires a Railspike cycle for every committed change (one-shot by default, staged when review is needed). Direct work is limited to questions and uncommitted exploration, or to an explicit opt-out by the user. The first reply must name the chosen mode and why. The railspike-dev todo note is in another repository and is recorded only here, not in this cycle's v3 commits. Scope v1 is kept above.
- 2026-11-14: smoke set plus the related tests: 471 of 472 passed. `StatusCommandTest::testStatusPrintsAlignedHealthSummaryAndEveryFix` failed only because `./v3 status` adds a warning row when `public/index.php` has uncommitted edits. That test passes on clean `main`, and is expected to pass once the work is committed. Rerun after the completion commit.

- 2026-11-14T17:20Z study revision: **0.15 reaffirmed** for scope v2. The AGENTS.md paragraph adds very little work.
- 2026-11-14 closeout: scope v2 delivered in v3. The railspike-dev todo note was not delivered. There were no corrections after implementation. Retrospective scope assessment: 0.15. Maintainer acceptance pending.
- 2026-11-14: tested a clean snapshot of the working tree (`git stash create` checked out in a temporary worktree): smoke set plus the related tests, 472 of 472 passed, including `StatusCommandTest`. A count check found exactly one line in every in-scope file. Two files have more by design: the generated catalog has one per query plus its header, and the generator has its own comment plus the header string.
- 2026-11-14: after the AGENTS.md edit, `PlatformDocsCatalogTest` and `LlmsTxtRendererTest` passed (22 of 22). `tests/platform_docs_release_test.py` fails its `<table>` count at line 81 both with and without this change, so the failure predates this cycle. `git diff --check` is clean.

## Outcome

Delivered in v3:
- The line is in 1,369 source files.
- The SQLite query catalog generator and its regenerated outputs carry it.
- The AGENTS.md Railspike paragraph now runs every committed change as a Railspike cycle, and the first reply must name the chosen mode.

Not delivered: the note in `gulkily/railspike-dev/todo.txt`. That repository was not found. `rlspk/railspike-dev` exists, but attaching it was blocked because its owner differs from the one the maintainer named, so it needs the maintainer's confirmation.

Limitations:
- Source files added later will not get the line automatically.
- The full suite was not run.

Possible follow-up: a test that fails when an in-scope source file lacks the line.

## Verification

- `php -l`, `node --check`, `py_compile`, `bash -n` and `perl -c` on every changed file: passed.
- `./v3 test --smoke SqliteQueryCatalogTest AssetFingerprintTest OpenPgpAssetSmokeProbeTest NavIconSetsTest TemplateRendererMediaEmbedsScriptTest ThemeCatalogPageTest` on a clean snapshot: passed (472 of 472).
- `./v3 test PlatformDocsCatalogTest LlmsTxtRendererTest`: passed.
- `tests/platform_docs_release_test.py`: failed, and fails the same way on the unchanged tree.
- Full suite: not run, per AGENTS.md.

## Final review

- Diff: one inserted line per file. The generator gained one header line, and the regenerated catalog changed. The AGENTS.md change is one paragraph.
- No vendored files, `docs/railspike/` or `todo.txt` were touched.
- No dependencies were added. The dependency delta is zero.
- No concepts were touched.

## Commit/merge/publication status

- Planning commit: `f70fcd7`.
- Completion commit: pending at the time of writing. The PR reports its hash.
- Not merged. Not deployed.

*— Claude (Opus 5.5), 2026-11-14*
