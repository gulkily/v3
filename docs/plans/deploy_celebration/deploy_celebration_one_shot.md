Cycle: `v3/deploy_celebration`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); log: [deploy_celebration_debug_log.md](deploy_celebration_debug_log.md)
Effort study: `railspike-effort` / `v3/deploy_celebration` — [host ledger](../effort_estimation_trial.md#cycle-v3deploy_celebration); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.10**
Status: planned; next permitted action: implement on `feat/deploy_celebration`

## Original request

Thread https://zenmemes.com/threads/thread-20261115003051-544aa720 (feedback item PF-20261114-F03), posted by the maintainer (ilyag): "The deploy button needs more celebration". The root post has no body.

## Authorization

In a Claude session on 2026-11-14 the maintainer pasted the thread link and wrote: "Bless you, Claude. Period. If you have to make a PR, you have my permission." Read under `docs/agents/thread_to_pr.md` as the go-ahead for a one-shot cycle on the bounded outcome below, pushing the branch, opening a PR and replying in the thread. Merging and deploying are not covered.

## Outcome and work type

Application change. Normal entries: the header Deploy now button (`FORUM_DEPLOY_HEADER_PROMPT_ENABLED`) and Tools → Deploy (Deploy now, Queue, or a queued deploy finishing while the page watches).

Outcome: when a deploy started in this browser actually changes the code (outcome `deployed`), the page that loads afterwards greets it with a short burst of confetti from the top of the screen and a small "Deployed" banner carrying the server's message (e.g. "Updated to 1a2b3c4d5e6f."). The banner leaves on its own after a few seconds or on a tap. "Already up to date", refusals and failures get no celebration. With reduced motion the confetti is skipped and only the banner shows.

Excluded: sound, server-side changes, changing how fast the deploy runs.

## Approach and risks

- Both deploy scripts reload the page on success, so the celebration has to cross the reload. Before reloading on `deployed`, the script puts the message in `sessionStorage` (`forum_deploy_celebrate`); a new `deploy_celebrate.js` reads and clears it on the next load and plays once. sessionStorage is per tab, so other tabs and later visits never replay it. A storage failure means no celebration, never a broken deploy.
- Considered: celebrating before the reload (cut off by the reload), or delaying the reload (slows the deploy the maintainer wants kept fast). Crossing the reload keeps "Deploy now" exactly as fast.
- Confetti is plain DOM pieces with one CSS keyframe animation, removed after it ends. No dependency.
- Risk: double load on the Deploy page when the header flag is on (layout loads it and the page asks for it). The script guards against running twice.

## Reuse decisions

- Deploy flow: `public/assets/deploy_prompt.js` and `public/assets/deploy_control.js` keep their requests and outcomes; each gains one line before its success reload. The existing `forum_deploy_prompt` localStorage key is untouched.
- Script loading: the layout's existing deploy-prompt slot (`deployPromptScriptPath` in `TemplateRenderer`, `templates/layout.php`) and the Deploy page's `scriptPaths` in `DeployController`.
- Colors: theme variables (`--active-line`, `--surface`, `--ink`, `--line`, `--ui-font`) so the banner sits in every theme. No existing toast or banner component exists in `public/assets/` (checked for toast/flash/notice); the banner is new and minimal.

## Completion conditions

- Normal entry: press Deploy now in the header, or on Tools → Deploy, with GitHub ahead.
- End-to-end: the reloaded page shows confetti and the Deployed banner once; reloading again shows nothing.
- Recovery: no storage or reduced motion degrades to banner-only or nothing; the deploy itself is unchanged.
- Release: PR merged and deployed by the maintainer.

## Concepts touched

None.

## Verification and recovery plan

Smoke set plus `DeployPageTest` and the layout/asset tests. Browser check with Playwright on the layout fixture: banner and confetti appear when the session key is set, not otherwise, and not twice; reduced motion skips confetti; phone and desktop widths in a couple of themes. `tests/browser/deploy_prompt.mjs` re-run if Chromium is available. Full suite not run, per `AGENTS.md`. The live deploy cannot be exercised here.

Starting revisions and branch: `feat/deploy_celebration` from `bd2b0fad`.
Planning commit: pending

## Study history

- 2026-11-15T02:13Z intake: whole-cycle projection **0.10**, scope v1 as above, R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: rated after reading `deploy_prompt.js` and `deploy_control.js`; no pristine intake.

## Dated execution history

- 2026-11-14: no existing feedback item for deploy celebration; created PF-20261114-F03. No open branch for it on origin.

*Claude (claude-opus-5-5), 2026-11-15 UTC*
