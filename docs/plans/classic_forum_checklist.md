# Classic Forum Checklist

Working checklist for a v3 configuration that matches the traditional forum permission model: anyone reads, and posting, replying and reacting need a registered account. It is for demos ("v3 can do this too") and as the first real preset. It is not a Railspike artifact and approves no implementation. Each item below is its own Railspike cycle, following AGENTS.md. Record progress, checks and limitations here.

Requested by the maintainer on 2026-11-17. Related feedback: PF-20261111-F42 (feature-flag presets), PF-20261108-F02 (calendar as its own permission). The per-feature permission system is a separate, later request; nothing here waits for it.

## Gap found

Read at `5508257`.

- v3 has two access modes. Public: anyone reads, and guests write through guest keypairs and unsigned posts. `FORUM_APPROVED_MEMBERS_ONLY`: reads are gated too, with a lobby for unapproved users. A classic forum sits between them, and no flag combination gives it.
- `FORUM_VERIFIED_ATTRIBUTION` marks unsigned posts unverified; it does not stop them.
- Configuration is split. `src/ForumRewrite/SiteProfileRegistry.php` holds presentation (themes, nav, editorial copy, presentation slots). Feature flags hold behavior and are set per instance through the site record, the private config file or environment variables (`src/ForumRewrite/Support/FeatureFlags/`). Nothing bundles the two into a named setup.

## Reuse inventory

Found on a first read. Each cycle re-checks its own part before planning.

- `src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php` — flag definitions and the ACCESS group (`APPROVED_MEMBERS_ONLY`, `AUTOMATIC_GUEST_KEYPAIR_ENABLED`, `VERIFIED_ATTRIBUTION`, `ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL`).
- `FeatureFlagEvaluator.php` and `FeatureFlagState.php` — layering of site record, private config and environment, and lock reasons. A preset layer belongs here.
- `src/ForumRewrite/Support/PrivateConfig.php` and `scripts/terminal_llm_editor.php` — the private-config editor already has presets for LLM providers; prior art for applying a named bundle.
- `src/ForumRewrite/Http/WritePostAndIdentityApiController.php` and `RouteServices::unsignedAttributionLines()` — where unsigned writes are accepted.
- The members-only lobby (`APPROVED_MEMBERS_ONLY` in `Application.php`, `FrontController.php`, `TemplateRenderer.php`) — prior art for gating by approval.
- Staging (`accept_and_stage` principle) — prefer staging a turned-away write over rejecting it.
- Presentation slots (`navigation` => `forum`, `boardCard` => `thread`) as used by the `chouse` profile — the classic look.

## Cycles

- [ ] **1. `member_write_gate`** — one flag, "Posting requires an approved account". Guests read everything; compose boxes become "Register to post"; guest and unsigned writes (posts, replies, likes, flags) are turned away or staged. Off by default.
  - Status: not started.
  - Open: stage or reject turned-away writes; whether guests may still like or flag (suggested: no); how the no-JavaScript path behaves (no-JS browsers can't be blocked from reading).
- [ ] **2. `registration_auto_approve`** — optional flag so a new username is approved on registration, with no moderator in the loop, like traditional instant signup. Needed for a demo that doesn't stall.
  - Status: not started.
  - Open: what "registration" means in v3 (key + username, approval); rate limits or other abuse guards; whether this can be the built-in form of the #specialoffer bot.
- [ ] **3. `config_presets`** (PF-20261111-F42) — a preset is a plain-text, git-tracked file (e.g. `resources/presets/classic_forum.ini`) listing flag values and profile settings. Applied at setup (`./v3 init --preset classic-forum` or similar); Tools → Feature flags shows where an instance differs from its preset. Presets are a starting point, adjusted afterwards.
  - Status: not started.
  - First presets: **open garage** (ZenMemes today), **private club** (members only, public backups off; the fan site needs this), **classic forum** (cycles 1–2). Later: "small family board", "high-auditability board".
  - Open: file format; whether a preset can set profile/presentation values or only flags; apply at install only, or also to a running instance.
- [ ] **4. `classic_forum_demo`** — a demo instance (localhost first, then a subdomain) on the classic-forum preset with a traditional theme (Chicago or Word97, `forum` nav). Demo script: guest reads, taps "Register to post", signs up, posts, in about 15 seconds on an iPhone SE.
  - Status: not started.

## Decisions

Record maintainer decisions here with dates.

- 2026-11-17: proposed order is gate (1), auto-approve (2), presets (3), demo (4). Presets-first is the alternative if the fan site needs one sooner. Not yet confirmed.
