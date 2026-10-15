> **Feature plan:** [Step 1](./fastmod_prepublication_hold_step1_solution_assessment.md) · [Step 2](./fastmod_prepublication_hold_step2_feature_description.md) · [Step 3](./fastmod_prepublication_hold_step3_development_plan.md) · [Step 4](./fastmod_prepublication_hold_step4_implementation_summary.md)

Debug mode: enabled

## Status

Stage 1 investigation complete; readiness gate failed. Stages 2–8 have not started. No application changes, flag, private intake, or visibility enforcement have been implemented. The branch contains planning and investigation documentation only.

## Stage 1 - Validate the release boundary

- Changes: organized approved plans and debug log, repaired the Step 1 reference link, and indexed the feature in planning commit `f6e33768`. Recorded the inspected paths, conceptual lifecycle and revised size estimate below. The Stage 1 commit includes this summary and the evidence addendum to Step 3.
- Verification: inspected `LocalWriteService` normal thread/reply and prepared-post finalization, `RouteServices::pdo`, shared repository reads, source/current/blob/commit handling, image eligibility/serving, static routing, offline snapshot construction and repository deployment configuration. Ran `git diff --check` and local planning-link/path validation. Runtime, UI and migration tests are not applicable to this investigation-only stage. Host configuration and external distribution are unverified, not assumed safe.
- Notes: approved Step 3 explicitly requires stopping before application changes if the remaining stages cannot fit approximately one-hour units. Stages 3, 4, 5 and 6 are multi-stage efforts. No Stage 2 work is authorized under a knowingly invalid stage breakdown. No active feature flag or rollout was changed.

## Observed publication and reading paths

| Surface | Repository evidence | Consequence |
| --- | --- | --- |
| Normal writes | `src/ForumRewrite/Write/LocalWriteService.php`, `createThreadForAuthoringMode` and `createReply` write canonical files, commit them, then synchronize derived state | Intake must branch before canonical commit; a render filter cannot provide private receipt |
| Signed writes | Same file, `createPreparedPost` validates canonical bytes/signature, writes both files, commits and synchronizes | Private intake must preserve exact bytes/signature and existing validation, with stable idempotent finalization |
| Normal reads | `RouteServices::pdo()` opens the common read model; `ThreadRepository` queries public posts/threads/profiles directly | Pending content requires a shared viewer-aware read projection or consistent private overlays, including aggregates and pagination |
| Raw source/history | `SourceFileController::currentFile/blob/commit` reads canonical files/history; retraction suppression is a specific existing mechanism | No generic audience policy exists here; direct Git access cannot be controlled by these routes |
| Images | `LocalWriteService::assertImageAuthor` already requires approved signing identity; `ImageController` serves named images without a post-audience check | Preserve the existing unapproved-upload prohibition; references and any future eligibility changes still need audience analysis |
| Static serving | `public/.htaccess` routes content/downloads through PHP; `FrontController` resolves public HTML and offline artifacts before ordinary page rendering | Repo-owned routing helps but does not establish actual Apache/host behavior; static responses need flag-aware freshness/authorization |
| Offline artifacts | `PublicOfflineSnapshotBuilder::visibleThreadRows` checks `posts.is_hidden`, while snapshots/updates are selected by `FrontController` | A new tier is not automatically respected; build and serving behavior must both follow the flag |

The read-path search found 61 PDO/open-connection references in HTTP/application code and 14 methods across ThreadRepository, AuthoredContentRepository and ProfileRepository. These are inspection counts, not unique affected routes or a complete coverage claim.

## Conceptual lifecycle to preserve

- Resolve the enforcement snapshot and author approval under the existing write serialization. When on, store a neutral unapproved submission and its signature privately before acknowledging receipt. When off, normal visibility applies, while preserving enough moderation metadata to reapply restrictions later.
- Approved-user reading combines public and authorized neutral content through a shared projection/overlay; it must not expose private bodies in raw public downloads. The author self-view/import/override defaults in the approved plan remain the proposed baseline, not newly implemented behavior.
- Human approval records an attributable decision and publishes exact signed bytes idempotently. Account approval separately clears the author's restrictions. Interruptions must be recoverable at every private-store/publication boundary.
- Off intentionally makes existing restricted content visible without granting human approval. On hides it again through the app and regenerates controlled artifacts. It cannot revoke downloads or erase public Git history. Whether canonical Git must be a public-only projection, or a private source with separately published exports, is an architectural decision still required.

## Revised sizing — estimates, not implementation stages

| Approved stage | Estimated one-hour units | Reason |
| --- | --- | --- |
| 2: private thread receipt | 2–3 | Durable state, both authoring modes, author receipt and recovery |
| 3: normal reading and approval | 2–3 | Viewer projection, ordinary navigation, signed approval/publication and concurrency |
| 4: replies/account transitions | 2–3 | Parent visibility, approved replies, author/account transitions and interrupted release |
| 5: discovery/metadata | 3–4 | Multiple repositories/controllers and aggregate/pagination behavior |
| 6: media/artifacts | 3–4 | Source/history, exports, static/offline builds, flag transitions and direct-host boundary |
| 7: operations/recovery | 1–2 | Flag, status/retry and crash-recovery verification |
| 8: integration/host checks | 1–2 | Viewer/surface matrix, deployment evidence and failure checks |

Estimated remaining slice-1 work is **14–21 hour-sized units**, plus host coordination. This exceeds the approved eight-stage/day guardrail. These ranges replace the earlier provisional eight-stage estimate; the earlier conversational 18–24 estimate for the unsplit whole feature was not a full breakdown and should not be relied upon.

## Required next decision

Either authorize a larger slice-1 plan with a revised stage budget, or approve a narrower releasable scope after reviewing which public distribution channels remain supported. Do not silently drop a surface or substitute a queue-only reading experience. Resolve the public canonical-history contract before implementation; host verification remains an activation prerequisite. No implementation can claim reversible restrictions over already distributed copies.
