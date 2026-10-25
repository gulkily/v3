# Retraction design mistakes checklist

**2026-10-24 direction change:** Read the [retraction simplification handoff](retraction_simplification_handoff.md). The user wants deletion plus a retained record, with later history purge and accepted delayed unavailability. Reassess the corrective proposals below against that contract; the sweep diagnostics plan is canceled. Historical findings remain evidence, not authorization to expand the old machinery.

Date: 2026-10-24. Retrospective covering the documented retraction feature history,
the zenmemes.com publication outage, and the subsequent recovery patches.

The central failure was making publication depend on reconstructing retraction
state without adequately handling historical deletions or budgeting the work on
the production host. The recovery then introduced a full rebuild per render and,
after removing that, retained six full-record fingerprint passes per refresh.
Those recovery mistakes were introduced by the coding agent; user approval of the
plans did not validate their architecture or production performance.

Boxes track review and acceptance of the corrective work. They are left unchecked
for this retrospective; the status on each item distinguishes existing fixes from
open work. Earlier user-visible gaps are included as design lessons, while
deliberately approved retention limits and deferred scope are identified separately.
This checklist does not authorize executing its follow-up work.

## Content and behavior we did not model completely

- [ ] **R01 — We did not cover every served copy in the initial privacy model.**
  Recorded LLM requests, responses and errors could disclose retracted prose,
  including through saved exchange URLs. Viewer approval was insufficient
  protection against this disclosure. Corrective requirement: inventory every
  served representation and enforce suppression at the payload reader, including
  direct URLs, rather than relying on disappearing links. **Status:** addressed by
  the exchange-privacy implementation; keep it in regression coverage. [Original gap][privacy] [Fix][privacy-fix]

- [ ] **R02 — We had not recorded enough provenance to retract copied context reliably.**
  An exchange's primary post did not identify all parent, thread, comment, related
  post or cached-summary content included in its prompt. Historical ambiguity then
  required withholding exchanges that might otherwise be useful. Corrective
  requirement: record actual input dependencies when producing derived material,
  and define conservative handling for legacy records. **Status:** provenance and
  historical withholding implemented for exchanges; this is not proof that every
  generated-effect family has complete provenance. [Evidence][privacy]

- [ ] **R03 — We treated removal of a post and withdrawal of its effects separately.**
  A source reply could be retracted or physically deleted while its `pin`/`pinned`
  label records remained effective, including after rebuilding. Corrective
  requirement: track ownership of generated effects and recompute shared results
  from surviving contributions, preserving independently supplied labels.
  **Status:** source-owned label cleanup implemented; the broader all-effects
  outcome remains incomplete. [Evidence][effects-assessment] [Fix][effects-fix] [Scope][effects-scope]

- [ ] **R04 — An early cascade proposal confused dependency with deletion authority.**
  The effects plan temporarily expanded into recursively retracting cross-author
  replies. The user had to clarify that independently authored replies must
  survive. Corrective requirement: distinguish generated effects from authored
  responses; parentage alone does not justify deletion. **Status:** corrected
  during planning, not evidence that the broad cascade was shipped. [Evidence][effects-assessment]

- [ ] **R05 — Listing behavior was not aligned with the expected removal outcome.**
  Retracted items remained as placeholder rows in discovery lists. Corrective
  requirement: specify listing visibility separately from permalink/thread
  placeholders, including counts and pagination. **Status:** board, authored-content
  and Activity content-view filtering implemented. Activity's `all` view remains
  a separate policy; the listing handoff also records missing rendered-page
  verification for some consumers. [Evidence][listings]

- [ ] **R06 — Display attribution used signing-key identity instead of the existing account model.**
  An operator using another approved key for the same canonical account could be
  described as an outside operator. Corrective requirement: distinguish authority,
  actual signer and displayed account attribution, using acceptance-time evidence.
  **Status:** corrected; matching usernames alone must still not grant authority.
  [Original gap][attribution] [Fix][attribution-fix]

## Lifecycle and publication failures

- [ ] **R07 — We did not support the combination of retained retraction evidence and an absent source.**
  Physically deleted legacy posts could retain valid retraction records without
  accepted source-removal receipts. The strict reader classified that situation as
  unavailable state. Corrective requirement: define separate outcomes for present
  targets, confirmed absence, unreadable targets, malformed evidence and missing
  authority, including old deletions made before the newer receipt mechanism.
  **Status:** absent-target snapshot and shared-HTML publication now have scoped
  handling; this does not establish safe destructive cleanup for the same state.
  [Evidence][snapshot] [Current publication work][publication]

