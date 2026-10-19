# Scripture response reliability evaluation

The candidate **does not qualify for release**. Source-owned quotations prevented published quotation errors in this sample, but six of sixteen candidate requests failed before publication and a completed answer changed a childcare pickup deadline into a departure time. No paid expansion trials, candidate activation, model substitution, or deployment followed.

## Frozen comparison and result

Both candidates used `gpt-5-mini`, low reasoning, 8192 completion tokens, and the same automatic dispatch/status-polling delivery. A used current guidance; B combined concern-centered guidance and local KJV verification. Eight fixed cases ran twice per candidate through isolated normal HTTP/worker/publication/browser flows. All 808 frozen candidate/source files remained unchanged. This measures the combined change, not independent effects of guidance versus verification.

| Measure | A: current | B: verified |
| --- | ---: | ---: |
| Completed / scheduled | 16/16 | 10/16 |
| All-rubric quality passes / scheduled | 1/16 | 7/16 |
| Everyday passes / 8 | 0/8 | 6/8 |
| Critical published quotation errors | 4 | 0 |
| Median visible completion, completed samples | 13.22 s | 9.25 s |
| Maximum completed-sample time | 23.55 s | 11.30 s |

B produced six matched everyday improvements, but misses the required 15/16 initial passes and all eight everyday passes. Both deep-interpretation requests failed, degrading complex-case completeness. The completed-sample median was 30.0006% below A and below 20 seconds, but six missing completions block the complete-latency-evidence gate; this is not a successful joint quality/latency result. Failed calls remain scheduled failures rather than being discarded or replaced. Subjective rating changes cannot overcome the mathematical ceiling of ten completed answers.

Anonymous ratings were saved before loading candidate labels and computing gates. See [per-answer ratings, hashes, gates, and accounting](./results.json). There was one evaluator; labels and timing were hidden, but style and the verification footer can disclose candidate identity. One baseline departure answer was partially exposed with its label during measurement diagnosis. Proportionality and contextual interpretation are judgments; the missing publications, altered deadline and named-translation mismatches are directly observable.

## Why B failed

- **Overbroad quotation guard:** All six failures had `scripture_verification_failed` and published no partial answer. Five drafts included ordinary terminology, reader wording or casual quoted labels: theological terms, the reader's money question, a maybe-list label, the name of the cheek teaching, or New Perspective terminology. These were blocked by the blanket prose quotation prohibition. The remaining NIV draft put a near-scriptural phrase in quotes without an independently available NIV source. A safer contract must distinguish actual scripture quotations from terminology/reader wording; simply removing all checks would reopen the original failure.
- **Wrong practical constraint:** Anonymous answer `570ab8e6` told the volunteer to communicate departure at 5:30 when 5:30 was the childcare pickup deadline and the shift ended at 5. Accurate scripture wording does not establish sound practical judgment.
- **Framework overgeneralization:** Both job-loss/shame answers overused deadlines, expense triage, self-sufficiency commitments and a stopping point. One appended ten verses without proportionate interpretation. These answers needed pastoral understanding of asking for help, rather than a universal task-management routine.
- **Remaining strengths:** Both departure answers passed the rubric, as did focused rent/loan answers. All eleven quotation blocks in B's ten published answers matched source wording under documented typographic normalization. They were checked against the pinned text and passage context, including [Proverbs 21](https://www.biblegateway.com/passage/?search=Proverbs%2021&version=KJV), [Galatians 6](https://www.biblegateway.com/passage/?search=Galatians%206%3A1-5&version=KJV), [Matthew 5](https://www.biblegateway.com/passage/?search=Matthew%205%3A38-48&version=KJV) and [Matthew 6](https://www.biblegateway.com/passage/?search=Matthew%206%3A25-34&version=KJV). This does not prove every possible quotation/prose bypass is prevented or that interpretations are correct.

## Baseline quotation evidence

- `c27965e0`: unmarked modernization inside James 2:16 labeled KJV; compare [source wording](https://www.biblegateway.com/passage/?search=James%202%3A15-16&version=KJV).
- `5cb4f9ea`: rewritten Luke 6:29 labeled KJV; compare [the passage](https://www.biblegateway.com/passage/?search=Luke%206%3A29&version=KJV).
- `3570ead6`: modernized wording inside Matthew 5:38 labeled KJV; compare [Matthew 5:38–39](https://www.biblegateway.com/passage/?search=Matthew%205%3A38-48&version=KJV).
- `dfa15633`: adds Timothy inside the purported NIV quotation of 1 Timothy 6:11; compare [the licensed NIV text](https://www.biblegateway.com/passage/?search=1%20Timothy%206%3A6-11&version=NIV).
- `bc9befa1`: additionally duplicates a word in Romans 4:4. It fails exact quotation fidelity but was conservatively classified as a transcription error rather than another critical fabrication; compare [the verse](https://www.biblegateway.com/passage/?search=Romans%204%3A4&version=KJV).

## Delivery, cost, and retained evidence

The ledger records **32 writing calls, zero supporting moderation calls, $0.069561 conservative uncached token cost, and zero unsettled charges**. Existing fixture/permission/moderation behavior was preserved; no moderation bypass was added. No replacement or judge-model calls occurred. Prices came from the [official mini model page](https://developers.openai.com/api/docs/models/gpt-5-mini), checked 2026-10-16. The first observed model response succeeded; the selected model was not substituted.

Completed-sample median phases were A: queue 68 ms, generation 11,015 ms, publication 42 ms; B: queue 61 ms, generation 7,400 ms, verification 113 ms, publication 178 ms. Publication includes revalidation under the write lock; generation includes prompt/source-status preparation. Phase medians must not be summed to reconstruct the median total. Browser monotonic timing measured submission through rendered completion. These are isolated localhost observations, not production latency guarantees.

The first browser measurement was initially classified as a harness error because `innerText` collapsed four leading paragraph spaces. The stored words/punctuation and browser result agreed after layout-whitespace normalization. The original error row, original browser result/timing, recovered measurement and charge were retained; no response was regenerated. The comparison correction has a regression test, and resumption refuses unresolved operational failures or unknown charges. Candidate application files and acceptance gates did not change.

Full final/raw answers, anonymous packets, ratings, candidate key, API traces, screenshots, canonical records, databases, phase markers, frozen hashes and ledger remain in `state/private/scripture-reliability-evaluation-20261016/`. Versioned results keep hashes/ratings without republishing full translated text. Stub rehearsals remain in adjacent private directories. Structural/normal-flow verification passed 191 focused PHP/API checks, shared desktop/mobile/offline browser checks, and ten evaluation/proxy/review/gate tests.

## Reassessment boundary

Keep the local source, publication/recovery safeguards and evaluation evidence, but leave verified mode unpromoted. Revisit the quotation contract for non-scriptural quoted terms and reader wording, conditionalize the practical-decision guidance, and preserve numeric deadlines independently of generated application. Add these observed failures as regressions and freeze fresh held-out selection cases before any new comparison. Do not silently weaken the quality/latency gates, tune and rerun these held-out cases as a fresh test, or renew the paid-call budget. Stage 7 remains blocked by the approved initial gates; the releasable feature contract has not been achieved.

Post-trial isolation correction: generic JSON failures retain their prior failure classification; only malformed verified-scripture documents are wrapped as scripture verification failures. Twelve focused generator tests passed after this correction. This changes no prompt, model setting, source data, rating or acceptance gate and makes no new paid call. The recorded paid candidate remains commit `0976fddb`; the corrected working branch is still unpromoted.
