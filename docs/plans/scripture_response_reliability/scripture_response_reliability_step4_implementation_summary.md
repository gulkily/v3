> **Feature plan:** [Step 1](./scripture_response_reliability_step1_solution_assessment.md) · [Step 2](./scripture_response_reliability_step2_feature_description.md) · [Step 3](./scripture_response_reliability_step3_development_plan.md) · [Step 4](./scripture_response_reliability_step4_implementation_summary.md)

Debug mode: enabled

# Scripture Response Reliability — Implementation Summary

Status: Stages 1–6 completed; Stage 7 blocked by failed initial release gates. Candidate not activated. The releasable feature contract is not achieved.

## Stage 1 - Pinned source and protocol

- Changes: Imported the pinned 1769 KJV archive with original attribution/notice, explicit 66-book inventory, 1,189 chapters and 31,102 verses. Added digest-checked local passage/chapter access and reproducible fail-closed import. Recorded exact evaluation cases, candidates, gates, and 48/96/$5 ceilings. Added final-published-answer review mode.
- Verification: `php tests/run.php ScriptureSourceTest` (2 passed); `python -m unittest discover -s tests/Support -p test_scripture_evaluation.py` (7 passed). Source tests cover known passages, retained spaces around nested markup, chapter context, unsupported references, corrupt/missing corpus. Review test distinguishes published output from raw provider output; ledger tests preserve unknown charges and hard stops. `git diff --check` passed.
- Notes: Imported text excludes footnotes, lexical markup, nonverse headings and paragraph markers; preserves words/punctuation. Other books/translations remain unsupported. No request-time download, paid call, runtime activation, migration, or deployment. Full normal-flow measurement rehearsal remains Stage 5 after publication wiring.

## Stage 2 - Verified initial replies

- Changes: Added opt-in `SCRIPTURE_RESPONSE_GUIDANCE=verified`, concern-centered guidance and a strict internal reflection/quotation document. Quotations are inserted from source-owned references, with optional exact ordered excerpts and visible omission marks. Reject unknown fields, unsupported translations/references, mismatches, quotation markup/direct-speech channels, and incomplete documents; retain the document/source digest with the generated record. Existing modes and default configuration remain unchanged.
- Verification: `php tests/run.php ScriptureVerifiedReplyTest WriteApiSmokeTest::testVerifiedScriptureInitialPublishesThroughNormalRequest AgentResponseGeneratorTest` (13 passed). Includes real request/fulfillment/status/thread rendering with stub generation, excerpt mismatch/order/word-boundary tests, explicit unmarked direct-speech and markup attacks, and existing mode/provider routing.
- Notes: One writer call; no automatic editorial call or quote repair. Source typography is normalized using the existing display policy; any further normalization of the final answer fails closed. Distinguishing arbitrary deceptive prose from interpretation is not a semantic proof; final contextual review remains a release gate. Stored-draft revalidation and translation/source recovery follow in Stage 3.

## Stage 3 - Translation limitations and explicit recovery

- Changes: Added configurable local source path with mandatory digest validation; unavailable sources and unsupported requested translations receive application-owned limitation text and permit complete paraphrase-only answers. Quotations in those states fail rather than substitute. Added stable verification-failure feedback, persisted-document revalidation before publication, old-draft rejection and explicit retry. Recheck source visibility/hash, feature permission, and requester approval under the existing write lock.
- Verification: Focused generator/source/verification, private-config and normal API checks: 28 passed. Includes unavailable source and NIV recovery without KJV substitution, tampered/legacy draft rejection with no publication, repeated failure without regeneration, explicit retry and duplicate prevention, and revoked requester at publication. Existing completion-exhaustion checks remain passing.
- Notes: No automatic paid retry or claim-changing quotation repair. Known translation identifiers and explicit translation/version requests are handled conservatively; unsupported translation references do not become verified quotations. Already published history is preserved. A changed source/document state requires explicit retry rather than silently re-rendering a different answer.

## Stage 4 - Shared expansion safeguards and stored surfaces

