# MIT Rap Club — de-genericize + extend checklist

Raw candidate list, not yet scoped or decided — input for the next FDP round
(likely Step 1, since several items are genuine either/or calls). Grouped by
what to strip vs. what to add; each item below reflects something actually
checked in the current code, not a guess.

## Strip away generic (not-MIT-Rap-Club-specific) surface

- [x] Header/title text (`SiteConfig::siteName()`) renders the profile's
      `name` (`"mitrapclub"`, lowercase slug) rather than `displayName`
      (`"MIT Rap Club"`) — inconsistent with the about page, which already
      says "About MIT Rap Club". **Done in Cycle 1**
      (`feature/mitrapclub-branding-fixes`): added `SiteConfig::displayName()`
      and switched the 6 pure-display call sites to it, leaving the 6
      identifier/filename call sites on `siteName()`.
- [ ] `permittedThemes` still offers all 13 generic app themes (light, dark,
      console, lcd, chicago, vapor, forge, sticker, arena, thermal,
      whitehot, word97, auto) alongside `mitrapclub` — decide whether
      visitors should be able to switch away from the club's own look at
      all (qdb keeps the same breadth; chouse does too).
- [x] About page's "A continuous social graph" and "How participation
      works" sections are hardcoded, identical on every profile, and
      describe the app's OpenPGP identity/vouching system in infra-level
      language — not club-voiced, not gated by any presentation slot.
      **Done in Cycle 2** (`feature/mitrapclub-about-copy`): rewritten
      club-voiced ("Built on who vouches for whom" / "Anyone can watch,
      members hold the mic") for `mitrapclub`, with the same functional
      links preserved; other profiles unchanged.
- [x] About page's "Portable by design" section (Backup / API / llms.txt)
      is generic developer-facing content, unlikely to matter to a visiting
      fan or member. **Done in Cycle 2**: rewritten club-voiced ("Nothing
      here lives or dies with one server"), same links preserved.
- [x] Nav labels (Board / About / Users / Tools / Account) are the shared
      forum vocabulary. Precedent exists for a fully custom nav: qdb's
      `QdbPresentation::navigation()` replaces this entirely (Welcome /
      Latest / Top / 1337 / Random / Add Quote / Search), with Account/
      Invite deliberately dropped from the nav but still reachable by
      direct URL. **Done in Cycle 3** (`feature/mitrapclub-structural-identity`):
      `mitrapclub` keeps the generic forum nav/routes (no qdb-style bespoke
      nav), just drops `Tools` from the visible list via a new `toolsNav`
      presentation slot; `/tools/` stays reachable by direct URL.
- [x] `/tools/` page (backup downloads, SQLite viewer, bookmarklets, etc.)
      is generic site-operator tooling — decide whether it belongs in the
      club's nav at all, or should just stay reachable by direct link
      (same pattern qdb already uses for Account/Invite). **Done in Cycle 3**:
      resolved together with the nav item above.
- [x] Favicon (`public/favicon.ico`) and the PWA manifest's `theme_color`/
      `background_color`/`icons` (`BrowserRuntimeAssetRenderer::manifest()`)
      are hardcoded and shared across every profile — the browser tab icon
      and installed-app icon aren't club-branded, even though the manifest
      `name`/`short_name` already are. **Done in Cycle 3**: added a real
      per-profile favicon/icon override mechanism (`favicon` presentation
      slot + `FaviconRegistry`); `mitrapclub` is wired to
      `public/assets/favicon-mitrapclub.ico`, currently a byte-identical
      copy of the shared default since no club icon asset exists yet —
      swapping in real art later is a data change, not new plumbing.
- [x] Busy-page copy ("Temporarily Busy") is the generic boston/qdb
      fallback text, not club-voiced. **Done in Cycle 1**
      (`feature/mitrapclub-branding-fixes`): now "The Cypher's Full" /
      "The mic's getting passed around right now. Try again in a moment."
      for `mitrapclub` only.
- [x] The fixed sentence in `ProfilePresentationContent::about()`
      ("... is a small forum for people who want a more durable local
      internet...") is still generic infra copy, shared verbatim by every
      profile — flagged and accepted as v1 scope in the last feature; worth
      revisiting now that we're extending further. **Done in Cycle 2**:
      intro is now per-profile (`introText`); `mitrapclub` reads "...is
      where the rhymes get studied as hard as they get spit..."; other
      profiles byte-identical (confirmed by diff).

## Extend the theme (visual identity)

- [ ] Current `theme-mitrapclub.css` only overrides CSS color variables and
      a handful of selectors (site-header, eyebrow, card, nav-link,
      inputs/buttons) — no distinctive visual motif like cypherposium.com's
      Polaroid-photo-in-a-chalk-cube imagery or chalk-style display type.
- [ ] No hero/banner treatment on the board page — cypherposium.com leads
      with a bold graphic headline ("what is rap?") plus two event CTAs;
      the current board is just the shared layout with new colors.
- [ ] No club-specific display typeface for headings (only a body/UI font
      swap today).
- [ ] No visual distinction between post types on the board — an event
      announcement thread looks identical to an ordinary discussion thread.

## Club-specific features (new capability, not just skin)

- [x] Event posts have no structured presentation (date/location/RSVP
      link) — still plain threads. This is the deferred "Option B" from
      the original feature's Step 1 (a qdb-style dedicated events
      experience); worth a fresh Step 1 decision now that there's appetite
      to extend. **Done in Cycle 4** (`feature/mitrapclub-events-experience`):
      took the lighter-weight route instead of a full dedicated experience —
      optional structured date/location/link on a thread's root post,
      rendered as a distinct block on the board and thread page. A full
      qdb-style events experience remains available later if this proves
      insufficient.
- [x] No embedded media support for YouTube clips or Instagram posts inside
      threads, even though the club's actual content is video/photo-heavy.
      **Done** (Cycle 5, `feature/mitrapclub-media-embeds`,
      `mitrapclub_media_embeds_*`): a YouTube/Instagram URL in a post body
      renders as a small local card (no raw third-party HTML), with a
      narrow per-provider tracking-param strip on the card's displayed
      URL. Shipped behind a new feature flag (`FORUM_MEDIA_EMBEDS_ENABLED`)
      defaulted off everywhere; still needs a manual operator enable on the
      production `mitrapclub` instance before it's visible there.
- [ ] Media-embed provider list beyond YouTube/Instagram — unscheduled,
      flagged during Cycle 5's Step 2 for a future iteration. Suggested
      platforms, prioritized by fit for the club's actual (rap/hip-hop)
      content: **SoundCloud** (where independent hip-hop gets posted
      first), **TikTok** (short clips, freestyles), **Spotify**
      (track/album/playlist links), **Bandcamp** (indie/underground
      releases) — higher priority than general social platforms
      (**X/Twitter**, **Facebook**, **Vimeo**, **Twitch**) for this club's
      content mix.
- [ ] No "what's next" callout/banner on the board distinct from the
      generic thread list (cypherposium.com's two event CTAs are the
      closest reference).
- [ ] Composer has a club-flavored placeholder prompt, but no guided post
      type (announce an event vs. share a clip vs. start a discussion).
- [ ] About page mentions Code Cypher / CMS/W / Lupe Fiasco's rap-theory
      lineage in two sentences — could expand with more of the Step 1
      research now that there's room for it.
- [ ] Social links only appear on `/about/` — consider surfacing them in
      the header or footer too.
- [ ] The Facebook link still points at a single photo post, not a
      confirmed club page URL — needs the real page URL once known.

## Cycle split

Grouped into five FDP cycles by what moves together and how much solution
uncertainty each has:

1. **Branding fixes** — `displayName` vs `name` in the header, busy-page
   copy. Small, low-uncertainty; skipped Step 1, went straight to Step 2.
   **Done** on `feature/mitrapclub-branding-fixes` (not yet merged to
   `main`). Per-profile favicon/manifest icon moved to Cycle 3 (no icon
   asset available yet); the Facebook link swap is unscheduled until the
   club's real page URL is known.
2. **De-genericize copy** — gate/rewrite the about page's social-graph,
   participation, and portable sections plus the fixed intro sentence;
   expand the Code Cypher/CMS/W paragraph. Low-uncertainty, mostly content.
   **Done** on `feature/mitrapclub-about-copy` (not yet merged to `main`),
   except the Code Cypher/CMS/W paragraph expansion, which stayed out of
   scope (`communityParagraphs` untouched) and remains open below.
3. **Visual identity extension** — hero/banner, display typeface, nav
   relabel/trim, theme-freedom decision. Real design trade-offs; run a
   Step 1 first. Split via that Step 1 into a structural half (done) and a
   creative half (deferred):
   - Structural half — nav trim, theme-freedom decision, favicon/icon
     override mechanism. **Done** on `feature/mitrapclub-structural-identity`
     (not yet merged to `main`). Theme-freedom resolved as a no-op: kept
     full `permittedThemes` breadth, matching `qdb`/`chouse` precedent.
   - Creative half — hero/banner treatment, display typeface. **Unscheduled**;
     deserves its own Step 1 once there's room to actually design it, per
     the structural feature's Step 1 recommendation (Option B).
4. **Events experience** — structured event posts (date/location), a
   "what's next" callout. This is Option B deferred from the original
   feature's Step 1; give it a fresh Step 1. **Done** on
   `feature/mitrapclub-events-experience` (not yet merged to `main`): a
   thread's root post can carry an optional event date/location/link
   (canonical record headers → write-path validation → read model →
   a structured event block on the board card and thread page, for any
   profile, not just `mitrapclub`). A dedicated "what's next" callout
   distinct from the thread list stayed out of scope — still open below.
5. **Media embeds** — YouTube/Instagram embedding in threads. Independent
   of events; has real technical options (oEmbed fetch vs. link preview
   vs. iframe); give it a Step 1. **Done** on
   `feature/mitrapclub-media-embeds` (not yet merged to `main`): Step 1
   picked the link-preview card (no raw third-party HTML); shipped a
   YouTube/Instagram URL detector, a card-aware body renderer (byte-identical
   output when disabled), wired into the one shared body-rendering closure
   used by all 8 post/reply render call sites, behind a new
   `FORUM_MEDIA_EMBEDS_ENABLED` flag defaulted off everywhere. A broader
   provider list (SoundCloud, TikTok, Spotify, Bandcamp, etc.) and
   general-purpose URL canonicalization both stayed out of scope — see the
   open item above and `todo.txt`.
