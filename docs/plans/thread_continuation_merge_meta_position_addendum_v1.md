# Addendum: Move the merged-run byline to the last post, not the first

Follows on from the original continuation-merge feature docs:
`thread_continuation_merge_step1_solution_assessment.md` / `step2_feature_description.md` /
`step3_development_plan.md` / `step4_implementation_summary.md` (see also `merge_consecutive.txt`
for the original raw task spec). That work made a run of quick same-author replies render as one
visually merged card. This addendum changes *where in that merged card the byline sits*.

## Problem

Today, the meta line (`author · time · labels · N replies`) is always attached to the **first**
post of a run — the root card when the run starts at the root, or the first reply when a run
starts mid-thread — and hidden (`.continuation > .meta { display: none }`) on every post after it.
Since a merged run reads as one continuous piece of writing, having the byline pinned to the top
means it sits far from where the reader actually finishes reading it.

## Change

Show the meta line under the **last** post in a run instead of the first. Everything else about
the run is unchanged:
- the run still visually merges into one card (border/margin/separator rules untouched),
- each individual piece keeps its own hover-revealed action row and permalink,
- deep links to any individual piece still scroll to and highlight it.

**Timestamp shown:** the moved meta line shows the *last* post's own time (not the first post's),
since the line now sits directly under that post. Each earlier piece's own exact time is still
available by hovering it (see below) — nothing is lost, just no longer duplicated on the line
itself.

**Reply count / thread labels:** these are thread-level, not post-level, so they still only ever
appear on the run that starts at the root (never on a run that starts mid-thread), just relocated
to that run's last post instead of the root card.

**Hover behavior — unchanged in spirit, generalized in mechanism:** previously only posts with the
`continuation` class got a hover-revealed time badge in the gutter (`.continuation::before`). Since
meta-hiding is no longer exclusive to `.continuation` posts (the *head* of a run — which may be the
root card, or a plain reply that starts a new run mid-thread — now also hides its meta), the hover
badge is generalized to any post whose meta is currently hidden, not just `.continuation` posts.
The run's last post (which now shows its meta inline) does not get a redundant hover badge.

## Which post is "the last of a run"?

A run is a maximal chain of consecutive posts satisfying the existing continuation rule (same
author, ≤15 minutes apart, neither agent-authored). A post is the run's tail when the *next* post
either doesn't exist or isn't a continuation of it. This is a straightforward extension of the
`isContinuation` flag already computed per-reply in `templates/pages/thread.php` — the tail is
just "the post right before continuation flips back to false" (or the very last post overall).

Only one run can ever start at the root (the contiguous run of continuations immediately following
it); every other run starts at a plain reply.

## Implementation

- `templates/pages/thread.php`: alongside the existing `$replyContinuationFlags` /
  `$trueReplyCount` pre-computation, compute:
  - `$rootRunTailIndex` — the last index of the contiguous continuation-from-root prefix, or `-1`
    if the root has no leading continuations (root is its own tail).
  - Per reply, `$isRunTail` — true if it's the last reply overall or the next reply's
    continuation flag is false.
  - Pass `isRunTail` and a `showRootMetaExtras` flag (`true` only at `$rootRunTailIndex`) into
    `post_card.php`; pass `metaVisible = ($rootRunTailIndex === -1)` into `thread_root_card.php`.
- `templates/partials/thread_root_card.php`: only render the `<p class="meta">` line when
  `metaVisible` is true; otherwise emit `data-time="HH:MM"` (root's own time) and a class that
  triggers the hover badge, matching how continuations already do it.
- `templates/partials/post_card.php`: render `<p class="meta">` only when `isRunTail` is true
  (using the post's own time, plus root's labels/reply-count when `showRootMetaExtras` is true);
  otherwise emit `data-time` + the hover-badge class as above, regardless of whether the post
  itself is a `.continuation` (a non-continuation run head needs the same treatment).
- CSS (`public/assets/content-interactions.css`): the old `.continuation > .meta { display: none }`
  / `.continuation::before` hover-badge rules become a class that isn't tied to `.continuation`
  specifically (e.g. `.meta-deferred`), since the root card and mid-thread run heads are never
  themselves `.continuation` but now need the same hidden-meta + hover-badge treatment. The
  card-merge rules keyed off `.continuation` (borders, spacing, action-row reveal) are untouched.
- Existing test `LocalAppSmokeTest::testThreadMergesSameAuthorQuickRepliesIntoContinuations` needs
  updating: it currently asserts the reply count lands in the *root's* meta line and that both
  continuations carry `data-time`. Under this change the reply count moves to the run's tail
  reply's meta line, and only the non-tail continuation keeps `data-time`.
- **DOM order matters for the tail's meta line.** `post_card.php` already renders a reply's meta
  *before* its body (root's own meta was already *after* its body). When the tail of a run is
  itself a continuation, its meta must render *after* its body too — otherwise, since a run's
  pieces are visually flush against each other, the meta line lands directly under the
  *second-to-last* piece's paragraph (looking like it belongs there) instead of trailing the last
  piece's own text. A standalone (non-continuation) reply keeps the ordinary before-body order.

## Fixed during implementation

Live-tested against a real 6-continuation thread and caught two things the synthetic test fixture
(only 2 continuations) didn't exercise:
- The DOM-order bug above (meta visually attached to the wrong piece).
- Confirmed via a headless-browser hover check that the per-piece hover badge and action-row
  reveal both still work correctly after the class rename (`.continuation` → `.meta-deferred` for
  the hidden-meta hover mechanism) — this had looked, from a live page, like hovering did nothing,
  but that was a symptom of the DOM-order bug making it unclear which piece was under the cursor,
  not an actual loss of hover behavior.

## Out of scope

- No change to the 15-minute window, the data model, post ids/permalinks, or per-post
  like/flag/agent-request endpoints.
- No change to agent-authored replies (never part of a run).
- No change to the board/tag list thread summary (`thread_card.php`).
