Cycle: `v3/bookmarklet_host_prefix`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); no separate log unless something comes up
Effort study: `railspike-effort` / `v3/bookmarklet_host_prefix` — [host ledger](../effort_estimation_trial.md#cycle-v3bookmarklet_host_prefix); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.05**
Status: planned; next permitted action: implement

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
Planning commit: pending

## Dated execution history

- 2026-11-16T18:53Z: no matching item in `docs/feedback/items/` (bookmarklet items F09, F50, B15, F04, F06, B25 are about clip content, not the address).

## Study history

- 2026-11-16T18:53Z intake: **0.05**, scope v1, R1, P2, single exposed rater Claude (claude-opus-5-5), rated after reading `tools_bookmarklets.js` and its test (no pristine intake).
