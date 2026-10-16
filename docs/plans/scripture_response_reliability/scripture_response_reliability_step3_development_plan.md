> **Feature plan:** [Step 1](./scripture_response_reliability_step1_solution_assessment.md) · [Step 2](./scripture_response_reliability_step2_feature_description.md) · [Step 3](./scripture_response_reliability_step3_development_plan.md) · [Step 4](./scripture_response_reliability_step4_implementation_summary.md)

Debug mode: enabled

# Scripture Response Reliability — Development Plan

## Completion Contract

- Existing scripture request and Expand controls deliver complete, relevant replies with verified KJV quotations, explicit translation/source limitations, and explicit retry after failure. Preserve permissions, moderation, deduplication, historical replies, and unrelated modes; no schema change or remote deployment.
- Release only after every quality, quotation, recovery, and latency gate below passes through isolated normal HTTP/worker/browser delivery. Otherwise report failed gates and return for reassessment without promoting the candidate. Approval authorizes implementation and the bounded trials below, not weaker gates or a separate editor.
- Organize and commit approved plans on a feature branch first; commit each stage with its summary before the next. Seven stages target at most one hour each; split unexpectedly larger work before proceeding. Document local source installation, worker setup, activation, and rollback.

## Key Risks

- **High risk: false verification.** Import annotations, omitted words, wrong references, or quotations embedded in prose can evade checks. Validate imported passages independently and exercise adversarial output before publication wiring; reject ambiguous/unsupported quotation forms rather than repair claims silently.
- **High risk: unusable recovery/rollback.** Failure or old stored drafts could bypass safeguards or strand requests. Test publication boundaries and restart/retry early; preserve stored history but revalidate unpublished drafts. Rollback disables new verified requests rather than silently reverting their assurance.
- **Judgment, latency, and cost:** Mini and local verification remain unproven together. Freeze candidates, cases, review, timing, and budget first; no tuning on held-out outputs, paid judges, automatic regeneration, or omitted failed samples.

## Frozen Source and Evaluation

- Source: eBible.org's [1769 KJV package and usage notes](https://ebible.org/bible/details.php?id=eng-kjv), [USFM archive](https://ebible.org/Scriptures/eng-kjv_usfm.zip), retrieved 2026-10-16; archive SHA-256 `05b021ab465bd283e7df02409c78436487d582276b3a0b60d6fcf5d967a0af14`. Download and chapter/verse markers checked during planning. Pin an attributed local copy, source notices (including the publisher's UK printing qualification), import version, and full chapter context. No per-request network retrieval. Unsupported books/editions/translations get explicit limitations; supplied text is not verification. Stage 1 must resolve exact supported-book coverage and import fidelity before dependent work.
- A: `gpt-5-mini`, current scripture guidance, low reasoning, 8192 completion tokens, existing unverified path in isolated baseline only. B: same model/reasoning/allowance, concern-centered guidance plus local verification. Both use existing dispatch/status delivery. Freeze payloads, prompt/source/code hashes before paid calls; no substitutions. Compare the combined change, without claiming separate causal attribution to guidance and verification.
- Eight questions, twice per candidate: (1) exact departure-time post from Step 1; (2) original overloaded-day question from the predecessor fixture; (3) “My volunteer shift ends at five, but cleanup remains. I have childcare pickup at five thirty. What should I do?”; (4) “My sibling needs money, but lending it would leave my rent unpaid. How should I respond?”; (5) “Does turning the other cheek mean remaining in an abusive relationship? Explain the passage and differing Christian interpretations.”; (6) “After losing my job, I feel ashamed asking my church for help. What might scripture offer?”; (7) “Explain James 2 and Romans 4 on faith and works in depth, including differing Christian readings.”; (8) “Use NIV. Is ‘Money is the root of all evil’ an exact biblical quotation? What does the passage mean?” Expand cases 1, 5, 8 twice per candidate. Keep cases 3–8 out of guidance/examples. Preserve the late-start case and all prior quotation failures as additional stored/stub regressions, with no extra paid calls.
- Review final stored/rendered answers, not raw provider content, in randomized packets without candidate/timing labels; save ratings before revealing labels. Record unavoidable blinding limitations. Every rubric item must pass: direct useful answer, proportionate nonrepetition, accurate quotation/translation, faithful contextual application, complete initial/additive expansion, and honest interaction promises. Independently inspect cited passage context; any fabricated/misattributed quotation or unsupported scriptural promise blocks B.
- B must pass ≥15/16 initial answers, all eight everyday runs (cases 1–4), all six expansions, and every quotation check. Require at least four everyday improvements over A, with no loss of complex-case completeness. Initial and expansion groups separately need median submission-to-rendered-completion ≤20 seconds and ≥30% below matched A, with no sampled completion >40 seconds. Failed/interrupted samples remain in denominators and reports; insufficient completions cannot qualify.
- Budget: **44 planned writing calls** (32 initial, 12 expansion); **48 maximum writing calls**, **96 supporting moderation calls**, **$5 total provider spend**, stopping at the first ceiling. Four spare writing calls cover only explicitly documented replacement attempts after operational failure, retaining originals; never replace a quality failure. Every retry counts. Reserve conservative cost before dispatch using then-verified official rates; keep uncertain charges reserved. No automatic retry or renewed predecessor budget. Run B expansions only if its initial gates pass; stop and reassess on failure or insufficient funds.

