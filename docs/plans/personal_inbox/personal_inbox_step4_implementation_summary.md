> **Feature plan:** [Step 1](./personal_inbox_step1_solution_assessment.md) · [Step 2](./personal_inbox_step2_feature_description.md) · [Step 3](./personal_inbox_step3_development_plan.md) · [Step 4](./personal_inbox_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/personal_inbox`; [host ledger](../effort_estimation_trial.md#cycle-v3personal_inbox). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.85** (Step 3 projection reaffirmed at Step 4 entry)

Branch: `feat/personal_inbox`, planning commit `33a76c03`. Starting revision `f343b626`.

## Step 4 entry

2026-11-07, Step 3 approved by the maintainer ("approved step 3"). The Step 3 projection of 0.85 is reaffirmed with no change. Stage identities and original stage-work estimates are copied from the [Step 3 plan](./personal_inbox_step3_development_plan.md): Stage 1 0.12, Stage 2 0.06, Stage 3 0.14, Stage 4 0.06, Stage 5 0.10, Stage 6 0.08, Stage 7 0.05, Stage 8 0.06. Single exposed Claude rater; copying is not a new rating.

Ledger links were repaired to the feature folder in the Stage 1 commit, because the planning commit excludes the ledger.

## Stage 1 - Flag, route and unread conversations

**Original estimate: 0.12**
**Retrospective: 0.10**

- Changes:
  - `FeatureFlagRegistry` registers `FORUM_INBOX_ENABLED` ("Inbox", default off, site mutable), appended to the Forum experience group.
  - `Application` routes `/inbox` and `/inbox/`. With the flag off they answer "not found". The paths join the live application routes.
  - New `InboxPageController` and `templates/pages/inbox.php`. Signed-out and unapproved viewers get `private_message_access.php`, which now takes a `featureName` so its copy reads "Inbox is for approved members". Messages copy is unchanged.
  - `PrivateMessageReadState::unreadConversations`, exposed through `PrivateMessageStore` and `PrivateMessageMailboxService`, lists counterparts with unread messages, newest arrival first, at most 25.
  - The Messages section shows each conversation as a thread row with its name, unread count and latest time, plus an "All messages" link. With private messages off the section is omitted. If private storage fails, the section shows Retry and the page still renders.
- Verification:
  - `./v3 test --smoke InboxPageTest FeatureFlagEvaluatorTest PrivateMessageReadStateTest PrivateMessagePageControllerTest`: 445 run, 444 passed. The one failure, `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, also fails with this stage's changes stashed; the runner reports it as long-standing.
  - `InboxPageTest` (new, registered in `tests/run.php`): flag off gives "Not Found"; signed out gives the Inbox access copy; unread conversations list newest first with counts and no ciphertext; empty, failed-store and messages-disabled states; unapproved viewer.
  - Full suite not run, per project policy.
- Notes:
  - Reuse decision changed from Step 3: Messages rows do not reuse `private_message_conversation_row.php`. That partial needs the decryption scripts to fill its preview, and Inbox lists only who wrote and how many. Rows use the existing thread-row classes instead, so no new CSS was needed.
  - Ledger links now point into the feature folder.
- Estimate details: original scope as planned; uncertainty low; no revision.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 2 - Parent index

**Original estimate: 0.06**
**Retrospective: 0.04**

- Changes:
  - `ReadModelSchema` adds `posts_parent_idx (parent_id)`; `ReadModelMetadata::SCHEMA_VERSION` goes from 50 to 51.
  - `docs/specs/read_model_schema_v1.md` lists the index on `posts` and adds a Version 51 section.
- Verification:
  - `InboxPageTest::testStartupRebuildsAVersion50ReadModelWithTheParentIndex`: a read model set back to version 50 without the index is rebuilt on the next request, and a lookup by parent uses the index.
  - `./v3 rebuild` on a scratch copy of the minimal fixture: 3 posts, 2 threads; the result reports schema 51.
  - `./v3 test --smoke InboxPageTest ActivityProjectionTest` at commit `0eee8731` also failed `ReadModelSchemaTest::testCanonicalStatementsCreateExpectedTablesAndIndexes`, which lists every index. The stage was committed before that failure was read. A follow-up Stage 2 commit adds `posts_parent_idx` to its expected list.
  - After the fix, `./v3 test --smoke InboxPageTest ReadModelSchemaTest`: 438 run, 437 passed; only the baseline `BoardListTest` failure remains. `ActivityProjectionTest` and `BoardSearchTest`, which check the schema version, pass.
  - Full suite not run.
- Notes:
  - Deploying this stage triggers the normal startup rebuild once.
- Estimate details: original scope as planned; uncertainty low; no revision.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 3 - Mark store, Replies and Caught up

**Original estimate: 0.14**
**Retrospective: 0.14**

- Changes:
  - New `ForumRewrite\Inbox\InboxMarkStore` keeps one mark per username and section in `state/private/inbox.sqlite3` (`InboxDatabaseConfig`), plus the post IDs it acknowledged in the 10 minutes before the mark. A mark starts at the current time on first use and only moves forward.
  - New `InboxFeed` lists the oldest 50 unread posts of a section and counts them. Replies are posts whose parent was written by any key of the username, including retired keys, as `ApprovedUserKeyResolver` returns them with history. The member's own posts never count.
  - `RecentPostRepository` shares its select with new `since` and `countSince` queries, so Inbox rows carry exactly what Recent rows carry. `RecentPageController::present` is the extracted row shaping, used by Recent, Gallery and Inbox.
  - Inbox shows Replies with `partials/recent_entry.php` rows and a Caught up form listing the shown IDs. A POST acknowledges only IDs that are still unread in that section, then redirects to `/inbox`. A refused or failed POST re-renders the same items with "Couldn’t save. Nothing was marked read." and a Retry button, status 409.
  - New `SameOriginRequest::check`, extracted from `DeployController::sameOrigin`, which now calls it.
- Verification:
  - `InboxFeedTest` (new): replies to either key, anonymous replies, exclusion of own replies and of replies to others; history before first use; Caught up hides exactly the shown posts while a late post signed during the visit stays unread; forward-only marks and independent sections; bad usernames and sections rejected.
  - `InboxPageTest`: Replies render with links to the exact reply; opening Inbox marks nothing; a cross-site POST changes nothing and shows Retry; a same-origin POST empties Replies.
  - `./v3 test --smoke InboxPageTest InboxFeedTest RecentPostTest GalleryPageTest`: 453 run, 452 passed; only the baseline `BoardListTest` failure. `DeployPageTest`: 5 passed.
  - `state/private/inbox.sqlite3` is covered by the existing ignore rules.
  - Full suite not run.
- Notes:
  - Reuse decision from Step 3 kept: the store follows `PrivateMessageReadState`'s forward-only, transaction-wrapped pattern, in its own file so Inbox works with private messages off.
  - The first 10 minutes before a member's first Inbox visit count as new, because of the late-arrival window. That is deliberate and small.
- Estimate details: original scope as planned; uncertainty medium; no revision.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 4 - New posts

**Original estimate: 0.06**
**Retrospective: 0.03**

- Changes:
  - `InboxPageController::POST_SECTIONS` adds `new`. The page shows New posts after Replies with its own Caught up form; `InboxFeed` already excluded the member's own posts and replies to them.
- Verification:
  - `InboxPageTest::testNewPostsFollowRepliesWithTheirOwnMark`: Replies come first; a reply to me is listed only under Replies; my own new thread is never listed; catching up on New posts leaves Replies unchanged.
  - `./v3 test --smoke InboxPageTest InboxFeedTest`: only the baseline `BoardListTest` failure.
  - Full suite not run.
- Notes:
  - Smaller than planned because Stage 3 built the feed for both sections.
- Estimate details: original scope as planned; uncertainty low; no revision.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 5 - Navigation

**Original estimate: 0.10**
**Retrospective: 0.08**

- Changes:
  - `TemplateRenderer::navItems()` adds Inbox (`/inbox`, member only) while `FORUM_INBOX_ENABLED` is on, outside the QDB profile. In the app shell, `navigationItems()` gives Inbox the Messages slot on the desktop bar and moves Messages into the You menu. With the flag off, navigation is unchanged.
  - Phones: Inbox opens from More like Messages did, and members can pin it to a personal tab. `site.css` gains the Inbox slot rules; `layout.php` and `nav_slots.js` treat Inbox as member-only, like Messages.
  - `theme-nav-icons.css`: an inbox-tray glyph with a down arrow for Word97, Chicago and Midnight.
  - The navigation refresh endpoint needed no change; it renders `navigationItems()`.
- Verification:
  - `AppNavigationPlacementTest::testInboxTakesTheMessagesPlaceOnTheBarOnlyWhileItsFlagIsOn`: flag off keeps Messages on the bar; flag on puts Inbox there and Messages in the menu; Inbox is member-only, slottable on phones and present without private messages; the refresh fragment carries it.
  - `tests/browser/nav_slots.mjs`: passed (Chrome at `/usr/bin/google-chrome`, Playwright linked temporarily, link removed).
  - Screenshots of the icon in Word97, Chicago and Midnight, and of the app shell at desktop and phone width with the You and More menus open. Checked by eye.
  - `./v3 test --smoke AppNavigationPlacementTest InboxPageTest FeatureFlagEvaluatorTest`: only the baseline `BoardListTest` failure.
  - Full suite not run.
- Notes:
  - Maintainer review of the placement is the planned checkpoint before Stage 6.
- Estimate details: original scope as planned; uncertainty medium; no revision.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 6 - Inbox count

**Original estimate: 0.08**
**Retrospective: 0.09**

- Changes:
  - `GET /api/inbox/unread` (no-store, approved members, 404 with the flag off) returns `unread_count`, the sum of unread conversations and unread replies, with each part and whether messages were available. Reading it moves no mark. It is listed in `ApiRouteCatalog`.
  - New `public/assets/inbox_unread.js`, loaded on every page while the Inbox item exists, so server HTML stays the same for every viewer. It asks only when the browser is remembered as approved. It sets `data-unread-count` on the Inbox link and its mirrors, and writes the count to a visually hidden status span. It refreshes on load, focus, return to the tab, `pageshow` and sign-in in place; it never polls. A failure removes the count rather than leaving a stale one.
  - `nav.php` marks the Inbox link and its hidden status span. In `app_header.php` the You/More trigger mirrors the Inbox count while Inbox exists, and the Messages link keeps its own message count wherever it sits, now including the You menu.
  - The existing outline rules draw the count; no CSS was added.
- Verification:
  - `InboxPageTest::testCountAddsUnreadConversationsAndRepliesButNotNewPosts`: 1 conversation + 1 reply = 2, new posts excluded; reading twice marks nothing; Caught up on Replies lowers it; replies only without private messages; a failing message store still returns the reply count; unapproved viewers get an error.
  - New `tests/browser/inbox_unread.mjs`: at desktop and phone width, link, More tab and status agree; changing the count from 3 to 0 and then failing moves no header box; a browser not remembered as approved makes no request. Passed.
  - `tests/browser/nav_unread_outline.mjs` now also checks the Inbox link in all 16 themes: no layout shift, outline only above zero. Passed. `nav_slots.mjs` passed.
  - `./v3 test --smoke InboxPageTest AppNavigationPlacementTest InboxFeedTest`, then `ApiRouteCatalogTest`: the catalog failure from the first run is fixed; only the baseline `BoardListTest` failure remains.
  - Full suite not run.
- Notes:
  - Reuse decision changed from Step 3: the count is summed on the server and drawn by a small separate script, instead of extending `private_message_unread.js`. That script loads only for messaging and binds to the Messages link, while Inbox must work with private messages off.
  - The conversation part counts at most 25 conversations; any count still shows the outline.
  - If an operator turns the flag on mid-visit, the count appears on the next page load.
- Estimate details: original scope as planned; uncertainty low; no revision.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
