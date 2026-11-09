Title: Clean thread URLs after creation
Date: 2026-10-28
Status: Implemented
Scope: Small direct change requested by the maintainer

Behavior: After the version checker consumes pending navigation state, thread
pages remove created_post_id and __v using history.replaceState. The initial
request still carries both hints. Reload uses the clean URL. Other query
parameters, reply anchors and existing history state remain intact. Forte keeps
its created_post_id parameter because its client uses it for post selection.

Verification: All eight VersionCheckBehaviorTest checks passed, including hint
cleanup, preservation of query parameters and anchors, and pending-version
redirect ordering. Headless Chrome checks at 390px and 1440px confirmed initial
URL cleanup and a clean reload request with the reply anchor preserved.

Limitations: Browser checks used a minimal routed HTML fixture with the real
version checker, not a live posting flow. No theme or layout changes. Browsers
without JavaScript or the History API retain the existing URL behavior.

Dependencies: No additions or updates.

Update 2026-11-09: The cleanup only ran when the version-notification flag was on,
because it lived in version_check.js, so production thread URLs kept both hints.
Post-submit redirects no longer add __v: created_post_id alone makes the first
request bypass static and high-traffic caches, so __v was redundant there. All URL
stripping now lives in url_hints.js, loaded on every page right after the version
checker whatever the flag state: it removes __v on any page and created_post_id on
thread pages. version_check.js only reads __v and no longer edits the URL; while it
is starting a pending-version reload it marks the page (data-version-redirect) so
url_hints.js leaves the URL alone. Verified with UrlHintsTest, VersionCheckBehaviorTest,
the browser-signing redirect cases, the compose/reply redirect smoke test, and
headless Chrome with and without the version checker on the page.

Update 2026-11-09 (later): Trimming on the first load made the address bar flash the
full URL and then shorten it, which the maintainer found jarring. url_hints.js now
leaves the hints in place on the visit that brought them and removes them only when
the page is reloaded (Navigation Timing type "reload"). Verified with UrlHintsTest
and a headless Chrome load-then-reload check.
