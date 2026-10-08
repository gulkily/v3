# QDB Permalink Quote-Card Parity — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./qdb_permalink_quote_card_parity_step1_solution_assessment.md) · [Step 2](./qdb_permalink_quote_card_parity_step2_feature_description.md) · [Step 3](./qdb_permalink_quote_card_parity_step3_development_plan.md) · [Step 4](./qdb_permalink_quote_card_parity_step4_implementation_summary.md)

## Original Query

Would it make sense to also design the individual quote pages to be more
in line with the listing? Or separate slice?

## Problem

A qdb quote's permalink page (`/threads/<id>`, reached via its listing
card) renders the generic `thread_root_card.php` — no quote-number
header, no score readout, and Like/Flag buttons instead of quote_card
.php's upvote/downvote/flag — so it looks and behaves differently from
the listing it was just clicked from.

## Context found

- `thread_root_card.php`'s `<article>` already carries the same
  `data-thread-reactions-root`/`data-thread-id`/`data-post-id` attributes
  `quote_card.php` relies on, and the permalink page already loads
  `/assets/thread_reactions.js` — so swapping which buttons render there
  needs no new client-side wiring, just the markup and the viewer-state
  data thread.php passes in.
- Real, pre-existing score-math bug (`qdb_todo.txt` item 1): Like is
  scored (`TagScore::scoredTags()`) but only `upvote`/`downvote` count
  toward `vote_count` (`TagScore::voteTags()`). A Like on the permalink
  moves the numerator without the denominator, producing ratios like
  `(5/0)`. The todo's own note already rejected the narrow fix (adding
  `like` to `voteTags()`) as leaving two buttons able to move one score.
- Related, separate, still-open bug found in the same todo item: quote
  cards' upvote/downvote/flag buttons don't work at all on `/latest`,
  `/top` (`board()`), `/random`, `/search` — `thread_reactions.js` isn't
  in those controller methods' `scriptPaths`. Shipping working buttons on
  the permalink while the listing's are still dead would be a confusing
  asymmetry for a feature about matching the two, so this one-line fix is
  proposed as part of the same slice (flagged here, not assumed).
- `thread_root_card.php` also drives agent-reply requests, Codex
  handoff, post analysis, and LLM-exchange links — real features used on
  other site profiles, unrelated to qdb. Any approach must not fork or
  drop these for the shared template's other consumers.

## Options

### Option A — Conditionally swap the button row/score inside `thread_root_card.php` (Recommended)
When the thread is a qdb-numbered quote, render the score readout and
upvote/downvote/flag buttons (matching `quote_card.php`'s markup exactly)
in place of Like/Flag; everything else in the card — title, body, meta,
agent-reply/Codex/analysis panels — stays untouched, since this is one
template, not two.
- Pros: single source of truth, so every other `thread_root_card.php`
  feature keeps working unchanged for every profile; resolves the
  score-math bug as a side effect (no more Like button on these quotes,
  so the score only ever moves via upvote/downvote/flag, same as the
  listing); smallest change.
- Cons: adds one more conditional branch to an already-busy shared
  template.

### Option B — New dedicated qdb-only permalink card, used in place of `thread_root_card.php` for qdb quotes
A new partial renders title/body/meta/score/buttons in quote_card.php's
style; `thread.php` calls it instead of `thread_root_card.php` for qdb
numbered quotes, dropping agent-reply/Codex/analysis entirely there.
- Pros: keeps qdb's card fully isolated from the generic template's
  growing complexity.
- Cons: forks title/body/meta markup that Option A doesn't need to
  duplicate; silently removes agent-reply/Codex/analysis availability
  from qdb permalinks, which is probably fine (classic-quote site, not a
  power-user forum) but is a real behavior decision to confirm, not a
  side effect to assume.

### Option C — Fix only the score-math bug, leave Like/Flag as-is
Add `like` to `TagScore::voteTags()` so the ratio is at least coherent;
no markup or button changes.
- Pros: smallest possible change.
- Cons: does not address the actual request — the permalink still looks
  nothing like the listing; already rejected once in `qdb_todo.txt` as
  leaving two buttons able to move one quote's score.

## Recommendation

**Option A**, including the one-line `scriptPaths` fix for the listing's
dead vote buttons as part of this slice — without it, "parity" would mean
shipping a *second*, newly-working voting surface while the first stays
broken. This is a real, independently releasable vertical slice: normal
entry (click a quote, or vote from either the listing or the permalink),
observable end-to-end outcome (same look, same working buttons, same
score, in both places), and no dependency on any other in-flight feature.

**Estimated lift:** small-to-moderate — roughly half a day, 2-3 Step 3
stages.
