# QDB Quotes Instance — Step 3: Development Plan

## Stage 1
- Goal: stand up the new site instance's identity/branding plumbing.
- Dependencies: none.
- Expected changes:
  - New entry in `SiteProfileRegistry::all()` (e.g. `qdb`) with its own default theme.
  - New entry in `ThemeRegistry` for the QDB-style theme.
  - `docs/runbooks/production_deploy.md` gains the new `FORUM_SITE_ID` value and a vhost example pairing it with its own `FORUM_REPOSITORY_ROOT`/`FORUM_DATABASE_PATH`/`FORUM_STATIC_HTML_ROOT`, mirroring the `chouse` entry.
- Verification approach: run locally with `FORUM_SITE_ID=qdb`; confirm site name/default theme resolve to the new profile; existing `SiteProfileRegistryTest.php` pattern extended for the new entry.
- Risks or open questions:
  - none.
- Canonical components/API contracts touched: `SiteProfileRegistry`, `ThemeRegistry` (both extended, not forked).

## Stage 2
- Goal: ship the QDB-accurate visual skin (no voting behavior yet).
- Dependencies: Stage 1 (theme entry must exist to attach the stylesheet).
- Expected changes:
  - New `public/assets/theme-qdb.css` carrying the archived palette/fonts (burnt-orange/white chrome, Arial UI text, courier-new quote body) per the theme guide's variable-block/swatch/scoped-section structure.
  - `tests/LocalAppSmokeTest.php` theme allow-list updated for the new theme name.
- Verification approach: local dev server visual check against the archived classic-QDB snapshot; smoke test passes.
- Risks or open questions:
  - none.
- Canonical components/API contracts touched: `ThemeRegistry` stylesheet path convention (no new wiring needed, per theme guide).

## Stage 3
- Goal: extend the existing scored-reaction vocabulary with upvote/downvote, no schema change.
- Dependencies: none (independent of Stages 1-2).
- Expected changes:
  - `TagScore::scoredTags()` gains `upvote` and `downvote` weights (symmetric, e.g. +1/-1), alongside existing `like`/`flag`.
  - Confirm `PostReactionRecordParser`'s tag-token rules already accept `upvote`/`downvote` tokens as-is (lowercase, no new validation needed).
- Verification approach: unit-level check of `TagScore::scoreValueForTag()` for the two new tags; confirm `ReadModelBuilder`/`IncrementalReadModelUpdater` pick up the new weights automatically since both already iterate `TagScore::scoredTags()` generically.
- Risks or open questions:
  - Score aggregation currently only counts reactions from identities present in `approvalState` (existing gating in the score-reduction loop). Need to verify whether anonymous QDB-style voting will move the displayed score under this gate, or whether that gate needs a documented exception for this instance before Stage 6 sign-off.
- Canonical components/API contracts touched: `TagScore` (extended weight table only).

## Stage 4
- Goal: render the index as a flat QDB-shaped quote list instead of the generic thread-card board.
- Dependencies: Stages 1-3 (profile, theme, and tag vocabulary must exist first).
- Expected changes:
  - New `templates/partials/quote_card.php`, modeled on `thread_root_card.php`'s existing `data-thread-reactions-root`/`data-action="apply-thread-tag"` block (same score node, same AJAX wiring) but trimmed to `#ID` + score + full quote body — no subject, preview truncation, or labels — with upvote/downvote/flag buttons in place of like/flag.
  - `templates/pages/board.php` branches on the active site profile to render `quote_card.php` instead of `thread_card.php` for this instance only; other profiles' board rendering is untouched.
- Verification approach: manual browser check of the index against the archived reference shape; confirm zenmemes/chouse board pages render unchanged.
- Risks or open questions:
  - none.
- Canonical components/API contracts touched: `templates/pages/board.php` (new conditional branch), `templates/partials/quote_card.php` (new, reuses existing reaction JS/API contract rather than inventing one).

## Stage 5
- Goal: make the shared reaction feedback copy tag-accurate so upvote/downvote don't say "Liked."
- Dependencies: Stage 4 (new buttons must exist to exercise this).
- Expected changes:
  - `bindThreadReactions()` in `public/assets/thread_reactions.js` stops hardcoding `"Liked."`/`"Already liked."` and instead derives the feedback copy from the button's existing `data-applied-label` attribute (already passed in but currently unused for this string).
  - Regenerate the fingerprinted copies of `thread_reactions.js` per the existing asset-fingerprinting process.
- Verification approach: manual check that Like still reads "Liked."/"Already liked." (unchanged wording, now parameterized) and that Upvote/Downvote read their own applied labels.
- Risks or open questions:
  - none.
- Canonical components/API contracts touched: `thread_reactions.js` (`bindThreadReactions`) — shared by all instances; change is additive/generalizing, not behavior-changing for existing tags.

## Stage 6
- Goal: end-to-end verification pass across the whole feature.
- Dependencies: Stages 1-5.
- Expected changes: none (verification-only stage).
- Verification approach:
  - Confirm the quotes instance's index visually matches the blended classic-QDB/qdb.us reference.
  - Confirm clicking a quote's `#ID` reaches the existing `/threads/<id>` discussion page with working replies, unmodified.
  - Confirm upvote/downvote/flag update the displayed score without a page reload and persist across reload.
  - Confirm zenmemes/chouse boards show no behavioral or visual regression.
- Risks or open questions:
  - Resolve the Stage 3 approval-gating question definitively here if still open.
- Canonical components/API contracts touched: none (verification only).
