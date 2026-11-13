# Agent intro prompt

Cycle: `v3/agent_intro_prompt`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: disabled

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: disabled (one approach, small template change)

**Cycle estimate: 0.20 (intake)**

Status: implemented and verified locally; PR open, awaiting maintainer review, merge and deploy.

## Original request

Maintainer, 2026-11-12, in a Claude session: "We need to add a prompt like in railspike.dev where the user prompts their agent to interact with Zen memes, like intro me and find some useful things for me to do."

Related (not closed by this): PF-20261030-F51, discoverable posting instructions for agents.

## Authorization

The request names the outcome directly. Bounded one-shot through an open PR; no merge, deploy or production access.

## Outcome and work type

Application change. Like the install prompt on railspike.dev, zenmemes shows a read-only prompt with a Copy button under "Paste this into your agent:". It appears in the first-visit intro on the board (so only visitors without a saved identity see it there) and in the About intro. The prompt:

> Please visit https://zenmemes.com and read https://zenmemes.com/llms.txt. Then introduce me to zenmemes: what it is, who is active, and what people are working on lately. Suggest a few useful things I could do there, based on what you know about me. Ask me before you post anything.

"Ask me before you post anything" keeps a visitor's agent from posting on their behalf unasked. Only the zenmemes profile has the prompt; other profiles render nothing.

## Approach and risks

The origin comes from this request's host, so clones and localhost name themselves. HTML rendered without a request (high-traffic cache, offline snapshot) gets an `{origin}` placeholder; the partial's inline script swaps whatever origin the server wrote for `location.origin` before paint. Copy shows only where the Clipboard API exists, revealed by the inline script before paint (as on railspike.dev); with JS off the text is still selectable. Risk: the visitor intro's absolute-positioned Hide button rule matched every button inside the card; narrowed to `.visitor-intro > button`.

## Reuse decisions

- Copy lives in `ProfilePresentationContent` EDITORIAL beside `siteDescription` (new `agentPrompt()`); `TemplateRenderer` passes it to every template like `siteDescription`.
- One partial, `templates/partials/agent_prompt.php`, used by `visitor_intro.php` and `pages/about.php`.
- `ViewerSessionCookie::requestIsHttps()` for the scheme.
- The existing copy buttons (`.account-key-viewer-copy`, `tools_bookmarklets.js`) are tied to key and bookmarklet markup; the partial's dozen lines of script are self-contained instead. No new dependencies.

## Completion conditions

- Normal entry: the board (first visit) and About.
- End-to-end: Copy puts the prompt, with this site's origin, on the clipboard; the button reads "Copied" for two seconds.

## Verification

- `./v3 test --smoke PresentationProfileMatrixTest LlmsTxtRendererTest`: 468 run, 468 passed. Full suite not run, per `AGENTS.md`. New tests: only zenmemes renders the prompt on board and About, with the request origin; placeholder origin without a host; no prompt for other profiles.
- Headless Chrome on a local zenmemes server: board and About in Gloss, Gloss Dark, Word 97 and Engagify at 1280×900 and 390×844. Copy visible, no horizontal overflow, no page errors; clicking Copy put the prompt on the clipboard and showed "Copied". Fixed during checks: hard line breaks wrapped badly and clipped the field (prompt is now one paragraph, field sized to content); Gloss stretched the phone button.

Branch: `feat/agent-intro-prompt` from `e1d938c`.

## History

- 2026-11-12: intake, implemented, verified locally.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*
