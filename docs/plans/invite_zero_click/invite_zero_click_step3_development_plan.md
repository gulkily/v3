> **Feature plan:** [Step 1](./invite_zero_click_step1_solution_assessment.md) · [Step 2](./invite_zero_click_step2_feature_description.md) · [Step 3](./invite_zero_click_step3_development_plan.md) · [Step 4](./invite_zero_click_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

Application change: normal Invite button → unchecked destination opt-in, source-page prefill → generated link/QR → automatic targeted redemption → authenticated target, on public and members-only sites. Preserve existing keys; offer optional Account naming afterward. Include same-recipient retry, invalid/used invites, storage/network errors, unavailable targets, and untargeted compatibility. Verify using isolated local repositories and real browsers; release requires focused regressions, rebuild, and the complete browser matrix. No production deployment is authorized; record any external deployment check as outstanding. Each stage targets ≤1 hour; stop and replan if eight stages cannot deliver this contract.

## Key Risks

- **High risk: identity continuity/data.** Bootstrap publication rejects existing fingerprints and profiles derive names from bootstrap records. Add signed name updates without rewriting bootstrap bytes or keys; prove full/incremental projection parity before UI depends on them.
- **High risk: consumption/recovery.** Redemption rejects approved identities and all used tokens. Validate bearer status without mutation; require cryptographic ownership for same-recipient recovery, recheck under the write lock, and preserve single-use semantics.
- **High risk: navigation/usability.** Source capture drops query/fragment; source is only a suggestion; authentication competes with redemption. Validate destination handling and auth ordering before enabling automatic entry. Never navigate off-site or bypass unrelated permissions.

## Stage 1 — Opt-in destination
- Goal: Source-page URL → editable opt-in target.
- Dependencies: Planning commit on Step 4 branch.
- Expected changes: Capture path/query/fragment; prefill once, preserve edits; unchecked omits target.
- Verification approach: Issuance/navigation tests cover toggles, unsafe URLs, and link/QR output.
- Risks or open questions: Fragment rejection currently blocks exact targets; align validation first.
- Canonical components/API contracts touched: Invite navigation/form, issuance, destination parser/writer.

## Stage 2 — Signed naming
- Goal: Change names while preserving keys.
- Dependencies: Stage 1.
- Expected changes: Signed name-update records; prepareProfileName(array $input): array and finalizePreparedProfileName(array $input): array contracts.
- Verification approach: Reject forged/mismatched signatures and invalid names; preserve bootstrap bytes.
- Risks or open questions: Validate existing username/collision rules before defining name-update semantics.
- Canonical components/API contracts touched: Canonical records, LocalWriteService, identity API/routes/allowlists.

## Stage 3 — Naming projections
- Goal: Display names consistently after rebuild.
- Dependencies: Stage 2 verified.
- Expected changes: Deterministic signed-update replay into existing name/route/author fields; invalidation, schema version/reference updates per CONTRIBUTING.
- Verification approach: Full/incremental parity; prior-version startup; unchanged approval/ownership.
- Risks or open questions: Rebuild could restore placeholders; prove ordering and replay before UI wiring.
- Canonical components/API contracts touched: ReadModelBuilder/metadata, ProfileRepository, signature validation, identity caches.

## Stage 4 — Identity and Account
- Goal: Silent temporary naming; optional later naming.
- Dependencies: Stage 3 verified.
- Expected changes: Extend identity options for noninteractive names; preserve existing keys/names; wire signed Account rename and browser-label synchronization.
- Verification approach: No fresh-key dialogs; rename survives reload/rebuild with same fingerprint/approval; failure preserves state.
- Risks or open questions: Local/canonical names can diverge; reconcile verified server state without key replacement.
- Canonical components/API contracts touched: browser_signing, Account template/actions, toolbar, naming APIs.

## Stage 5 — Inspection and recovery
- Goal: Identify targets and resume redemption safely.
- Dependencies: Stage 4.
- Expected changes: No-store nonmutating bearer inspection; same-signer prepare/finalize recovery; valid already-approved entry without duplicate consumption/approval.
- Verification approach: Lost-response retry, concurrent recipients, invalid/expired/revoked/used tokens, approved recipients, forged hints.
- Risks or open questions: Existing code rejects approved/redeemed identities; require ownership proof and lock-protected rechecks before recovery.
- Canonical components/API contracts touched: Invitation API/ledger/writer, signature contracts, private-site allowlists.

## Stage 6 — Automatic arrival
- Goal: Open targeted resources without interaction.
- Dependencies: Stages 1, 4, 5 verified.
- Expected changes: Inspect before key creation; serialize redemption/authentication; prioritize target after verified access; retain manual untargeted redemption and actionable recovery.
- Verification approach: Public/private exact targets; fresh/existing identities; storage/network failure, missing targets, retries without loops.
- Risks or open questions: Auth races lose destinations; retain pending state until verified arrival, then clear stale state.
- Canonical components/API contracts touched: Lobby, invite_redemption, PrivateSiteAuth, destination/missing-resource handling.

## Stage 7 — Acceptance
- Goal: Verify normal-flow release readiness.
- Dependencies: Stage 6.
- Expected changes: Focused browser acceptance coverage, operator notes, implementation-summary evidence.
- Verification approach: UI-generated links/QR; public/private × fresh/pending/approved; source opt-in, exact targets, naming, recovery, untargeted flow; affected PHP/JS suites and isolated ./v3 rebuild.
- Risks or open questions: Compare unrelated baseline failures; block release on feature failures. Roll back code without deleting keys/records; document older-version naming limits.
- Canonical components/API contracts touched: Test harness/browser fixtures, operator docs, Step 4 summary.

