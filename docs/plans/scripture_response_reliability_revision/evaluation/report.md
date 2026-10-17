# Scripture reliability revision: regression stop

The repaired Sol writer passed five of six known regressions, but neither writer
qualified for fresh-case testing. The candidate remains inactive. Stages 1–5
were executed; conditional Stages 6–7 were not eligible to run. This is a failed
release qualification, not a completed feature release.

## Cost and evidence

- Frozen candidate commit: `4347e99f`.
- Twelve writing calls; zero supporting moderation calls, paid judges,
  replacements, retries, fresh-case calls, or expansion calls.
- Conservative cost: **$0.12700975**, including a 25% Sol input margin and no
  cache discounts. All calls settled; no unknown reservations remain.
- Returned models: `gpt-5-mini-2025-08-07` and `gpt-5.6-sol`.
- All candidate files/configuration and evaluation-runner hashes remained frozen.
- All twelve published quotation blocks independently matched the pinned corpus;
  nearby context was reviewed. No critical quotation errors or unsupported
  scriptural promises were identified in published candidate replies.
- Private evidence: `state/private/scripture-revision-evaluation-20261017/`.
  Durable ledger, exchanges, browser results/screenshots, anonymous packets,
  saved ratings, gate hashes, returned IDs and local failure diagnosis are retained.
  [Portable results and ratings](./results.json) contain no credentials.

## Qualification

| Repaired writer | Completed | All-rubric passes | Eligible |
| --- | ---: | ---: | --- |
| Mini | 4/6 | 0/6 | No |
| Sol | 5/6 | 5/6 | No |

Mini still overused planning categories in pastoral responses, lacked a clear
stopping rule in the departure answer, and proposed unsupported childcare
lateness when cleanup could not be handed off. It failed to publish the theology
and NIV answers because reflection included prohibited quoted wording or
attribution constructions.

Sol preserved the volunteer shift/pickup roles, gave useful pastoral support,
explained nonretaliation and differing Christian readings, and handled the NIV
misquotation with an explicit limitation and accurate paraphrase. Its theology
reply was rejected by the application for **James writes to Christian
communities**, an ordinary contextual statement matched by the overly broad
scriptural-attribution guard. This is a software false positive; upgrading the
writer cannot by itself remove it. Diagnosis replayed the saved document locally
with the actual original question and translation, without another provider call.

The five completed Sol replies had a median of 9.087 seconds and a maximum of
31.810 seconds. These are completed-only diagnostic figures: the missing sixth
completion prevents qualification, and no fresh matched control was run. They do
not establish a speed improvement or satisfy the initial/expansion latency gates.

## Stop and next boundary

Both candidates failed the required all-six regression qualification. The runner
refused the initial phase with the saved failed gate, and the ledger remained
unchanged. There was no candidate tuning, substitution, activation or shared
configuration change after observing results. The current mini control was
prepared from `51b4fca9` but needed no paid calls because neither candidate qualified.

A narrow follow-up can repair the contextual-attribution false positive using the
saved Sol document and local adversarial tests. No additional paid generation is
needed to demonstrate that parser correction. Such a changed candidate would
still need a new frozen qualification before promotion; this report does not
renew the unused budget or relax the release gates.

Ratings were saved from randomized final published answers before inspecting
candidate keys and timing. Earlier progress logs exposed sample metadata, but
not the reviewed answer-to-model mapping. One reviewer, recognizable model style,
and application labels limit blinding. These six known cases are qualification
checks, not independent held-out evidence of general model superiority.

## Subsequent local correction

After the user requested continuation, the audience-description false positive was
corrected and the exact saved Sol document was replayed through generation and
persisted publication validation, including Expand, using a fake provider. All
22 focused tests passed, including direct-speech, quoted-text and source guards.
No API calls were made. The original failed qualification above remains historical
evidence for its frozen candidate; this local correction does not retroactively
change its result, authorize more paid calls, or establish release readiness.
