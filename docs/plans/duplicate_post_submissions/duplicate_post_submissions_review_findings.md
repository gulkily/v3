# Duplicate post submissions: review findings

**Status (2026-11-05): awaiting review, not merged into `main`.** The fixes are
pushed on `fix/duplicate-post-submissions`. Before merging, review the branch and
settle the [decision below](#decision-to-confirm-before-deployment). The verdict
below describes `302b56e3`, before the fixes in [Resolution](#resolution).

Review of branch `fix/duplicate-post-submissions` at `302b56e3` against `main`
(2026-11-04). The branch addresses
[PF-20261030-B03](../../feedback/items/PF-20261030-B03.txt).

**Verdict: not ready to merge.** The Back and re-click fixes are sound. The
server's attempt-ID handling can block or backdate posts, and the composer has
two regressions.

**How this was checked:** by reading the code, not by reproducing each failure.
The smoke set plus `BrowserSigningNormalizationTest` passed (506 of 506). The
full suite was not run, and nothing was tested in a browser.

Line numbers refer to `302b56e3`.

## Fix before merge

### 1. A failed commit can block a draft from posting

`src/ForumRewrite/Write/LocalWriteService.php:147`

The replay check treats any post file on disk as committed, but nothing removes
the file if the commit fails or the process dies. `git show HEAD:<path>` then
fails on every retry with "Unable to verify existing compose submission". The
400 page restores the same attempt ID, so the draft stays stuck until its text
is edited, and that edit publishes under a new ID. `createReply` already recovers
from this case for expansions, using `git status --porcelain` and a recovery
commit.

### 2. Anonymous clients control the post's permanent date

`src/ForumRewrite/Write/LocalWriteService.php:134`

`created_at` comes from the client's attempt ID, and only future dates are
limited. `compose_attempt=20010101000000-<32 hex>` creates a thread dated 2001.
It also happens by accident with a slow clock, or when a failed draft is retried
days later.

### 3. A fast device clock blocks anonymous threads

`public/assets/browser_signing.js:3579`

The timestamp comes from the device, and the server rejects anything more than
5 minutes ahead. Every retry fails until the clock is fixed. These posts worked
before this branch.

### 4. Bookmarklet and link prefills get wiped

`public/assets/browser_signing.js:3800`

The recently-cleared branch runs before the prefill check and now blanks subject
and body. The old `form.reset()` kept them. After publishing, opening
`/compose/thread?subject=X&body=Y` in the same tab loses the clipped text.

### 5. Published text can come back after Back

`public/assets/browser_signing.js:4101`, also `:3801`

These branches now restore the saved draft instead of clearing it, and the
`input` and `change` listeners keep saving while a submit is in flight. Press
Ctrl+Enter, then click away or type before navigation. Back restores the
published text, and a second click posts a duplicate, which is the bug this
branch is meant to fix.

## Should fix

### 6. Anonymous replies can still duplicate

`public/assets/browser_signing.js:3572`

Only thread forms get an attempt ID, and `createReply` does not use one. If a
reply's response is lost and the user resubmits, it posts twice. The feedback
item lists replies among its sources.

### 7. Replay can report success for a thread that is not fully set up

`src/ForumRewrite/Write/LocalWriteService.php:152`

If the process dies after the commit but before `synchronizePostDerivedState`,
there is no stale marker, so replay skips the refresh. The thread may then be
missing from the read model. The API path also skips fast moderation scoring;
the browser form never enqueues it anyway.

### 8. The new server code has no tests

`compose_attempt` appears in no PHP write-service test. Validation, identical and
conflicting replay, the removed-post rejection, stale-marker repair and the
uncommitted-file case are all uncovered, and findings 1 and 7 sit on those paths.

### 9. Feedback item not updated

Both commits carry the `Feedback: PF-20261030-B03` trailer, but neither updates
the item file, which still says `Workflow: untriaged`. `AGENTS.md` requires that
update in the same commit. The item also calls duplicate posts "speculation, not
proof of cause", so its `Evidence` and `Notes` should say what was confirmed.

## Minor

- **Removed-post error:** the `410` at `LocalWriteService.php:145` never reaches
  the client, because both thread controllers always return 400. Users also see
  the internal wording "replay cannot restore it".
- **Storage:** each draft keeps a second full copy of the post in
  `anonymousAttempt.fields`, rewritten on every keystroke.
- **First paint:** a published draft stays visible until `/api/get_post` returns.
  This only happens after an interrupted submit.
- **Double lookup:** `pageshow` also fires after a fresh load, so reconciliation
  runs at init and again from `pageshow`, costing two requests.

## Found while fixing

### 10. Published text returns after a Back that reloads the page

Neither this review nor the unit tests caught this; the browser check did. On a
Back that reloads the page instead of using the back-forward cache, the composer
blanks itself at init and uses up the recently-cleared key. Chrome's form
restoration then puts the published text back before `pageshow`, which saves it
as a new draft with no attempt ID. A second click publishes a duplicate. This is a
likely production cause of PF-20261030-B03, but that is not confirmed.

## Dropped

The automated review also reported a stale hidden `compose_attempt` input being
sent with new text. It needs `crypto` to be available when the attempt is created
and missing on a later submit from the same page, which is not realistic.

## Resolution

Fixed in the working tree on 2026-11-04, on top of `302b56e3`.

| Finding | Change |
| --- | --- |
| 1 Failed commit | An uncommitted file from an earlier try is committed on retry, keeping its original time. An unreadable partial write is replaced. |
| 2 Backdating, 3 Fast clock | The attempt ID is now only 32 random hex characters, with no client date. The server sets `Created-At`. |
| 4 Prefill wiped | A link opened after publishing keeps its text. Back to that link still blanks the published text. |
| 5 Text returns after Back | The field listeners no longer save the draft during a submit. |
| 6 Replies | Anonymous replies use the same attempt mechanism, with `reply-<32 hex>` IDs. |
| 7 Replay repair | Replay rebuilds the read model when the marker is stale or the post is missing from it, and only then re-enqueues fast scoring. |
| 8 Tests | Added `tests/ComposeAttemptReplayTest.php`, Node cases in `BrowserSigningNormalizationTest`, and `tests/browser/compose_attempt_replay.mjs`. |
| 9 Feedback item | Updated `PF-20261030-B03` to `blocked` and `locally-verified`. |
| 10 Back that reloads | `pageshow` finishes the clear after a back-forward load. |
| Removed-post error | Plain wording, with the unused `410` removed. |
| Double lookup | `pageshow` reconciles only when restoring from the back-forward cache. |

**Left as is:**

- **Storage:** the second copy of the post in the draft stays. Removing it needs a
  synchronous hash for little gain, and post text is small.
- **First paint:** after an interrupted submit, the draft stays visible until the
  lookup answers. The client learns of a real state change at that moment, and
  clearing earlier could lose an unpublished draft.

### Decision to confirm before deployment

Anonymous posts from JavaScript browsers get IDs like
`thread-0123456789abcdef0123456789abcdef`, stored directly in `records/posts/`
rather than in dated folders. `postCandidates` finds the date folder from the ID,
so an ID with no date uses the existing undated path. IDs are permanent once
published, so this format is hard to change after deployment.

### Reuse

- `createSignedPost` and the expansion path in `createReply` are the models for
  replay: compare with the committed bytes, finish an uncommitted write, repair
  derived state.
- `refreshDerivedStateAfterCommit`, `CanonicalPathResolver::post`,
  `AcceptedRemoval`, `LabelRemoval` and the existing draft storage are reused
  unchanged. No new dependency or schema change.

### Verification

- `./v3 test --smoke BrowserSigningNormalizationTest ComposeAttemptReplayTest LocalWriteServiceTitleDirectiveTest LocalAppSmokeTest`.
  528 of 528 passed. The full suite was not run.
- `tests/browser/compose_attempt_replay.mjs` in Chrome on desktop and phone
  viewports, against a real PHP server: lost response with the lookup down,
  identical retry, reconcile on reload, for threads and replies; then a prefilled
  link after publishing, and Back after publishing.

### Limitations

- The failed-commit case was tested by recreating the leftover file, not by making
  Git fail during a request.
- A thread label or title record left by a failed commit has a random ID, so it is
  not reused. The retry writes a new one and the old file stays on disk, as it
  would have before this branch.
- Without JavaScript there is no attempt ID, so a plain-form resubmit can still
  publish twice. The basic form keeps working as before.
- The browser check blocks the service worker, because `page.route` cannot see
  requests it handles. Lost responses were not simulated with the worker active.
- Chrome only. Firefox and Safari restore forms at their own times, so finding 10
  is unverified there.
- Finding 5's in-flight case is covered by the Node test, not the browser check.
- Other themes were not checked. No markup or CSS changed.

## Second review (2026-11-10)

An independent review, requested in the ZenMemes thread
`thread-20261110175436-b3745c6d`, rebased the branch onto `main` and found two
recovery gaps. Both are fixed on `fix/duplicate-post-submissions-rebased`.

### 11. A refused attempt ID could block posting forever

When the server refused an attempt ID (a stored post that does not match the
draft, or a post removed after a lost response), the error page restored the
draft with the same ID. Every retry sent that ID and got the same refusal until
the user happened to edit the text. A deploy that changes the record format
between a lost response and the retry is enough to trigger it.

**Fix:** these refusals throw `ComposeAttemptRetired`. The compose page then
carries `data-compose-attempt-retired`, and the browser drops the ID and saves the
draft without it. The text stays, and the next send publishes under a fresh ID.
Other errors keep the ID, because the post may already have been accepted.

### 12. Reconcile could clear text the server does not hold

On load, reconcile cleared the composer when any post existed under the attempt
ID, without checking its text. It now clears only when the stored body matches
the draft body after the server's normalization (line endings, trim, NFC). A
mismatch keeps the draft, and finding 11's fix lets it publish.

**Decision (confirmed by Ilya, 2026-11-10):** after a moderator removes a post
whose response was lost, posting again publishes the same text as a new post. A
re-sent reply must stay a reply to the same post, because dropping that would
change what the author sent. The reply form carries `thread_id` and `parent_id`,
so the fresh send keeps both; `testRemovedReplyIsResentAsAReplyToTheSamePost`
checks this for a reply to another reply. A planned feature to reparent a reply
as its own post is separate and leaves this unchanged.

**Verification:** `./v3 test --smoke BrowserSigningNormalizationTest ComposeAttemptReplayTest LocalWriteServiceTitleDirectiveTest LocalAppSmokeTest`
on the rebased branch; the full suite was not run. The browser check now also
covers a refused retry followed by a fresh send, for threads and replies, on
desktop and phone viewports; with the browser part of the fix disabled it fails.
It also reads the lost response's body, because Node 22's `fetch` crashed on the
unread response before any scenario ran.
