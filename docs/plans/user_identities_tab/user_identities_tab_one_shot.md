Cycle: `v3/user_identities_tab`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/user_identities_tab` — [host ledger](../effort_estimation_trial.md#cycle-v3user_identities_tab); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.08 (scope v2: 0.12)**
Status: implemented and verified locally; next permitted action: push and PR (merge and deploy need the maintainer)

## Original request

Thread https://zenmemes.com/threads/thread-20261103193401-0e0f7d33 (feedback item PF-20261104-F72): the /user/ilyag page should have Threads, Replies and Identities tabs, with the identities section moved into the Identities tab instead of sitting at the bottom of the page.

Added by the maintainer in session, 2026-11-09: "can we also list the most recent activity from that identity and when it was most recently active and sort them reverse chronologically?"

## Authorization

In a Claude session on 2026-11-09 the maintainer asked about adding the Identities tab, confirmed the thread, then wrote "While we're doing this, can we also list the most recent activity from that identity and when it was most recently active and sort them reverse chronologically?" Read under `docs/agents/thread_to_pr.md` as the go-ahead for a one-shot cycle on the bounded outcome below, pushing the branch, opening a PR and replying in the thread. Merging and deploying are not covered.

## Outcome and work type

Application change. Normal entry: `/user/{username}` and its tabs.

Outcome: the person page has three tabs, Threads, Replies and Identities (`?tab=identities`). The identities list moves out of the bottom of every tab into the Identities tab. Each identity row still links to its key page and shows its approval or retired status, and now also shows the date it was last active and the first line of its most recent post. Rows are sorted newest activity first; identities with no visible posts come last, in their existing order.

Excluded: reactions, flags, approvals and other non-post activity (a later slice if wanted); paging; the operator "Manage access" link stays where it is.

## Approach and risks

"Activity" means the identity's latest visible post (thread or reply). The page already loads every visible post for the person's identities (`AuthoredContentRepository::visiblePosts`, newest first) to fill the Replies tab, so the controller groups those rows by `author_profile_slug` and takes the first per identity. No new query and no schema change.

Considered and set aside: the `activity` read-model table (as `ProfileRepository::pendingDirectoryProfiles` uses) would also catch reactions and other records, but it is not filtered for hidden or retracted posts the way `visiblePosts` is, so it could surface a label from a hidden post on a public page. One obvious approach remains.

Risk: theme styling. Word97 and Midnight draw the `Identities` heading as a window title bar over the key list. The tab replaces the heading (the Threads and Replies tabs have none), so those heading rules and their coverage-test entry go too. Check the Identities tab in the default, Word97 and Midnight themes at phone and desktop widths.

## Reuse decisions

- Tabs: the existing `nav.board-filter` links in `templates/pages/username.php`.
- Rows: the existing `.account-group` / `.account-row.account-row--link` key rows, with `account-row__title` and `account-row__value`; one small added secondary line inside the title for the latest post.
- Dates: the `$shortDate` template helper (`<time>` with the friendly timestamp as its title).
- Post text: `$plainText` plus the page's existing `$firstLine` helper, as the Replies tab already uses.
- Data: `AuthoredContentRepository::visiblePosts` rows already passed to the template.

## Concepts touched

None.

## Verification and recovery plan

- `LocalAppSmokeTest`: Identities tab link present; identities absent from the Threads tab; `?tab=identities` lists the key with last-active date and latest post line.
- `Word97ThemeCoverageTest` after removing the heading selector.
- `tests/browser/users_profile.mjs` and `tests/browser/account_key_approval.mjs` expect `#account-keys` on the default tab; move them to the Identities tab.
- Render the tab in default, Word97 and Midnight at phone and desktop widths if a browser is available here; otherwise say what was not checked.
- Smoke set plus changed tests only; full suite not run.

## Starting revisions and branch

Host `main` at `8f551ffb9494f40204bed66e3c66441315f9e727`; branch `feat/user_identities_tab`.

## Planning commit

`f2c58b68` (confirmed with Git).

## Study history

- 2026-11-09T05:59Z intake: whole-cycle projection **0.08**, scope v1 as above (thread request plus the in-session addition), R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: rated after reading `username.php`, `ProfilePageController::username` and `AuthoredContentRepository::visiblePosts`; no pristine intake.
- 2026-11-09T05:59Z execution entry: 0.08 reaffirmed.

## Dated execution history