- Changes: Reused the verified quotation contract and requested-translation/source limitation handling for Expand; added additive expansion guidance with independent verification of new content. Revalidate recovered unpublished expansion documents before saving/writing and under the existing permission/source callback. Preserve already published canonical expansions and legacy stored-output recovery outside verified mode.
- Verification: Focused expansion/generator/verification suite (18 passed), then affected recovery/race tests after the final compatibility adjustment (2 passed). `node tests/browser/scripture_expansion.cjs` passed thread/post desktop/mobile, shared result, safe text, keyboard, offline snapshot, legacy schema and source revocation checks. Verified-mode API test checks old-draft rejection, explicit retry, final quotation text, thread rendering and identical offline stored content.
- Notes: No separate presentation component or schema change. Historic initial replies remain unchanged and are explicitly untrusted context for new quotations. Full verified-mode HTTP/browser/worker trial rehearsal is Stage 5; browser presentation checks here use the existing shared-result fixture.

## Stage 5 - Frozen normal-flow trial protocol

- Changes: Added two-candidate reliability runner, shared mini-aware budget proxy (48 writing/96 moderation/$5), conservative current-rate reservations, durable dispatch accounting, candidate/source hashes, matching automatic dispatch/polling, final stored-versus-rendered answer checks, anonymous final-answer/context packets, and executable joint initial gates with evidence hashes. Added verification phase timing and operator installation/recovery/rollback notes. Existing predecessor runner remains available with its original defaults.
- Verification: Focused PHP/API suite: 191 passed; final generator check after translation-alias adjustment: 11 passed. Python evaluation/proxy/review/gate tests: 9 passed. Final recovery rehearsal at `state/private/scripture-reliability-recovery-rehearsal-20261016/` completed six stub initial/expansion trials (including unsupported NIV) and one deliberately failed malformed-output trial through real HTTP, detached workers, publication and browser discovery. Stored and rendered final answers agreed; both variants captured queue/generation/publication markers and B also captured verification markers. No external provider calls in rehearsals. `git diff --check` passed.
- Notes: Official mini price checked 2026-10-16: $0.25/M uncached input and $2/M output; ignore caching for conservative accounting. Official model page marks the selected model deprecated; retain any provider rejection without substitution. Style can reveal candidate identity despite hidden labels/timing; record this limitation in the final review. No candidate activation or paid trial before this stage commit.

## Stage 6 - Initial comparison and failed release gates

- Changes: Ran all 32 frozen initial trials, saved anonymous final-answer/source/context ratings before label reveal, and published the [evaluation report](./evaluation/report.md) and per-answer result hashes. Corrected browser layout-whitespace comparison and added controlled resumption without regenerating the original sample; original measurement-error evidence remains retained. New test distinguishes harmless whitespace collapse from changed words/punctuation.
- Verification: 808 frozen files unchanged; 32 writing calls, zero supporting moderation calls, $0.069561 conservative cost, no unsettled charges or replacement calls. Python evaluation tests: 10 passed. A: 16/16 completed, 1/16 quality passes. B: 10/16 completed, 7/16 quality passes, 6/8 everyday passes, zero published critical quotation errors. Completed-sample medians 13.22 s versus 9.25 s; incomplete B delivery blocks full latency acceptance. All eleven published B quotation blocks passed source checking. `git diff --check` passed.
- Notes: A post-trial isolation correction preserves generic JSON failure classification outside scripture; 12 focused generator tests passed, without prompt/model/source changes or new paid calls. Six B failures exposed an overly broad prose quotation prohibition, one published answer confused departure with pickup time, and pastoral cases overgeneralized deadline planning. Preserve failed/interrupted/operational evidence; one baseline sample's partial pre-review exposure and style-based blinding limitations are disclosed. No candidate tuning, production trial posts, activation, deployment, or model substitution.

## Stage 7 - Blocked by initial gates; not executed

- Changes: No paid expansion comparison or promotion; existing expansion implementation remains opt-in and stub-verified only.
- Verification: Saved initial gates are false. The expansion runner rejects failed gates; no dependent paid expansion calls were made.
- Notes: Return for reassessment with fresh selection cases and any separately approved new budget. This is a failed release experiment, not completed delivery. No schema migration or remote deployment was in scope.
