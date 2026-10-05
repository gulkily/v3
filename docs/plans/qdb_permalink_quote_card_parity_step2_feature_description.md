# QDB Permalink Quote-Card Parity — Step 2: Feature Description

> **Feature plan:** [Step 1](./qdb_permalink_quote_card_parity_step1_solution_assessment.md) · [Step 2](./qdb_permalink_quote_card_parity_step2_feature_description.md) · [Step 3](./qdb_permalink_quote_card_parity_step3_development_plan.md) · [Step 4](./qdb_permalink_quote_card_parity_step4_implementation_summary.md)

## Problem

A qdb quote's permalink page looks and votes differently from the listing
card it was clicked from: no quote-number header, no score readout, and
Like/Flag buttons instead of upvote/downvote/flag. Separately, the
listing's own vote buttons do nothing at all.

## User stories

- As a visitor reading a quote on its permalink page, I want it to look
  like the listing card I clicked, so the page feels like the same quote
  rather than a generic forum thread.
- As a visitor, I want to upvote, downvote, and flag a quote from either
  its listing card or its permalink page, and see the same score in both
  places, so the vote means one thing.
- As a visitor browsing the listing, I want the vote buttons to actually
  work, so voting isn't silently broken on the pages I use most.

## Core requirements

- A qdb quote's permalink root card shows the same `#N` header, `(score/votes)`
  readout, and quote body styling as its listing card.
- Its vote controls are upvote, downvote, and flag, matching the listing;
  Like is no longer offered on qdb quotes.
- Voting works on the listing's quote cards (`/latest`, `/top`, `/random`,
  `/search`, board) as well as on permalink pages, with the same score
  updating in place.
- Everything else on the permalink page — the reply list, reply composer,
  and existing root-card features on other site profiles — is unchanged.
- Non-qdb site profiles are unaffected.

## Completion boundary

- **Normal entry:** clicking a quote on the listing to reach its permalink,
  or voting directly from a listing card.
- **End-to-end outcome:** the permalink matches the listing card visually;
  an upvote, downvote, or flag from either surface moves the same
  `(score/votes)` readout in both places.
- **Recovery:** a vote on a quote the visitor has already voted on stays
  disabled as today, and a vote that fails to save shows the existing
  reaction-feedback message rather than silently changing the displayed
  score.
- **Release condition:** shippable alone; no dependency on other in-flight
  work.

## Risks

- **Risk:** quotes that already carry historical `like` votes keep those
  Likes in their score numerator, but Likes never counted toward the
  denominator — so the `(5/0)`-style inconsistency persists for existing
  data even after Like is removed from the UI.
  *Impact:* existing qdb quotes can still show impossible ratios.
  *Earliest validation:* before implementation, count existing `like`
  tags on qdb quote threads in the local read model to size the problem.
  *Mitigation (decided, Option B):* on the qdb profile only, existing
  `like` votes count toward a quote's vote total alongside upvote and
  downvote, so every historical Like adds one to the denominator as well
  as the numerator. No data migration or schema change; applied where
  vote totals are derived.
  *Residual risk:* vote totals are derived in two places (full rebuild and
  incremental update), so the qdb-only rule must be applied to both or
  the two will disagree. Step 3 must verify they match.
- **Risk:** the listing-button fix changes script loading on the board,
  random, and search pages, which other profiles' pages also use.
  *Impact:* a regression on other profiles' pages if the script binds
  where it shouldn't.
  *Earliest validation:* confirm zenmemes and chouse board pages render
  identically before and after, in Step 3's verification.
  *Mitigation:* scope the change to qdb quote-card pages only, not a
  global script-list change.
- **Risk:** the permalink's vote buttons show the wrong pressed/disabled
  state if the viewer's existing upvote/downvote history isn't passed to
  the permalink page the way the listing already receives it.
  *Impact:* visitors may think their vote didn't register.
  *Earliest validation:* a Step 3 check that a previously upvoted quote
  shows as already upvoted on its permalink.
  *Mitigation:* wire the same viewer-state lookup the listing uses into
  the permalink page.

## Shared component inventory

- `templates/partials/thread_root_card.php` — extended: for qdb quotes
  only, its Like/Flag button row and missing score readout are replaced
  with the listing's upvote/downvote/flag and `(score/votes)` markup.
  Reused, not forked; all other root-card features stay in place.
- `templates/partials/quote_card.php` — the reference for markup and
  behavior; not changed by this feature.
- `templates/pages/thread.php` and `ThreadAndPostPageController` — extended
  only to supply the upvote/downvote/flag viewer state the permalink card
  now needs; reply list and composer unchanged.
- `BoardPageController` (`board()`, `random()`, `search()`) — one-line
  script-loading fix so the listing's existing vote buttons bind; no
  markup change.
- `TagScore` — reviewed, not necessarily changed; the Like-scoring decision
  above determines whether it changes.

## Simple user flow

1. Visitor browses `/latest` and clicks a quote's `#N`.
2. The permalink shows the quote with the same header, score readout, and
   upvote/downvote/flag controls as its listing card.
3. Visitor upvotes it; the score updates in place on the permalink.
4. Visitor returns to `/latest`; the same quote shows the same updated
   score, and its vote buttons work there too.

## Success criteria

- A qdb quote's permalink header, score readout, and vote controls match
  its listing card.
- An upvote, downvote, or flag from the listing or the permalink updates
  the same score in both places without a reload.
- No qdb quote shows a Like control; no new Likes can be added to qdb
  quotes.
- On qdb, every quote's `(score/votes)` denominator is at least its
  numerator's count of votes: no ratio like `(5/0)` appears for any
  quote, including ones with historical Likes.
- Listing vote buttons (`/latest`, `/top`, `/random`, `/search`) work.
- Zenmemes and chouse pages render unchanged.