- [ ] **R08 — We gave a local evidence problem a site-wide publication impact.**
  The two absent reply targets prevented unrelated uncached public pages, including
  `/`, from being generated. Corrective requirement: determine when affected
  content can be safely excluded while publishing the remainder, instead of
  globally blocking on every unavailable target. **Status:** fixed for the observed
  shared-HTML case; malformed or untrusted evidence must still block unsafe output.
  [Evidence][publication-assessment]

- [ ] **R09 — We reused one strict validation outcome across operations with different needs.**
  Destructive cleanup, exchange disclosure, offline snapshots and shared HTML do
  not require identical authority decisions. Reusing a null-or-success reader
  coupled their availability and recovery behavior. Corrective requirement:
  explicit operation-specific contracts and structured validation results.
  **Status:** scoped publication contracts exist; the general diagnostic/result
  contract remains proposed work. [Evidence][diagnostics]

- [ ] **R10 — We did not complete the cross-consumer impact review when fixing a shared guard.**
  The offline-snapshot fix deliberately kept strict callers unchanged, leaving
  high-traffic generation vulnerable to the same absent-target data shape.
  Corrective requirement: maintain a consumer matrix and track each affected
  normal entry point, including intentionally deferred consumers. An offline fix
  should not imply that the publication problem is closed everywhere.
  **Status:** high-traffic coverage added later; sweep diagnostics still have a
  separate unresolved report. [Evidence][snapshot] [Evidence][diagnostics]

- [ ] **R11 — We left an expected publication failure outside the HTTP error boundary.**
  The supplied production stack trace shows offline snapshot bootstrap throwing
  `Cannot validate retraction state for publication` before the front controller's
  application error handling. It became an uncaught PHP fatal. Corrective
  requirement: handle unavailable snapshot publication with a controlled response
  and actionable private diagnostics. **Status:** generic offline HTTP bootstrap
  error handling was explicitly deferred in the incident implementation summary;
  do not mark it fixed merely because the absent-target case improved. [Evidence][publication]

- [ ] **R12 — We lacked an effective readiness check for historical data before activating stricter publication.**
  The affected deployment could enable high-traffic mode successfully and then
  fail every cold homepage generation. Corrective requirement: exercise existing
  retraction/source combinations and successful cold publication as rollout
  criteria, with a recovery path that does not require restoring removed prose
  or deleting valid evidence. **Status:** runbook and fixtures improved; enabled
  status alone still does not establish publication readiness. [Evidence][runbook]

## Performance and recovery architecture mistakes

- [ ] **R13 — The first HTML recovery fix rebuilt the entire read model for every excluded-target render.**
  This put Git history lookup, canonical parsing and all indexing stages inside a
  30-second render worker. Production preparation later measured 47.965 seconds.
  Corrective requirement: prepare reusable validated input outside page serving.
  **Status:** removed in `5ccf36d58`; explicit preparation is reused across pages.
  The underlying preparation cost has not itself been eliminated. [Evidence][publication]

- [ ] **R14 — We initially missed the renderer's second, implicit rebuild path.**
  Even a prepared input could reach `Application::ensureReadModel()`, whose normal
  fallback rebuilds missing or stale databases. Corrective requirement: make the
  renderer's no-rebuild rule explicit through the application call chain and test
  missing/stale inputs. **Status:** shared rendering now refuses that fallback;
  ordinary application behavior retains its existing recovery policy. [Evidence][publication]

- [ ] **R15 — We retained six whole-record fingerprint passes after switching to a prepared model.**
  Parent admission, worker admission, input entry, input exit, post-render validation
  and final publication each reread the canonical corpus. These checks belonged to
  deriving the model. Corrective requirement: separate exact preparation identity
  from the evidence required to publish an already validated snapshot. **Status:**
  removed from rendering in `2d91c8029`; full fingerprints remain in preparation.
  An actual CLI/worker regression test fails on any such hashing during refresh.
  [Evidence][publication] [Test][publication-tests]

- [ ] **R16 — We almost made every ordinary post invalidate prepared-page availability.**
  The first reusable-model implementation rejected cold navigation after a new
  post until preparation ran again. The browser test caught this before release.
  Corrective requirement: distinguish content freshness from retraction safety;
  permit an earlier safe snapshot while requiring current exclusion evidence.
  **Status:** corrected before `5ccf36d58`; changed retraction evidence still blocks
  reuse, and explicit preparation updates ordinary content. [Evidence][publication]

- [ ] **R17 — We did not budget the complete request and maintenance costs on the actual host.**
  A 30-second child timeout did not bound parent validation or explain total command
  time. After the latest fix, the first two evidence checks still account for
  approximately 7.81 seconds of an 11.802-second refresh. Corrective requirement:
  measure preparation, validation, copy, render and final publication independently,
  and establish host-appropriate budgets and concurrency behavior. **Status:**
  instrumentation improved; the remaining validation cost is open. Its exact
  internal cause is not yet established by the stage-level timings.

