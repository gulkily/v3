> **Feature plan:** [Step 1](./readable_page_urls_step1_solution_assessment.md) · [Step 2](./readable_page_urls_step2_feature_description.md) · [Step 3](./readable_page_urls_step3_development_plan.md) · [Step 4](./readable_page_urls_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/readable_page_urls`; [host ledger](../effort_estimation_trial.md#cycle-v3readable_page_urls). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.55 (revised; Step 1 0.60, first Step 2 draft 0.65)

## Revised Direction

Maintainer instruction (2026-11-07): "We are still building traction, so you can leave out backwards compatibility." Old `position` links therefore get no redirect. This is a deliberate exception to the "Keep our own backward compatibility" principle, which asks for a documented error when an interface is retired. Ignoring the parameter avoids any error, so no old link breaks badly; it only loses its place.

## Problem

Every paged list hides its place in an unreadable base64 token, such as `/recent/?position=eyJ2ZXJza…`. People and agents cannot read, edit, guess or trust the links they share.

Concepts touched: [collection selection](../../concepts/collection_selection.md) (changed: lists select a page by number, and find the page holding a given item); [static publication](../../concepts/static_publication.md) (used unchanged: any query string already bypasses first-page artifacts). This cycle updates the collection selection entry.

No matching feedback item in `docs/feedback/items/`.

## User stories

- As a reader, I want page links like `/recent/?page=3`, so that I can see where a link goes and type a page number myself.
- As a reader, I want a link to a post's place in a list, so that it still finds that post after new posts arrive.
- As an agent, I want page URLs I can build from a documented pattern, so that I can page through lists without parsing tokens.
- As a contributor, I want a written URL rule, so that new pages do not reintroduce opaque URLs.

## Core requirements

- Board, Search, Tags, Recent, Gallery, Activity and profile history page with `?page=N`. Page 1 has no `page` parameter.
- `?with=<ID>` on every list except Activity opens the page holding that post or thread and brings it into view. Activity lists events, not posts, so it has page numbers only.
- Old `position` tokens are not supported. The parameter is ignored, so an old link opens page 1 of its list without an error. The token decoder is removed.
- Links leave out default values, including `sort=newest` and page 1. No page link carries encoded, versioned or JSON state.
- `PRINCIPLES.md` gains a "URLs are interface" rule, and the pagination reference describes the new scheme.

## Delivery scope

- Work type: application change.
- Files: `PageNavigation` and its partial, the seven list controllers and their read adapters, list row templates where an item needs an anchor, tests and browser tests that build position links, `PRINCIPLES.md`, the pagination reference, the collection selection concept entry and the consolidation checklist.
- Out of scope: Forte, by maintainer instruction (2026-11-07: "Leave forte alone for this cycle"). Its **Load more** cursor is fetched by script and never appears in a shared link.
- Out of scope: the private-message cursors, which are also fetched by script. The private-message read-state token is also out of scope; it is signed to prevent forgery, which is a real reason to encode it.
- Out of scope: path forms such as `/page2/`, path filters, thread slugs and any "show in Recent" control. These remain Enhancement Requests.

## Completion boundary

- Normal entry: the Next, Previous and First page links on each list, and a typed `?page=N` or `?with=<ID>`.
- End-to-end outcome: every list pages through all its items with readable links.
- Recovery: a page number past the end, zero or not a number, a `with` ID that is retracted or not in that list each show the list with a First page link. None of them shows a server error.
- Release condition: smoke and changed tests pass, the browser pagination tests pass, and each list is paged by hand with JavaScript on and off.

## Risks

- **Temporary page targets.** A `with` link resolves to a page number that changes as posts arrive. Impact: a permanent redirect would get cached and point at the wrong page later. Earliest validation: confirm which redirect status the front controller already uses. Mitigation: use a temporary redirect, so every visit resolves afresh.
- **Missing item anchors.** Board and Recent rows carry `data-post-id` but no `id`, so the browser cannot scroll to them. Impact: `with` would land on the right page without showing the post. Earliest validation: list every row template the seven lists use. Mitigation: add one anchor per row, reusing the post's existing fragment name where one exists.
- **Deep page cost.** Page N skips earlier rows instead of seeking past them. Impact: very deep pages get slower. Earliest validation: time page 40 of Recent against the capacity study's 10000-record model. Mitigation: none expected at current size; record the measurement.

## Shared component inventory

- `src/ForumRewrite/Pagination/PageNavigation.php`: change. It builds page-number links; its token encoder and decoder are removed.
- `templates/partials/page_navigation.php`: reuse unchanged; it renders whatever links it is given.
- `src/ForumRewrite/Qdb/QdbBoardPolicy.php` and `templates/partials/board_pagination_nav.php`: inspect for reuse. QDB already parses and bounds `?page=N`.
- `RecentPostRepository`, `AuthoredContentRepository`, `ThreadKeysetPage` (shared by `TagPage` and `ThreadSearchPage`) and `ActivityService::fetchActivityPage`: change from boundary seeks to page offsets, plus a count that finds an item's page.
- `BoardPageController`: change. Its list already sorts in memory, so the page is a slice and `with` is the item's index.
- `HighTrafficMode` rewrite rules: reuse unchanged. Any query string skips the first-page artifact.
- New: none expected.

## Simple user flow

1. A reader opens `/recent/` and selects Next.
2. The address bar shows `/recent/?page=2`.
3. The reader edits it to `?page=5` and lands on page 5.
4. The reader shares `/recent/?with=thread-20261105202334-ba48d42e`.
5. A friend opens it days later and lands on whichever page now holds that post, scrolled to it.

## Success criteria

- No page link on any of the seven lists contains `position`, base64 or JSON.
- Paging from page 1 to the end with Next shows every item once when nothing is posted meanwhile.
- `?with=` lands on the page holding its item for every list except Activity.
- An old `?position=` link opens page 1 of its list, not an error.
- Every recovery case above shows the list with a First page link.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
*— Revised by Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-07T01:35:42Z, `railspike-effort` / `v3/readable_page_urls`, Step 2 handoff. Step 1 Option B explicitly approved. Scope v2 unchanged. Starting revision `20d24918274561ccb07a29f27f43659747417c04`; reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Projected total 0.60 → **0.65**, from new understanding, not changed scope: Activity lists events and cannot take `with`, its old tokens carry an expiring revision, and list rows need anchors. Step 1 values preserved in the Step 1 artifact. No implementation, measured labor or accepted outcome.

Revised 2026-11-07T01:37:43Z, Step 2 revision. Scope v3: Option B without old-token support, by maintainer instruction. Same starting revision, R1, P2 and single exposed Claude rater. Projected total 0.65 → **0.55**, reason changed scope: no token decoder, no per-list boundary conversion, no Activity revision handling. No implementation, measured labor or accepted outcome.

## Enhancement Requests

Carried from Step 1, all not approved and backlog only: path filters with path page numbers, thread slugs, a route audit against the new URL rule, and a "show this post in Recent" control built on `with`.
