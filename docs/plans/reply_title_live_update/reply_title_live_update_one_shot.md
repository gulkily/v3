Cycle: `v3/reply_title_live_update`; Mode: one-shot; Record: this file; Index: [plans index](../README.md); Predecessor: [v3/reply_title_directive](../reply_title_directive/reply_title_directive_step4_implementation_summary.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/reply_title_live_update` — [host ledger](../effort_estimation_trial.md#cycle-v3reply_title_live_update); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.15**
Status: implemented and verified locally; next permitted action: completion commit on `feat/reply-title-live-update` (merge and push need the user's instruction)

## Original request

"it's working great. one thing i noticed is that when i retitle a thread, the card at the top of the page does not update with the new title until i reload the page. i think this is to be expected. can you please estimate complexity of improving this" — followed, after the estimate and the offer to run it as a one-shot, by "go ahead please" (2026-11-03).

## Authorization

User instruction "go ahead please" on 2026-11-03, replying to the offer "Say the word and I'll run it as a one-shot" for the scope estimated at about 0.15. Scope: after a reply that retitles its thread is accepted through the inline composer, update the root card heading, the browser tab title and the root card's "Title set by" segment in place, and the Forte pane subject when posted from the pane. Exclusions: no change to records, schema, authority or flags; a rejected directive carries nothing in the response and shows no change or explanation (agreed in the predecessor). Merge and push are not covered by this authority.

## Outcome and work type

Application change. Normal entry: the thread page's inline reply composer (and the Forte pane composer where it shares the same delivery code). End-to-end outcome: the accepted-reply response names the thread's effective title and the title author's name when, and only when, the reply wrote a subject record; the page updates heading, tab title and signals segment without a reload, and a reload shows the same. Recovery: if the response lacks the fields or the client code fails, the page simply keeps the old heading until reload, which is today's behavior; nothing is lost.

## Approach and risks

- Server: `LocalWriteService::createPreparedPost`, `createReply` and `createSignedPost` already know when a subject record was written; add `thread_subject` and `thread_subject_author` (username) to their results in that case only. The finalize route passes the writer result through, so no route change is expected.
- Client: in `public/assets/reply_continuation.js`, on an accepted result carrying those fields, update `.thread-root-card h1`, `document.title` (preserving the site suffix), and the root signals line's `[data-signal-tag="title"]` segment (create it if absent, mirroring `appendSignalActor`); update `.paned-content-subject` when present.
- Risks: the finalize JSON parser may whitelist fields (check `parsePreparedPostJsonResponse`); a reply accepted through the retry/confirm path has no fresh response and keeps the old heading (acceptable, reload shows it). Mitigation: only act on fields present in a fresh accepted response.

## Concepts touched

- [Thread title](../../concepts/thread_title.md) — changed: scenario gains the in-place heading update.
- [Post presentation](../../concepts/post_presentation.md) — used unchanged.

## Verification and recovery plan

- PHP: extend `LocalWriteServiceTitleDirectiveTest` to assert the new result fields on an authorized reply and their absence on a rejected one.
- Browser: extend `tests/browser/title_directive_replay.mjs` so the author's retitle is asserted on the page before any reload, then after reload.
- Focused suites touched by the change; `git diff --check`; dependency delta zero.

## Starting revisions and branch

Host `main` at `699ebd26` (merged predecessor, schema 46); branch `feat/reply-title-live-update`.

## Study history

- 2026-11-03T19:00:27Z intake: whole-cycle projection **0.15**, scope v1 as above, R1 (hashes verified in the predecessor cycle today), P2, Claude Code single exposed rater (model claude-fable-5-1). Exposure: the estimate was formed after reading the predecessor's write path and the signals script, but before reading the inline continuation module and finalize route; disclosed, not pristine. Execution entry: **0.15 reaffirmed** after reading those two.

## Dated execution history

- 2026-11-03T19:00:27Z: branch created from `main`; record created; implementation begins.
- 2026-11-03T19:07:37Z: approach change (simplification within scope, not a scope change): the accepted-reply render pass in `public/assets/browser_signing.js` already fetches the thread page (`/threads/<id>?created_post_id=…`) and reconciles earlier cards from it. A new `syncThreadRootFromDocument(doc)` copies the root card heading, the document title and the root signals line from that server-rendered document when they differ. No server response fields, no PHP change; the planned `LocalWriteServiceTitleDirectiveTest` extension is therefore not applicable.
- 2026-11-03T19:07:37Z: Forte: the continuation module is deliberately not bound under `/forte`, so the pane composer is outside this path; whether the pane re-renders its subject after posting was not verified. Recorded as out of scope for this cycle, not as done.
- 2026-11-03T19:07:37Z: `tests/browser/title_directive_replay.mjs` now asserts, before any reload, that the root heading, tab title and `Title set by` segment update for the author's retitle and for the member's retitle after the flag change.

## Outcome

Delivered: after a reply that retitles its thread is accepted through the inline composer, the thread page's heading, tab title and root signals line update in place; a reload shows the same. Rejected directives change nothing on the page, as agreed. Not delivered: Forte pane (outside the continuation path, unverified). Recovery unchanged: if the render fetch fails the page keeps the old heading until reload, which was the previous behavior.

## Verification

- `node tests/browser/title_directive_replay.mjs`: ok (2026-11-03T19:07:37Z), with the new pre-reload assertions.
- `node tests/browser/inline_reply_link.mjs`, `signed_reactions.mjs`, `post_signals.mjs`: pass.
- `php tests/run.php AccountKeyPageHooksTest LazyComposeSigningTest PostRetractionScriptTest BrowserSigningNormalizationTest PrivateMessageComposerTest WriteApiSmokeTest LocalAppSmokeTest` (every PHP test referencing the script): 466 run, 463 passed; the 3 failures are the known baseline `LocalAppSmokeTest` ones.
- `git diff --check` clean (see commit). Dependency delta zero. Full suite not rerun for this cycle; it was run on the merged predecessor tree the same day (see that cycle's ledger entry).
- Limits: Chromium only; not Firefox/WebKit, no physical phone, not production.

## Study closeout

Intake 0.15; execution entry 0.15 reaffirmed; **retrospective scope assessment 0.10**: the client-only approach removed the planned server and PHP-test work. Single exposed rater Claude Code (model claude-fable-5-1), R1/P2. Maintainer comfortable-fit judgment and acceptance pending; no accepted-count increment.

## Commit status

Completion commit pending at the time of writing; its hash is appended afterwards in a record-only handoff commit. Merge and push are not covered by this cycle's authority.

*— Claude Code · 2026-11-03 UTC · Model: claude-fable-5-1*
