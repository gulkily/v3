> **Feature plan:** [Step 1](./docs_independent_publication_step1_solution_assessment.md) · [Step 2](./docs_independent_publication_step2_feature_description.md) · [Step 3](./docs_independent_publication_step3_development_plan.md) · [Step 4](./docs_independent_publication_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/docs_independent_publication`, standing enrollment P2; [host ledger](../effort_estimation_trial.md#enrollment-index-after-p2).

## Original Query

“Let's do that, please.”

## Understood Intent

Publish documentation separately from forum imports, retaining complete static access. The request accepts the recommendation immediately preceding it: “Separating publication would avoid making an import wait for a docs rebuild while retaining static access.” It follows a real `import-instance --resume` reporting documentation rendering after the code/tree changed.

Problem: forum import/resume still blocks on a complete docs rebuild whenever reusable output is absent or incompatible.

## Option A — Independent static documentation publication (recommended)

Give documentation its own complete publication lifecycle. Import/resume publishes forum content without rendering or rebuilding docs; a separate operator/deployment operation refreshes the static library while keeping the last complete docs publication available.

- Pros: removes docs rebuilds from imports, preserves static/no-JavaScript access and existing reader URLs, isolates docs failures from forum imports.
- Cons: documentation refresh becomes a separate operation; fresh static-only hosts need an initial docs publication, and source changes are not published merely by importing forum records. Docs versions/assets must remain usable independently of newer forum releases.

## Option B — Render documents on demand

Generate each documentation page when a reader requests it, optionally caching rendered pages, so imports never build the library.

- Pros: no full-library build is required before reading a document; only visited pages incur rendering work.
- Cons: requires a rendering service for uncached requests and changes static-only hosting guarantees; adds cold-request latency and cache/recovery behavior.

## Recommendation and boundary

Recommend Option A, matching the user's selected direction. Deliver through normal import/resume plus a separate normal docs publication operation; retain current public-reader URLs, browse/search/downloads, assets and existing supported no-JavaScript behavior. Imports must complete even with missing, outdated or damaged docs publication. Preserve prior complete docs on failed docs refresh, and provide an actionable initial-publication/retry path for static hosts. Audit docs version notifications, asset ownership and existing forum release guards before requirements are finalized. No new third-party component is proposed.

## Boundary findings and acceptance focus

- Current docs readers, search/downloads and retained assets are selected from the forum publication. Separating ownership must cover all of them, including the last published library when forum releases switch.
- Docs currently carry the forum repository version and publication guards. Their update notification must follow the docs publication so an ordinary forum import does not create a false reload prompt; forum access/retraction safeguards must retain their existing protection.
- Acceptance must replay the reported WSL `import-instance --resume` with changed application/docs inputs and missing/damaged docs output: forum publication completes with zero docs rendering and no implicit docs rebuild. A separately invoked docs refresh publishes a complete library; failure retains the preceding docs library with an actionable retry. Verify existing URLs, assets, search/downloads and supported no-JavaScript browsing before and after forum activation.
- Preserve existing PHP reader fallback where available. On a fresh static-only deployment, the dedicated docs publication operation is still needed before the static library is available. No new worker, scheduling or external dependency is proposed.

## Predecessor and request disposition

Predecessor: [documentation reuse cycle](../docs_publication_policy/docs_publication_policy_step4_implementation_summary.md#commits-and-study-outcome), technically complete. Request: separate documentation from imports / Disposition: distinct follow-up application-maintenance cycle because cold/incompatible docs currently must rebuild as part of an import / Approval: user requested this direction; Railspike Step 1 approval pending / Outcome: this assessment. Preserve predecessor outcomes and estimates. Current checkout is `feat/docs-publication-reuse`; no follow-up branch reuse is assumed. Establish this cycle's branch after Approved Step 3.

## Study snapshots

Intake UTC 2026-10-23T10:04:42Z; cycle `v3/docs_independent_publication`, scope v1, R1/P2; starting revision `ddc9d2c020addbc41b19f543d0247b83259c6f69`. **0.75 projected total**, Codex single exposed rater, including current assessment/planning, implementation, normal operator/recovery and reader verification, documentation and ordinary correction. Predecessor implementation is existing baseline infrastructure, not work forecast again. Prior-cycle plans/scores and source are already visible; this intake follows inherited detailed source exposure and initial read-only publisher review, so no pristine pre-inspection or independent rating is claimed. R1 report/diff hashes match; exact frozen feedback recovered read-only from Git `4f6cfe2b436fae8d939b1349fe724a3879ee0a0e` and matches recorded hash. Debug enabled by project marker presence for this new cycle. No implementation, measured active effort, completed or accepted outcome. No pristine earlier checkpoint is reconstructed.

Step 1 handoff UTC 2026-10-23T10:05:49Z: **0.75 projected total reaffirmed** for Option A, scope v1 / R1 / P2, same baseline and exposed single rater. Read-only serving/build/deployment review confirms that independent assets, release selection, docs update identity, cold-host setup and actual resume recovery belong in the scoped outcome. Earlier 0.75 intake retained; no implementation or measured actual. Await **Approved Step 1** before Step 2.

## Step 1 Approval

UTC 2026-10-23T10:20:48Z: user replied **“Approved Step 1”**. Option A advances to [Step 2 requirements](./docs_independent_publication_step2_feature_description.md); earlier pending states remain historical. No change to the selected direction or 0.75 projection.
