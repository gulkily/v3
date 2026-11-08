# Ctrl+Enter after late signing load — one-shot

Cycle: `v3/ctrl_enter_late_signing`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](ctrl_enter_late_signing_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/ctrl_enter_late_signing`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; one approach, no unit estimates)

**Cycle estimate: 0.08**

Status: complete. Merged in #73 (`1b3de824`), deployed, and accepted by the maintainer on zenmemes.com, 2026-11-08.

## Request and authorization

Feedback item: [PF-20261108-B02](../../feedback/items/PF-20261108-B02.txt), created for this cycle (no existing item matched; PF-20261104-F68 covers Ctrl+Enter from Title and tags, a different change).

Original request, 2026-11-08 (https://zenmemes.com/threads/thread-20261108164027-d91d0c9c, by thaks): "ctrl+enter has stopped working on the board page's compose form #bug #regression".

Authorization, 2026-11-08: the maintainer pasted the thread link into a session. Under [thread to pull request](../../agents/thread_to_pr.md) the paste authorizes a one-shot cycle through an open PR and a reply in the thread. Exclusions: merge, deployment.

## Outcome and work type

Bug fix (client script). For an approved member, Ctrl+Enter in the board composer submits the post again.

## Cause

`browser_signing.js` set itself up only on `DOMContentLoaded`. Since `d9a0b0a1` (2026-10-29, messaging loaded on demand), `member_approval.js` fetches it for approved members together with the messaging scripts, and that fetch can land after `DOMContentLoaded`. The script then never initialized, so the board composer was never bound and had no Ctrl+Enter handler. The lazy composer loader (`lazy_compose_signing.js`) did not recover: it found the signing tag already in the page, treated it as loaded, called `init` before the script had run (a no-op) and cached that result.

Evidence from the reporter's browser (Chrome on Windows, approved member, page served by the service worker): `DOMContentLoaded` at 916 ms; `browser_signing` requested by script at 940 ms, ending 943 ms; the only signing tag was the dynamic one; `ForumBrowserSigning` loaded but the compose root had no `data-browser-signing-bound`; the keydown reached the form unhandled (`defaultPrevented=false`). Calling `ForumBrowserSigning.init(document)` by hand bound the composer and Ctrl+Enter worked.

## Approach and reuse decisions

- **Initialize when loaded late.** At the end of `browser_signing.js`, call `initBrowserSigning(document)` immediately when `document.readyState` is `interactive` or `complete`; otherwise keep waiting for `DOMContentLoaded`. Binding is already idempotent (`data-browser-signing-bound` per compose root), and pages that include the script statically already get a second `init` from the lazy loader on focus, so this adds no new double-setup path.
- **Considered and not done:** making the lazy loader wait for a present-but-unexecuted tag. It would fix only the lazy path; any other script that loads signing late would still leave it uninitialized. The fix at the source covers every loader.
- **Considered and not done:** dropping `browser_signing.js` from the messaging script list. The messaging scripts need it, and the order matters.
- No new dependency, markup, CSS, API or copy.

## Concepts touched

None in the concept inventory.

## Risks

- `scheduleIdentityPrewarm` and `scheduleAutomaticGuestIdentity` now also run on pages where an approved member's messaging scripts load signing late. Approved members already have an identity, and these already run on every page that includes signing. Accepted.

## Verification and recovery plan

- New `BrowserSigningNormalizationTest::testLateLoadBindsComposerCtrlEnterWithoutDomContentLoaded`: with `readyState` already `complete` and no further `DOMContentLoaded`, the compose root is bound and Ctrl+Enter requests the primary submit. Fails on `main` (`Expected '1' but got ''`), passes with the fix.
- Browser replay in headless Chrome against zenmemes.com with POSTs blocked.
- Recovery: revert the commit.

Starting revision and branch: `d1052b3ff716c55b8d6bb53118d00aa585294b0e` on `main`, rebased onto `ef72ce2b` before the PR; branch `fix/ctrl-enter-late-signing`.

## Study history

- **Intake, 2026-11-08T16:45Z, 0.05.** Single exposed rater: Claude (claude-opus-5-5). Pristine intake missing: the keyboard handler was read before rating. Scope v1: restore Ctrl+Enter in the board composer. Compared with R1's duplicate preview suppression (0.10).
- **Execution entry, 2026-11-08T17:05Z, 0.08.** Revised up: the cause could not be reproduced in a fresh, anonymous or simulated approved session and needed diagnostics from the reporter's browser.
- **Closeout, 2026-11-08.** Projection 0.08 reaffirmed; retrospective scope assessment **0.08**, same single exposed rater. Corrections: none. Additions: none. Delivered scope v1 in full. Maintainer accepted after verifying on zenmemes.com. No active-work measurement collected.

## Dated execution history

- 2026-11-08: replayed in headless Chrome locally and against production (fresh, typed immediately, saved key, all 15 themes, simulated approved member, offline); all submitted. Reporter's browser diagnostics located the unbound composer and the late signing load.
- 2026-11-08: record and feedback item created; fix and test implemented.
- 2026-11-08: merged in #73 and deployed; the maintainer confirmed Ctrl+Enter works on zenmemes.com.

## Outcome

Delivered as bounded. Limitations: the exact load interleaving in the reporter's browser was not reproduced headlessly; the fix was checked against its observed state.

## Verification

- Passed: `./v3 test BrowserSigningNormalizationTest`, 84 run, 84 passed (includes the new test).
- `./v3 test --smoke BrowserSigningNormalizationTest`: all passed except `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, which fails identically on `main` with this change stashed (pre-existing, unrelated).
- Headless Chrome, zenmemes.com, POSTs blocked: with the fixed script served in place of production's and the signing script appended after `DOMContentLoaded` (as `member_approval.js` does), the composer is bound before any interaction and Ctrl+Enter prevents default and fires submit, at 1280px and 390px.
- Not reproduced: the reporter's exact interleaving (headless Chrome either held `DOMContentLoaded` for the messaging chain or let the lazy loader add its own copy first, and both bound on production too). Checked against the reporter's recorded state instead.
- Not run: the full suite (maintainer runs it). Not checked: Firefox and Safari.
