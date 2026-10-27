# UI redesign — visual direction notes (round 2, open)

Date: 2026-10-25 · Branch: `ui-redesign-mockups` · Status: one themed
direction built (see Round 2 below); open questions still apply.

Follows the round 1 mockups (`plan_prompt.md`, `mockups/`). Gallery:
https://claude.ai/artifact/EaZEdrJMr5fHPyGw11zLMS (private).

## User feedback on round 1

Liked:

- Clear, concise, current interface; consistent and easy to navigate.
- The Account page in particular (handling of non-standard key identity).

Wants improved:

1. **Generic.** It could be any app; nothing is recognizably ZenMemes.
2. **Not captivating.** A shoulder-surfer on laptop or phone wouldn't do a
   double-take and ask what the app is.
3. **Colored UI icons.** Carry the multi-colored user avatars over into UI
   icons (e.g. the Tools page); color-distinguishable icons scan faster.

The user asked for pushback and agreed with the colored-icon analysis below.

## Analysis

### Why it looks generic

The neutral skin was a deliberate round 1 choice: decision #3 said themes
handle visuals. Fixing this means the **default theme's visual design comes
into scope**, reversing that decision. Most visitors only see the default,
so the default effectively is the brand. Novelty themes (Word97, Chicago,
Midnight, …) stay as alternates. *Needs explicit confirmation (question 1).*

### "Captivating" vs calm

These pull against each other. Personality spread everywhere makes reading
worse. Put it in a few signature places and keep the reading surface quiet
(the Stripe, Linear, Arc pattern):

- wordmark and header
- empty states
- the account-creation moment
- people's colors

### Where ZenMemes identity could come from (to validate with the user)

- **The key is the identity.** That's unusual for a forum, and today it's
  hidden. Derive each person's color, or a small generated pattern, from
  their key fingerprint rather than their name. This also fixes a real
  problem: usernames aren't unique, so two people named "mouse" look
  identical today.
- **Starfield.** The backup format already exists and the name appears 13
  times in `public/assets/*.js`. Use it as the visual motif for account
  creation, empty states, and perhaps a faint header backdrop.
- **"Zen" + "memes".** Calm layout, playful moments; matches the split above.

### Colored icons (agreed)

- **Grouped color, not a rainbow.** Color helps people scan when it stays
  consistent and there are only a few colors; 16 unrelated colors can't be
  learned. Give each tool a fixed color, with similar shades within each
  group, iOS Settings style. Example: Your device in blues, Community in
  greens, Operator in grays and oranges.
- **Shape separates people from things.** Circles for people (avatars),
  rounded squares for tools and places, so a colored tile is never mistaken
  for a person.
- **Color is a second signal.** Every icon keeps a distinct glyph for
  color-blind users. White glyphs need AA contrast on their tile in both
  light and dark mode.
- The retro icon themes (Word97, Chicago, Midnight) keep their own icon
  mappings in `public/assets/theme-nav-icons.css`.

## Proposed next step (not started)

Build 2–3 visual directions, each applied to the same two screens (Board and
Tools) on phone and desktop. The user picks one or mixes them; then roll the
chosen direction out to all 11 mockups. This is quicker to react to than
prose and cheaper if the first guess is wrong.

## Open questions for the user

1. Is the default theme's visual design now in scope (reversing decision #3)?
2. In three words, how should ZenMemes feel? Any reference site or app?
3. Are fingerprint-derived colors acceptable, given that someone who gets a
   new key would get a new color?

## Round 2 built — “Ink & Stars” (2026-10-25)

The user asked for a themed set of six pages layered on the round 1 structure.
I built one direction rather than the proposed 2–3, to match that request.

- Files: `mockups/zen/{board,thread,profile,account,tools,conversation}.html`
  plus the skin `mockups/zen/zen.css`. Each page has a Base / ZenMemes
  switch; with Base selected, the same markup renders exactly as in round 1.
