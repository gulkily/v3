Cycle: v3/publisher_pr_handoff; Mode: one-shot; Index: [plans](../README.md)
Debug mode: enabled
Effort study: railspike-effort / R1 / P2; Ledger: [cycle](../effort_ledger/publisher_pr_handoff.md)
Extended estimates: enabled

**Cycle estimate: 0.25**

Status: integration and epic checklist locally verified; ready to push and open PR.
Authorization: 2026-11-23 session user asks "do we have a checklist for this epic that we can update? please go ahead and make pr". Authorizes updating the existing epic coordination record, integration needed for a reviewable publisher PR, branch push and PR creation. No merge/deploy, additional messaging feature, live key publication or external forum reply.
Parent: [direct publication](../publish_keys_direct/publish_keys_direct_one_shot.md), with original publisher and CLI follow-ups retained. Scope is documentation/integration/release handoff; no new application feature. Existing ordinary CLI and safety contracts remain unchanged.
Starting revisions: c6e73b7d1; freshly fetched origin/main 2e14bcb96. New feat/publish-public-keys branch in the existing /home/wsl/v3-publish-local-public-keys checkout; remote branch absent and no existing direct-publication PR found. No new worktree; original ~/v3 untouched.
Planning commit: bf7d48966 (plan/index only, confirmed before integration/checklist edits).

## Outcome, reuse and risks

Reuse docs/plans/private_messaging_usability_master_checklist.md as the epic tracker; do not create a duplicate epic. Add the public-key publication prerequisite and link all implemented slices, recording the user's "done" after the production command as a user-reported run with no per-key output supplied. Registration is not proof of approval/history access. Leave production per-key results, approval readiness and actual recovery verification unchecked; existing history recovery is already implemented and needs verification, not a claim of a missing feature.

Open one PR containing the original publisher plus hostname/source/help and direct-publication fixes. Integrate fetched main to resolve conflicts and rerun smoke/affected checks if the integration changes the test baseline. Reuse existing CLI/HTTP fixtures and docs; no new components, dependencies, schema or web UI. Allowed authored changes: epic checklist, this cycle's plan/debug/ledger and index, feedback collision/evidence/index, PR metadata, and conflict resolution preserving both existing publisher and main functionality. Do not rewrite completed commits or historical parent plans.

Feedback collision discovered on fresh main: PF-20261122-B04 now belongs to an unrelated Ochre issue; our earlier unpublished publisher intake used the same ID. Preserve main's canonical item byte-for-byte. Archive the original local publisher record unchanged outside canonical items, and allocate a new canonical publisher intake PF-20261123-B02 (main has only B01 today), explicitly documenting the collision and historical local commit trailers. This is a new intake reconciliation, not reassignment of the canonical Ochre ID. Earlier records/commits retain their historical IDs; no silent renumbering or issue closure. F03 broader reverse import remains open.

Material risks: stale-main conflicts and feedback-ID collision must not overwrite other contributors' work; preserve both histories and inspect the final diff against main. User-reported completion must not become fabricated production results. PR state must be checked before pushing/updating an existing head. Open draft if material verification remains unresolved beyond reproduced baseline failures; do not claim merge/deploy from PR creation.

## Completion and verification

- Existing epic checklist shows implementation, user-reported run, remaining verification and PR/review/deployment separately.
- Main integration is conflict-free and final diff contains only the intended publisher/docs/tests and handoff, with main's other features/feedback intact.
- If integrated, run ./v3 test --smoke PublishKeysCommandTest PublicKeyPublisherTest PublicKeyPublishHttpTest InstanceSourceResolverTest ScriptsIndexTest; no full suite. Record changed baseline and retained failures honestly. Verify feedback check/index, scoped links, syntax and diff.
- Push authorized branch, create PR against main via connected GitHub tools, verify returned URL/head/base/state. Record PR link in checklist/record without inventing this commit's own hash. No forum reply.
- Browser/theme/nav and schema/dependency checks inapplicable to authored work; integration preserves current-main changes and selected shared consumers are covered.

Concepts touched: existing public-key publication and historical-message recovery used unchanged; this coordination/handoff does not change those concepts.

## Study and history

2026-11-23T17:41:46Z: Intake unavailable after prior review/exposure; no retrospective intake invented. Entry whole-cycle projection 0.25, v1/R1/P2, exposed Codex rater; R1 hashes verified. Includes checklist evidence reconciliation, fresh-main/feedback collision handling and PR verification. No measured labor or artificial stages. Maintainer fit and acceptance pending.

Outcome: pending. Commit/push/PR/merge/deployment: planning pending, others not yet performed.
Attribution: Codex, 2026-11-23 UTC.

2026-11-23: Integrated origin/main 2e14bcb96 without rewriting prior publisher commits. Three conflicts were limited to plan/feedback indexes and the colliding B04 file. Plans index preserves both sides; feedback index regenerated. Main's canonical Ochre B04 is byte-identical, original local publisher B04 is archived byte-identical, and new canonical publisher intake B02 explains the collision/historical trailers. No authored application behavior change; normal auto-merge preserved both current main and publisher CLI/help. Runtime verification reruns because main advanced.

2026-11-23: Updated the existing private-messaging master checklist, not a duplicate epic. Checked implementation and user-reported production execution separately; per-key outcomes, actual destination approval/history recovery, PR opening and merge/update remain unchecked until their own evidence. Existing history synchronization is already implemented; this task does not authorize a new recovery mechanism. User's "done" records only that the suggested production command was run, not a transcript or successful registration count.

2026-11-23: Integrated smoke/affected checks: ./v3 test --smoke PublishKeysCommandTest PublicKeyPublisherTest PublicKeyPublishHttpTest InstanceSourceResolverTest ScriptsIndexTest — 513 run, 509 passed, only the same four parent-reproduced Unicode/PCRE failures. All selected publisher/resolver/index and actual API checks passed; no full suite run. PHP syntax, bash syntax, help and git diff --check pass. Feedback check: 361 items, zero problems, 36 existing source-review notices; index regenerated. Verified main's B04 and the original publisher archive byte-for-byte.

2026-11-23: Existing epic had stale "not pushed" claims: ed36a069, 95ac3114, 34b48f40 and 68a5a3fd are proven ancestors of fetched origin/main. Updated code-availability wording without claiming deployment, linked implemented sender-assisted/bounded-background recovery, and repaired the epic's missing old FDP workflow link to Railspike. No new runtime feature was implemented. Per-key live registration/approval/decryption evidence still unchecked.
