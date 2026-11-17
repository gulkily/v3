# Pending chip: summary and approval in place (Direct)

Request (Ilya, 2026-11-14): clicking the "Pending" pill next to a user's name
shows a summary of that user in the page and lets an approver approve them,
with the result taking effect in the page.

## Reuse

- Chip: `renderRowMeta()` in `src/ForumRewrite/View/TemplateRenderer.php`
  (Board list rows, Recent, user page posts, reply context). The existing
  `.chip.chip--pending` span is kept and wrapped in a link to the profile, so
  every theme's chip styling still applies and the chip still works without
  script (the profile page has Approve).
- Summary data: existing `GET /api/get_profile` (`Username`, `Threads`,
  `Posts`, `Approved`), as the Forte profile summary dialog uses. No API change.
- Approval: existing `ForumBrowserSigning.submitSignedApproval(slug, { confirm:
  false })`, the same call as the Waiting list's one-click Approve; the dialog
  is the review step. Signing scripts load on first use from
  `window.__forumAssetPaths` (`openpgpLoader`, `browserSigning`) on pages that
  do not already include them.
- Viewer gating: the Approve button is `[data-member-only]`, hidden before
  paint by the existing `:root:not([data-member="approved"])` rule. The server
  still decides authority in `prepare_approval`.
- Dialog: new, because no site-wide dialog component exists (Forte's
  `paned-*` dialogs are styled only in `forte.css`). It is a native `<dialog>`
  with the `.card` class, built by the script on first use, so server HTML is
  unchanged for every viewer.

## Behaviour

- Plain left click on the chip opens the summary; modified clicks follow the
  profile link.
- Approve success closes the dialog and removes every chip for that key on the
  page. A key found already approved when the summary loads is settled the same
  way. Failure shows the error in the dialog and keeps Approve enabled.

## Verification (2026-11-14)

- `node tests/browser/pending_chip_approval.mjs`: summary, member-only
  Approve, lazy signing load, signing failure then retry, chips removed for
  that key only, modified click untouched; 1280x800 and 375x667.
- `node tests/browser/approval_review.mjs` still passes.
- Local instance with an imported ZenMemes copy and a seeded approved browser
  key: approved a pending key from `/recent/` end to end (approval record
  written, `get_profile` reports `Approved: yes`, chips gone, and gone after
  reload).
- Screens checked at 375 and 1280 wide in Gloss, Gloss Dark, Word97,
  Midnight, Bubblegum, Chicago, Engagify, Light and Dark. Word97/Midnight
  boxed unclassed card paragraphs, so the counts line carries a class.
- `./v3 test --smoke BoardListTest WriteApiSmokeTest`: all pass except
  `WriteApiSmokeTest::testLlmExchangeLinksAppearOnlyOnTheirPostDetailPages`,
  which fails the same way on `main`. Full suite not run.

## Follow-up (2026-11-14, same PR)

Requested after a first look: a feature flag, the popup beside the chip, a
top-right close, and a sample of recent participation.

- Flag `FORUM_PENDING_CHIP_APPROVAL_ENABLED` ("Approve from Pending chips",
  Experience group, on by default, site-mutable). Off, the chip is the old
  plain `.chip--pending` label and `pending_user_summary.js` is not loaded.
- Placement: the dialog stays a modal `<dialog>` (Escape, focus, backdrop
  click) but is positioned in script just below the chip, left edges aligned,
  or above it when there is more room there, clamped to the viewport and kept
  there on scroll and resize.
- Close is an icon button (`×`, `aria-label="Close"`) at the top right.
- Recent participation: `GET /api/get_profile` now ends with up to three
  `Recent-Post: <created_at> TAB <thread_id> TAB <post_id> TAB <text>` lines,
  taken from `AuthoredContentRepository::visibleIdentityPage()` (the profile
  page's Posts list, so the same visibility rules). Text is a thread's title or
  a reply's opening words, one line, at most 120 characters. The popup shows
  each as a one-line link with its date.
- Checks: browser test extended (recent posts, opens just below the chip,
  above it near the bottom of the window); `BoardListTest` flag-off case;
  `AgentApiWorkedExampleTest` asserts the `Recent-Post` line; a live server
  with the flag off renders plain chips without the script; real approval
  from `/recent/` repeated; all themes checked again at 375 and 1280 wide
  (a grid column was letting long titles widen the popup; fixed).
  `./v3 test --smoke BoardListTest FeatureFlagEvaluatorTest
  AgentApiWorkedExampleTest`: 454 passed. Full suite not run.

## Fix: page jumped on open (2026-11-17)

Reported from production on desktop Chrome and Firefox: opening the summary
scrolled the page. Cause: Bubblegum and Sticker (also chouse and MIT Rap
Club) set `:root[data-theme="..."] .card { position: relative }`, which
outranked the dialog's `position: fixed`. A non-fixed modal dialog sits at the
top of the document, and focusing its close button scrolled the page there.
The positioning rule is now `:root dialog.pending-user-dialog.card`, which
outranks those theme rules.

Checks: reproduced on zenmemes.com in Chrome and Firefox (every theme; only
Bubblegum and Sticker jumped, to the top) and confirmed the rule stops it
there; the browser test now opens the summary on a scrolled page under the
real Bubblegum and Sticker stylesheets (fails without the fix);
`./v3 test --smoke`: 470 passed. Full suite not run.

## Not covered

- Thread pages mark unapproved authors with "(unapproved)" text, not a chip,
  so they do not get the summary.
