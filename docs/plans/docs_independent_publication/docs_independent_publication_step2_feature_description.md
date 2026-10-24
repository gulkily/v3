> **Feature plan:** [Step 1](./docs_independent_publication_step1_solution_assessment.md) · [Step 2](./docs_independent_publication_step2_feature_description.md) · [Step 3](./docs_independent_publication_step3_development_plan.md) · [Step 4](./docs_independent_publication_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/docs_independent_publication`, standing enrollment P2; [host ledger](../effort_estimation_trial.md#enrollment-index-after-p2).

## Problem

Forum imports still wait for a full documentation rebuild after a source/tree change or missing reuse evidence. Documentation needs an independent publication lifecycle while retaining complete static access.

## User Stories

- As an instance operator, I want import/resume to finish without documentation work so that docs cannot delay or fail forum publication.
- As a docs operator, I want a separate publish/retry operation so that I can refresh documentation without replacing forum releases or offline snapshots.
- As a reader, I want the last complete docs library to remain usable across forum updates so that its links, assets, search and downloads keep working.

## Core Requirements

- Import, resume, forum static builds and shared refreshes never build docs or perform full-library inventory/validation/copy work, including when code/docs change or output is absent, legacy or damaged. Selecting an existing docs publication must be independent of that bulk work.
- Provide a dedicated operator publish/retry operation with clear help, readable and automation output, and actionable recovery. Publish the complete eligible library, browse/search pages, safe downloads and assets atomically; failure retains the prior complete docs publication. Never activate or replace forum releases, read-model databases or forum offline snapshots through that operation.
- Preserve current URLs, Tools access, source boundaries, related links, search/filter/pagination, downloads, missing-document recovery and supported no-JavaScript browsing. Retain docs assets across forum/application updates. Docs notifications follow docs publication without false prompts or reload loops from forum imports; preserve forum access/retraction/classification safeguards.
- Keep existing published docs available during a documented independent transition/refresh. Missing/damaged docs remain separately recoverable and never cause an import failure or rebuild. Preserve existing PHP reader fallback; fresh static-only hosts require an explicit initial docs publication with clear setup guidance.
- Keep docs/forum activation and recovery independent, including overlapping operations. Reject unsafe paths, partial output and changing publication inputs; do not expose forum/private files through docs. Reuse canonical components and approved facilities; dependency additions/updates are zero.

## Delivery Scope and Completion Boundary

Work type: **application change**, internal publication maintenance. Includes serving/publication integration, operator CLI/help, regression tests, import/deployment runbooks and cycle plans/debug/effort ledger. No new reader UI, navigation, database schema, worker or schedule; production deployment is outside scope.

The user's normal WSL entry is `./v3 import-instance --resume`, which reported `Rendering documentation (...)` after a tree change. Replay pending publication with changed code/docs and absent/legacy/damaged docs: forum content becomes available, the journal clears without duplicate commits, and no docs bulk work runs. Existing same-destination resume handles forum failures. Separately publish docs, inject failure and retry while preserving prior docs and forum state. Release requires this WSL CLI recovery and served dynamic/static phone/desktop evidence; helper-only or another-platform evidence is insufficient.

## Risks

- **Ownership/migration:** current readers, assets and invalidation use forum releases. Audit first; verify retained docs survive forum activation/invalidation and failed transition without weakening forum protections.
- **Stale assets/updates:** shared versions/assets can cause broken readers or false reloads. Define a coherent docs publication boundary; vary forum/app/profile changes and test retained assets plus notification/reload recovery.
- **Coupled recovery/unsafe activation:** locks, input changes and partial files may interfere or expose content. Audit lock/access/ownership contracts before dependent work; test overlapping operations and failures, rejecting incomplete/unsafe candidates.

## Shared Component Inventory

Reuse/extend `PlatformDocsCatalog`, canonical Markdown rendering, `PlatformDocsController`, templates/search assets and Tools links. Separate lifecycle through `DocsPublication`, static builder/publisher and `FrontController` resource/asset resolution. Integrate existing import/recovery and full/shared CLI; audit invalidation/retraction/classification guards. Reuse version-notification behavior with docs publication identity while preserving forum version semantics. A dedicated docs operation replaces the current combined publication responsibility; no replacement reader/search policy is needed.

## Simple User Flow

1. Import/resume publishes forum content without docs bulk work; existing docs remain readable.
2. Publish docs separately for initial static setup or source/application updates.
3. Browse/search/download; on refresh failure, use the prior docs publication, correct the reported cause and retry docs publication.

## Success Criteria

Cold/legacy/changed/damaged-doc imports/resumes perform **zero docs rendering or bulk inventory/validation/copy work**. Dedicated docs publication updates the complete library independently. Transition, initial setup, failed/concurrent operations, reader/assets/notifications and normal recovery checks pass. Measure import work to confirm docs publication is absent from that path.

## Approval and Study Snapshot

UTC 2026-10-23T10:20:48Z: user explicitly replied **“Approved Step 1”**, selecting independent static publication (Option A). Requirements clarify that approved direction; no implementation or extra dependency is authorized yet. Step 2 approval pending.

Study `railspike-effort / v3/docs_independent_publication`, scope v1 clarified / R1 / P2; start `ddc9d2c020addbc41b19f543d0247b83259c6f69`; predecessor `v3/docs_publication_policy` remains completed and distinct. **0.75 projected total reaffirmed**, including this cycle's investigation/planning, implementation, transition/recovery/reader verification and documentation, with predecessor infrastructure already available. Codex single exposed rater; earlier source/score exposure and non-pristine intake preserved in [Step 1](./docs_independent_publication_step1_solution_assessment.md#study-snapshots). All three frozen R1 hashes reverified; exact feedback recovered read-only from its recorded Git revision. No independent rating, measured active effort, implementation or accepted outcome claimed.

## Step 2 Approval

UTC 2026-10-23T10:27:18Z: user replied **“Approved Step 2”**. Requirements advance unchanged to [development planning](./docs_independent_publication_step3_development_plan.md). Earlier pending state and 0.75 checkpoint remain historical; revised Step 3 projection reflects source-boundary understanding, not changed scope.
