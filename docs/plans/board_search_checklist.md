# Board Search Checklist

Working checklist for full-text search of the zenmemes board from the
ordinary web UI. It is not a Railspike artifact and approves no
implementation. Start each item as its own Railspike cycle or Direct change,
following AGENTS.md. Record progress, checks and limitations here.

Requested by the user on 2026-11-03: as a zenmemes user, search the board
from the frontend, reusing the qdb search where possible.

## Reuse inventory

- `src/ForumRewrite/ReadModel/QdbPage.php` already selects search results
  by counting matches, choosing one page of thread IDs and then hydrating
  them. Its eligibility rules are qdb-specific.
- `templates/pages/qdb_search.php` is a plain GET form with a result count
  and the shared `partials/board_pagination_nav.php`. Its quote cards do not
  fit zenmemes, which uses the list board card.
- `/search` is already in the application route list. Its nav icon already
  exists in `public/assets/theme-nav-icons.css` for Word97, Chicago and
  Midnight.
- `BoardPageController::search()` has no caller. It filters every thread in
  memory and should be removed rather than copied.
- `src/ForumRewrite/Analysis/RelatedContentSearchService.php` has
  tokenizing and excerpt code that later items can reuse.
- Docs search is a browser-side JSON index and does not fit the board.

## Estimates

Stage-work judgments on the `railspike-effort` R1 scale, where 1.0 is the
largest comfortably approved single-cycle slice. R1 hashes were verified on
2026-11-03. These are single exposed-rater annotations, not ledger
snapshots, and must not be summed. A cycle that starts an item records its
own intake snapshot under protocol P2.

## Checklist

- [ ] **1. Thread-only search, like qdb.**
  **Estimate: 0.25**
  Search the subject and body of opening posts only, using the same
  case-insensitive substring match as qdb. Exclude retracted threads and
  hidden posts. Order by latest activity and paginate with the shared
  pagination partial.
  - Extract the count, select and hydrate flow from `QdbPage` into a shared
    selection. qdb keeps its eligibility rules and output unchanged.
  - Serve `/search?q=` for zenmemes with the existing list board card. The
    page has one search field and no explanatory copy. It works without
    JavaScript and is final on first paint.
  - Add a Search entry to the zenmemes app navigation. Check the existing
    icon in Word97, Chicago and Midnight, and check text in the other
    permitted themes.
  - Remove the unused `BoardPageController::search()`.
  - Verify through the ordinary UI that an empty query, no results, multiple
    pages and a just-retracted thread all behave correctly. Run the qdb
    search tests to confirm qdb is unchanged.
  - No read-model schema change and no new dependency.
- [ ] **2. Include replies.**
  **Estimate: 0.30**
  Match reply bodies as well. Show each matching post with an excerpt,
  grouped under its thread and linked to the post. Hidden and retracted
  replies stay out. Measure query time on a production-sized read model
  before choosing whether item 5 is needed.
- [ ] **3. Plain-text search for agents.**
  **Estimate: 0.10**
  Add a documented plain-text API endpoint that returns the same results,
  and list it in the API route catalog and `/llms.txt`.
- [ ] **4. Filters.**
  **Estimate: 0.25**
  Add author, tag and date filters as GET parameters, with a chronological
  and a relevance order.
- [ ] **5. SQLite FTS5 index, only if item 2's measurement needs it.**
  **Estimate: 0.45**
  Confirm FTS5 on the production host first. Follow the read-model schema
  rules in CONTRIBUTING.md. Keep the index current on new, hidden and
  retracted posts, consistent with the
  [retraction simplification handoff](retraction_simplification_handoff.md).

## Open questions

- Whether high-traffic static mode needs special handling for a dynamic
  search page.
- Whether item 1 should use `q` or qdb's `search` parameter. Item 1 assumes
  `q`, matching docs search, and leaves qdb's classic URL alone.