- 2026-11-09T05:59Z: found existing item PF-20261104-F72 (Workflow `untriaged`); no open branch for it on origin.
- 2026-11-09T06:10Z: implemented as planned. `ProfilePageController::identitiesByLatestPost` pairs each key with its first (newest) row from `visiblePosts` by `author_profile_slug` and sorts; `personTab` accepts `identities`. `username.php` adds the tab and moves `#account-keys` into it; each row shows status and the plain first line of the latest post (subject when present) under the key, and the short date (or "No posts") as its value. Removed the `Identities` heading and its rules in `site.css`, `theme-word97.css` and `theme-midnight.css`, and its entry in `Word97ThemeCoverageTest`. Added `.person-identity` layout rules (two-line title, ellipsis on the latest line).
- 2026-11-09T06:10Z: no nav icon change: the tabs are `board-filter` links, not navigation destinations, and Threads/Replies have no icons.
- 2026-11-09T06:15Z: updated PF-20261104-F72 (`Done when`, Workflow `blocked`, Evidence `locally-verified`, dated note).

## Scope v2 (2026-11-09)

Maintainer, in session after reviewing the PR, 2026-11-09: "You can make the identity cards a little bit taller so that they can fit more information. Right now, it's barely displaying the last activity, and the last active time is only the date period. We also want the tabs to be instant. Right now, they take a second or two to go back to the server." Renewed one-shot authority for both additions on the same unmerged branch.

- 2026-11-09T06:30Z: identity rows now have three lines: key; "Active <relative time> · <status>" using `$relativeTimestamp` (full timestamp in the `<time>` title); and the latest post (subject, else whitespace-collapsed body, up to 240 characters) clamped to two lines. The separate date value column is gone.
- 2026-11-09T06:30Z: tabs switch in place. All three panels render server-side in `[data-person-panel]` wrappers with only the requested one visible, so first paint is final and no-JavaScript navigation is unchanged. A small inline script (same pattern as `calendar.php`) toggles panels and `aria-current` on plain clicks and uses `pushState`/`popstate`, so each tab keeps its `?tab=` URL and Back/Forward work. Cost: the page HTML now carries all three lists; they were already fully queried on every request.
- Verification: smoke set plus changed tests 442/443 (same pre-existing `BoardListTest` failure); `users_profile.mjs` passes with new checks (no reload on tab click, Back/Forward restore the tab, no-JavaScript path still passes); `account_key_approval.mjs` passes the identities section and hits the same pre-existing later timeout; screenshots in default, Word97 and Midnight at 390px and 1200px.
- Study: scope v2 revision, whole-cycle projection 0.12 (from 0.08), reason: two in-session additions.

## Study closeout

Intake 0.08; execution entry reaffirmed; scope v2 revised to 0.12; **retrospective 0.12**. Single exposed rater Claude (claude-opus-5-5), R1/P2. Maintainer acceptance pending; no accepted-count increment.

## Outcome

Delivered: `/user/{name}` has Threads, Replies and Identities tabs. The key list appears only on Identities; each key shows its status, the first line of its latest visible post and the date it was last active, most recently active first, with keys that have no visible posts last. Excluded as recorded: non-post activity (reactions, flags, approvals).

## Verification

- `./v3 test --smoke LocalAppSmokeTest::testApplicationRendersCoreRoutes Word97ThemeCoverageTest`: 441 of 442 pass; the one failure, `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, is pre-existing on `main` (recorded in earlier cycles).
- `php tests/run.php PersonIdentityOrderTest` (new): newest-first order, quiet keys last in original order, latest post per key. Passes.
- `node tests/browser/users_profile.mjs` (Chrome): passes, including the new Identities tab checks (order, quiet keys last, no horizontal overflow at 390px).
- `node tests/browser/account_key_approval.mjs`: passes the updated Identities tab section, then times out later at the signed-approval "cancelled" feedback; the same timeout happens on unchanged `main`, so it is pre-existing and unrelated.
- Screenshots of `/user/{name}?tab=identities` with one key and one post: default, Word97 and Midnight at 390px and 1200px. Row reads well; the latest-post line truncates on phones.
- Full suite not run. Not checked: other themes (Gloss, Chicago, Engagify and the rest), several keys with mixed activity in a browser (covered by the unit test only), Firefox/WebKit, a physical phone, production.

## Final review

Reuses the existing tabs, key rows, `$shortDate`, `$plainText` and the already-loaded post list; no new query, dependency or schema change. Within the recorded scope.

## Commit status

Planning commit `f2c58b68`; scope v1 completion `9f4db420`; scope v2 commit pending at the time of writing (this record cannot hold its own hash).
