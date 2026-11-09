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
request bypass static and high-traffic caches, so __v was redundant there.
thread_url_hints.js, loaded on every thread page, now removes created_post_id
regardless of that flag. version_check.js still removes its own __v reload token
after using it. Verified with ThreadUrlHintsTest, VersionCheckBehaviorTest, the
browser-signing redirect cases and the compose/reply redirect smoke test.
