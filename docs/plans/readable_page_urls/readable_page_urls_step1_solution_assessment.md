> **Feature plan:** [Step 1](./readable_page_urls_step1_solution_assessment.md) · [Step 2](./readable_page_urls_step2_feature_description.md) · [Step 3](./readable_page_urls_step3_development_plan.md) · [Step 4](./readable_page_urls_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/readable_page_urls`; [host ledger](../effort_estimation_trial.md#cycle-v3readable_page_urls). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.60 (revised; original 0.65)

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

## Revised Direction

Maintainer follow-up (2026-11-07), after reading Marcin Wichary's [Unsung Heroes: Flickr's URLs scheme](https://unsung.aresluna.org/unsung-heroes-flickrs-urls-scheme/) and its [mailbag](https://unsung.aresluna.org/mailbag-urls-as-ui): "All of these are good guidelines, period." Asked how Flickr handled pagination, then: "Is it really that big a deal if a new item is posted and a user sees duplicates?" Then: "Please go ahead. Consider adding another option using the with method in addition to page numbers."

Flickr put page numbers in the path, such as `/photos/<user>/page2/`. It also offered `/photos/<user>/with/<photo-id>/`, which shows the page containing that photo. Neither scheme could be confirmed from a current source; Flickr pages now render by script.

Duplicates matter little here. A new post pushes items down one slot, so the next page repeats one item. A retraction shifts items up and skips one, but retractions are rare. Keyset paging does not prevent the worse case either: a thread that gets a reply jumps to the top under both schemes.

This revision adds page-number options, keeps the item-anchor and plain-token options, and drops the `before`/`after` option. That option needed two parameters and named an item the reader cannot see.

Prior decision: the [consolidation checklist](../pagination_consolidation_checklist.md) chose not to require totals or arbitrary jumps. Page numbers need neither totals nor a jump control, but they do reverse its keyset choice. That reversal is the maintainer's call.

Problem: Page links on every list hide one ID inside an unreadable token, so people cannot read, edit, guess or trust the URLs they share.

Concepts touched: [collection selection](../../concepts/collection_selection.md) (changed: how a list selects its page); [static publication](../../concepts/static_publication.md) (used unchanged: any query string already bypasses first-page artifacts).

No matching feedback item in `docs/feedback/items/`.

## Shared by every option

- Add a "URLs are interface" rule to `PRINCIPLES.md`. URLs are short, readable, shareable as they are, editable, guessable and permanent. State may sit in a URL only when the page cannot work without it. Cite Wichary and "Cool URIs don't change".
- Links leave out default values, such as `sort=newest` and page 1. Removing a parameter therefore never changes what a page shows, beyond returning to its default.
- Old `position` tokens keep working, as "Don't break what already works" requires. Each one opens the page that holds its boundary item.
- Correct [Implementing pagination](../../reference/pagination.md) to describe the chosen scheme.
- Step 2 decides whether Forte's raw-JSON `cursor` and the private-message cursors are visible URLs, and therefore in scope.

## Option A - page numbers

**Estimate: 0.50**
Main driver: replacing seven keyset adapters with counted offsets. Uncertainty: low to medium, mostly in Activity's mixed item kinds.

Every list uses `?page=2`, `?page=3` and so on, like QDB and Platform docs already do. Next and Previous link to the neighbouring numbers. Search and Tags need no timestamps, because the page number replaces every boundary. An old token counts the items before its boundary and redirects to that page number.

- Pro: the shortest, most guessable URL; a reader can type any page directly.
- Pro: one scheme across the whole site, including QDB and Docs.
- Pro: removes all boundary formats, including the timestamps that would need escaping.
- Con: a new post shifts every page by one item, so readers may see one repeat.
- Con: a shared "page 4" link shows different posts later; permalinks stay the stable link.
- Con: deep pages scan past earlier rows; negligible at thousands of posts.

## Option B - page numbers plus `with`

**Estimate: 0.60**
Main driver: Option A plus one lookup that finds the page containing a given item. Uncertainty: medium, mostly in the case where that item was retracted.

Option A, plus `?with=<post-id>` on every list. It opens the page that contains that post and marks the post. Paging from there uses ordinary page numbers. Old tokens redirect through the same lookup. The lookup also gives later work a stable "show this post in Recent" link.

- Pro: everything in Option A.
- Pro: a link that stays meaningful after new posts arrive, unlike a page number.
- Pro: old tokens and future in-list links share one lookup.
- Con: two parameters to document, though each one reads as plain English.
- Con: a retracted target needs a fallback, such as the time inside its ID.

## Option C - `position` names the first item on the page

**Estimate: 0.65**
Main driver: computing the Previous link in seven list adapters. Uncertainty: medium, mostly in Activity's mixed item kinds and the activity-sorted thread pages.

The `position` parameter keeps its name and holds the ID of the first item shown, such as `position=thread-20261102002605-a0954d22`. Search and Tags add a compact activity time, such as `20261102002605`, to avoid escaped characters. Each adapter finds the first item of the previous page while it renders the current one.

- Pro: pages never shift, so no repeats.
- Pro: the URL names the first post the reader sees.
- Con: IDs are long, and no one can guess the next page's URL.
- Con: each adapter needs a second bounded query for the Previous link.

## Option D - plain-text value in `position`, adapters unchanged

**Estimate: 0.35**
Main driver: rewriting `PageNavigation` encoding and its tests. Uncertainty: low.

Only `PageNavigation` changes. It writes the direction and boundary values as plain text, such as `position=next~thread-20261102002605-a0954d22`, and drops version and context.

- Pro: the smallest change; one file plus tests and docs.
- Con: still a small private format with a direction prefix and a separator.
- Con: fails the guessable and editable tests in the new rule.

## Recommendation

Choose Option B. Page numbers meet every guideline the maintainer endorsed, and `with` covers what page numbers cannot: a link to a post's place in a list.

Recent is the first vertical slice: page numbers, `with`, and redirects for old Recent tokens. Gallery, profile history, Activity, Board, Search and Tags follow, each using the same lookup. Path forms such as Flickr's `/page2/` wait for the separate path-filter follow-up, so URLs change only once.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
*— Revised by Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-07T00:18:52Z, `railspike-effort` / `v3/readable_page_urls`, intake and Step 1 handoff. Scope v1: Option A as recommended above. Starting revision `20d24918274561ccb07a29f27f43659747417c04`. Reference R1, protocol P2; frozen R1 hashes not re-verified for this checkpoint. Single exposed Claude rater. Before rating, the rater read `PageNavigation.php`, the seven controllers that call it, the boundary checks in `RecentPostRepository`, `AuthoredContentRepository` and `ThreadKeysetPage`, and the pagination reference. Pristine intake is missing. Intake and Step 1 projected total **0.65**. Option estimates 0.65, 0.60 and 0.35 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

Revised 2026-11-07T00:49:18Z, same `railspike-effort` / `v3/readable_page_urls`, Step 1 revision. Scope v2: Option B, page numbers plus `with`. Same starting revision, R1, P2 and single exposed Claude rater. Reason: new requirement after the maintainer accepted repeated items and asked for page-number options. Projected total 0.65 → **0.60**. Option estimates 0.50, 0.60, 0.65 and 0.35 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

## Enhancement Requests

- Filters in the path, such as `/threads/liked/` instead of `?view=liked`, with path page numbers like `/page2/`. Disposition: separate follow-up cycle.
- Readable slugs after thread IDs, such as `/posts/<thread-id>/<title-slug>`, with the ID authoritative. Disposition: separate follow-up cycle.
- An audit of every route against the new URL rule. Disposition: separate follow-up cycle.
- A "show this post in Recent" link on post pages, built on `with`. Disposition: separate follow-up after this cycle.