- Concept: paper, ink and a vermilion seal (a key signs posts, like a
  hanko seal) by day; a night sky by dark. Each person has their own color
  and a seeded starfield, in the same visual language as the existing
  Starfield backup format. Likes are drawn as gold stars. Serif display
  titles, system sans body, no web fonts, zero dependencies.
- Colored icons follow the agreed rules: grouped shades, rounded squares for
  things and circles for people, a glyph and label always present, white
  glyphs at 4.1:1 or better.
- Hooks the base must expose for a theme (the "themeable base" contract):
  `--person` on author-owned items, `data-tone` on tool and settings rows,
  an empty `.mark` slot in the wordmark, starfield slots (profile banner,
  identity rows, account hero, empty states), and per-theme icon swaps
  (heart → star).
- Assumptions made for the open questions: (1) treated the default skin as in
  scope for mockups only; (2) the feel was chosen as calm, crafted, a little
  playful; (3) the mockups seed people's color and sky from their name; the
  notes recommend the fingerprint for key-level skies and leave the person-level
  seed open.
- Known trade-off: vermilion as the accent pushes the danger color to
  crimson-rose, so every danger state keeps an icon or label.
- Verified: all six pages at 1400px light and 390px dark, with no console errors
  and no horizontal overflow; screenshots reviewed (fixed the banner covering the
  profile name, the mark reading as a “C”, and the hard edge on the dark phone
  header). Round 1 pages re-checked after the shared script changes: unchanged.

## Round 3 — “Living Sky” replaces “Ink & Stars” (2026-10-25)

User feedback on round 2: “still pretty weaksauce … not interesting or
captivating or even eyecatching. Try harder.” Also: on Tools I had colored the
tile backgrounds rather than the icons themselves, which isn't what they asked for.

What changed, in the same six files (`mockups/zen/`, skin `zen.css`):

- **The whole screen is a moving sky**, modeled on Apple Weather and Apple Music.
  Light mode is a dawn gradient with drifting glows; dark mode is deep space
  with nebulae, twinkling stars and an occasional shooting star. Motion is
  slow and stops under `prefers-reduced-motion`.
- **Context paints the sky** (`data-sky`): a thread takes its original poster's
  color, and a profile or conversation takes that person's.
- **Frosted glass** surfaces (62% opaque) keep body text at AA over any sky.
- **People are glowing planets**; the profile avatar has a ring, and the
  person's constellation is drawn across the top of their page.
- **One brand gradient** (ember → magenta → violet) for the wordmark, primary
  buttons, badges, unread markers, hot counts and your own message bubbles.
  White text is 4.6:1 or better at every stop.
- **Colored glyphs, not tiles**: the icon is drawn in the tool's color
  (duotone fill on closed shapes and a glow), in Tools, Account rows, the account
  menu and the phone tab bar.
- Heavy display type (800 weight, tight tracking) replaces the serif.
- New hooks for the base: the sky layer in the app shell, `data-sky` per page,
  and `data-tab` on navigation items. The earlier hooks remain.

Verified: all six pages render with no console errors and no horizontal overflow
at 1400px light and 390px dark. I reviewed screenshots and fixed three problems:
the full-screen sky's styles had leaked into the small starfields, translucent
content showed through under the reply box, and the first-message orb was missing
its background. The round 1 pages still render unchanged with the base skin.
Cost to note: the blur effects are GPU-heavy, so a real implementation should
measure performance on low-end phones and fall back to solid surfaces.

## Round 4 ideas — amplifying Living Sky (2026-10-25, discussion only)

The user liked Living Sky and asked how to amplify it while staying performant.
They have not noticed performance problems and want us to stay mindful of cost,
not optimize now. Direction agreed in chat: make *moments* louder (getting an
identity, posting, a like, opening a profile). Reading surfaces stay calm.
Nothing below has been mocked up yet.

### Grading key

- **Difficulty:** effort to build in the real app (Low / Med / High).
- **A11y/compat risk:** the chance it hurts contrast, motion sensitivity,
  screen readers or color-blind users, or breaks in some browsers.
- **Perf risk:** cost on a low-end phone if built as described.

### Set A — space ideas that build on Living Sky

