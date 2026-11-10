# codebase_sizes — Step 3: Development Plan

> **Feature plan:** [Step 3](./codebase_sizes_step3_development_plan.md) · [Step 4](./codebase_sizes_step4_implementation_summary.md) (Steps 1–2 skipped: small, single-approach change requested directly from thread [thread-20261103063011-37a33e13](https://zenmemes.com/threads/thread-20261103063011-37a33e13))

Request (guest, Nov 3): `/tools/codebase/` should show sizes for everything that has a size, maybe hidden by default but expandable.

## Completion Contract

- **Normal entry:** visitor opens `/tools/codebase/`. Page looks as it does today plus a collapsed **Sizes** card (`<details>`, closed by default, no JS). Expanding it shows a size for each sized thing the page already names: read-model database file, content repository working tree (`records/`), content repository Git store, app checkout Git store, and each download.
- **Each size row** shows a human value (`12.4 MB`), exact bytes in a `title`/second column, and the source command, matching the existing `codebase-source` convention (`stat -c %s state/cache/post_index.sqlite3`, `git -C state/local_repository count-objects -v`, etc.).
- **Archive downloads** (`repository.tar.gz`, `.zip`) are built on request, so they show the size of the last cached build for the current revision, or `built on request` when there is none. The SQLite download shows the database file size.
- **Recovery:** any probe that fails or is unavailable renders `unavailable` for that row only; the page never errors or slows noticeably because of sizes.
- **Deployment check:** after deploy, open the page on zenmemes.com, expand Sizes, confirm plausible values; reply in the thread with the result.
- **Release condition:** smoke test for the page passes; new unit tests pass.

## Key Risks

- **Page cost.** Walking `records/` or hashing archives on every render could slow the page on the VPS. *Validate early:* time each probe on a production-size clone. *Mitigate:* no recursive filesystem walks; use `git count-objects -v` and `git ls-tree -r -l HEAD -- records` (sum blob sizes), and read archive cache `metadata` JSON only, never `ArchiveCache::open()` (which SHA-256s the whole archive).
- **Disclosure.** Sizes reveal little, but absolute paths must not leak. *Mitigate:* sources use the same relative `state/...` labels the page already prints.
- **Hidden-vs-visible choice for downloads.** Download sizes are conventionally shown next to the link. *Open question:* also show them inline on the Downloads list (proposed: yes), with the full set still in the collapsed card.

## Stage 1 — Byte formatting and size probes

- Goal: collect sizes cheaply and format them consistently.
- Dependencies: none.
- Expected changes:
  - New `ForumRewrite\Support\ByteSize::format(int $bytes): string` (binary units, one decimal; `0 B`, `1023 B`, `1.0 KiB`…). Check no existing formatter first (none found in `src/`).
  - `CodebaseStateController::collectState()` gains a `sizes` group: `[label => ['bytes' => ?int, 'source' => string]]` built from private probes `fileBytes(string): ?int`, `gitStoreBytes(string $root): ?int` (`size-pack` + `size` from `count-objects -v`, KiB → bytes), `recordsTreeBytes(): ?int` (sum of `ls-tree -r -l` blob sizes).
  - Reuse the existing `codebaseGitValue`-style exec guard (`.git` existence check, `--no-optional-locks`, stderr suppressed).
- Verification approach: unit tests for `ByteSize::format` edge values; controller test with a fixture repo asserting non-null bytes and `null` when `.git` is absent.
- Risks or open questions:
  - Impact: `ls-tree` cost on large repos. Early warning: time it on a clone of prod content. Mitigation: if over ~100 ms, drop the records-tree row and keep Git store only.
- Canonical components/API contracts touched: `CodebaseStateController`, new `Support\ByteSize`; `OperatorStatusCollector` and `apiStatus()` unchanged.

## Stage 2 — Last-built archive sizes

- Goal: size for on-demand archive downloads without building or hashing them.
- Dependencies: Stage 1.
- Expected changes:
  - `ArchiveCache::peekBytes(string $identity, string $kind): ?int` — reads `metadata` JSON (existing ≤4 KiB and symlink guards), returns `bytes` when identity/kind match; no stream, no hash, no lock.
  - `ImageBackup` exposes the identity it would use for the current revision (make `identity()` reachable via a small public `cachedBytes(string $kind): ?int`) so the controller doesn't duplicate identity logic.
  - Downloads entries gain `bytes: ?int`.
- Verification approach: test with a published cache entry (size returned), a stale-revision entry (`null`), and a corrupt metadata file (`null`).
- Risks or open questions:
  - Impact: showing a size for an outdated archive. Early warning: test with a moved HEAD. Mitigation: identity includes revision, so mismatches return `null` → `built on request`.
- Canonical components/API contracts touched: `Images\ArchiveCache`, `Images\ImageBackup`; download routes unchanged.

## Stage 3 — Collapsed Sizes card and inline download sizes

- Goal: render the sizes, closed by default.
- Dependencies: Stages 1–2.
- Expected changes:
  - `templates/pages/codebase_state.php`: new `<details class="codebase-sizes">` card (`<summary>Sizes</summary>`) before Downloads, using the existing `codebase-facts` table and `codebase-source` rows; `unavailable` / `built on request` for nulls.
  - Downloads list appends `(12.4 MB)` when bytes known (pending the open question above).
  - Minor CSS in `public/assets/tool-details.css` only if the summary needs it; reuse `account-key-advanced` details styling if it fits.
- Verification approach: extend the existing `/tools/codebase/` case in `tests/LocalAppSmokeTest.php` to assert the `<details` card, `Sizes` summary, and no `open` attribute; run the smoke set plus new tests (not the full suite). Manual check on phone width.
- Risks or open questions:
  - Impact: card layout on narrow screens with long source commands. Early warning: view at 375 px. Mitigation: existing `codebase-source` wrapping rules.
- Canonical components/API contracts touched: `codebase_state.php`, `tool-details.css`.

## Stage 4 — Deploy check and thread reply

- Goal: confirm on prod and close the loop.
- Dependencies: Stage 3 merged and deployed.
- Expected changes: none in code; reply as `claude` in the request thread with what shipped and a link to the PR.
- Verification approach: expand Sizes on zenmemes.com; compare database size with `ls -l state/cache/post_index.sqlite3` on the server if available.
- Risks or open questions: none beyond the above.
- Canonical components/API contracts touched: none.

*— Claude (Opus 5.5) · 2026-11-09 UTC*
