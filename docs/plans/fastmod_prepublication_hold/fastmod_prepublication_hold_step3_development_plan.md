> **Feature plan:** [Step 1](./fastmod_prepublication_hold_step1_solution_assessment.md) · [Step 2](./fastmod_prepublication_hold_step2_feature_description.md) · [Step 3](./fastmod_prepublication_hold_step3_development_plan.md) · [Step 4](./fastmod_prepublication_hold_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- **Slice 1 only:** normal/signed local thread and reply submission → approved-only neutral visibility → individual approval by any approved user → public visibility. Approved authors bypass holds. Include author receipt/status, ordinary approved-user reading, authenticated review, durable audit, retries and recovery. No negative tier or automatic score-driven release in this slice.
- While enforcement is on, restricted payloads and attachments must not enter unprotected canonical distribution, source history, public databases, static output or offline snapshots. Approved post publication preserves signed bytes. Existing instance access rules still apply.
- Enable only after the full viewer/surface matrix, rebuild/restart and failure checks pass, and host checks confirm all content/download requests pass through authorized routing with no direct access to private stores. The dedicated enforcement flag defaults off, independently of Fastmod scoring. Off restores current visibility for new and existing content, including held posts, subject to existing instance access rules. Preserve scores, standing, per-post decisions and audit state; on reapplies applicable restrictions. Flag-off visibility is not an individual approval. No timeout releases while on.
- Eight stages is a target, not an assertion that unresolved distribution work fits. Stage 1 must validate approximately one-hour stage sizes; if any remaining stage exceeds that, stop and revise scope before application implementation. Completion is not claimed without real host verification; record unavailable external checks as release blockers.

## Key Risks

- **High risk: disclosure.** Public Git/history, raw databases and static hosting defeat page filtering. Validate actual paths in Stage 1; keep restricted bytes server-private while enforcement is on; flag-off public visibility is explicitly authorized and requires reversible public projections, not irreversible publication of private bytes into public Git history. Previously public material is outside slice 1’s new-intake boundary.
- **High risk: data/rollback.** Cross-store receipt/release failures can lose or duplicate posts. Exercise each transition failure before release; use durable decision state, stable IDs, idempotent recovery and serialized authorization. Test flag transitions against retained state, stale artifacts and in-flight writes; disabling the scorer alone must not disable enforcement.
- **High risk: usability/sizing.** Private thread/reply overlays may be larger than eight stages. Trace normal reading and aggregate queries first; do not substitute a queue-only experience or omit a surface to meet the limit.
- **Policy gate:** before Stage 2, confirm author self-view, imported-content treatment and durable human-approval precedence. Proposed defaults: authors see own pending content; imports remain unchanged in slice 1; human approvals remain public until a separately authorized action. Later slices require their own approvals and calibration.

## Stage 1 — Validate the release boundary

- Goal: resolve policy/distribution risks and prove this slice fits the budget (one-hour investigation).
- Dependencies: approved slice 1; clarification of the three proposed policy defaults.
- Expected changes: record runtime and host publication paths, viewer matrix, pending-data lifecycle, chosen private intake/reading approach and stage estimates in the plan; no application changes.
- Verification approach: trace normal/signed writes, canonical Git, read models, direct downloads, images, source/history, static/offline builds and deployment routing.
- Risks or open questions: unknown direct hosting or oversized stages block Stage 2; resolve by host evidence and explicit rescoping, not assumed HTTP filtering.
- Canonical components/API contracts touched: LocalWriteService, RouteServices, FrontController, deployment runbook, canonical and read-model publication.

## Stage 2 — Receive a neutral thread safely

- Goal: submit a local text thread privately and obtain durable author receipt.
- Dependencies: Stage 1 gates resolved.
- Expected changes: private pending-content/decision storage with post identity, exact signed bytes where present, audience, author, timestamps and audit; intake/status contracts shared by normal and prepared writes. Public canonical/read-model outputs follow the active enforcement policy, without rewriting signed bytes or losing held-state metadata when off.
- Verification approach: submit through both write paths; verify restart recovery, stable IDs, receipt/status and absence from public files/history.
- Risks or open questions: receipt persistence failure must reject safely without publishing; no successful receipt before durable storage.
- Canonical components/API contracts touched: LocalWriteService.createThread/prepareThread/createPreparedPost, WritePostAndIdentityApiController, new pending-content service/store and status contract.

## Stage 3 — Read and approve the neutral thread

- Goal: approved users discover/read neutral threads in normal board/thread views and approve one to public.
- Dependencies: Stage 2.
- Expected changes: approved-view private-content composition, shared card approval action, authenticated approval contract, idempotent publication and attributable decision audit; account approval stays separate.
- Verification approach: approved versus unapproved/no-session discovery and permalink checks; forged, repeated and concurrent approval; publication failure/retry.
- Risks or open questions: authorization must be current at execution, and publication must preserve original signed bytes; failed release stays recoverable.
- Canonical components/API contracts touched: BoardPageController, ThreadAndPostPageController, shared thread/post cards, ApprovalPolicy, writer publication path, pending service.

## Stage 4 — Complete reply and account transitions

- Goal: extend the same journey to normal/signed replies and approved-author behavior.
- Dependencies: Stage 3.
- Expected changes: reply target validation/composition, author self-status, account-approval bypass and release of existing pending items; retry-safe transition handling.
- Verification approach: pending/public parent combinations, approved reply to a pending root, signed reply recovery, account approval during pending submission/release.
- Risks or open questions: no inaccessible parent text in public replies; preserve approved replies without exposing a restricted ancestor, using a content-free parent placeholder where necessary.
- Canonical components/API contracts touched: createReply/prepareReply/createPreparedPost, compose templates, thread tree/card rendering, identity approval flow.

## Stage 5 — Cover discovery and derived metadata

- Goal: enforce neutral visibility consistently outside board/thread entry paths.
- Dependencies: Stage 4.
- Expected changes: apply the shared audience policy to standard/Forte/QDB random/search, profiles/user/username, activity, tags, lobby, RSS and APIs, including counts, previews and pagination.
- Verification approach: viewer-matrix checks on every listed surface, hidden-ID probes and aggregate metadata; approved views include neutral content.
- Risks or open questions: scattered queries may exceed the time budget; Stage 1 must identify canonical integration points or block this schedule.
- Canonical components/API contracts touched: ThreadRepository, AuthoredContentRepository, ProfileRepository, activity services, content controllers and shared cards.

## Stage 6 — Protect media and distributed artifacts

- Goal: prevent non-page routes and generated artifacts from bypassing the neutral audience.
- Dependencies: Stage 5.
- Expected changes: private attachment lifecycle; tier-aware reads; export/static/offline generation and source/history handling governed by the enforcement flag; invalidate cached artifacts on release/account/flag changes, including off-to-on narrowing.
- Verification approach: image/source/blob/commit probes, database inspection, static/offline generation, cached paths and rebuilds across viewer identities, plus off → on → off → on with existing held posts and retained state. Already-downloaded artifacts cannot be revoked.
- Risks or open questions: direct Git/file serving cannot be secured by a controller; refuse activation when restricted bytes or stores are externally exposed.
- Canonical components/API contracts touched: ImageController, SourceFileController, FrontController, public artifact builders, PublicOfflineSnapshotBuilder/Publisher.

## Stage 7 — Operate and recover

- Goal: make pending receipt and release failures diagnosable and recoverable without SQLite edits.
- Dependencies: Stage 6.
- Expected changes: review discovery/status and narrow retry, audit retention, independent default-off enforcement flag with on/off behavior from the Completion Contract, activation preflight and rollback instructions; preserve the advisory Fastmod pipeline for already-public posts.
- Verification approach: crashes between private save/publication/audit, restart/rebuild, duplicate retries, flag transitions for existing/new posts, scoring disabled with enforcement still on, and preservation of all moderation metadata.
- Risks or open questions: flag off intentionally makes held posts visible; it must neither erase moderation decisions nor grant author/post approval. Flag on must restore restrictions across caches/artifacts; concurrent operations must use consistent policy state.
- Canonical components/API contracts touched: tools/navigation, operator CLI, feature registry, pending service, deployment/Fastmod reference docs.

## Stage 8 — Verify release end to end

- Goal: demonstrate the complete slice in a representative deployment.
- Dependencies: Stages 1–7 verified; host access/evidence available.
- Expected changes: focused regression scenarios, deployment checklist and final summary of actual results/limits; no later-slice behavior.
- Verification approach: run meaningful write/authorization/read-model/artifact regressions and normal browser flows for operator, ordinary approved, unapproved author/other and no-session viewers; verify real routing, storage permissions, downloads and all enforcement-flag transitions, including previously held content.
- Risks or open questions: missing host verification blocks activation, and new defects require fixing/retesting before completion; never substitute local checks for external evidence.
- Canonical components/API contracts touched: tests/run.php, existing smoke suites, static-artifact checks, production runbook and feature Step 4 summary.

## Execution discipline

After explicit Approved Step 3, branch, organize/commit approved plans, then commit each verified stage with its summary before starting the next. Stage 1 unresolved decisions block dependents. Slice 1 completion does not authorize slices 2–3.
