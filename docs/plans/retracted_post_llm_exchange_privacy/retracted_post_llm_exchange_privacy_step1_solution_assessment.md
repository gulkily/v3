> **Feature plan:** [Step 1](./retracted_post_llm_exchange_privacy_step1_solution_assessment.md) · [Step 2](./retracted_post_llm_exchange_privacy_step2_feature_description.md) · [Step 3](./retracted_post_llm_exchange_privacy_step3_development_plan.md) · [Step 4](./retracted_post_llm_exchange_privacy_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

That's good. For retracted posts, the LLM exchanges can still disclose the post's contents. How do we fix this? Please write a Step 1 if we can't one-shot it.

## Understood Intent

Honor author and operator retraction when serving recorded LLM exchanges, including previously recorded conversations and access through saved direct URLs.

## Problem Statement

Recorded requests, responses, and errors can preserve retracted prose, while exchange access currently checks viewer approval without checking the retraction of any included post.

## Current Behavior

The [exchange controller](../../../src/ForumRewrite/Http/LlmExchangesController.php) serves approved viewers the list and individual records; post cards also retain exchange links. A record identifies its primary post, but [analysis context](../../../src/ForumRewrite/Agent/PostWorkflowService.php) can include parent, thread, comments, and related posts. Historical records do not consistently retain every included post's identity, so filtering only the primary post cannot close every exchange leak.

## Option A Suppress exchanges by primary post

Hide exchange links and records whose primary post has been retracted, including direct detail access.

- **Pros:** Small, immediate fix for the reported direct-post leak; reuses current retraction state.
- **Cons:** Exchanges associated with other posts can still disclose retracted context; insufficient as the complete fix.

## Option B Suppress exchanges by all included posts

Track the posts included in each exchange and suppress the entire exchange when any is retracted. Classify historical exchanges using reliable existing evidence; withhold payload access when dependencies cannot be established safely.

- **Pros:** Covers copied context as well as the primary post; retains diagnostic storage without exposing it through the application.
- **Cons:** Requires historical handling and recording changes; conservative treatment can hide otherwise useful exchanges.

## Option C Remove affected exchange payloads

Erase affected requests, responses, errors, and context from the live exchange store, keeping only safe operational metadata.

- **Pros:** Removes a retained local copy and reduces accidental disclosure through future tools.
- **Cons:** Irreversible diagnostic loss; still needs dependency identification and historical handling; backups and provider-held copies remain separate.

## Recommendation

Choose **Option B**. Option A may be its first implementation stage, but does not satisfy the release boundary alone.

- **One access policy:** Apply suppression before rendering links, lists, and detail payloads, including known exchange IDs. Viewer approval or operator privilege must not bypass it. Hide whole affected exchanges rather than attempting to redact fragments of prose or model output.
- **Historical and future coverage:** Include primary, parent, thread, comment, and related-content dependencies for all recorded call types. Recover historical dependencies only from reliable provenance; otherwise withhold payloads conservatively. Do not rely on text matching to prove absence of retracted content.
- **Consistency:** Check current effective retractions when serving exchanges, so existing records, cached pages, and late-arriving provider results cannot bypass suppression. Keep unrelated exchanges usable where dependencies are established; apply list filtering consistently with result limits.
- **Vertical slice:** User retracts a post → its exchange links disappear → direct exchange access is denied → exchanges containing it as context are also withheld → unrelated exchanges remain inspectable. Verify author/operator cases, historical ambiguity, retraction during a provider call, rebuild, and direct URLs.
- **Boundary:** Preserve signed retraction audit evidence and diagnostic storage; defer physical payload purge, provider deletion, and automatic retraction of published replies. Target one day/eight stages; validate historical classification early and rescope before implementation if it cannot be bounded.

## Review

**Approved Step 1** received. Option B, including conservative historical handling and all exchange entry points, is the basis for Step 2.
