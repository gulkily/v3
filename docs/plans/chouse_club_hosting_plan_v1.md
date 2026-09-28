# Hosting chouse.club on this framework — plan v1

**Update (2026-08-10):** confirmed direction is to run chouse.club and
zenmemes.com as two separate communities (separate content, separate
database, separate moderation posture) from one shared codebase — see
"Multi-site architecture" below. This replaces the single-site framing
of the rest of this doc's Slice 1/5; read those with that in mind.

## What chouse.club is today

Fetched 2026-08-10. "The C(lub) House" — a Red Bull–associated community
board for builders/entrepreneurs. Single page:

- header with a vertical "CHOUSE" wordmark as the main graphic element
- a form: "What are you building?" (project description, what help is
  needed, optional contact info)
- a reverse-chronological "Recent submissions" feed of all public posts
- contact info is collected but kept private, unlike the rest of the post
- minimal, text-forward layout, no elaborate graphics

Content quality is a real problem: genuine submissions ("building a next
gen family office network," "sneakernet of Alexandria," etc.) are mixed in
with heavy crypto-scam, fake-lottery, and SEO spam — there's no visible
moderation or identity gate today.

## Why this framework fits

The site is structurally a single-board forum: one composer at the top,
one flat feed below. That's almost exactly `templates/pages/board.php` as
it exists right now — inline "Start a thread…" composer, thread list,
nothing else required. The bigger win isn't layout, it's everything
chouse.club is currently missing:

- **OpenPGP identity + approval/vouching** (`docs/plans/php_user_approval_vouching_slices_v1.md`)
  gives a real lever against the spam flood — anonymous submission stays
  possible, but approved/vouched identities can be surfaced above it.
- **Git-backed canonical records** make every submission an auditable,
  diffable commit instead of a row in an opaque DB — useful for a
  community board where people want to point at "here's what I posted."
- **Dedalus post analysis** (`src/ForumRewrite/Analysis/DedalusPostAnalyzer.php`)
  can be pointed at submissions for automatic spam/quality flagging
  instead of building that from scratch.
- **Tags** give "what are you building" a lightweight taxonomy
  (`fintech`, `hardware`, `ai`, …) the current site has no way to express.
- Replies become possible for free — people can respond to a submission
  offering the help that's being asked for, which the current flat
  submit-only feed can't do at all.

## Multi-site architecture: zenmemes.com + chouse.club

Decision: separate content per site (separate git repository, separate
read-model database, separate moderation), one shared application
codebase. Sharing the database/repo was considered and rejected — it
would merge chouse's open, spam-exposed submission stream with
zenmemes' content and moderation, defeating the reason chouse needs its
own approval/vouch gating in the first place.

**The good news: this app is already multi-tenant-capable for content.**
`public/index.php:11-13` resolves `FORUM_REPOSITORY_ROOT`,
`FORUM_DATABASE_PATH`, and `FORUM_PUBLIC_ARTIFACT_ROOT` from environment
variables on every request, and `docs/examples/apache_vhost.conf` already
sets these per-vhost via `SetEnv`. Two vhosts pointing at two deployed
copies of the same code, each with its own three env vars, get fully
separate content and databases today with **zero application changes**.

**One important correction to "same repo":** it should mean same
*source*, not one literal `public/` directory answering both hostnames.
Static HTML artifacts are written as siblings inside `public/`, keyed by
thread/post ID (`public/threads/root-001.html`); if zenmemes.com and
chouse.club shared one `public/` tree, their artifacts would collide on
matching IDs. So: one repo, one release/build process, but **two
deployed instances** (two `public/` directories, two vhosts) — same
pattern as deploying the same Docker image twice with different config,
not literally the same running process serving two `Host` headers.

**What isn't parameterized yet and needs to be:** branding. Today
`SiteConfig::SITE_NAME` is a hardcoded PHP constant, and there's no
per-site default theme or composer copy. Extend the existing env-var
precedence pattern (env override → site content flag → code default,
already documented in `production_deploy.md`) one level further with a
small site-profile registry, following the same shape as
`ThemeRegistry.php`:

```php
// src/ForumRewrite/SiteProfileRegistry.php (new)
final class SiteProfileRegistry
{
    public static function all(): array
    {
        return [
            'zenmemes' => ['name' => 'zenmemes', 'defaultTheme' => 'auto', /* existing copy */],
            'chouse'   => ['name' => 'chouse', 'defaultTheme' => 'chouse', 'composer' => [...]],
        ];
    }

    public static function active(): array
    {
        $id = getenv('FORUM_SITE_ID') ?: 'zenmemes';
        return self::all()[$id] ?? self::all()['zenmemes'];
    }
}
```

`SiteConfig::SITE_NAME` becomes `SiteProfileRegistry::active()['name']`;
`FORUM_SITE_ID` gets set per-vhost next to the three existing `SetEnv`
lines. This is a small, self-contained change and reuses an idiom
(registry-of-profiles) already established by `ThemeRegistry`, rather
than inventing a new configuration mechanism.

## Key design decisions to make before building

These are real mismatches between chouse.club's current model and this
framework's, not implementation details — flag them to the site owner
before writing code.

1. **Private contact field.** Canonical posts are public git-committed
   plaintext; there's no concept of "submitted but not shown." Options:
   (a) store contact info outside the canonical repo entirely — e.g. a
   private SQLite table or `state/private/` file, written by the same
   compose request but never rendered or committed to `records/`; or
   (b) make contact info opt-in-public and drop the privacy guarantee.
   (a) preserves current behavior and is the recommended path — it's a
   small, isolated addition (one extra write next to the canonical post
   write), not a framework change.
2. **Structured submission fields vs. free-text body.** The current form
   has three fields (what you're building / what help you need / contact).
   MVP: fold the first two into the post body as light structure (e.g. two
   labeled paragraphs), keep parity with `thread_compose_form.php` as-is.
   Later: add real `help_needed` as a second body field if the product
   wants it queryable/filterable — bigger effort, not needed for parity.
3. **Anti-spam posture.** Recommend keeping anonymous submission (matches
   current openness) but enabling the approval/vouch flow and Dedalus
   analysis from day one rather than bolting it on after the spam shows up
   again, since that's the whole reason to migrate off the current setup.
4. **HTTPS.** The production runbook's "don't force HTTPS, don't set HSTS"
   default is written for the general forum-identity case (public-HTTP
   OpenPGP fallback). A public site collecting contact info should run
   HTTPS (Let's Encrypt) as the primary path; keep the runbook's guidance
   about *not force-redirecting or setting HSTS* so a cert hiccup can't
   lock out the domain.
5. ~~Migrating existing content.~~ Resolved: no migration — chouse's
   instance launches with an empty repository/database. See "Resolved"
   below.

## Plan

### Slice 1 — Site identity (site-profile registry)
- Add `SiteProfileRegistry` (see architecture section above) with
  `zenmemes` and `chouse` profiles; resolve the active one from
  `FORUM_SITE_ID` at request start, default `zenmemes` for back-compat if
  the env var is absent (existing single-site deploys keep working
  unchanged).
- Replace `SiteConfig::SITE_NAME` and the handful of places that hardcode
  it (`FrontController.php:265,278,281`, `templates/pages/about.php`)
  with reads from the active profile. Internal-only storage keys
  (`zenmemes-theme` localStorage key, etc.) can stay as-is — renaming
  them is cosmetic risk for zero user-facing value, and they're per-site
  anyway since each deployed instance has its own origin/localStorage.
- Write a chouse-specific `about.php` variant (or a per-profile about
  body) alongside the existing zenmemes one.
- Single-board mode for chouse: hide/collapse the tags and multi-board
  chrome on `board.php` when the active profile is `chouse`, since it's
  one flat feed, not a tagged multi-board forum (tags can still exist
  under the hood for later taxonomy use). zenmemes' board is unaffected.

### Slice 2 — Composer parity
- Repoint the home route at the board composer, relabel it: prompt
  "What are you building?", body placeholder split into "what you're
  building" / "what help you need," submit label "Submit."
- Add the private-contact-field write path (decision #1 above): new
  optional form field, written to a non-canonical private store next to
  (not inside) the canonical post commit.
- Feed heading → "Recent submissions."

### Slice 3 — New "chouse" theme
Follow `docs/runbooks/theme_development_guide.md` exactly:
- `ThemeRegistry.php`: add `['name' => 'chouse', 'label' => 'Chouse', 'mode' => 'light']`.
- `public/assets/site.css`: variable block, swatch, menu-row garnish,
  scoped section — same three-block pattern as every existing theme.
- Design direction: minimal, text-forward, one strong accent color (not a
  literal Red Bull palette/logo copy — build an original mark inspired by
  the brief, since reusing another brand's trademark colors/logo verbatim
  is a legal risk for the site owner, not just a style choice). The
  vertical "CHOUSE" wordmark is a good CSS-only signature element —
  `writing-mode: vertical-rl` or a rotated fixed-position label, no image
  asset, matching the repo's no-asset theme convention.
- Update `tests/LocalAppSmokeTest.php` allow-list per the guide.
- Work in slices with a screenshot-driven verification pass (headed
  Chrome, ~380px viewport, theme popover leak check) as the guide
  prescribes — this is its own sub-plan doc when it starts.

### Slice 4 — Moderation wiring
- Enable/seed the approval-vouching flow so a first trusted identity
  exists at launch.
- Wire Dedalus post analysis for submission quality/spam signal (reuses
  existing `reply-agent` style infra — no new provider integration).
- Decide surfacing: hide flagged-spam submissions from the default feed
  view rather than deleting (keeps the git history honest).

### Slice 5 — Deploy (two instances, one codebase)
Follow `docs/runbooks/production_deploy.md` / `apache_vhost.conf`, doubled:
- One source checkout/release process, deployed to two independent
  instance directories:
  - `/srv/zenmemes/app` + `/srv/zenmemes/repository` + `/srv/zenmemes/state`
  - `/srv/chouse/app` + `/srv/chouse/repository` + `/srv/chouse/state`
  Each `app/` gets its own `public/` so static artifacts never collide
  (see architecture section — this is the one place "same repo" does
  *not* mean "same directory").
- Two vhosts: `ServerName zenmemes.com` / `ServerName chouse.club`, each
  setting its own `FORUM_REPOSITORY_ROOT`, `FORUM_DATABASE_PATH`,
  `FORUM_PUBLIC_ARTIFACT_ROOT`, plus the new `FORUM_SITE_ID`
  (`zenmemes` / `chouse`) from slice 1.
- Point chouse.club's DNS at the host; issue a Let's Encrypt cert (see
  decision #4 below) for chouse.club specifically — leave zenmemes.com's
  existing TLS posture alone unless asked to change it. Do not add forced
  HTTP→HTTPS redirect or HSTS on either vhost.
- Initial read-model rebuild + static artifact build for chouse's
  instance only (zenmemes' is presumably already running); cron for
  `run_agent_reply_requests.php` per instance if Dedalus fulfillment is
  async, pointed at that instance's own paths.
- Shared code changes (bug fixes, new themes, framework features) get
  released to both instances together; site-specific config
  (`FORUM_SITE_ID` and friends) stays per-vhost so one release doesn't
  need site-specific branches.
- Walk the runbook's pre-launch checklist and manual-verification list
  for the chouse instance before pointing DNS; re-run it against the
  zenmemes instance too once slice 1's `SiteProfileRegistry` change lands
  there, since that's a shared-code change touching a currently-running
  site.

## Open questions for the site owner
- Keep contact info private (recommended, needs slice 2's private-store
  work) or accept it becoming public going forward?
- Is Dedalus (or another LLM provider) already available/budgeted for
  spam analysis, or should slice 4 ship without it initially and rely on
  approval-gating alone?

## Resolved
- **Content migration: not needed.** chouse's instance launches with an
  empty repository/database — no import script, no hand-picking genuine
  submissions out of the current spam-heavy feed. Removes what was
  Slice 6 entirely.
