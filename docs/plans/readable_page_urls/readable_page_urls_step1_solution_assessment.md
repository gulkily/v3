> **Feature plan:** [Step 1](./readable_page_urls_step1_solution_assessment.md) · [Step 2](./readable_page_urls_step2_feature_description.md) · [Step 3](./readable_page_urls_step3_development_plan.md) · [Step 4](./readable_page_urls_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/readable_page_urls`; [host ledger](../effort_estimation_trial.md#cycle-v3readable_page_urls). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.65

## Original Query

Also, what the heck is going on with this URL?
https://zenmemes.com/gallery/?position=eyJ2ZXJzaW9uIjoxLCJjb250ZXh0IjoiZ2FsbGVyeTpjcmVhdGVkX2F0OnBvc3RfaWQ6ZGVzYyIsImRpcmVjdGlvbiI6Im5leHQiLCJib3VuZGFyeSI6eyJjcmVhdGVkX2F0IjoiMjAyNi0xMS0wMlQwMDoyNjowNVoiLCJwb3N0X2lkIjoidGhyZWFkLTIwMjYxMTAyMDAyNjA1LWEwOTU0ZDIyIn19

Doing something like this should be strictly against the rules in this project (unless we're actually storing some data in the URL for some necessary reason).

How does pagination in other areas of the site work?

Was there any guidance on this in the docs? Also, just a question, why not just use position?

Holy crap, the threads page does it too:
https://zenmemes.com/threads/?view=liked&sort=newest&position=eyJ2ZXJzaW9uIjoxLCJjb250ZXh0IjoiYm9hcmQ6bGlrZWQ6bmV3ZXN0IiwiZGlyZWN0aW9uIjoibmV4dCIsImJvdW5kYXJ5Ijp7InRocmVhZCI6InRocmVhZC0yMDI2MTAwODA5MzkzOC0xZDE1NWI4YyJ9fQ

So does Recent:
https://zenmemes.com/recent/?position=eyJ2ZXJzaW9uIjoxLCJjb250ZXh0IjoicmVjZW50OmNyZWF0ZWRfYXQ6cG9zdF9pZDpkZXNjIiwiZGlyZWN0aW9uIjoibmV4dCIsImJvdW5kYXJ5Ijp7ImNyZWF0ZWRfYXQiOiIyMDI2LTExLTA1VDIwOjIzOjM0WiIsInBvc3RfaWQiOiJ0aHJlYWQtMjAyNjExMDUyMDIzMzQtYmE0OGQ0MmUifX0

OK, this is fucked up, we need to fix this globally, this is completely unacceptable. Write Step 1 please.

## Understood Intent

Every paged list puts its position in the URL as base64-encoded JSON. The shared helper is `PageNavigation`, used by Board, Search, Tags, Recent, Gallery, Activity and profile history. Decoded, the Gallery token holds a version number, a context string, a direction and a boundary. The context repeats the path and filters already in the URL. The boundary is the only necessary data, and it is usually one post or thread ID.

The maintainer wants page URLs that a person can read and edit, on every list, and a written rule against opaque state in URLs. Data may stay in the URL only when the page cannot work without it.

Two sorts need more than an ID. Search and Tags order threads by last activity, which changes when someone replies, so their position also needs that timestamp. Board pages by thread ID alone for every sort.

Prior guidance: [Implementing pagination](../../reference/pagination.md) prescribes the token and calls it "readable and editable", which base64 is not. AGENTS.md forbids JSON in human-facing data, though it names records and files rather than URLs. No document explains why the token was made opaque.

Problem: Page links on every list hide one ID inside an unreadable token, so people cannot read, edit or trust the URLs they share.

Concepts touched: [collection selection](../../concepts/collection_selection.md) (changed: how a list receives its boundary); [static publication](../../concepts/static_publication.md) (used unchanged in Options A and C, changed in Option B: later pages bypass first-page artifacts when a `position` query is present).

No matching feedback item in `docs/feedback/items/`.

## Option A - `position` names the first item on the page

**Estimate: 0.65**
Main driver: computing the Previous link in seven list adapters. Uncertainty: medium, mostly in Activity's mixed item kinds and the activity-sorted thread pages.

The `position` parameter keeps its name and holds the ID of the first item shown, such as `position=thread-20261102002605-a0954d22`. Search and Tags add that thread's activity timestamp before the ID. The page lists that item and the ones after it. Each adapter finds the first item of the previous page while it renders the current one, so links need no direction. Old tokens redirect to the new form.

- Pro: a page URL names what the reader sees first, so a shared link is easy to check.
- Pro: one parameter on every list, matching the existing static-artifact bypass.
- Pro: removes version, context and direction entirely.
- Con: each adapter needs a second bounded query to find the previous page's first item.
- Con: if the named item is retracted, the page must fall back to the time inside its ID.

## Option B - separate `before` and `after` parameters

**Estimate: 0.60**
Main driver: the same adapter changes as Option A, without the previous-page lookup. Uncertainty: medium.

Next links use `before=<ID>` and Previous links use `after=<ID>`, each naming the last item of the neighbouring page. Activity-sorted lists add the timestamp as in Option A. Adapters keep their current direction logic, and the parameter name replaces the direction field.

- Pro: maps directly onto the current keyset queries, so no extra lookup.
- Pro: a common convention on other sites.
- Con: two parameters, and the URL names an item the reader does not see on that page.
- Con: the static-artifact bypass and several browser tests must learn the new names.

## Option C - plain-text value in `position`, adapters unchanged

**Estimate: 0.35**
Main driver: rewriting `PageNavigation` encoding and its tests. Uncertainty: low.

Only `PageNavigation` changes. It writes the direction and boundary values as plain text, such as `position=next~thread-20261102002605-a0954d22`, and drops version and context. Every adapter keeps receiving the same direction and boundary.

- Pro: smallest change; one file plus tests and docs.
- Pro: keeps every adapter and its validation untouched.
- Con: still a small private format with a direction prefix and a separator.
- Con: multi-field boundaries such as Recent's time and ID stay redundant in the URL.

## Recommendation

Choose Option A. It answers "why not just use position" directly: the parameter stays, and its value becomes the item the page starts with.

Recent is the first vertical slice, because its ID already contains its time. Gallery and profile history follow the same adapter shape, then Board, Search, Tags and Activity. Option C remains a fallback if Step 2 finds the previous-page lookup too costly.

Every option includes three shared tasks. Add a rule to `PRINCIPLES.md` against opaque or encoded state in URLs. Correct [Implementing pagination](../../reference/pagination.md). Check Forte's raw-JSON `cursor` and the private-message cursors in Step 2, deciding which are visible URLs.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-07T00:18:52Z, `railspike-effort` / `v3/readable_page_urls`, intake and Step 1 handoff. Scope v1: Option A as recommended above. Starting revision `20d24918274561ccb07a29f27f43659747417c04`. Reference R1, protocol P2; frozen R1 hashes not re-verified for this checkpoint. Single exposed Claude rater. Before rating, the rater read `PageNavigation.php`, the seven controllers that call it, the boundary checks in `RecentPostRepository`, `AuthoredContentRepository` and `ThreadKeysetPage`, and the pagination reference. Pristine intake is missing. Intake and Step 1 projected total **0.65**. Option estimates 0.65, 0.60 and 0.35 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

## Enhancement Requests

None yet.
