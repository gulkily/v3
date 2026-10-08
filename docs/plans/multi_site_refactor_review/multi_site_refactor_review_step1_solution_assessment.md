# Multi-Site Refactor Review — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./multi_site_refactor_review_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_review_step2_feature_description.md) · [Step 3](./multi_site_refactor_review_step3_development_plan.md) · [Step 4](./multi_site_refactor_review_step4_implementation_summary.md)

## Original Query

We have now developed 3 different site designs and configs with this framework: zenmemes, qdb, and chouse.

I would like to conduct a code review for refactor candidates.

Also, look for things that are hard-coded that shouldn't be, especially for zenmemes.

The deliverable should be a checklist document for things that would be good to refactor.

Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md.

## Problem

The three profiles share a registry but profile-specific behavior, browser state, and editorial content are split across route, view, asset, and script code.

## Options

### Option A — Extend the profile to a declarative capability contract

Expand each `SiteProfileRegistry` entry into the authoritative description of a site's identity, enabled experiences, and browser-facing namespace. Shared routing and rendering would ask the profile which behavior to use instead of recognizing individual site names.

- Pros: one source for site copy, navigation, browser namespaces, offline metadata, and enabled route/card capabilities; makes a fourth site predictable.
- Cons: requires carefully separating stable shared defaults from genuine per-site differences.

### Option B — Isolate each specialized site in a dedicated profile module

Keep the existing small registry, but give each nonstandard site a module that owns its specialized routes, presentation decisions, and data conventions. Generic forum code would delegate only at defined seams, leaving the default experience largely unchanged.

- Pros: quickly removes QDB conditionals from shared controllers; preserves QDB's intentionally unusual routes and quote model.
- Cons: duplicates orchestration unless modules share a well-defined contract; Zenmemes hard-coding can remain scattered.

## Recommendation

Adopt Option A incrementally, with a small QDB module only for its unique quote/routing rules. This is a vertical slice: first centralize profile metadata and consume it in one browser/offline path, then migrate routing, views, and content one capability at a time.

## Refactor Checklist

- [ ] **P0 — Give every profile an explicit browser namespace and offline/PWA metadata.** Replace Zenmemes-only local-storage keys in `templates/layout.php`, `public/assets/theme_toggle.js`, and `public/assets/thread_density_toggle.js`; cache-name matching in `public/service_worker.js`, `public/assets/pwa_registration.js`, and `public/assets/offline_health.js`; the bootstrap-query validation in `src/ForumRewrite/Host/FrontController.php`; and the fixed `public/manifest.webmanifest` identity. Pass the values through rendered configuration so Zenmemes remains the default, without becoming the only working profile.
- [ ] **P0 — Centralize profile-specific static-artifact paths.** Replace the repeated `zenmemes ? '' : '_' . siteId` convention in `public/index.php`, `scripts/publish_offline_snapshot.php`, `scripts/task_queue.php`, and `scripts/diagnose_offline_reading.php` with a profile-owned state/cache suffix (or resolver).
- [ ] **P1 — Replace `siteName() === 'qdb'` branches with named capabilities/strategies.** Move QDB navigation (`TemplateRenderer`), classic routes (`Application`), board card selection/pagination/reaction state (`BoardPageController` and `templates/pages/board.php`), and quote-ID generation (`LocalWriteService`) behind capabilities such as `classic_quote_board`, `quote_numbering`, and `quote_card`. Keep QDB's specialized behavior out of the generic board contract.
- [ ] **P1 — Make site chrome data-driven.** Extend `SiteProfileRegistry` beyond name/default theme/composer prompt to own nav items, home-page mode, available themes, card renderer, and user-facing labels. Support profile overrides only for an explicit catalog of baseline template/asset slots—an applicable Pollyanna pattern—and keep fallback ownership in the shared renderer; do not introduce unbounded theme stacking or let the theme menu offer every site's branded theme unless intentional.
- [ ] **P1 — Move editorial content out of shared templates.** `templates/pages/about.php` contains Zenmemes/Boston-specific community copy, has a Zenmemes-specific DOM key also consumed by `public/assets/about.css`, and adds a Chouse-only branch. Define profile content/sections (or profile-owned templates) so QDB does not inherit forum copy that does not describe it. Apply the same review to the Zenmemes-specific busy message in `FrontController` and platform-document branding.
- [ ] **P2 — Separate theme data from the global theme registry.** Keep reusable palette themes global, but associate branded assets/styles (`theme-chouse.css`, `theme-qdb.css`) with the corresponding profile. This reduces cross-site coupling and makes profile removal/addition local.
- [ ] **P2 — Encapsulate QDB's quote-number format.** The `-qdb-<N>` parsing/SQL pattern currently spans `LocalWriteService`, `ThreadRepository`, and `quote_card.php`. Introduce a quote-number value/parser/repository API so storage format is not presentation and routing logic.
- [ ] **P2 — Eliminate presentation-specific constants from generic controllers.** QDB's page size, random-count, 1337 comparator, footer/copyright, and welcome/search templates should be profile configuration or QDB-module concerns, not generic board-controller policy.
- [ ] **P2 — Parameterize operational defaults deliberately.** Retain `zenmemes.com` as the documented production default only where desired, but make the OpenPGP smoke/canary scripts' origin explicit profile/deployment configuration rather than implicit Zenmemes behavior.
- [ ] **P3 — Strengthen the profile contract tests.** Replace hard-coded theme arrays and Zenmemes cache/key assertions in `LocalAppSmokeTest` with registry-derived expectations. Add a matrix covering each profile's route set, rendered chrome, browser namespace, manifest/cache isolation, and rejection of another profile's specialized routes.
