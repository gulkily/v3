> **Feature plan:** [Step 1](./session_key_bootstrap_step1_solution_assessment.md) · [Step 2](./session_key_bootstrap_step2_feature_description.md) · [Step 3](./session_key_bootstrap_step3_development_plan.md) · [Step 4](./session_key_bootstrap_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/session_key_bootstrap`; [host ledger](../effort_estimation_trial.md#cycle-v3session_key_bootstrap). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.80**

# Step 3: Development Plan — Session key bootstrap

Step 2 approved 2026-11-16. No scope was added after approval.

## Completion Contract

- Normal entry: any page load in a browser with a live session and no key, with `session_key_bootstrap` on.
- End-to-end outcome: the browser holds a new key under the session's username. On a fingerprint match it is approved, and the member sees one notice with a backup offer. The lost key shows a Replaced pill and sits in the replaced-keys queue.
- Recovery: on a mismatch the key stays pending, and the notice says so. With the flag off, or over HTTP without `session_key_bootstrap_http`, nothing changes. A reload never makes a second key.
- Deployment: Ilya deploys; the replay on his iPhone in Safari runs in production (Stage 7).
- Release condition: Stages 1–6 pass their checks, and Stage 7 passes on a real iPhone. Until then the cycle stays pending.

## Key Risks

- **High risk:** a session-sourced grant could break rebuilds. Impact: keys gain or lose membership after a rebuild. Validation: Stage 2 replays a grant through a full rebuild and an incremental update first. Mitigation: build the grant on the invitation precedent, with a signed reply and a repository receipt.
- **High risk:** a stolen session mints a lasting member key. Impact: an impostor member. Validation: Stage 3 tests every refusal path. Mitigation: HTTPS, the fingerprint, one bootstrap per session, no root seed, and a retractable grant.
- **Existing sessions have no fingerprint.** Impact: every current member would land on the pending path. Validation: Stage 1 checks that a browser with a key records one quietly. Mitigation: a browser with a key re-signs once when its session lacks a fingerprint.
- **PR #274 is open.** It touches `RouteServices.php`, `Application.php` and `authentication_modes.md`, which this plan also touches. Validation: check at Step 4 entry. Mitigation: branch from `main` after it merges; otherwise rebase before the PR.
- **The receipt must stay free of device details.** The acceptance spec forbids IP or device details in receipts. Mitigation: the receipt says only that the fingerprint matched, never the traits.

## Stage 1

**Estimate: 0.12**

- Goal: each session carries a coarse fingerprint and a bootstrap-used mark, behind two new flags.
- Dependencies: none.
- Expected changes: add fingerprint and bootstrapped-at columns to the private session store. Record browser family, OS family and device class from the User-Agent at sign-in. A browser with a key re-signs once when its session lacks a fingerprint. Add `session_key_bootstrap` and `session_key_bootstrap_http` to the flag registry, both off by default.
- Verification approach: session store tests for capture, the quiet re-sign, and an old store upgraded in place. Flag registry test.
- Estimate details: driver: store upgrade and the quiet re-sign; uncertainty: how User-Agent parsing treats iPad Safari.
- Risks or open questions: iPad Safari can report itself as a Mac. Treat that as one device class so it never counts as a mismatch.
- Canonical components/API contracts touched: `ViewerSessionStore.php`, `ViewerSessions.php`, `AuthApiController.php`, `private_site_auth.js`, `FeatureFlagRegistry.php`.

## Stage 2

**Estimate: 0.15**

- Goal: a session-sourced grant exists as a record and survives every rebuild.
- Dependencies: Stage 1.
- Expected changes: the new key signs a bootstrap reply on its own bootstrap thread, naming the lost key. Add receipt kind `session` beside `direct` and `invitation`; the actor is the lost key. Teach full and incremental replay to accept it. Retracting the reply removes membership. Update the acceptance spec.
- Verification approach: tests for a full rebuild, an incremental update, a retraction, and a forged receipt (refused). A test that no approval seed is ever written.
- Estimate details: driver: the replay shared by full and incremental builds; uncertainty: the receipt validation rules.
- Risks or open questions: see Key Risks, first item. Do not start Stage 3 until this passes.
- Canonical components/API contracts touched: `ApprovalAcceptance.php`, `ApprovalPolicy.php`, `InvitationApprovalReplay.php` (pattern), `ReadModelBuilder.php`, `IncrementalReadModelUpdater.php`, `approval_acceptance_record_v1.md`.

## Stage 3

**Estimate: 0.12**

- Goal: one API call turns a session and a public key into an approved or pending key.
- Dependencies: Stage 2.
- Expected changes: a signed bootstrap endpoint. It checks the flags, the scheme, the session, the bootstrap-used mark and the fingerprint. On a match it writes the grant and receipt; on a mismatch it leaves the key pending. Planned call: bootstrap a key from a session, given the public key and the signed reply; it returns approved or pending.
- Verification approach: write-API tests for match, mismatch, missing fingerprint, HTTP with and without its flag, flag off, a second call and a retired lost key.
- Estimate details: driver: the refusal paths; uncertainty: low.
- Risks or open questions: see Key Risks, second item.
- Canonical components/API contracts touched: `AuthApiController.php`, `LocalWriteService.php`, `RouteServices.php`.

## Stage 4

**Estimate: 0.12**

- Goal: a keyless page load with a session gets its key without any action from the member.
- Dependencies: Stage 3.
- Expected changes: the browser makes a key, calls the endpoint and stores the key. It shows one notice with the backup offer. The pending case shows its own line.
- Verification approach: a browser test that deletes the stored key and keeps the cookie, then reloads twice. A check without JavaScript shows the page unchanged.
- Estimate details: driver: the notice; uncertainty: where the notice sits before first paint.
- Risks or open questions: the notice must follow the first-paint rule in `CONTRIBUTING.md`.
- Canonical components/API contracts touched: `browser_signing.js`, `private_site_auth.js`, `key_backup_ui.js`, `key_backup_status.js`, an existing page notice.

## Stage 5

**Estimate: 0.12**

- Goal: the lost key shows a Replaced pill and waits in a queue.
- Dependencies: Stage 2.
- Expected changes: a read-model field for the replacement key, with a schema version bump. Add a `chip--replaced` variant. Show the pill and "replaced by" on key pages and the pending pane. List replaced keys on the member's key page and on Tools → User Access. Each entry offers the existing retirement action.
- Verification approach: a schema regression test from the prior version. Template tests. Check the pill in every theme, including Word97 and Midnight, whose chips are flat.
- Estimate details: driver: the schema bump and theme checks; uncertainty: low.
- Risks or open questions: none beyond the schema rules in `CONTRIBUTING.md`.
- Canonical components/API contracts touched: `chip` with `chip--pending`, `TemplateRenderer.php`, `public_key.php`, `profile_access.php`, `user_access.php`, `paned_user_pending_detail_pane.php`, `key_retirement.php`.

## Stage 6

**Estimate: 0.04**

- Goal: docs and disclosure match the code.
- Dependencies: Stages 1–5.
- Expected changes: update the viewer session and approval acceptance concepts. Add a Replaced key concept entry. Update `authentication_modes.md`. Draft the one-line disclosure for the anti-cookie notice thread; post it at release with Ilya's go-ahead.
- Verification approach: link checks and `git diff --check`. Final diff review for duplication and scope.
- Estimate details: driver: small; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `docs/concepts/`, `docs/reference/authentication_modes.md`.

## Stage 7

**Estimate: 0.05**

- Goal: prove the fix where the failure happens.
- Dependencies: Stages 1–6 deployed, with the flag on.
- Expected changes: none to code. Record the replay in the Step 4 summary.
- Verification approach: on a real iPhone in Safari, remove the stored key and keep the cookie. Expect one notice, an approved key and a post that shows as approved. Reload and expect no second key. Desktop Safari or a simulator does not count.
- Estimate details: driver: Ilya's time on the device; uncertainty: how to remove only the stored key on iOS.
- Risks or open questions: use Account → Key → "Remove this key", which clears the stored key and keeps the session. Stage 7 first confirms it leaves the cookie in place. If it does not, the replay waits for a real seven-day wipe.
- Canonical components/API contracts touched: none.

## Concepts touched

- [Viewer session](../../concepts/viewer_session.md): changed in Stage 1.
- [Approval acceptance](../../concepts/approval_acceptance.md): changed in Stage 2.
- [Key retirement](../../concepts/key_retirement.md): used unchanged in Stage 5.
- Replaced key: created in Stage 6.
- Feature flags: changed in Stage 1.

## Study history

- Step 3 snapshot, 2026-11-16 UTC: **0.80**. Scope v2, unchanged since Step 2. Rises from 0.70 because of the quiet re-sign and the read-model schema bump. Reference R1, protocol P2, starting revision `9bdd4b3`. R1 hashes not re-verified. Single exposed rater: Claude. Stage values are stage work only, not parts of the cycle total.

*— Claude · 2026-11-16 UTC · Model: claude-opus-5-5*
