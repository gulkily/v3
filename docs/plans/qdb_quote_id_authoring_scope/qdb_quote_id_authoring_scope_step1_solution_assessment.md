> **Feature plan:** [Step 1](./qdb_quote_id_authoring_scope_step1_solution_assessment.md) · [Step 2](./qdb_quote_id_authoring_scope_step2_feature_description.md) · [Step 3](./qdb_quote_id_authoring_scope_step3_development_plan.md) · [Step 4](./qdb_quote_id_authoring_scope_step4_implementation_summary.md)

## Original Query

I found the following in todo.txt:

> QDB should only add quote ID when using the Add Quote form. Otherwise, it should create a regular thread (e.g. when using `/compose/thread`).

Please write Step 1 of `docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md`.

## Understood Intent

On the QDB site, distinguish quote creation through Add Quote from ordinary thread creation through the general composer.

## Problem

QDB currently chooses its ID format from the active site profile, so every thread-authoring path receives a quote number instead of only the Add Quote path.

## Options

### Option A — Carry an Add Quote intent through the existing thread APIs

Have the Add Quote form send an explicit quote-authoring value while other thread forms omit it; use that value when choosing the ID format in create and prepare flows.

- Pros: small public-surface change; keeps one set of thread endpoints.
- Cons: the write layer receives a caller-supplied value rather than a route-defined authoring path; signing, preparation, and API clients must all preserve it consistently.

### Option B — Provide Add Quote-specific submit and preparation routes

Route Add Quote form submissions and its browser-signing preparation through dedicated quote-create operations; retain the existing thread operations for `/compose/thread` and general API clients.

- Pros: makes quote allocation an explicit server-recognized authoring path; keeps normal thread creation the default; gives signed and unsigned flows the same clear contract.
- Cons: adds small routing/client coordination and focused endpoint coverage.

### Option C — Keep profile-wide quote allocation and special-case `/compose/thread`

Continue treating QDB thread creation as quote creation by default, then override the ID only for the general composer.

- Pros: narrowly changes the reported example.
- Cons: reverses the requested rule; future non-quote paths can accidentally receive quote IDs.

## Recommendation

Adopt Option B. Make Add Quote the sole explicit quote-authoring path and have all other QDB thread creation—including `/compose/thread`—use regular thread IDs by default. This is a releasable vertical slice: an Add Quote remains a numbered quote, while a general-composer post remains a regular thread without changing quote discovery or existing records.

## Continuation Handoff

- Existing evidence: `LocalWriteService::mintThreadPostId()` currently branches only on the QDB profile; both direct creation and signed preparation call it. The Add Quote and general composer reuse the thread form today.
- Scope boundary: no database migration, changes to existing IDs, quote-list eligibility, or QDB presentation redesign.
- Resume point: review this Step 1. Do not create Step 2 until the user responds `Approved Step 1`.
