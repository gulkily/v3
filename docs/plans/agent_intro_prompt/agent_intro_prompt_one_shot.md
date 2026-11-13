# Agent intro prompt

Cycle: `v3/agent_intro_prompt`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: disabled

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: disabled (one approach, small template change)

**Cycle estimate: 0.20 (intake)**

Status: first version (fold on About, intro link, Tools button, flag) merged in PR #187. Dedicated `/agent/` page and copy alignment implemented and verified locally on `feat/agent-page`; PR open, awaiting review, merge and deploy.

## Original request

Maintainer, 2026-11-12, in a Claude session: "We need to add a prompt like in railspike.dev where the user prompts their agent to interact with Zen memes, like intro me and find some useful things for me to do."

Related (not closed by this): PF-20261030-F51, discoverable posting instructions for agents.

## Authorization

The request names the outcome directly. Bounded one-shot through an open PR; no merge, deploy or production access.

## Outcome and work type

Application change. Like the install prompt on railspike.dev, zenmemes offers a read-only prompt with a Copy button. Placement, revised the same day after the maintainer asked for it to be easy to find but not in your face:

- About holds the prompt, folded under one "Use with your agent" line (`<details id="agent">`) below the intro; `/about/#agent` opens it.
- The board's first-visit intro adds one link, "About · Agent prompt", to `/about/#agent`. It goes away with the intro.
- Tools gets an "Agent Prompt" button under Your device, also to `/about/#agent`, with icons in both sets.

The first version put the whole box in the board intro and on About; screenshots showed it crowding the board. The prompt:

> Please visit https://zenmemes.com and read https://zenmemes.com/llms.txt. Then introduce me to zenmemes: what it is, who is active, and what people are working on lately. Suggest a few useful things I could do there, based on what you know about me. Ask me before you post anything.

"Ask me before you post anything" keeps a visitor's agent from posting on their behalf unasked. Only the zenmemes profile has the prompt; other profiles render nothing.

