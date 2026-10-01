# QDB Theme Fidelity Checklist

Working checklist, not a committed FDP artifact. Check items off as they land.
Each item names the concrete gap between what we have now and the archived
reference (bash.org 2007 for base look, qdb.us 2016 for voting UX).

## A. Highest-impact, CSS-only (scoped to `:root[data-theme="qdb"]`)

- [x] **Quote body isn't actually monospace.** `--code-font` is defined but
  nothing applies it to `.quote-card-body` — it's silently rendering in
  `--body-font` (Arial). Add `font-family: var(--code-font)` to the body rule.
  This is probably the single most noticeable miss.
  _Done: added `font-family: var(--code-font)` to `.quote-card-body` in `theme-qdb.css`._
- [x] **Every quote sits in a bordered, padded box.** The shared `.card` rule
  (`border: 1px solid var(--line); padding: 0.9rem; background: var(--panel)`)
  applies to `quote-card` too. The original has *zero* per-quote chrome — just
  bare `<p>` tags flowing in a table cell. Override `.quote-card` to drop
  border/background/padding entirely.
  _Done: `.quote-card` override drops border/background/padding (including
  `.post-card`'s `padding-bottom` reserved for a permalink glyph we don't use)._
- [x] **Quotes are spaced ~1rem apart in boxes; the original is dense.** The
  shared `.stack > * + *` margin rule adds a full rem between every quote.
  Needs a much tighter, QDB-specific vertical rhythm (closer to the
  original's tight `<p>`-to-`<p>` flow).
  _Done: `.quote-card` margin-top reduced to 0.6rem (overriding the generic
  1rem stack gap), and the header/body `<p>` tags (which had unreset browser
  default margins) tightened to small explicit margins so a quote's own
  header/body/buttons sit close together, like `p.quote`/`.qt`'s
  margin-collapse in the original._
- [x] **Everything is sized like a modern forum, not a dense 2004-era page.**
  Original body/chrome text ran 8–10px; ours inherits the standard
  `.meta` (0.95rem) / body (1rem) scale. Shrink font sizes for this theme.
  _Done: quote body 0.85rem, header/score and action-row text 0.8rem._
- [x] **Score has no color coding.** Original (and qdb.us) show the score in
  green (`#008000`) when positive; ours is plain `--ink-soft` gray via `.meta`.
  Needs a themed override, ideally conditional on sign (harder — see Section B).
  _Done together with Section B's matching item — see there for details._

## B. Needs a template/JS change, not just CSS

- [x] **Score reads "Score: N", not "(N)".** `thread_reactions.js`'s
  `setThreadScore()` hardcodes the `"Score: "` prefix. Already flagged as a
  known gap in the Step 4 summary — fixing it means touching shared JS used
  by every theme, or restyling that text at the CSS level with a wrapper.
  _Done: made the format opt-in via a `data-score-format="bare"` attribute
  on the score node (`setThreadScore`/`parsedThreadScore`/the optimistic-
  update path all check it, default unchanged `"Score: N"` for every other
  theme/page that doesn't set the attribute). `quote_card.php` sets the
  attribute and renders `(N)` server-side too, so there's no flash of the
  wrong format before JS runs._
  _Caught and fixed a real regression while verifying: the existing
  thread-reaction JS tests mock `scoreNode` as a plain object with no
  `getAttribute` method, which crashed the new code
  (`scoreNode.getAttribute is not a function`) and broke 5 passing tests.
  Fixed by making the format lookup defensive (falls back to the old
  default if `getAttribute` isn't present) rather than editing every test's
  mock. All 5 tests pass again._
- [x] **Vote button wording is plain English, not QDB's.** We say
  "Upvote/Downvote/Flag"; the original used bare `+`/`-`/`[X]`; qdb.us used
  labeled `↑Funny`/`↓Not`/`⚑Flag`. Pick one and change `quote_card.php`'s
  button labels.
  _Done: went with the 2007 original's exact symbols (`+`/`-`/`[X]`) rather
  than qdb.us's labeled version, since "the original" is specifically what
  was asked for. Added `aria-label`s ("Upvote this quote", etc.) so the
  terse symbols stay accessible. Kept the same symbol before and after a
  click (just disabled + `aria-pressed`), matching how the original's links
  never changed text either — `data-applied-label` is now the same symbol,
  not a swapped word. Restyled `.quote-card-vote-button` to look like an
  inline link (no border/background/padding) instead of a boxed button._
  _Side effect, accepted: the hidden feedback paragraph now reads something
  terse like "+." after a vote instead of "Upvoted." — that paragraph isn't
  part of the original experience at all, so this is a minor, low-visibility
  tradeoff, not a regression worth a separate mechanism._
- [x] **No color-coded score logic.** Doing this properly (green when
  positive, something else when negative/zero) needs a small conditional in
  `quote_card.php`, not just a CSS rule, since score sign varies per quote.
  _Done: `quote_card.php` computes a `quote-card-score-positive`/
  `-negative` class from `score_total`'s sign (no class when zero);
  `theme-qdb.css` colors them with the existing `--status-ok`/`--status-error`
  tokens (already green/red-ish in this theme) rather than inventing new
  colors. Verified by temporarily forcing the read-model score to +5/-3/0 and
  confirming the right class/color rendered each time, then resetting to 0._
  _Known gap, left as-is: the class is set at page-render time only — after
  a vote, `thread_reactions.js` updates the score **text** live but not this
  class, so a quote that crosses zero via voting won't flip color until the
  next page load. Fixing that needs a shared-JS change (`setThreadScore`),
  which is more than this item's scope._
- [x] **The generic forum chrome above the list doesn't exist in the
  original.** `board.php`'s "Tags / All / Liked / Newest / Oldest / Top /
  New Post" nav bar and the inline "Submit a quote..." composer card are
  pure modern-forum additions. Decide: hide them for this instance (CSS
  `display:none` scoped to the theme, or a template conditional), or keep
  them as a deliberate modern feature layered on top. **Needs your call.**
  _Decided: hide both. Done CSS-only, no `board.php` change needed —
  `:root[data-theme="qdb"] .thread-list > .card:not(.quote-card)` hides
  any direct-child card of the list that isn't a quote (currently just the
  nav row and composer), leaving quote cards untouched. The markup still
  renders (so `/compose/thread` still works by direct navigation), it's
  just visually suppressed for this theme._

## C. New chrome the original had that we have none of

- [ ] **No orange title-bar header.** Original's top bar was a two-cell
  table: bold-italic "QDB" + an "Admin" link on the left, bold page title
  ("Quote Database Home") right-aligned, both on a `#c08000` background.
  Ours uses the shared theme-neutral `.site-header`. Can get partway there
  with a scoped CSS override (background + font-style), since `.eyebrow`
  already exists as a themeable element in other themes — but the exact
  two-cell layout needs checking against what `nav.php`/`layout.php`
  actually render for the site name.
- [ ] **No `#f0f0f0` link-bar nav.** Original's second row (Home / Latest /
  Browse / Random / Top 100 / Add Quote / Search, plus a `#` quote-number
  jump box) was a flat link row on a light-gray background. Ours uses
  button-styled `.nav-link`s with borders. Restyle for this theme.
- [ ] **No footer at all.** Original had an orange footer bar with live
  counts ("20414 quotes approved; 330 quotes pending") and a copyright line
  ("© QDB 1999-2007"). We render nothing after the list. Would need a new
  template addition (or partial) scoped to this instance.
- [ ] **No quote-number jump box.** Original had a `#` + text input in the
  nav to jump straight to a quote by ID. We have no equivalent — low
  priority unless you want real quote-number navigation.

## D. qdb.us refinements not yet carried over

- [ ] **No zebra striping.** qdb.us alternated row background `#ffffff`/
  `#e8e8e8` per quote. Easy CSS (`:nth-child` or similar) once the card
  chrome from Section A is removed.
- [ ] **No vote-count-vs-score ratio ("20/38").** qdb.us showed score and
  total votes cast side by side. Step 2 explicitly scoped this *out* as
  more than "small modifications" — flagging it here since "closer to the
  original" may now outweigh that earlier call. **Needs your call** on
  whether to reopen it (it needs a new read-model aggregate, unlike plain
  score which already exists).

## E. Lower priority / cosmetic

- [ ] Page width: original was an `80%`-wide table; we use a fixed
  `min(100%, 760px)` column. Visually close already; narrow only if it
  still looks too wide/roomy once A–D land.

---

**My suggested order:** A (all CSS-only, biggest visual payoff, zero risk)
→ the color-coding and button-wording parts of B → C's footer/header →
then D/reopening the vote-ratio decision only if you still want it after
seeing A–C.

Two items need your decision before I touch them: hiding the modern board
chrome (B) and whether to reopen the vote-ratio display (D) — both change
things we deliberately scoped out in Step 2.
