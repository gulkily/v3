# QDB page title review checklist

Review the browser document titles of QDB pages and their URL aliases. Replace
generic titles where needed, preserving titles that already identify the page.
Checked review items reflect inspection of route handlers and title producers;
rendered and static checks are recorded separately below.
This checklist was created before title changes. Shared pages available from
direct URLs are included, even when QDB navigation does not link to them.

## QDB pages

- [x] Welcome — `/`.
- [x] Latest quotes — `/latest`, `/?latest`, numbered pages and query pagination. Changed `Board` to `Latest Quotes`.
- [x] Top quotes — `/top`, `/?top`, numbered pages and query pagination. Changed `Board` to `Top Quotes`.
- [x] Leetness — `/leetness`, `/?leetness`, numbered pages and query pagination. Changed `Board` to `1337 Quotes`.
- [x] Random quotes — `/random`, `/?random`.
- [x] Add quote — `/add`, `/?add`, including form responses.
- [x] Search — `/search`, `/?search`, results and pagination.
- [x] Thread listing — `/threads/`, including view and sort options. Retained `Board` for this actual shared forum board; it includes regular threads as well as quotes.
- [x] Quote and regular thread details — `/threads/{id}`, numeric and ID aliases.
- [x] Post details — `/posts/{id}`.

## Shared pages

- [x] About and documentation — `/about/`, `/docs/`, `/docs/{page}`. Retained the index titles; articles now use their catalog title (or relative source path for uncatalogued files) followed by ` - Platform Docs`.
- [x] Activity — `/activity/`.
- [x] Users and profiles — `/users/`, `/users/pending/`, `/profiles/{id}`, `/user/{name}`.
- [x] Tags — `/tags/`, `/tags/{tag}`.
- [x] Account and invitations — `/account/key/`, `/invites/`, `/lobby/`.
- [x] Compose — `/compose/thread`, `/compose/reply`, including form responses.
- [x] Messages — `/messages/inbox`, `/messages/sent`, `/messages/conversation/{id}`.
- [x] Tools — `/tools/`, bookmarklets, outbox, codebase, visitor statistics and feature flags.
- [x] Backup and SQLite — `/instance/`, `/backup/`, `/tools/backup/`, `/tools/sqlite/`.
- [x] Offline — `/offline/`, `/offline/reader/`. Changed the duplicate `Offline Reading` titles to `Offline Reading Status` and `Offline Reader`, respectively.
- [x] LLM exchanges — `/tools/llm-exchanges/` and exchange details.
- [x] Source views — current file, historical blob and commit history. These return plain text, so HTML document titles do not apply.
- [x] API documentation — `/api/`. This returns plain text, so an HTML document title does not apply.
- [x] Alternate Forte views — board, activity, users and profiles when enabled.
- [x] Error pages — missing content and access errors.

## Verification

- [x] Check rendered titles for affected routes and aliases, including pagination.
- [x] Confirm the shared forum board retains its existing title behavior.
- [x] Check static HTML output uses the corrected titles.
- [x] Run relevant existing checks and record results below.

## Results

All other reviewed HTML routes already supply titles identifying their page or
content. Redirect aliases retain `Redirecting` while forwarding to the titled
destination. Pagination retains the section title, including when the requested
page number is clamped. RSS and API responses are outside the HTML title review.

The documentation and offline changes apply to their shared controllers across
profiles. The quote listing changes apply only when the QDB board policy is used.

- PHP syntax checks passed for all three changed controllers.
- 31 rendered title checks passed using a temporary QDB fixture, covering the
  primary routes, classic aliases, numbered pages, out-of-range pagination,
  quote details, documentation and offline pages.
- Six generated static listing files passed title checks: the `.html` and
  `/index.html` forms of Latest, Top and Leetness. Numbered pages use dynamic
  rendering; the static builder emits only the first page of each listing.
- The normal forum board still renders `<title>Board</title>`.
- Existing suites: `QdbExperienceRoutingTest`, `QuoteCardDisplayNumberTest`,
  `PlatformDocsPageTest`, `PlatformDocsStaticTest`,
  `OfflineReadingDiagnosticCommandTest` and `PresentationProfileMatrixTest`:
  **39 run, 38 passed, 1 failed**.
- The failure in
  `QuoteCardDisplayNumberTest::testQdbWelcomeDisplaysThreeNewestNewsItemsAndLinksToAllNews`
  expects `⚑ Flag something that` in the unchanged welcome template. It also
  fails with all three original controllers loaded from `HEAD`, confirming it
  predates these changes.
