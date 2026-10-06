# QDB Quote Listing Filter — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./qdb_quote_listing_filter_step1_solution_assessment.md) · [Step 2](./qdb_quote_listing_filter_step2_feature_description.md) · [Step 3](./qdb_quote_listing_filter_step3_development_plan.md) · [Step 4](./qdb_quote_listing_filter_step4_implementation_summary.md)

## Original Query

I want the QDB site to display only top-level threads that have a quote ID. Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md.

## Understood Intent

Apply the rule to QDB collection surfaces, not generic profiles; retain or reject direct legacy-thread permalinks only after Step 2 makes that recovery boundary explicit.

## Problem

QDB collection pages currently draw from all top-level threads, so legacy or generic thread IDs without a QDB quote ID can appear as quotes.

## Options

### Option A — Central QDB quote-eligibility policy

Use the existing quote-ID contract as one QDB policy and apply it consistently to board, welcome, random, search, counts, and pagination inputs.

- Pros: one definition of an eligible quote; prevents every QDB collection surface from leaking non-quotes; keeps generic profiles unchanged.
- Cons: requires auditing all QDB collection paths and deciding direct legacy-permalink behavior.

### Option B — Filter only the primary board controller

Filter the main QDB board before its sort and pagination behavior.

- Pros: small, fast change for the most visible listing.
- Cons: welcome, random, search, and quote counts can still expose or count non-quote threads.

### Option C — Store quote eligibility in the read model

Add a derived quote-eligibility field and query it from QDB pages.

- Pros: supports future indexing or reporting.
- Cons: adds a schema/read-model concern for a property already derivable from the canonical thread ID.

## Recommendation

Adopt Option A. Deliver one QDB-only collection filter derived from the existing quote-number contract, applied before every QDB list, count, sort, and pagination result is rendered. This is a releasable vertical slice: entering any QDB collection surface produces quotes only, while non-QDB behavior stays unchanged and Step 2 defines the retained-direct-link recovery rule.

## Continuation Handoff

- Current evidence: `QdbQuoteNumbers::fromThreadId()` already identifies quote IDs, while QDB board, welcome, random, and search paths currently fetch all threads.
- Scope boundary: do not add a database field or alter generic profile listings; do not decide legacy direct-permalink behavior without Step 2 approval.
- Resume point: review this Step 1. Do not create Step 2 until the user responds `Approved Step 1`.