| # | Idea | Difficulty | A11y/compat risk | Perf risk | Notes |
|---|------|-----------|------------------|-----------|-------|
| A1 | **Unique planets:** bands, craters, ring tilt and moons derived from the key fingerprint | Med | Low | Low | Generated once and cached. The name stays the identifier; the planet is a second signal. Answers open question 3. |
| A2 | **Growing constellation:** one star per post on the profile; liked posts glow brighter | Med | Low | Low | Needs per-identity post counts from the read model, so follow the read-model schema rules in `CONTRIBUTING.md`. Cap the number of drawn stars. |
| A3 | **Reactive sky:** a new star when you post, a flare on a like, a shooting star in the poster's color for new activity | Med | Med | Low | Flashes must stay under 3 per second and stop under reduced motion. Live updates depend on whatever push or polling already exists. |
| A4 | **Time-of-day sky:** dawn, midday or dusk in light mode; deeper or lighter night in dark mode | Low | Med | Low | One variable set at page load. Each sky variant multiplies the contrast checks. |
| A5 | **Planet flight:** the avatar flies from the thread row into the profile header | Med | Med | Low | Uses the browser's cross-page View Transitions. Firefox lacks support and just navigates normally. Disable under reduced motion. |
| A6 | **Orbit loader** for key generation and decryption | Low | Low | Low | Keep a text status (`aria-busy`/live region); the orbit is decoration. |

### Set B — "zen" ideas, from a modern-artist brainstorm

The critique behind these: space is borrowed from Apple Weather. The app is
called *Zen*Memes, and nothing in the theme uses zen yet.

| # | Idea | Difficulty | A11y/compat risk | Perf risk | Notes |
|---|------|-----------|------------------|-----------|-------|
| B1 | **Ensō** (one-stroke zen circle) as the logo and loader. Each thread's mark is an open circle that closes as replies come in | Low for the logo and loader; Med for the reply circle | Low–Med | Low | Need to decide what counts as a "finished" thread. Keep the reply count as text; the circle alone doesn't convey a number. |
| B2 | **Day garden, night cosmos:** light mode is a raked-sand garden (threads as stones, replies as ripples); dark mode is the sky, with the ripples as orbits | High | Med | Med | A second full art direction. Sand texture must never sit behind text. Draw ripples once as static SVG, not per frame. Strongest "double-take" idea. |
| B3 | **Kintsugi:** edited posts and resent messages show a gold seam | Low–Med | Low | Low | Check whether the app records edits and resends. Keep the "Edited" text label. Gold needs a darker variant for contrast in light mode. |
| B4 | **Seal on every post:** a hanko or wax-seal sigil unique to the author's key | Med | Med | Low | **Security UX:** show the seal only after the signature verifies, or it becomes a convincing fake badge. Generate from the key, not the per-post signature, so it's stable. The generator must keep lookalike seals apart, including for color-blind users. Helps tell apart people who share a name. |
| B5 | **Community pulse:** sky density and brightness follow the last 24 hours of activity, and the sky goes still when it's quiet | Low–Med | Low | Low | One aggregate number per page load. |
| B6 | **Riso prints:** meme thumbnails in two-tone halftone in the poster's color; hover or tap shows the real image | Med | Med–High | Med | Hides the content people came for, so use it on thumbnails only, if at all. Pre-render on the server; live SVG filters on many images are expensive. |
| B7 | **Data-driven type:** busier threads get heavier, wider titles | Low–Med | Med | Low | A variable font is a third-party asset and needs dependency approval under `CONTRIBUTING.md`. Watch wrapping at large text sizes. |
| B8 | **Agent aurora:** the AI agent is drawn as light, not a planet | Low | Low | Low–Med | Keep the "agent" text label. Use a static gradient, or a slow animation that stops under reduced motion. |
| B9 | **Grain:** a faint paper texture over the UI | Low | Low–Med | Low (static) / High (animated or blended) | Use a static tiled image with no blend modes, and keep it out from behind body text. |

### Recommendation

