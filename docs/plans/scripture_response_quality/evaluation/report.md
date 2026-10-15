# Initial comparison: no qualifying candidate

The faster configuration demonstrates a latency improvement, but neither revised candidate meets the approved quality gate. Keep candidate settings opt-in. Expansion trials and release are blocked by the [development plan's dependency and reassessment rules](../scripture_response_quality_step3_development_plan.md).

All 48 scheduled generation calls were dispatched. Forty-seven produced complete canonical replies visible in Chromium; one was interrupted during the provider call and was never repeated. The interrupted call retains its full worst-case reservation. There were no observed provider-generation or publication failures among the other 47 calls. One earlier source-creation rejection made no provider call and is retained separately.

| Approach | Complete / scheduled | All-rubric passes / scheduled | Everyday passes / 8 | Answers with critical quotation errors | Median visible completion | Maximum completed time |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| A: current, default reasoning | 16/16 | 0/16 | 0/8 | 1 | 27.80 s | 36.21 s |
| B: revised, default reasoning | 16/16 | 6/16 | 3/8 | 4 | 25.01 s | 34.94 s |
| C: revised, low reasoning | 15/16 | 5/16 | 2/8 | 1 | 8.94 s | 27.04 s |

C's median among completed samples is 67.8% below A. B improves 10.0%, missing both the ≤20-second and ≥30% targets. No completed sample exceeded 40 seconds. The interrupted C sample has no completion time and is reported separately, not silently discarded or assigned an invented duration. C's completed-sample latency result does not establish a full successful release trial.

The quality threshold is ≥15/16 initial passes, all eight everyday passes, at least four everyday improvements, no loss of complex-case completeness, and zero critical quotation errors. B and C achieved respectively three and two conservative everyday improvements (candidate passes where matched baseline fails). Neither clears these gates. Both also failed to explain the competing theological arguments in the explicitly requested Sabbath discussion. No expansion candidate was selected, so the twelve dependent paid expansion trials were not run.

## Source-checked failures

Review packets used randomized IDs without candidate or timing labels. Ratings were saved before candidate identification. These errors independently block release, regardless of subjective judgments about verbosity:

- C, case 2, repeat 1 (`f740756b`): attributes improvised wording in James 4:13 to KJV, including a changed opening and an added phrase about the city. Compare [James 4:13–17 KJV](https://www.biblegateway.com/passage/?search=James%204%3A13-17&version=KJV).
- B, case 8, repeat 1 (`52086ced`): labels Proverbs 21:5 as NIV while using ESV wording. Compare [NIV](https://www.biblegateway.com/passage/?search=Proverbs%2021%3A5&version=NIV) and [ESV](https://www.biblegateway.com/passage/?search=Proverbs%2021%3A5&version=ESV).
- B, case 5, repeat 2 (`94b958b0`): substitutes an unmarked paraphrase inside a quotation labeled KJV. Compare [2 Corinthians 2:7–8](https://www.biblegateway.com/passage/?search=2%20Corinthians%202%3A7-8&version=KJV).
- B's Sabbath answers contain an unmarked synthetic Genesis quotation and altered Isaiah wording. Compare [Genesis 2:2–3](https://www.biblegateway.com/passage/?search=Genesis%202%3A2-3&version=KJV) and [Isaiah 58:13–14](https://www.biblegateway.com/passage/?search=Isaiah%2058%3A13-14&version=KJV).

Other failures include repeated scripture/application sections, unsupported offers of follow-up conversation, and using passages as productivity instructions without distinguishing their original argument from an analogy. Minor omissions and spelling/edition questions are recorded separately from critical fabricated or misattributed wording. Full per-answer ratings and measurements are in [results.json](./results.json).

## Timing, cost and evidence

Median queue/generation/publication times were A: 255 ms / 27.14 s / 31 ms; B: 55 ms / 22.80 s / 32 ms; C: 49 ms / 6.64 s / 27 ms. Provider generation dominates this controlled setup. Combined request/discovery overhead medians were 262 ms, 1.82 s and 1.27 s respectively. These phase medians are descriptive and must not be added to reconstruct a median total.

A used an immediately started public CLI worker and reload, with no artificial cron delay. B/C used automatic dispatch and status polling. Early browser discovery cannot be separated exactly from request overhead; total submission-to-visible duration was measured directly with a monotonic clock. Later trials also retain wall-clock endpoints. The isolated fixture disables automatic background fast-scoring consistently across variants; production background-scoring load was not measured. These are isolated localhost measurements, not production latency guarantees.

The ledger contains 48 generation calls and zero supporting moderation calls; no moderation bypass was added. Forty-seven calls settled to a conservative uncached token-cost estimate of **$0.05347665**. The interrupted call retains **$0.00398345**, bringing estimated/reserved spend to **$0.05746010**, within the approved $5 ceiling. Actual billing may be lower because input caching is conservatively ignored. Pricing provenance is in the [protocol notes](./README.md).

Exact answers, source-page caches, request/response traces, screenshots, canonical records, databases, hashes, timings and the original ledger remain in `state/private/scripture-quality-evaluation-20261015/`. The versioned results include answer hashes and artifact paths, avoiding unnecessary republication of translated passages. All 777 frozen source/prompt artifacts remained unchanged.

Limitations: one baseline answer appeared with metadata before anonymous review; the source-creation punctuation correction and the interrupted trial are retained in the [protocol notes](./README.md). Review used one evaluator, with independent passage lookup rather than paid judge-model calls. Proportionality and interpretive completeness remain review judgments; the quotation mismatches are directly verifiable against sources.

## Reassessment

Retain the verified dispatch, status discovery, expansion delivery and opt-in controls. Re-plan quotation verification and explicit coverage of requested interpretive differences before another paid comparison. A different model or a verified-source quotation component would require revised approved scope and a fresh evaluation protocol; the current plan excludes retrieval and automatic review passes. No new candidate, extra call, default-setting change or deployment is authorized by this failed comparison.
