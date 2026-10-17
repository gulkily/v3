> **Feature plan:** [Step 1](./root_approval_pages_step1_solution_assessment.md) · [Step 2](./root_approval_pages_step2_feature_description.md) · [Step 3](./root_approval_pages_step3_development_plan.md) · [Step 4](./root_approval_pages_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

We should make pages for the root approval posts.

## Understood Intent

Make root approval evidence readable in the web UI, like ordinary approval posts, and link account key history to those pages instead of plaintext seed files.

## Problem

Root approvals are canonical seed records rather than posts, so they currently lack a rendered evidence page.

## Option A — Dedicated root approval evidence pages (recommended)

Render existing root seed records as readable pages with the approved identity/account, recorded seed reason, available repository evidence, and a raw-source link. Account history opens these pages. Clearly distinguish recorded seed facts from unknown authorizer or acceptance time.

- Pros: Works for existing seeds; preserves their authority and history; provides an end-to-end history-to-evidence flow without changing canonical records.
- Cons: Adds a distinct evidence page type rather than making seeds ordinary posts.

## Option B — Introduce root approval posts

Define a new root approval post format and a transition for existing seeds, then render approvals through post pages.

- Pros: Could unify future root approval presentation with ordinary posts.
- Cons: Changes authority semantics and requires migration/compatibility decisions; existing seeds lack post facts that cannot safely be invented; substantially broader than readable evidence pages.

## Recommendation

Choose Option A. Deliver the account-history link, readable seed page, missing-record handling, and existing private-site access boundaries together. Keep current canonical seed authority and raw-source access, and avoid synthetic signatures, authors, or acceptance dates. Production deployment is outside this scope.
