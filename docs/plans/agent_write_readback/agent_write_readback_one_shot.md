Cycle: `v3/agent_write_readback`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); no separate log unless something comes up
Effort study: `railspike-effort` / `v3/agent_write_readback` — [host ledger](../effort_estimation_trial.md#cycle-v3agent_write_readback); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.35**
Status: planned; next permitted action: implement on `fix/agent_write_readback`

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
Planning commit: pending

## Dated execution history

- 2026-11-16T05:40Z: no matching item in `docs/feedback/items/`; created PF-20261116-B01. Source inspected before the estimate (the comma cause had to be found first), so the intake is not pristine.

## Study history

- Intake, 2026-11-16T05:40Z: **0.35**, after source inspection; single exposed Claude rater; R1/P2.
