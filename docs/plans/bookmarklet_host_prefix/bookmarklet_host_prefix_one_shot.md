Cycle: `v3/bookmarklet_host_prefix`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); no separate log unless something comes up
Effort study: `railspike-effort` / `v3/bookmarklet_host_prefix` — [host ledger](../effort_estimation_trial.md#cycle-v3bookmarklet_host_prefix); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.05**
Status: implemented and verified locally; pushed with a PR; merge and deploy need the maintainer

## Original request

Maintainer, in a Claude session on 2026-11-16: "feature request: for the clip bookmarklets (and any others that interact with a server) have the server hostname in the beginning x chars of the bookmarklet address, so that it's easy to see when editing bookmark".

## Authorization

The feature request itself, 2026-11-16: a bounded change to the generated bookmarklet source, carried through to a pushed branch and an open PR. Merging and deploying are not covered.

## Outcome and work type

Application change. Every bookmarklet on Tools → Bookmarklets (all seven post to the server's `/compose/thread`, so all are in scope) starts with the server's host right after the scheme, e.g. `javascript:/*zenmemes.com*/(function(){…`. This holds for both the dragged link (`href`) and the copied one-line form. A bookmark editor that shows only the first few dozen characters (iPhone Safari's address field, a desktop bookmark dialog) shows which server it posts to.

Excluded: what the bookmarklets clip or where they send it; the page layout.

## Approach and risks

`public/assets/tools_bookmarklets.js`: one helper builds the `/*host*/` prefix from `baseOrigin` (`host` includes a non-default port, so two local instances on different ports are told apart). The prefix is left unencoded in the `href`: the existing percent-encoding would turn `/` into `%2F`, which defeats the purpose. `*`, `/`, letters, digits, `.`, `-` and `:` are all literal-safe in a `javascript:` URL.

Risks: (1) a host containing `*/` would end the comment early. Hosts cannot contain `*`, but the helper strips anything outside `[A-Za-z0-9.:\-\[\]]` anyway. (2) qutebrowser `:jseval` parses `javascript:` as a label; a label followed by a block comment and an expression is valid. The existing `bookmarklet_copy.cjs` compiles the copied form in that position, so it covers this.

## Reuse decisions

Same generator (`bookmarkletScript`/`bookmarkletSource`/`bookmarkletCopySource`) and same page; only a prefix is added in the two places that emit `javascript:`. No server or template change: the origin is already taken from `window.location.origin` in the page script.

## Completion conditions

On `/tools/bookmarklets/`, each of the seven buttons' `href` and copied text begins `javascript:/*<host>*/`; each still runs and opens the composer with the same subject and body as before.

## Concepts touched

Bookmarklet: changed (source begins with the server host). No inventory change.

## Verification and recovery plan

`node tests/browser/bookmarklet_copy.cjs` (extended to assert the prefix on both forms and still execute them); `./v3 test --smoke`. Full suite not run (maintainer runs it).

Starting revision and branch: `f13ed676` (origin/main); `feat/bookmarklet_host_prefix`
Planning commit: `cb33b367`

## Dated execution history

- 2026-11-16T18:53Z: no matching item in `docs/feedback/items/` (bookmarklet items F09, F50, B15, F04, F06, B25 are about clip content, not the address).
- 2026-11-16T19:00Z: implemented as planned in `tools_bookmarklets.js` (`hostPrefix`, used by `bookmarkletSource` and `bookmarkletCopySource`). The vm test context has no `URL`, so the helper falls back to stripping the scheme; both paths give the same host.
- 2026-11-16T19:02Z: discovery (correction, pre-existing): `tests/browser/bookmarklet_copy_ui.mjs` timed out on `f13ed676` too, because it expected Auto to leave `data-theme` unset; since [gloss_default](../gloss_default/gloss_default_one_shot.md) Auto resolves to Gloss/Gloss Dark. Fixed the wait to accept whatever Auto resolves to, per the maintainer's rule that tests failing on the base commit get fixed in the same work.

## Study history

- 2026-11-16T18:53Z intake: **0.05**, scope v1, R1, P2, single exposed rater Claude (claude-opus-5-5), rated after reading `tools_bookmarklets.js` and its test (no pristine intake).
- 2026-11-16T19:05Z closeout: retrospective **0.07** (the pre-existing UI test fix added a little). Maintainer acceptance pending; no accepted-count increment.

## Verification

- Passed: `node tests/browser/bookmarklet_copy.cjs`, now asserting both the link and the copied form begin `javascript:/*forum.test*/(` and still run to the same composer URL (and still compile after a `javascript:` label, the qutebrowser `:jseval` case).
- Passed: `node tests/browser/bookmarklet_copy_ui.mjs` against `tests/Support/bookmarklet_browser.php`, live and `BOOKMARKLET_EXPECT_STATIC=1` static (real clipboard, all themes at 1280 and 375px). Failed on the base commit before the Auto-theme fix (reproduced baseline failure).
- Passed: real page in Chrome: all seven `href`s begin `javascript:/*127.0.0.1:18765*/(function()…`.
- Passed: `./v3 test` smoke set, 468/468.
- Checked by hand: `hostPrefix` gives `/*zenmemes.com*/`, `/*localhost:8080*/`, `/*[::1]:8000*/`, and strips `*` and `/` from a malformed origin.
- Not run: `bookmarklet_copy_qutebrowser.mjs` (no qutebrowser here; the label-plus-comment form is compiled in the node test); iPhone Safari's bookmark editor itself; the full suite (maintainer runs it); the live site, which needs the deploy.

## Outcome

Delivered as scoped. Existing bookmarks keep working but don't gain the prefix; re-add them from Tools → Bookmarklets to get it. Next action: maintainer merges and deploys.

## Final review

Diff: `public/assets/tools_bookmarklets.js`, `tests/browser/bookmarklet_copy.cjs`, `tests/browser/bookmarklet_copy_ui.mjs` (pre-existing failure fix), this record, the plans index and the ledger. No duplication; no debug findings.

Commit/merge/publication status: completion commit pending at time of writing; pushed with a PR; not merged or deployed.

Attribution: Claude (claude-opus-5-5), 2026-11-16 UTC.
