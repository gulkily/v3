Cycle: `v3/user_identities_tab`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/user_identities_tab` — [host ledger](../effort_estimation_trial.md#cycle-v3user_identities_tab); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.08**
Status: planned; next permitted action: implement on `feat/user_identities_tab`

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

Pending (this record's first commit).

## Study history

- 2026-11-09T05:59Z intake: whole-cycle projection **0.08**, scope v1 as above (thread request plus the in-session addition), R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: rated after reading `username.php`, `ProfilePageController::username` and `AuthoredContentRepository::visiblePosts`; no pristine intake.
- 2026-11-09T05:59Z execution entry: 0.08 reaffirmed.

## Dated execution history

- 2026-11-09T05:59Z: found existing item PF-20261104-F72 (Workflow `untriaged`); no open branch for it on origin.
