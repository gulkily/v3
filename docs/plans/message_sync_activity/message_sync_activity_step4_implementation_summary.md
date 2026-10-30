> **Feature plan:** [Step 1](./message_sync_activity_step1_solution_assessment.md) · [Step 2](./message_sync_activity_step2_feature_description.md) · [Step 3](./message_sync_activity_step3_development_plan.md) · [Step 4](./message_sync_activity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/message_sync_activity — [ledger](../effort_estimation_trial.md#cycle-v3message_sync_activity)
Extended estimates: enabled
Status: Step 3 approved; implementation in progress

**Cycle estimate: 0.95**

Entry: 2026-10-30 UTC; projection 0.95 reaffirmed, scope v1/A, baseline 4b14e622f, R1/P2, Codex single exposed rater; prior verified references retained. Planning b58d281c7 precedes this summary. Original S1–S8 estimates 0.12/0.10/0.15/0.17/0.13/0.13/0.15/0.06, scopes/uncertainties preserved in Step 3; non-additive. No measured labor. Retirement and cap remain separate unapproved proposals.

*— Codex · 2026-10-30 UTC*

## Stage 1 - Baseline and admission

**Original estimate: 0.12**
**Retrospective stage work: 0.12**

- Changes: Added rolling private admission before history processing, covering GET/POST legacy and combined routes with 429/Retry-After. One bounded event list per account contains at most 64 admitted attempts, independent of session renewal; expired events are discarded on use and rejected requests cannot extend lockout. Added 115-key fixture support and preserved the read-only natural-reload diagnostic.
- Verification: Focused history/feature-flag tests 23/23; additional eight-process concurrency test passes both 64/account and 16/single-key aggregate limits. Fake-clock checks cover exact window expiry, independent accounts and repeated rejection. Existing sync-store outage/isolation tests pass; git diff --check passes.
- Notes: Private admission table is additive; no public read-model or dependency change. Browser pacing/429 recovery arrives in Stage 6; no new release claim until the full slice is complete. Original scope and estimate unchanged.

*— Codex · 2026-10-30 UTC*

## Stage 2 - Private foreground activity

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes: Add private coalesced activity keyed by authenticated account/fingerprint, ordered by server time. Record on approved sign-in and explicit foreground unread refresh; no extra heartbeat. Background unread refresh, sync and acknowledgments do not promote activity. A sync-store outage cannot block sign-in or unread status, and disabled messaging does not touch sync storage.
- Verification: 33/33 focused history/feature-flag/mailbox tests pass; shared-page gate test checks activity on visible entry but not background refresh. Tests cover five-second coalescing, account isolation and no activity promotion by combined discovery. Static shared-page identity checks remain passing.
- Notes: Prior stage 68167edd2; additive private activity table/index, no canonical record or public read-model changes. No estimate revision. Cross-account sender activity is not exposed.

*— Codex · 2026-10-30 UTC*