## Stage 1

- Goal: Resolve source and measurement risks without paid calls.
- Dependencies: Step 3 approval and planning commit.
- Expected changes: Pinned local source/import manifest, supported-book inventory, fixed evaluation manifest and ledger configuration.
- Verification approach: Check full chapter availability, known passages, annotations/footnotes, verse boundaries, and source hash; reject corrupt/missing packages. Stub budget limits, isolation, complete timing capture, and anonymous final-answer packets.
- Risks or open questions: Import or measurement uncertainty blocks dependents; no silently updated source or secret-bearing artifacts.
- Canonical components/API contracts touched: Local scripture source adapter, existing evaluation/proxy/review helpers and browser fixture.

## Stage 2

- Goal: Make verified initial replies usable through normal delivery.
- Dependencies: Stage 1 source checks.
- Expected changes: Shared quotation contract and verification, concern-centered guidance, opt-in candidate setting, initial request-to-publication wiring. Keep one writer call; no editorial generation pass.
- Verification approach: Stub normal browser request; verify references, translation labels, exact excerpts and ordered omissions against source; reject blended wording, hidden/unmarked quotation forms, and malformed output before publication. Check normalized final text too.
- Risks or open questions: Quotation detection cannot rely solely on quotation marks or writer assertions; unresolved bypasses block publication and later stages.
- Canonical components/API contracts touched: Generator/settings/prompts, shared verification, fulfillment/publication and request status.

## Stage 3

- Goal: Complete translation and failure recovery for initial replies.
- Dependencies: Stage 2.
- Expected changes: Explicit unsupported-translation/source limitation, safe paraphrase path, terminal failure and manual retry; revalidation of recoverable unpublished output.
- Verification approach: Unavailable/corrupt source, inaccurate supplied text, unsupported NIV, mismatch, exhaustion, restart, duplicates, stale source, access revocation, and moderation tests through normal requests.
- Risks or open questions: A limitation must still accompany a complete supported answer; otherwise fail without partial publication. No silent translation substitution or claim-changing repair.
- Canonical components/API contracts touched: Shared verifier, queue recovery, fulfillment and browser status/error rendering.

## Stage 4

- Goal: Extend the same safeguards through Expand and stored-result surfaces.
- Dependencies: Stage 3.
- Expected changes: Expansion guidance and shared verification/recovery wiring; preserve initial-answer attribution and additive content.
- Verification approach: Normal Expand, stale/legacy initial reply, duplicates, permissions, recovery, and final normalized rendering; check cached/offline views and profile/RSS exclusions with stored fixtures.
- Risks or open questions: Historical quotation errors must not be copied into new expansions; independently validate new content without rewriting history.
- Canonical components/API contracts touched: ScriptureExpansionService, generator, shared expansion partial, canonical post cards and snapshot rendering.

## Stage 5

- Goal: Freeze and prove the complete trial protocol before spending.
- Dependencies: Stages 1–4.
- Expected changes: Candidate/source/code manifest, final-output review export, stage timing and cost evidence; rollout/rollback instructions.
- Verification approach: Stub complete initial/Expand browser trials, including failures, anonymous packet content, cost reservation and hard stops; check A/B use identical delivery and trial conditions.
- Risks or open questions: Existing proxy hardcodes nano pricing/model and review reads raw output; both must be corrected and verified before paid trials.
- Canonical components/API contracts touched: Existing evaluation harness/proxy, browser timing, exchange diagnostics and operator documentation.

## Stage 6

- Goal: Establish whether B qualifies for expansion evaluation.
- Dependencies: Stage 5 protocol passes and candidates frozen.
- Expected changes: 32 interleaved initial trials and source-checked anonymous ratings; retain every attempted call, failure, timing and charge.
- Verification approach: Apply unchanged initial quality and latency gates to final answers, including departure regression and fresh cases.
- Risks or open questions: Failed gates block paid expansion trials and promotion; report results rather than tune or renew budget.
- Canonical components/API contracts touched: Isolated request/worker/publication/browser flow and evaluation report.

## Stage 7

- Goal: Verify expansions and deliver the releasable slice.
- Dependencies: Stage 6 qualifying B and sufficient remaining budget.
- Expected changes: 12 matched expansion trials; promote identical tested artifacts only if all gates pass; finish source/setup/recovery/rollback notes.
- Verification approach: All expansion gates; focused stub integration, other-mode isolation, rendering, restart/retry and rollback checks. No extra paid smoke calls or untested prompt edits.
- Risks or open questions: Failure leaves the candidate unpromoted and requires reassessment; a failed experiment is not feature completion.
- Canonical components/API contracts touched: Shared scripture settings/lifecycle, existing result surfaces and operator handoff.
