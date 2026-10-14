> **Feature plan:** [Step 1](./qdb_numeric_url_retention_step1_solution_assessment.md) · [Step 2](./qdb_numeric_url_retention_step2_feature_description.md) · [Step 3](./qdb_numeric_url_retention_step3_development_plan.md) · [Step 4](./qdb_numeric_url_retention_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

As a user, I want a link to quote #123 to end up at /123, not redirected to /thread/...

Please write Step 1 of Railspike.

## Understood Intent

On QDB, following a quote's numeric link should display that quote while keeping `/123` in the address bar, including after refresh or sharing. The current long route is `/threads/<id>`.

## Problem

Quote cards already offer numeric links, but the application redirects them to long thread URLs, losing the short address the user selected.

## Option A Serve the quote directly at its numeric URL

Display the existing quote detail experience at `/123`, retaining the short URL and allowing existing long thread links to continue working.

- Pros: Meets the request with a narrow change; reuses the quote experience and existing numbering; avoids data migration.
- Cons: The same quote remains accessible at two addresses; existing long links do not automatically become short ones.

## Option B Make numeric URLs canonical for numbered quotes

Serve the quote at `/123` and redirect existing long quote URLs to their numeric equivalents, making either entry point finish at the short address.

- Pros: Establishes one preferred address for each numbered quote; older shared thread links also finish at the short URL.
- Cons: Broadens routing and link compatibility work; requires care around redirects, anchors, and quotes without numbers.

## Recommendation

Choose **Option A**. It delivers the requested outcome without requiring a broader change to old links; Option B can be considered separately if all quote entry points must converge on numeric URLs.

- **Complete user journey:** Follow a quote link from a listing, read and interact with the same quote at `/123`, then refresh or reopen the shared address without a redirect to the long route.
- **Compatibility:** Preserve existing quote controls, missing-quote behavior, long links, and other site profiles. No schema change is expected.
- **Main risk:** Runtime routing and static hosting may serve numeric URLs differently. Validate both supported delivery paths so the short address and quote behavior remain consistent.
- **Scope:** A small, independently releasable routing change using the existing quote page; no quote-page redesign or numbering changes.