- **First:** A1 + A2 + A3 (identity as planet, history as constellation, a sky
  that responds).
- **From set B:** B3 Kintsugi, B4 Seals and B2 Garden/cosmos. Kintsugi gives the
  round-1 error recovery a visual voice. Seals make the app's invisible
  signatures visible and help tell apart people who share a name. Garden/cosmos
  finally puts the zen in ZenMemes and gives light mode its own character.
- **Skip or test carefully:** B6 Riso prints.

### Performance rules for any new effect

- Animate only `transform` and `opacity`. Never animate blur, filters or
  gradients.
- No new `backdrop-filter` surfaces beyond the current set. When the theme moves
  to app code, consider dropping blur everywhere except the header, the reply
  dock and sheets; the nebulae are already soft, so translucent tint looks
  nearly the same.
- Draw generated art (planets, seals, constellations, ensō, ripples) once and
  reuse it. Never redraw per frame.
- Every effect has a static version for `prefers-reduced-motion`. Add a solid
  fallback for `prefers-reduced-transparency`.
- Pause effects when the tab is hidden or the sky is off-screen.
- Before shipping, record a scroll with the CPU slowed 4×. Target 60 fps and
  under 2 ms of paint per frame.

### Built in the themed mockups (2026-10-25)

The user asked for the easy, low-risk ideas. Four are now in `mockups/zen/`:

- **B1 Ensō (logo and loader only):** a tapered one-stroke circle in the brand
  gradient replaces the star mark. The loader rotates a gradient under a fixed
  ensō mask (transform only); under reduced motion it becomes a solid ensō.
  Shown in the Board top bar and the Thread "agent working" screen. The
  reply-progress circle (Med) is not built.
- **A6 Orbit loader:** your planet with a moon on a rotating arm. Shown in a new
  Account screen, "creating the account after Continue". The heading is the
  status message and the card is `aria-busy`. The base skin falls back to a
  plain spinner.
- **B8 Agent aurora:** the agent avatar is an organic aurora shape with
  shimmering curtains (opacity animation), and agent replies carry aurora
  curtains along the top. The "Agent" chip stays.
- **B5 Community pulse:** `data-pulse` (`quiet` / `steady` / `busy`) on the
  app shell. Busy brightens the nebulae and sends shooting stars more often;
  quiet dims the sky, pauses the drift and removes the shooting star. The Board
  shows both (busy desktop and phone, plus a quiet phone screen).

All new motion animates only `transform` or `opacity`, and stops under reduced
motion. No new blur surfaces. Verified: six themed pages at 1400px light and
390px dark with no console errors or overflow; round-1 pages unchanged; base
fallback for the orbit checked.

## Round 5 — "Ma" (間), a second theme (2026-10-26)

The user asked for another iteration, designed as a collaboration between the
shortlist (Kenya Hara, teamLab, Shigeo Fukuda, Tyler Hobbs, James Turrell) and
the Japanese designers (Ikko Tanaka, Tadao Ando). Ma is a separate skin
(`mockups/zen/ma.css`) on the same six pages, so it can be compared with Living
Sky; the switch is now Base / Living Sky / Ma, and pages open in Ma.

Who contributed what:

- **Hara:** warm paper (with a static grain), generous space, hairlines
  instead of shadows, a mincho serif for names and titles, and one accent:
  vermilion seal ink. Your own message bubbles are ink on paper.
- **Ando:** one shaft of light crosses every page, drifting over 90 seconds
  like the sun. Moonlight in dark mode.
- **Turrell:** each page is lit from above in the color of whoever it's about.
  The profile banner is a skyspace: a crisp opening onto light in the person's
  color that spills onto the page. The agent is a disc of light, not a person.
- **teamLab:** petals fall in the person's color; how many follows the
  community pulse (busy 7, ordinary 3, quiet 0 with the light held still). A
  like blooms with five petals around a filled vermilion star.
- **Hobbs:** a seeded flow field draws each key: the account card, the
  identity rows on a profile, and the first-message screen. Mostly the
  person's color, some ink, and rarely one vermilion stroke.