- [ ] **R18 — The new preparation prerequisite was not checked early or communicated precisely enough.**
  The first attempt after deployment spent about 15 seconds validating before
  reporting that the prepared model did not exist. An earlier progress line said
  it was “using” that model before opening it. Corrective requirement: cheap
  readiness checks before expensive work and progress text that reports verified
  state. **Status:** the recovery command is now explicit and full-tree scans are
  removed; early prerequisite checking and that misleading progress wording remain
  follow-up items. [Code][render-input] [Code][cache]

## Diagnostics and operational mistakes

- [ ] **R19 — We collapsed actionable failure causes into generic messages.**
  `Cannot validate retraction state`, `No generation capacity or inactive mode`,
  and `Shared render exceeded limits` concealed different causes and recovery
  actions. Corrective requirement: preserve the failing phase, safe record/path
  identity, cause, retryability, configured limit and observed value through CLI,
  queue status and private logs. **Status:** refresh refusal and limit messages
  improved; the evidence scanner still loses causes at its null boundary, and the
  sweep's proposed structured diagnostics are not implemented. [Evidence][diagnostics]
  [Incident explanation][explanation]

- [ ] **R20 — We presented permanent validation failure as temporary load.**
  “Meme oven is busy” and retry feedback suggested waiting could fix the homepage.
  The underlying missing-target condition persisted across requests. Corrective
  requirement: distinguish transient capacity refusal from maintenance-required
  failure in operator reporting and recovery behavior, keeping public copy concise.
  **Status:** CLI discrimination improved; the public busy response remains shared
  across several failure causes. [Evidence][publication-assessment] [Explanation][explanation]

- [ ] **R21 — Automatic retries and popularity refresh could not guarantee recovery.**
  Snapshot tasks retried a deterministic evidence failure. The high-traffic access
  sampler selects 2xx/3xx requests, so a cold route seen only as 503 may not be
  selected after repair; the production report also found no refresh cron installed.
  Corrective requirement: classify permanent blockers, verify scheduling, and
  explicitly warm failed critical routes rather than assuming retries will heal
  them. **Status:** explicit `/` refresh restored publication; the new generated
  cron line includes preparation, but host cron installation and ongoing freshness
  still need separate confirmation. Missing cron is an operational condition, not
  proof that it caused the initial guard failure. [Evidence][snapshot] [Runbook][runbook]

## Verification and engineering process mistakes

- [ ] **R22 — Our original fixtures missed the production combination.**
  Clean retraction fixtures and separate cache tests did not expose retained
  retractions plus physically absent targets, stale SQLite rows, no accepted removal
  evidence, and a cold high-traffic homepage. Corrective requirement: preserve this
  combined incident fixture and test the real entry points, not only helpers.
  **Status:** both production IDs, stale-model exclusion, CLI, front controller,
  Apache and race cases now have coverage. [Evidence][publication]

- [ ] **R23 — Local correctness and fast local runs did not establish production feasibility.**
  The first fix passed extensive tests, and a local 1,794-post derivation took about
  3.2 seconds. Production preparation of 1,693 posts took about 48 seconds. Corrective
  requirement: make host cold-generation timing and resource limits explicit release
  criteria; test work counts as well as correctness. **Status:** deployment limits
  were documented, so this was not an undisclosed claim of production success.
  Nevertheless the selected architecture was unsuitable, and the user had to
  discover it in production. [Evidence][publication]

- [ ] **R24 — We fixed symptoms serially instead of reviewing the complete recovery path early.**
  The sequence was generic refusal, better refusal details, generic resource limit,
  stage diagnostics, live progress, rebuild removal, then repeated-hashing removal.
  Better messages were useful but did not repair the expensive architecture.
  Corrective requirement: trace the full parent/worker/read-model/publication path
  and account for total work before shipping another performance fix. **Status:**
  the two identified architectural mistakes are corrected; remaining validation
  work is still open. [Evidence][publication] [Explanation][explanation]

- [ ] **R25 — Communication supplied more narration than useful operational evidence.**
  The agent repeatedly described plans and local checks while the operator received
  terse failures and had to request usable diagnostics. Corrective requirement:
  short progress updates, precise CLI errors, visible stage timing, concrete next
  commands, and explicit separation of local verification from host recovery.
  **Status:** diagnostics and reporting improved; the explanation is saved, and
  this checklist preserves ownership of the mistakes. [Evidence][explanation]

## Latest production evidence and remaining work

