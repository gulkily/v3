> **Feature plan:** [Step 1](./import_without_static_rendering_step1_solution_assessment.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/import_without_static_rendering`, standing enrollment P2; [host ledger](./effort_estimation_trial.md#enrollment-index-after-p2).

## Original Query

```text
Fingerprinting and copying assets referenced by rendered pages...
Rendering shared pages (0/10).
Rendering shared pages (1/10): /.
Rendering shared pages (2/10): /threads/.
Rendering shared pages (3/10): /about/.
Rendering shared pages (4/10): /instance/.
Rendering shared pages (5/10): /activity/.
Rendering shared pages (6/10): /users/.
Rendering shared pages (7/10): /tools/.
Rendering shared pages (8/10): /tools/bookmarklets/.
Rendering shared pages (9/10): /tools/feature-flags/.
Rendering shared pages (10/10): /tags/.
Rendering shared pages complete (10/10).
Static docs are not published; run ./v3 docs publish with the same destination/profile.
Rendering tag pages (0/46).
Rendering tag pages (25/46).
Rendering tag pages (46/46).
Skipping individual thread and post pages (FORUM_STATIC_DETAIL_PAGES_ENABLED is disabled).
Rendering profile pages (0/145).
Rendering profile pages (25/145).
Rendering profile pages (50/145).
Rendering profile pages (75/145).
Rendering profile pages (100/145).
Rendering profile pages (125/145).
Rendering profile pages (145/145).

None of this page rendering should be happening from just an import.
```

## Problem

`ImportedContentPublisher` explicitly builds/activates a complete forum static release on every import, including duplicate-only runs and resumes; disabling detail pages does not disable shared/tag/profile rendering.

## Option A — Refresh data and invalidate stale HTML (recommended)

Import/resume commits records and refreshes required derived data, withdraws stale forum HTML and leaves page rendering to normal PHP requests or explicit `build-static`.

- Pros: zero import-time page rendering or asset fingerprint/copy pass; imported content is immediately available through PHP; existing independent docs survive.
- Cons: an import no longer prepares static-only hosting; operators explicitly build when that output is needed. Recovery must safely retire stale HTML/offline data without losing the committed import.

## Option B — Make static rendering an optional import mode

Disable page rendering by default but add an explicit import option to build static output after import.

- Pros: meets the default behavior and offers a combined static-host workflow.
- Cons: introduces another command mode and recovery contract despite an existing standalone `build-static` command.

## Recommendation and Scope

Choose A as one releasable follow-up. Cover cold/new/duplicate imports, pending journals created by the old publisher, remote-free resume, failed data refresh/retry, private destinations, retained docs, stale forum HTML and offline correctness. Keep required public offline data current without invoking a page renderer; private access gates remain in force. Preserve the read-model schema and existing explicit build commands. No background build, new dependency, navigation or reader UI. This extends the completed docs-only cycle; its approved outcome remains intact.

## Study Snapshots and Approval

UTC 2026-10-23T18:01:59.961066+00:00; scope v1 / R1 / P2, start `c86b89314602a17cf435448bb66053f4d750150f`, predecessor `v3/docs_independent_publication`. Intake recorded after prior-cycle exposure and read-only inspection of publisher/runner/invalidation; no pristine intake claimed. **0.55 projected total**, Codex single exposed rater, including planning, implementation, recovery tests, normal CLI/browser verification and runbook updates. Step 1 handoff reaffirms **0.55**. Smaller than R1 startup recovery because existing data promotion/invalidation/offline/CLI machinery is reusable, but stale output and old-journal recovery make this more than deleting a call. Exact host-local R1 report/diff/frozen-feedback hashes verified unchanged; no independent rating, measured active work, implementation or accepted outcome. Existing uncommitted reference recovery is preserved. No dependency additions proposed.

Await **Approved Step 1** before Step 2; this artifact does not approve implementation.

## One-shot execution override

User subsequently asked, “Can we do this as a one-shot, please?” This opts out of further Railspike approval/staging for this task and authorizes Option A directly. The historical Step 1 gate above is superseded for this task only. See [one-shot outcome](./import_without_static_rendering_one_shot_summary.md); no further approval requested.