- **Tanaka:** avatars are flat circles with a fixed geometric cut per name
  (half, quarter or a small moon). Navigation and Tools groups are marked by
  shape and color (circle, square, triangle, half-circle).
- **Fukuda:** the logo is an ensō with two dots, a zen circle that is also a
  calm meme face. It closes its eyes while the agent writes or your key is
  made, and looks sideways at the missing star on the empty Liked screen.

Performance: no `backdrop-filter` anywhere. Animation is transform and opacity
only (the light shaft, petals and loaders), and all of it stops under reduced
motion. Generated art is drawn once per page.

New hooks: `data-cut` and `--av-rot` on avatars (from a hash of the name),
`data-shape` on Tools group headings, petal elements in the sky layer, and a
flow-field SVG beside each starfield. The backup sheet keeps the real Starfield
thumbnail, because that is what the file looks like.

Verified: six pages at 1400px light and 390px dark with no console errors or
overflow; Living Sky and round-1 pages unchanged. Contrast: muted text 4.7:1 or
better on paper, accent text 5.85:1, white on vermilion 5.98:1, tool glyphs
3.86:1 or better (icon minimum 3:1). Fixed during review: petals stacked in
one place (selector counted the sky's other layers) and a gap inside a run of
posts by one person.

### Round 5b: Ma at night, with cyberpunk (2026-10-26)

The user asked how to add cyberpunk, then said go ahead. Ma's dark mode is now
"Shinjuku by night" (light mode stays "Kyoto by day"). The idea: Ma is the calm
surface and cyberpunk is the machinery underneath, which here is real (keys,
signatures, encryption).

- **Neon sign:** the logo and seal glow and flicker once when the page loads
  (2 dips in 1.8 s; none under reduced motion). Primary buttons, badges and
  the pinned edge get a neon glow.
- **Rain:** the petals become rain streaks; the busy/ordinary/quiet pulse still
  sets how many. The light shaft becomes magenta and cyan neon through rain.
- **Keys in neon tubing:** the key flow fields glow, with cyan and neon accents.
- **ID-chip look:** account cards get corner brackets and a monospaced caption
  of real data (fingerprint, approval state, "Generating key pair").
- **Decryption you can see:** an incoming message scrambles through hex and
  resolves once when it scrolls into view (Conversation page). Screen readers
  get the final text from the start; reduced motion shows it immediately.
- **Glitch only means failure:** short failure labels get a slight red/cyan
  offset; long error text stays clean and gets a split neon edge instead.
- **Holographic agent:** scanlines over the agent's disc of light and reply.
- **Data typeface:** times, counts, fingerprints and the encryption line use
  the system monospace font (no new dependency).

Avoided on purpose: code rain, decorative katakana, and neon everywhere.
Performance: glow sits on static art; rain and the flicker are transform or
opacity; the scramble is one short text update per message; still no blur.
Night contrast: cyan 12.6:1, neon 5.4:1, muted text 5.7:1 or better.

Profile review fixes (2026-10-26): the banner was painted over the avatar,
cutting it in half (now the avatar sits on the banner's edge); the skyspace was
a flat gradient with a hard white band at the bottom (now a knife-edged opening
with an inner shadow at the top and light rising from below); your own profile
on phone showed the name twice (the top-bar title is gone, which also applies
to Living Sky on that page); the large "moon" avatar cut poked past the circle
(now inside it).

Banner, second pass (2026-10-26): the empty block of color read like a cover
photo that hadn't loaded. Now the person's own flow field is drawn inside it in
faint white strokes (cyan and white glow at night), so every banner is unique; a
warm glow fades in and out over 32 seconds like a Turrell sunset (opacity only,
held still under reduced motion); and it is shorter (180px desktop, 160px phone).
The edge and inner shadow moved to an overlay so the strokes sit under them.
At night the banner was still the bright daytime gradient and glared against
the page. It now opens onto a dark sky lit low in the person's color, with
their flow field as neon lines (person color, cyan, rarely red), matching the
account card; the slow dusk glow turns neon.