| Observation | Result | What it establishes |
| --- | --- | --- |
| Initial normal `/` versus query-string route | 503 busy versus 200 | The high-traffic publication path was the observed blocker. |
| First explicit shared-model preparation | 47.965 s | Preparation itself remains expensive on the host. |
| Refresh after reusable-model fix | 22.273 s; repeated run about 20 s | Rebuild removal alone was insufficient. |
| Refresh after full-record scan removal | 11.802 s total; published at 11.12 s | Material improvement with successful publication. |
| Latest initial parent validation | 4.48 s | A remaining costly evidence-check interval. |
| Latest initial worker validation | 3.33 s | A second costly evidence-check interval. |
| Latest SQLite copy plus integrity check | 0.11 s | Not the dominant cost in this observed run. |
| Latest HTML rendering | 1.01 s | Not the dominant cost in this observed run. |
| Two external GET checks after earlier publication | HTTP 200, cache hit, about 0.67/0.69 s | The cached homepage was observed serving successfully; this does not measure regeneration. |

The latest stage timings above are from the user's production transcript after
`2d91c8029`. The roughly 7.81 seconds in initial validation needs further profiling;
the transcript does not prove whether Git history, signature/authority checks,
filesystem waiting, host scheduling, or another sub-operation dominates. Do not
attribute all remaining latency to one of those without measurement. Likewise,
the earlier timeout's last stage did not establish thread-subject indexing as the
cause: the complete preparation trace measured that stage at only 4.8 milliseconds.

Priorities after reviewing this checklist: investigate R17, improve evidence
diagnostics in R19, close the missing-model/preflight gap in R18, verify automatic
recovery in R21, and explicitly track the remaining consumer/effect work in R03,
R10 and R11. Passing publication tests does not close those items automatically.

## Limits and decisions that are not established mistakes

- Retaining signed evidence, protecting independently authored replies and refusing
  malformed or untrusted authority are valid requirements. Removing those checks
  is not the proposed performance remedy.
- Canonical files, Git history, backups and externally held copies were explicitly
  outside the physical-erasure promise. Retention alone is not proof that the
  implementation violated that stated contract. Served LLM disclosure in R01 was
  a distinct problem. [Contract][contract]
- The local sweep was an explicitly added cleanup phase. Its later introduction
  is not by itself evidence that the earlier suppression-only release falsely
  promised complete erasure. [Sweep assessment][sweep]
- The broader all-effects work was explicitly split, and generated metadata,
  reactions, scoring/classification and non-label automated work were left pending.
  Those remain incomplete outcomes; the approved label slice did not claim to
  finish them. [Scope][effects-scope]
- The host killing a Codex process does not establish that the homepage failure
  was an out-of-memory event. The supplied application logs establish a validation
  exception, followed later by a renderer timeout during the first recovery design.
- No evidence reviewed here establishes that all remaining performance problems
  originated with retractions, or that every possible retraction defect has now
  been found. This checklist records the identified mistakes and open outcomes.

[privacy]: retracted_post_llm_exchange_privacy/retracted_post_llm_exchange_privacy_step1_solution_assessment.md
[effects-assessment]: retraction_effects/retraction_effects_step1_solution_assessment.md
[effects-scope]: retraction_effects/retraction_effects_step2_feature_description.md
[listings]: retracted_posts_hidden_from_listings/retracted_posts_hidden_from_listings_step4_implementation_summary.md
[attribution]: account_retraction_attribution/account_retraction_attribution_step1_solution_assessment.md
[snapshot]: absent_retraction_snapshot/absent_retraction_snapshot_step1_solution_assessment.md
[publication-assessment]: high_traffic_absent_retractions/high_traffic_absent_retractions_step1_solution_assessment.md
[publication]: high_traffic_absent_retractions/high_traffic_absent_retractions_step4_implementation_summary.md
[publication-tests]: ../../tests/HighTrafficPublicationTest.php
[diagnostics]: retraction_sweep_evidence_diagnostics_report.md
[explanation]: high_traffic_diagnostic_explanation.txt
[runbook]: ../runbooks/high_traffic_mode.md
[render-input]: ../../src/ForumRewrite/Host/HighTrafficRenderInput.php
[cache]: ../../src/ForumRewrite/Host/HighTrafficCache.php
[contract]: ../reference/author_post_retraction.md
[sweep]: post_retraction_sweep/post_retraction_sweep_step1_solution_assessment.md

[privacy-fix]: retracted_post_llm_exchange_privacy/retracted_post_llm_exchange_privacy_step4_implementation_summary.md
[effects-fix]: retraction_effects/retraction_effects_step4_implementation_summary.md
[attribution-fix]: account_retraction_attribution/account_retraction_attribution_step4_implementation_summary.md
