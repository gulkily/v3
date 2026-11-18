Cycle: `v3/title_only_activity`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/title_only_activity`; [cycle ledger](../effort_ledger/title_only_activity.md); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.08**
Status: planned; next permitted action: implement and verify

Predecessor: [`v3/thread_activity_drawer`](../thread_activity_drawer/thread_activity_drawer_step3_development_plan.md) (merged #246) and [`v3/thread_activity_record_links`](../thread_activity_record_links/thread_activity_record_links_one_shot.md).

## Original request

2026-11-18: "can title: posts also go in the drawer please, one-shot pr"

## Authorization

An explicit one-shot through to a pushed branch and an open PR. Merging and deploying are the maintainer's.

## Outcome and work type

Application change. Normal entry: `/threads/<id>` with `FORUM_THREAD_ACTIVITY_DRAWER_ENABLED` on.

Outcome: a reply whose only content is a [`title:` line](../../../src/ForumRewrite/Support/TitleDirective.php), alone or with label hashtags, leaves the conversation for the Activity drawer under the same rules as a tag-only reply (unanswered leaves only, chains repeat). The drawer lists it as "e posted title: New name · date", or "e posted title: New name and tagged #meta · date".

Unchanged:
- Only when `FORUM_TITLE_DIRECTIVE_ENABLED` is on; with it off, `title:` is ordinary text and the reply stays.
- A reply with any other text, a second `title:` line (only the first takes effect), or a quoted `> title:` stays.
- The write side, the reply's own page and the thread subject records.

Wording is "posted title:", not "retitled", because the page does not know whether the replier had authority; an unauthorized line still moves (it is still a reply that only says `title: …`).

## Approach and risks

`ThreadLabels::isTagOnly` gains a sibling `isTagOrTitleOnly` sharing one private check, which also accepts exactly one unquoted line that `TitleDirective::lineValue` (split out of `extract`) reads as a title. `TagOnlyLeafFilter::split` and `ThreadActivity::partition` take a `titleLines` flag; the controller passes the title-directive flag through the thread template. Entries carry `title`; the partial prints it before any labels.

Risks:
- **Wrong replies hidden.** Mitigation: same field guards as tag-only; unit cases for text, two titles and quoted lines.
- **Tag-only behaviour changes.** Mitigation: `isTagOnly` keeps the old path (title lines not allowed); existing `ThreadLabelsTest` and drawer tests.

## Reuse decisions

- Rule: `Support/ThreadLabels.php` (one shared check) and `Support/TitleDirective.php` (line rule split out, no second regex).
- Filter, entries, markup: `ReadModel/TagOnlyLeafFilter.php`, `View/ThreadActivity.php`, `templates/partials/thread_activity_drawer.php`; the existing "tagged" entry kind gets a `title` field rather than a new kind.

## Completion conditions

- With both flags on, a title-only leaf reply is in the drawer, not the conversation.
- With the title directive flag off, it stays.
- No schema change, rebuild or new dependency. Handoff: pushed branch and an open PR.

## Concepts touched

[Thread activity](../../concepts/thread_activity.md) (changed: title-only replies join tag-only ones).

## Verification and recovery plan

- `TagOnlyLeafFilterTest`: title, title with tag, title with text, two titles, quoted; off by default.
- `ThreadActivityTest`: entry carries title and labels; flag off keeps the reply.
- Render the partial with title entries.
- Smoke set plus changed tests; full suite not run, per `AGENTS.md`.
- Recovery: the existing `partition()` fallback still shows every reply if building fails.

## Starting revisions and branch

Host `main` at `f5d53fa` (merge of #338); branch `feat/title-only-activity-drawer`.

## Study history

- 2026-11-18T22:40Z, intake: **0.08**. Not pristine and recorded late: I rated it after reading the drawer code and after a first implementation draft (see the execution history). Scope v1 as above. R1, P2, single exposed Claude rater.

*— Claude · 2026-11-18 UTC · Model: claude-opus-5-5*
