> **Feature plan:** [Step 1](./recent_activity_step1_solution_assessment.md) · [Step 2](./recent_activity_step2_feature_description.md) · [Step 3](./recent_activity_step3_development_plan.md) · [Step 4](./recent_activity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/recent_activity; [ledger](../effort_estimation_trial.md#cycle-v3recent_activity), standing P2 enrollment
Extended estimates: enabled
**Cycle estimate: 0.75**

## Problem and user stories

Readers need to see new conversation, including replies buried in older threads, without opening each discussion or interpreting technical activity records.

- As a reader, I want one Recent page showing new threads and replies so that I can catch up quickly.
- As a phone user, I want readable previews and direct post links so that I can find and open a contribution without hunting through a thread.
- As a returning reader, I want predictable paging and recovery so that interruptions do not strand me.

## Core requirements

- A **Recent** link in ordinary site navigation opens a dedicated public-conversation page. Existing board, Activity, Forte and technical/security destinations retain their behavior.
- Show individual threads and replies together, newest first by posting time, with deterministic ties. Include human and agent contributions regardless of thread age or approval status where publicly readable; exclude bootstrap/system events and hidden or removed content under existing visibility rules. No new ranking or personal filters.
- Each entry shows the existing author/trust presentation, posting time, thread context, a short readable excerpt and an exact-post link. Handle title-only, image-only and unavailable parent context without blank cards or leaking excluded content. Keep technical source details and composing controls out of the list.
- Use native Previous/Next links and bounded pages of at most 50 entries. Unchanged data traverses without gaps/duplicates; new arrivals become visible on the first page. Invalid positions offer First page; empty pages retain return navigation. Reload/Back recovers from a failed navigation without pretending it succeeded.
- Reading and paging work without JavaScript. Phone/desktop layouts, keyboard navigation and enabled themes remain usable on first paint. Public output is shared, with no read tracking or viewer-specific server markup; refresh follows the site's existing publication contract rather than introducing live polling.

## Delivery scope and completion boundary

Work type: application change. Deliver navigation, the readable paginated page, current publication/visibility integration, proportionate regression checks, touched concept documentation and this cycle's study records. No external dependencies.

Flow: 1. Open Recent from ordinary navigation. 2. Scan new contributions or page backward. 3. Open the exact post and return. 4. Reload after a connection failure or choose First page after an invalid position.

Completion requires that flow through the ordinary front controller in live and static-serving configurations, including empty/failure recovery. Release requires compatible application/read-model startup and refreshed published pages; document deployment steps and distinguish local completion from production acceptance. An unavailable offline page offers the existing reconnect/Reload/Back path, not an unrelated cached page.

Known environment: feedback includes slow Activity and phone use. Verify Recent on phone and desktop; retain separate physical-device and production limits. Do not close the existing Activity latency report without replaying its reported environment.

## Risks and early validation

- **Visibility/context leakage:** inspect existing eligibility and parent-context behavior before Step 3; use mixed visible/hidden/removed fixtures and preserve current policy rather than redesigning retraction.
- **Slow reads or oversized pages:** confirm bounded selection and excerpt work before planning dependent rendering; compare growing datasets and record response/first-paint evidence. Avoid per-entry technical-history enrichment.
- **Serving/navigation regressions:** trace static/live routes and shared navigation before Step 3; preserve older-page URLs, first-page refresh and existing destinations. Check Word97, Chicago and Midnight icons as well as text themes. Unresolved material risks block dependent work.

## Shared surfaces and concepts

Reuse the [post-card](../../../templates/partials/post_card.php) author/time/content helpers and exact-post behavior from [thread/post pages](../../../src/ForumRewrite/Http/ThreadAndPostPageController.php). Add a compact preview partial only because full cards include unrelated controls. Preserve existing board/thread cards, profile authored-content lists, standard/Forte Activity, Activity RSS and read-API contracts; no API/feed expansion. Reuse the [shared pagination contract](../../reference/pagination.md) and [navigation partial](../../../templates/partials/page_navigation.php); extend canonical site navigation and its theme-icon mapping rather than creating parallel controls.

Concepts touched: Recent conversation discovery (new, proposed inventory entry); post presentation/visibility (used unchanged, proposed entry); [static publication](../../concepts/static_publication.md) (extend coverage); [derived read model](../../concepts/read_model_rebuild.md) (reuse with required query/startup checks). Inventory changes stay limited to delivered behavior.

## Success criteria and deferred requests

A fixture with a new thread and five new replies to an old thread shows all six separately and opens each exact post. Paging handles equal timestamps, arrivals/deletions, invalid positions and empty results; excluded content never appears. Phone/desktop and no-JavaScript flows pass, including long text, theme icons and failure recovery. Existing shared consumers pass affected checks; performance and release limits are recorded honestly.

Deferred, unapproved: last-visit/read markers, author/type filters, RSS/API expansion, ranking/self-bump suppression and job-status activity. These are separate follow-ups, not prerequisites for this slice.

## Approval and study checkpoint

Approved Step 1: user selected the recommended Option A by approving Step 1. Scope v1/A and baseline 3a21fab67708c07ef77712790218e12a92c61bcd retained. Step 2 handoff, 2026-10-30T20:37:32Z: **0.75 reaffirmed**, R1/P2, Codex single exposed rater; three frozen hashes reverified. Earlier exposed intake/handoff 0.75, inherited 0.5 and missing pristine intake remain unchanged. No measured labor or runtime acceptance claimed; only source/document checks performed. Planning remains uncommitted. Await **Approved Step 2** before the development plan.

*— Codex · 2026-10-30 UTC*
