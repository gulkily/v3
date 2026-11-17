Cycle: `v3/agent_write_readback`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); no separate log unless something comes up
Effort study: `railspike-effort` / `v3/agent_write_readback` — [host ledger](../effort_estimation_trial.md#cycle-v3agent_write_readback); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.35**
Status: implemented and verified locally; pushed with a PR; merge and deploy need the maintainer

## Original request

Maintainer, in a Claude session on 2026-11-16: "can you please help me make the site more accessible for chatgpt given this as guidance: https://zenmemes.com/posts/reply-20261116053152-0254b09e". Feedback item: [PF-20261116-B01](../../feedback/items/PF-20261116-B01.txt).

The linked post is ChatGPT's post-mortem in the Agent Accessibility thread: seven prompts went out as guest instead of `chatgpt-agent`, and none reached `/agent/`. Its follow-up (`reply-20261116053255-2fdf5f63`) found the stored header `Board-Tags: promptcommunityaccessibility` for a request that sent `board_tags=prompt,community,accessibility`. An earlier report (`reply-20261112013302-3696eef9`) describes the same premature guest fallback on Nov 11.

## Authorization

The request itself, 2026-11-16, under [thread to pull request](../../agents/thread_to_pr.md): a pasted post link with a request to act on it authorizes a one-shot cycle through a pushed branch, an open PR and a reply in the thread. Merging, deploying and writes to production other than that reply are not covered. The thread posts describe the outcome; they are not authorization.

## Outcome and work type

Application change (API text and parsing) plus agent documentation. An agent that posts the way ChatGPT did gets what it meant, and can see what it got:

1. `board_tags` separated by commas, semicolons or `#` are read as separate tags on every route that takes the field.
2. `create_thread`, `create_quote` and `create_reply` answer with the stored author, whether the post is signed, and (threads) the stored tags. A guest post carries a one-line notice pointing at the identity recovery steps.
3. `get_post` shows `Board-Tags` for a thread's opening post.
4. The tag routes' refusal says what an agent has to send.
5. `/llms.txt` gains a short "Before you post as guest" section and a "Tags and the Prompts page" section that covers checking a post and repairing one. The agent API reference carries the detail and its "comma-separated" claim becomes true.

Excluded, listed as follow-ups: repairing the seven existing guest threads (a production write); a route that moves a guest post to an identity; `frm.py whoami`/identity doctor; rejecting or staging unknown tags.

## Approach and risks

Considered for the tag bug: (a) refuse `board_tags` containing separators other than spaces; (b) read the common separators as spaces. Chose (b): `scripts/publish_text.{php,py,pl}` and the API reference already say "comma-separated", so the server is the odd one out, and PRINCIPLES prefers keeping a submission over refusing it. Characters that are neither allowed nor separators are dropped as before, so existing tag input reads the same.

Response lines are read back from the read model after the write rather than echoed from the request, so they show what was stored. If the row is not there yet (high-traffic lag), the lines are left out; nothing fails.

Risks: clients that parse the `key=value` response by position (checked: `frm.py` and the publish scripts read keys by name); tests that pin the exact `get_post` header block or the tag refusal text (update them). `get_post` header added after `Author:` only when the post has tags.

## Reuse decisions

`LocalWriteService::normalizeBoardTags` (one normalizer for all four callers); `WritePostAndIdentityApiController` response builder and `RouteServices::unsignedAttributionLines` pattern for extra response lines; `ApiTextController::getPost`; `TagApiController` refusals; `LlmsTxtRenderer`; `docs/reference/agent_api.md`. No new routes, records or schema.

## Completion conditions

A form POST to `/api/create_thread` with `board_tags=prompt,community,accessibility` and no identity stores `Board-Tags: prompt community accessibility`, answers with `author=guest`, `signed=no`, `board_tags=prompt community accessibility` and the guest notice, and the thread is on `/agent/` (waiting list for a guest). `get_post` shows the tags. `/llms.txt` renders the two sections. Handoff: PR open; live only after merge and deploy.

## Concepts touched

Board tags: changed (separator parsing). Write response: changed (author, signed, tags). No inventory change proposed.

## Verification and recovery plan

`./v3 test --smoke WriteApiSmokeTest LlmsTxtRendererTest AgentPostingApiTest ApiRouteCatalogTest` plus any test the change touches; replay ChatGPT's request against a local instance with curl. Full suite not run (maintainer runs it). Recovery for an agent that already posted this way is the documented repair path.

Starting revision and branch: `b9ffa6b` (origin/main after PR #272); `fix/agent_write_readback`
Planning commit: `0d79dbb`

## Dated execution history

- 2026-11-16T05:40Z: no matching item in `docs/feedback/items/`; created PF-20261116-B01. Source inspected before the estimate (the comma cause had to be found first), so the intake is not pristine.
- 2026-11-16T05:50Z: implemented items 1 to 3 as planned. `board_tags` also accepts a JSON list (a JSON body with a list used to store the tag `array`); repeated tags are kept once.
- 2026-11-16T05:55Z: correction found in the local replay. The plan assumed `apply_thread_tag` could add `#prompt`; it takes only like, flag and votes, and answers `tag must be one of: like, flag, upvote, downvote`. Topic labels come from hashtags in a post's body, so the working repair is a reply of `#prompt` (replayed: a thread stored with `promptcommunityaccessibility` reached `/agent/` after that reply). The "identity hint" refusal ChatGPT hit was a dead end in front of that. Changes: the identity-hint refusal now says what to send; the tag refusal points at the hashtag reply; `/llms.txt` and the reference describe the hashtag reply as the way to tag an existing thread.
- 2026-11-16T06:05Z: guest attribution has no repair today. Documented what exists (signed reply claiming the post; signed copy first, then operator removal). A route for moving a guest post to an identity is a follow-up that needs the maintainer's choice.
- 2026-11-16T08:55Z: maintainer correction on the open PR: telling an agent to ask the operator when its key cannot be recovered adds friction and is often the wrong call; it should post as guest and sort it out later ("rather post something than stop and not post anything"). `/llms.txt` and the reference now say that. The rest of the guidance stands: look for the recovery path first, check the fingerprint, no new key under the same name.
- 2026-11-17T06:20Z: merged `origin/main` (5709775) so PR #276 can merge. The follow-up item filed as PF-20261116-F01 collided with main's item of the same ID and is now [PF-20261116-F03](../../feedback/items/PF-20261116-F03.txt). Smoke set plus this PR's tests: 739 passed.

## Study history

- Intake, 2026-11-16T05:40Z: **0.35**, after source inspection; single exposed Claude rater; R1/P2.
- Closeout, 2026-11-16T06:10Z: retrospective **0.35**; same rater; maintainer fit judgment pending.

## Outcome

Delivered: separators in `board_tags` (comma, semicolon, `#`, JSON list); `author`, `signed`, `board_tags` and a guest notice on `create_thread`, `create_quote` and `create_reply`; `Board-Tags` in `get_post`; actionable refusals on the tag routes; "Before you post as guest" and "Tags and the Prompts page" in `/llms.txt`; matching sections in `docs/reference/agent_api.md`.

Not done, follow-ups: the seven live guest threads still carry `promptcommunityaccessibility`-style tags (an approved member's `#prompt` reply on each lists them; a production write, not made here); moving a guest post to an identity (filed as [PF-20261116-F03](../../feedback/items/PF-20261116-F03.txt)); `frm.py whoami`; `frm.py --tags` still refuses commas; the JSON create routes (`create_prepared_post`, `create_signed_post`) do not echo author and tags.

## Verification

- Passed: `./v3 test --smoke WriteApiSmokeTest::testCreateThreadReadsSeparatedTagsAndReportsWhatItStored WriteApiSmokeTest::testApplyPostTagApiRejectsAnonymousViewerAndUnknownTag LlmsTxtRendererTest TagUnlocksTest PromptLibraryTest QdbBoardPolicyTest` (505 passed).
- Passed: `./v3 test WriteApiSmokeTest` (221), and `FrmClientTest ComposeAttemptReplayTest HighTrafficPostRefreshTest SingleRequestPostingTest TagUnlocksTest PromptLibraryTest LocalAppSmokeTest AgentApiWorkedExampleTest AgentPostingApiTest ApiRouteCatalogTest`.
- Passed, replay of the reported request: local instance over HTTP, form POST to `/api/create_thread` with `board_tags=prompt,community,accessibility` and no identity. Response carried `author=guest`, `signed=no`, `board_tags=prompt community accessibility` and the notice; `get_post` showed `Board-Tags`; the thread was in the `/agent/` waiting list.
- Not run: the full suite (maintainer runs it). Not verified: production, and ChatGPT's own workspace; whether ChatGPT reads the new `/llms.txt` section before falling back is only testable after deploy.

## Final review

Diff checked: one normalizer for all callers, one read-back helper beside `unsignedAttributionLines`, no new route, record or schema. `git diff --check` clean.

Commit/merge/publication status: completion commit `a22bf4e`, pushed; [PR #276](https://github.com/gulkily/v3/pull/276) open; not merged, not deployed. Thread reply drafted but not posted: the session refused the write to zenmemes.com, so it waits for the maintainer's go-ahead.
Attribution: Claude (Fable 5.2), 2026-11-16 UTC.
