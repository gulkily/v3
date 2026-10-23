> **Feature plan:** [Step 1](./unsigned_attribution_step1_solution_assessment.md) · [Step 2](./unsigned_attribution_step2_feature_description.md) · [Step 3](./unsigned_attribution_step3_development_plan.md) · [Step 4](./unsigned_attribution_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/unsigned_attribution · [Host ledger](../effort_estimation_trial.md) · Standing enrollment P2

## Completion Contract

- Normal entry: board, thread, Outbox and API posting and voting, as today.
- End-to-end outcome: unsigned items naming someone are accepted, shown as unverified and change no score, post count or profile; signed and anonymous items behave as before.
- Recovery: unverified and refused writers are told how to sign and keep their text; startup after upgrade rebuilds the read model to the same result.
- Deployment/external: zenmemes before/after counts from a read-only copy of its published repository backup; nothing is written to zenmemes.
- Release condition: counts reviewed by the maintainer; docs updated; focused, browser and full-suite results recorded, with the 9 known `main` failures listed separately.

## Key Risks

- **High risk:** members' past unsigned items become unverified. Impact: profile, post-count and ranking shifts. Early validation: Stage 1 counts. Mitigation: maintainer reviews counts before Stage 2; the claims follow-up restores them.
- **High risk:** rebuild and incremental update disagree. Impact: scores differ after a write versus after a rebuild. Early validation: parity tests in Stages 2–3. Mitigation: one shared verified rule used by both paths.
- The reply agent's replies turn unverified unless Stage 6 lands with Stage 3. Mitigation: both ship in the same release.

## Stage 1
- Goal: measure the impact before changing anything.
- Dependencies: none.
- Expected changes: extend `scripts/audit_post_signatures.php` to also count thread labels and post reactions (signed, unsigned naming an identity, anonymous) and approved-score votes that would stop counting.
- Verification approach: fixture repository test; run against the zenmemes backup copy; record counts in the Step 4 summary.
- Risks or open questions:
  - Impact: unexpectedly large shifts.
  - Early warning / validation: counts.
  - Mitigation: maintainer review gate before Stage 2.
- Canonical components/API contracts touched: post signature audit command.

## Stage 2
- Goal: votes count toward the approved score and profiles only when signed.
- Dependencies: Stage 1 reviewed.
- Expected changes: builder and incremental updater read the existing reaction signature status; unverified votes stay in the visible tally only.
- Verification approach: rebuild/incremental parity tests; signed, unsigned and anonymous vote cases.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: `ReactionSignatureIndex`, thread-label and post-reaction scoring.

## Stage 3
- Goal: posts link to profiles and count only when signed.
- Dependencies: Stage 2.
- Expected changes: post signature status derived with the existing detached-signature check (`ReactionSignature::status(root, path, contents, author)`); new posts column for it; profile linking and post counts use it. Schema version bump, `read_model_schema_v1.md`, prior-version startup test.
- Verification approach: CONTRIBUTING schema checklist; `./v3 rebuild`; parity tests.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: posts read model, profile linking.

## Stage 4
- Goal: show unverified items as unverified wherever the author appears.
- Dependencies: Stage 3.
- Expected changes: post, thread, quote and board cards, Activity and record details show the claimed name with the existing "unverified" status, unlinked. No new copy.
- Verification approach: phone and desktop in Light, Word97, Chicago, Midnight; UI copy checklist entry.
- Risks or open questions: none.
- Canonical components/API contracts touched: author display partials, source metadata.

## Stage 5
- Goal: tell unverified writers what happened.
- Dependencies: Stage 3.
- Expected changes: post and tag responses add an unverified notice naming prepare and create; `/api/` catalog, `/llms.txt`, `agent_api.md` updated.
- Verification approach: API smoke tests for text and JSON responses.
- Risks or open questions: none.
- Canonical components/API contracts touched: create and tag routes, route catalog.

## Stage 6
- Goal: the reply agent signs its replies.
- Dependencies: Stage 3.
- Expected changes: agent prepares its reply, signs it with its private key via `gpg`, and writes it through the signed path.
- Verification approach: agent reply test shows a verified, profile-linked reply.
- Risks or open questions:
  - Impact: unsigned agent replies if the key is missing.
  - Early warning / validation: test with the worker's private key path.
  - Mitigation: a failed signature fails the task, which can be retried; the agent never posts unsigned.
- Canonical components/API contracts touched: agent reply fulfillment, signed post path.

## Stage 7
- Goal: instances can refuse unsigned content.
- Dependencies: Stage 5.
- Expected changes: `accept_unsigned_content` flag (default on) in the feature flag registry; post, compose and tag routes refuse unsigned writes when off; anonymous controls hidden; compose and Outbox keep text.
- Verification approach: on/off tests per route; browser check with JavaScript on and off.
- Risks or open questions: none beyond Step 2's flag-off risk.
- Canonical components/API contracts touched: feature flags record and page, write routes.

## Stage 8
- Goal: acceptance and release evidence.
- Dependencies: Stages 1–7.
- Expected changes: none beyond fixes; after-counts recorded.
- Verification approach: full suite against the known 9 failures; zenmemes after-counts; prior-schema startup.
- Risks or open questions: none.
- Canonical components/API contracts touched: none new.

## Effort snapshot

- Step 3 handoff — 2026-10-23T14:42Z; study `railspike-effort`, cycle `v3/unsigned_attribution`, scope v4, R1/P2; starting revision `9f33caff` (mapped `764fdd82`), planned at `6f8e4189`. Claude (claude-opus-5-5), single exposed rater. **About 0.95 total projected**, up from about 0.90: the reply agent's signing path and rebuild/incremental parity add verification work. New understanding, not a scope change. Approval context: Approved Step 2 (revision 1).
