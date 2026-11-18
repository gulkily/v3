# Offline reader tag links — one-shot

Cycle: `v3/offline_reader_tag_links`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](offline_reader_tag_links_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/offline_reader_tag_links`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/offline_reader_tag_links.md))
Extended estimates: enabled (no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.02**

Status: planned; next permitted action: implement and verify, then push and open a PR. Merge and deployment need the maintainer.

## Request and authorization

Original request, 2026-11-18 (maintainer, in the Claude session, after being told the offline reader page logs a PHP warning on every render because it reads a setting its controller never passes): "Please fix it".

## Outcome

Bug fix. `templates/pages/offline_reader.php` reads `$tagLinksEnabled` to write `data-tag-links`, and `OfflineReaderController` does not pass it. Two effects:

- PHP logs "Undefined variable $tagLinksEnabled" on every render of the offline reader.
- The undefined value reads as false, so the page always says `data-tag-links="0"`, and `offline_reader.js` then leaves hashtags in post bodies unlinked offline, even though "Hashtag links in posts" (`FORUM_TAG_LINKS_ENABLED`) is on by default.

After the fix the controller passes the flag: no warning, and offline hashtags link when the flag is on and stay plain when it is off.

Feedback item: [PF-20261118-B01](../../feedback/items/PF-20261118-B01.txt), created in the completion commit (none matched "tag links" or "offline reader warning").

## Approach, reuse and risk

`OfflineReaderController::reader()` adds `tagLinksEnabled` from `RouteServices::featureFlags()`, the same flag `TemplateRenderer` uses for online post bodies. No template or script change.

Risk: offline, hashtags in post bodies become links where they were plain text. That is the flag's documented behaviour, but it is a visible change, so it is called out in the PR.

Concepts touched: none changed.

## Completion and verification plan

- `LocalAppSmokeTest::testOfflineReaderFallbackRouteUsesLocalSnapshotShell` extended: `data-tag-links="1"` by default and `"0"` with the flag off.
- The smoke set, with PHP warnings checked in the test server output of `tests/browser/offline_board_toolbar.cjs`.
- A browser check that a hashtag in an offline thread body is a link.

Starting revision and branch: `fd3a9aed` on `main`; branch `fix/offline_reader_tag_links`.

## Planning commit

Pending.

## Study history

- 2026-11-18T15:22Z intake: projection **0.02**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2). Not pristine (after reading the template, controller and script).

## Execution history

- 2026-11-18T15:22Z: traced the warning to the missing template value and found its second effect; record written.