Feature flag (added 2026-11-13 at the maintainer's request): `FORUM_AGENT_PROMPT_ENABLED`, "Agent prompt", site-mutable, default on, appended to Forum experience. Off removes all three entry points. The Tools button also drops wherever there is no prompt (flag off or a profile without one); before this it showed on every profile.

Dedicated page (2026-11-13, at the maintainer's request, "both, on one page"): `/agent/`, titled "Use with your agent", replaces the About fold. Three prompts, each under its own heading and anchor:

- `#introduce` Get introduced: the prompt above.
- `#post` Post as an agent: read llms.txt, set up an agent account per its Quickstart and Agent policy, keep keys private, show a draft before posting.
- `#install` Run your own site: clone gulkily/v3, inspect it for malicious content, read AGENTS.md, follow README's Local Run and `./v3 start`, ask before installing system packages.

About keeps one "Use with your agent" link; the first-visit intro and the Tools button (now "Agent Prompts") link to `/agent/`. The flag off, or a profile without prompts, removes all of them and makes `/agent/` a 404.

## Copy presentation review (2026-11-13)

Existing copyable text, compared before settling the prompt block:

- railspike.dev install prompt: label above, read-only textarea that selects itself on focus/click, Copy (copy icon + text) in the field's bottom-right corner, a status line below; if the clipboard fails it selects the text and says to press Ctrl+C or ⌘C.
- Account key (`account_key.php`, `public_key.js`): text button "Copy" that reads "Copied" for 1.5 s; failure goes to a `.meta` `role="status"` line.
- Bookmarklets (`bookmarklets.php`): the same copy-icon SVG, `role="status"` feedback.
- Invites (`invites.php`): read-only textarea plus Copy button side by side.

The prompt block (`templates/partials/agent_prompt.php`) follows railspike.dev's layout and fallback, uses the Bookmarklets icon and the theme's own button styling, and the Account key "Copied"/1.5 s feedback and `.meta` status line. The earlier side-by-side layout and 2 s "Copied" were inconsistent with these and are gone.

## Approach and risks

The origin comes from this request's host, so clones and localhost name themselves. HTML rendered without a request (high-traffic cache, offline snapshot) gets an `{origin}` placeholder; the partial's inline script swaps whatever origin the server wrote for `location.origin` before paint. Copy shows only where the Clipboard API exists, revealed by the inline script before paint (as on railspike.dev); with JS off the text is still selectable. Risk: the visitor intro's absolute-positioned Hide button rule matched every button inside the card; narrowed to `.visitor-intro > button`.

## Reuse decisions

- Copy lives in `ProfilePresentationContent` EDITORIAL beside `siteDescription` (new `agentPrompt()`); `TemplateRenderer` passes it to every template like `siteDescription`.
- The flag gates `agentPrompt` in `TemplateRenderer::renderFile()`; the same place drops the `agent-prompt` row from `toolPages`/`toolNavOptions` when the prompt is empty, so the Tools page and Tools bar agree without per-template checks.
- `AgentPageController` renders `pages/agent.php`, following `AboutPageController`; the route sits beside `/themes/` and is listed in `isApplicationRoute()`. Page-only CSS is `public/assets/agent.css` via `PAGE_STYLESHEET_PATHS`, like Themes.
- One partial per prompt, `templates/partials/agent_prompt.php`; one inline script in `agent.php` (runs before paint) does the origin swap, select-on-focus and Copy for all three.
- The Tools entry is a `ToolsPageSupport::registry()` row (standalone, tone `agent`), so the launcher and the Tools bar both get it; icons follow the Bookmarklets rules in `theme-nav-icons.css`.
- `ViewerSessionCookie::requestIsHttps()` for the scheme.
- The existing copy buttons (`.account-key-viewer-copy`, `tools_bookmarklets.js`) are tied to key and bookmarklet markup; the partial's dozen lines of script are self-contained instead. No new dependencies.

## Completion conditions

- Normal entry: `/agent/`, reached from the first-visit intro, About and Tools.
- End-to-end: Copy puts the prompt, with this site's origin, on the clipboard; the button reads "Copied" for two seconds.

## Verification

- `./v3 test --smoke PresentationProfileMatrixTest NavIconSetsTest ToolsRegistryTest LlmsTxtRendererTest`: 471 run, 471 passed. Full suite not run, per `AGENTS.md`. Tests: only zenmemes has the board link and the About fold, with the request origin; the board carries no prompt box; placeholder origin without a host; tool count 17.
- Flag: `./v3 test --smoke PresentationProfileMatrixTest FeatureFlagEvaluatorTest FeatureFlagsPageTest ToolsRegistryTest NavIconSetsTest`: 459 run, 459 passed. New test renders board, About and Tools for every profile with the flag on and off; the entry points appear only for zenmemes with it on. The Feature Flags page lists it last under Forum experience (checked in the browser).
- Agent page: `./v3 test --smoke PresentationProfileMatrixTest FeatureFlagEvaluatorTest FeatureFlagsPageTest ToolsRegistryTest NavIconSetsTest`: all passed (the matrix run covers 456 tests). Tests: the three prompts, headings, labels and request origin on `/agent/`; every profile with the flag on and off for board, About, Tools and `/agent/`; placeholder origin.
- Agent page, headless Chrome, all six icon themes at 1280×900 and 390×844: the intro link reaches `/agent/`; three Copy buttons; copying works and reads "Copied"; clicking a field selects it all; About and Tools link to `/agent/`; no overflow or page errors. With the clipboard rejected, the field is selected and the Ctrl+C/⌘C hint shows.
- Revised placement, headless Chrome, Gloss, Gloss Dark, Word 97, Chicago, Midnight and Engagify at 1280×900 and 390×844: the intro link opens About with the fold open, a plain About visit has it closed, Copy works, the Tools button has its icon, no overflow or page errors. Intro links no longer split across lines on phones.
- Headless Chrome on a local zenmemes server: board and About in Gloss, Gloss Dark, Word 97 and Engagify at 1280×900 and 390×844. Copy visible, no horizontal overflow, no page errors; clicking Copy put the prompt on the clipboard and showed "Copied". Fixed during checks: hard line breaks wrapped badly and clipped the field (prompt is now one paragraph, field sized to content); Gloss stretched the phone button.

Branches: `feat/agent-intro-prompt` from `e1d938c` (PR #187, merged); `feat/agent-page` from `b111bb3`.

## History

- 2026-11-12: intake, implemented, verified locally; PR #187.
- 2026-11-12: placement revised (About fold, intro link, Tools button) on the same unmerged branch, before review.
- 2026-11-13: `FORUM_AGENT_PROMPT_ENABLED` flag added on the same unmerged branch.
- 2026-11-13: PR #187 merged with the flag.
- 2026-11-13: copy presentation reviewed and aligned; prompts moved to a dedicated `/agent/` page with post and install prompts on `feat/agent-page` (re-tested on current main: 460 passed).

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*
