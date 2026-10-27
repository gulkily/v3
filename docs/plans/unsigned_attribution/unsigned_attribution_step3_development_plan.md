> **Feature plan:** [Step 1](./unsigned_attribution_step1_solution_assessment.md) · [Step 2](./unsigned_attribution_step2_feature_description.md) · [Step 3](./unsigned_attribution_step3_development_plan.md) · [Step 4](./unsigned_attribution_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/unsigned_attribution · [Host ledger](../effort_estimation_trial.md) · Standing enrollment P2

## Revision note

Revision 2 follows Step 2 revision 3 (scope v6): the rule ships behind a default-off verified attribution flag with an automatically written cutoff, and the accept-unsigned-content flag moves to its own cycle. Stage 1 is unchanged and already committed (`e061eea9`); later stages are renumbered.

Revision 3 (scope v7, 2026-10-26) follows an enhancement request at Stage 8: with the flag on, every full rebuild and many writes rerun GPG for each signed item after the cutoff (74 s on the zenmemes copy with an August cutoff, growing each month). Stage 8 now also saves signature check results so each record is checked once. Stages 1–7 are unchanged; the Stage 8 evidence already committed (`f5d485bd`) stays and is rerun after the change.

## Completion Contract

- Normal entry: board, thread, Outbox and API posting and voting, as today; operators turn the flag on from the Feature Flags page.
- End-to-end outcome: with the flag off, nothing changes. With it on, unsigned items naming someone created after the cutoff are accepted, shown as unverified and change no score, post count or profile; items before the cutoff, signed and anonymous items behave as before.
- Recovery: unverified writers are told how to sign; turning the flag off restores today's counts; moving the cutoff and startup after upgrade bring the read model to the same result as a full rebuild.
- Deployment/external: zenmemes before/after counts from a read-only copy of its published repository backup; nothing is written to zenmemes.
- Release condition: counts reviewed by the maintainer; docs updated; focused, browser and full-suite results recorded, with the 9 known `main` failures listed separately.

## Key Risks

- **High risk:** counts change when an operator turns the flag on. Impact: profile, post-count and ranking shifts. Early validation: zenmemes counts with the flag off, and on with a cutoff of now, equal the Stage 1 before-counts. Mitigation: the cutoff is written in the same request that first turns the flag on.
- **High risk:** rebuild, incremental update, flag toggles and cutoff moves disagree. Impact: scores differ depending on how the read model got there. Early validation: parity tests in Stages 3–4. Mitigation: the read model keeps both today's totals and verified totals, computed by one shared rule; the flag only chooses which to show, and a cutoff change recomputes verified totals automatically.
- **Medium risk:** signature checks grow with signed activity after the cutoff. Impact: rebuilds and cutoff moves slow by roughly 110 ms per signed record (about 15–40 s per month on zenmemes), and votes re-check their thread's signed votes. Early validation: Stage 8 measurement on the zenmemes copy. Mitigation: a persistent cache of check results (Stage 8).
- The reply agent's replies turn unverified once an instance turns the flag on, unless Stage 7 lands. Mitigation: Stage 7 ships in the same release.

## Stage 1 (done, `e061eea9`)
- Goal: measure the impact before changing anything.
- Dependencies: none.
- Expected changes: `scripts/audit_post_signatures.php` also counts thread labels and post reactions (signed, unsigned naming an identity, anonymous) and scored votes that would stop counting.
- Verification approach: fixture repository test; zenmemes backup copy; counts in the Step 4 summary.
- Risks or open questions: reviewed at the gate; led to the cutoff and default-off flag.
- Canonical components/API contracts touched: post signature audit command.

## Stage 2
- Goal: the flag and its cutoff.
- Dependencies: Stage 1 reviewed.
- Expected changes: `verified_attribution` flag (default off, site-mutable) in the feature flag registry. The first switch-on also writes `records/instance/attribution-cutoff.txt` (server `Created-At`, operator identity); later toggles keep it. The Feature Flags page shows the cutoff beside the flag and lets an operator move it, which writes a new version. Parser and Activity labelling follow the existing instance records.
- Verification approach: tests for first switch-on, off/on keeping the cutoff, moving it, and non-operators being refused; page check in Light, Word97, Chicago, Midnight.
- Risks or open questions:
  - Impact: two operators switching on at once write two cutoffs.
  - Early warning / validation: test concurrent first writes.
  - Mitigation: the earliest cutoff in the record history wins unless moved explicitly.
- Canonical components/API contracts touched: feature flags registry, record and page; instance records.

## Stage 3
- Goal: votes have a verified total beside today's.
- Dependencies: Stage 2.
- Expected changes: builder and incremental updater compute verified score totals from the existing reaction signature status and the vote's server `Created-At`: a vote counts if it is signed, or unsigned and created before the cutoff (or no cutoff exists yet). Today's totals are unchanged. Score displays and reaction responses use verified totals when the flag is on. A cutoff record change recomputes verified totals. Schema version bump for the cycle, `read_model_schema_v1.md`, prior-version startup test.
- Verification approach: rebuild/incremental/toggle/cutoff-move parity tests; signed, unsigned before and after cutoff, anonymous; client `action_at` ignored; CONTRIBUTING schema checklist; `./v3 rebuild`.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: `ReactionSignatureIndex`, thread-label and post-reaction scoring.

## Stage 4
- Goal: posts link to profiles and count by the same rule.
- Dependencies: Stage 3.
- Expected changes: post signature status from the existing detached-signature check (`ReactionSignature::status(root, path, contents, author)`) in a new posts column; verified post counts beside today's; with the flag on, profile linking and post counts use the verified rule. Labels in an unverified post's body count as unverified votes. Same schema version as Stage 3.
- Verification approach: parity tests as in Stage 3; schema documentation updated.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: posts read model, profile linking.

## Stage 5
- Goal: with the flag on, show unverified items as unverified wherever the author appears.
- Dependencies: Stage 4.
- Expected changes: post, thread, quote and board cards, Activity and record details show the claimed name with the existing "unverified" status, unlinked, for items after the cutoff. No new copy.
- Verification approach: flag off and on; phone and desktop in Light, Word97, Chicago, Midnight; UI copy checklist entry.
- Risks or open questions: none.
- Canonical components/API contracts touched: author display partials, source metadata.

## Stage 6
- Goal: with the flag on, tell unverified writers what happened.
- Dependencies: Stage 4.
- Expected changes: post and tag responses add an unverified notice naming prepare and create; `/api/` catalog, `/llms.txt`, `agent_api.md` describe the flag and contract.
- Verification approach: API smoke tests for text and JSON responses, flag off and on.
- Risks or open questions: none.
- Canonical components/API contracts touched: create and tag routes, route catalog.

## Stage 7
- Goal: the reply agent signs its replies (regardless of the flag).
- Dependencies: none beyond Stage 1; lands before release.
- Expected changes: agent prepares its reply, signs it with its private key via `gpg`, and writes it through the signed path.
- Verification approach: agent reply test shows a verified, profile-linked reply.
- Risks or open questions:
  - Impact: unsigned agent replies if the key is missing.
  - Early warning / validation: test with the worker's private key path.
  - Mitigation: a failed signature fails the task, which can be retried; the agent never posts unsigned.
- Canonical components/API contracts touched: agent reply fulfillment, signed post path.

## Stage 8
- Goal: signature checks run once per record, then acceptance and release evidence.
- Dependencies: Stages 1–7.
- Expected changes: `ReactionSignature::status` keeps check results in a SQLite file beside the read model (`signature_checks.sqlite3`), keyed by the hash it already uses in memory (record, signature, public key, fingerprint). Only finished checks are stored; timeouts and limit errors are not. A missing or unwritable file falls back to running GPG. The web app, CLI rebuild and write path set the file location where they resolve the read-model path; callers that do not keep memory-only behavior. Otherwise none beyond fixes; after-counts recorded.
- Verification approach: two rebuilds in a row, the second without GPG runs; a changed signature misses; missing or unwritable cache falls back; existing parity tests unchanged; full suite against `main`'s failures; zenmemes counts with the flag off and on (cutoff now) equal the before-counts, and rebuild time with an August cutoff before and after the cache; prior-schema startup.
- Risks or open questions: the cache is derived server state at the read model's trust level; deleting it is always safe.
- Canonical components/API contracts touched: `ReactionSignature::status` (shared signature checks); no record format or read-model schema change.

## Effort snapshot


- Step 3 handoff — 2026-10-23T14:42Z; study `railspike-effort`, cycle `v3/unsigned_attribution`, scope v4, R1/P2; starting revision `9f33caff` (mapped `764fdd82`), planned at `6f8e4189`. Claude (claude-opus-5-5), single exposed rater. **About 0.95 total projected**, up from about 0.90: the reply agent's signing path and rebuild/incremental parity add verification work. New understanding, not a scope change. Approval context: Approved Step 2 (revision 1).
- Step 3 revision 2 — 2026-10-23T15:35Z; same study, cycle, R1/P2 and rater; scope v6. **About 1.0 total projected**, reaffirming Step 2 revision 3: keeping both totals avoids rebuilds on toggle but adds parity cases. Approval context: Approved Step 2 (revision 3).
- Step 3 revision 3 — 2026-10-26; same study, cycle, R1/P2 and exposed single rater; scope v7 (signature check cache added to Stage 8). **About 1.1 total projected**, up from about 1.0 (scope change): one shared cache, its wiring into three entry points and the timing evidence. Earlier snapshots and the technical-closeout retrospective (about 1.05, v6) are preserved. Approval context: awaiting Approved Step 3 (revision 3).
