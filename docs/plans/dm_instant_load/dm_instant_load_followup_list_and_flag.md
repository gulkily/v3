# DM instant load follow-up: Messages list, flag fix, earlier lookups

Direct work after [PR #169](https://github.com/gulkily/v3/pull/169) merged, from the maintainer's iPhone Safari check (Stage 6 of the [Step 3 plan](./dm_instant_load_step3_development_plan.md)): reopening was faster, but placeholders still showed on both the Messages list and conversations. The maintainer asked for the same experience in both.

## Findings

- **Flag never reached the page.** `privateMessageCacheEnabled` was passed to the layout template only; page templates render first with their own data, so `data-private-message-cache` was always `0` and saved copies never ran in production. The speedup seen was Stage 1 alone. The #169 "flag on" browser runs passed because the check silently took its flag-off branch.
- **List previews ignored the cache** and waited for `/api/private_messages/conversations` before reading anything.
- **The 400ms safety reveal was too short.** With 6x CPU throttling in Chromium, saved copies appear 0.6–0.9s after navigation (mostly waiting for the page's scripts), so a slower phone would show placeholders first.
- **OpenPGP preload competed** with reading saved copies.

## Changes

- `TemplateRenderer::renderPageTemplate` passes the flag to page templates.
- `private_message_cache.js` starts one batched lookup for every message id on the page as soon as it runs (before the reader and later scripts); `get` returns those results.
- `private_message_list.js` fills server-rendered rows from saved copies immediately, skips decrypting rows already filled, and saves verified previews. Rows carry `data-sender` for the "You:" prefix. The list request still runs in parallel for anything new.
- List loading placeholders stay blank until saved copies are checked; both safety reveals are now 2s (only reached if scripts fail).
- Conversation pages with the flag on preload OpenPGP only after saved copies are checked, and only if a message still needs it.
- Concept entry updated for list previews.

## Checks

- Messaging browser suite with the flag on and off through `checkCachedConversation` (bypassing the two baseline failures recorded in the Step 4 summary): passed. The check now asserts the flag reaches the page; with it on, every shown message is saved, a reopened conversation renders all messages at reveal with no conversation fetch and no reader-initiated OpenPGP load, and the Messages list fills that conversation's preview while its list request is held.
- Mutation check: forcing cache misses makes `checkCachedConversation` fail.
- Timing (Chromium, flag on, saved copies present): messages shown with zero placeholders at reveal; ~0.2s after navigation unthrottled, 0.6–0.9s at 6x CPU throttling.
- `private_message_cache_browser.mjs`, `testing_self_approval.mjs`: passed.
- `./v3 test --smoke PrivateMessageReaderTest FeatureFlagEvaluatorTest PrivateMessageListTest PrivateMessagePageControllerTest TemplateRendererMediaEmbedsScriptTest`: 459 passed. Full suite not run.
- Baseline, reproduced on clean `origin/main`: `checkChatRecovery` timeout, and with that step bypassed, the list's "Retry preview" step for user-57 times out; later list steps are unreached on main too.
- **Not verified:** iPhone Safari. History sync still loads OpenPGP for its own background work after the unread check; left unchanged.

*— Claude · 2026-11-12 UTC · Model: Claude Opus 5.5*
