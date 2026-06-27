# Site Feature Flags Slices V1

This document outlines an atomic implementation plan for making site feature flags visible, auditable, and eventually mutable through the site while preserving the current environment-variable behavior.

## Goal

As a user, I want to see all available feature flags on the site, their current state, whether that state is the default, and later be able to change supported site flags with the change recorded in the site-content git history.

Each slice below should be independently deployable and should not break the existing site if later slices are not yet implemented.

## Current Context

The current public site flags are process-environment backed:

- `FORUM_UNICODE_AUTHORED_TEXT`
  - default: disabled
  - current helper: `SiteConfig::unicodeAuthoredTextEnabled()`
  - affects authored post `subject` and `body` validation plus browser compose policy exposure
- `FORUM_APP_VERSION_NOTIFICATION`
  - default: enabled
  - current helper: `SiteConfig::appVersionNotificationEnabled()`
  - affects layout metadata, version polling script loading, and reload-banner markup

Relevant code paths:

- `src/ForumRewrite/SiteConfig.php` owns the current env parsing.
- `src/ForumRewrite/View/TemplateRenderer.php` reads flags while rendering layout and page data.
- `src/ForumRewrite/Write/LocalWriteService.php` reads flags while validating authored text writes.
- `src/ForumRewrite/Write/LocalWriteService.php` already has the locked canonical write plus git commit path used by posts, tags, identity links, and approvals.
- `templates/pages/tools.php` and `templates/pages/codebase_state.php` already provide a Tools/System State surface where an operator-facing flags page can fit.

There are also private config booleans such as `DEDALUS_AGENT_REPLIES_ENABLED` and `DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED`. Those are secrets-adjacent because they live in `PrivateConfig` beside API credentials. They should not become git-backed public site-content flags in this plan unless a later product decision explicitly opts them in.

## Product Semantics

The site should distinguish three concepts:

- **registered flag**: a known flag with a stable key, label, description, default value, and mutability policy
- **configured site value**: a value stored in the content repository and committed to git
- **effective value**: the value currently used by the running process after precedence is applied

Precedence should be:

1. process/private override, when present
2. git-backed site-content value, when present and valid
3. code default

This preserves existing deployment behavior. Operators can still force a value with the environment, while normal site-level changes can be made through the repository-backed UI later.

The UI should make override state explicit:

- `default` when the effective value comes from the code default
- `site` when the effective value comes from a git-backed site-content setting
- `environment` when a `FORUM_*` process variable overrides the site value
- `private` when a private config source is displayed but not publicly mutable
- `invalid-site-value` when a repository setting exists but is ignored because it is malformed

Environment-overridden flags should render as read-only in the change UI. The user should be able to see the site value, the effective value, and why the site value is not currently effective.

## Proposed Canonical Format

Use one canonical repository file for site feature flags:

```text
records/instance/feature-flags.txt
```

Suggested V1 structure:

```text
Schema: site-feature-flags-v1
Updated-At: 2026-06-27T00:00:00Z

FORUM_APP_VERSION_NOTIFICATION: true
FORUM_UNICODE_AUTHORED_TEXT: false
```

Rules:

- Header block follows existing canonical text-record conventions.
- Body contains one `FLAG_KEY: boolean` line per stored site override.
- Allowed boolean values are `true` and `false` only.
- Unknown keys are preserved by the parser for forward compatibility but ignored by the evaluator until registered.
- Missing registered keys fall back to the code default unless overridden by environment.
- The file may be absent; absence means all flags use environment-or-default behavior.

Open implementation detail: if preserving comments becomes important, switch the body to JSON before implementing Slice 3. The plain text format above is easier to inspect and matches the current canonical record style.

## Slice 1: Central Flag Registry And Effective-State Evaluator

Focus:

- introduce a single internal registry for known site feature flags
- keep all existing runtime behavior unchanged

Checklist:

- add a small feature flag component, for example under `src/ForumRewrite/Support/FeatureFlags/`
- register the two current public flags:
  - `FORUM_UNICODE_AUTHORED_TEXT`
  - `FORUM_APP_VERSION_NOTIFICATION`
- each registry entry should include:
  - stable key
  - display label
  - description
  - default boolean
  - env variable name
  - category such as `site`
  - mutable-in-site-content boolean, initially `false`
- expose an evaluator that reports:
  - default value
  - site value, initially always absent
  - env value, when present
  - effective value
  - source
  - whether effective value equals default
  - whether the flag can be changed through the site, initially `false`
- preserve `SiteConfig::unicodeAuthoredTextEnabled()` and `SiteConfig::appVersionNotificationEnabled()` as compatibility wrappers.
- do not change `LocalWriteService`, `TemplateRenderer`, page output, or write behavior yet.

Expected outcome:

- there is one authoritative list of public feature flags
- current behavior remains exactly env-or-default
- later slices can render and mutate flags without searching for scattered helpers

Components likely touched:

- `src/ForumRewrite/SiteConfig.php`
- new support classes for registry/evaluation
- focused unit tests

Verification:

- unset env vars produce the current defaults
- `FORUM_UNICODE_AUTHORED_TEXT=true` still enables Unicode-authored text
- `FORUM_APP_VERSION_NOTIFICATION=false` still disables the version banner behavior
- existing smoke tests still pass

## Slice 2: Read-Only Flags Page

Focus:

- let users see every registered public feature flag and its effective state
- keep flags read-only

Checklist:

- add a route such as `/tools/feature-flags/`
- add a Tools nav item and overview card for Feature Flags
- add a page template listing all registered public flags
- display:
  - key
  - label
  - description
  - default value
  - effective value
  - source
  - whether effective value is default
  - whether mutable from the site
- make the page work with no `records/instance/feature-flags.txt`
- do not add write forms or APIs yet
- include `FORUM_*` env override state without displaying unrelated environment variables

Expected outcome:

- users can audit the current flags from the site
- no write path, canonical format, or git behavior changes are required

Components likely touched:

- `src/ForumRewrite/Application.php`
- `templates/pages/feature_flags.php`
- `templates/pages/tools.php`
- possibly `public/assets/site.css` for table/status styling
- `tests/LocalAppSmokeTest.php`

Verification:

- `/tools/feature-flags/` renders with both current flags
- default flags are shown as default-sourced when env vars are unset
- env-overridden flags show source `environment`
- no POST/API route exists yet

## Slice 3: Canonical Site Flag Record Parser

Focus:

- define and parse git-backed site flag state without using it for runtime behavior yet

Checklist:

- add `docs/specs/site_feature_flags_record_v1.md`
- add a canonical parser/model for `records/instance/feature-flags.txt`
- add `CanonicalPathResolver::featureFlags()` or similar
- support absent file as "no site overrides"
- validate:
  - `Schema: site-feature-flags-v1`
  - LF line endings and trailing LF through the generic parser
  - boolean values are exactly `true` or `false`
  - duplicate keys are rejected
  - malformed registered keys are rejected or reported as invalid
- decide whether unknown well-formed keys are preserved or rejected; recommended V1 behavior is preserve-but-ignore so forward compatibility is easier
- add parser tests with valid, absent, malformed, duplicate, and unknown-key cases
- keep `SiteConfig` and runtime behavior unchanged in this slice

Expected outcome:

- the repository can now contain a feature flags record safely
- the parser contract is tested before any runtime behavior depends on it

Components likely touched:

- `docs/specs/site_feature_flags_record_v1.md`
- `src/ForumRewrite/Canonical/*`
- `tests/CanonicalRecordParsersTest.php`
- fixture copy only if needed for parser tests

Verification:

- parser tests pass
- existing site behavior does not change when the file is absent
- existing site behavior still does not change if the file is present but the evaluator is not wired yet

## Slice 4: Site-Backed Evaluation

Focus:

- make the effective-state evaluator read the canonical site flag record
- preserve environment-variable precedence

Checklist:

- pass repository root into the feature flag evaluator from `Application`
- avoid hidden globals; do not make `SiteConfig` guess the repository root
- update render paths to use an application-scoped evaluator where repository-backed values matter
- update write paths that depend on flags to receive the evaluator or an explicit flag value
- preserve compatibility wrappers for code paths that do not yet have repository context
- implement precedence:
  - env override wins
  - valid site value wins over default
  - default wins when no site value exists
- invalid site values should not crash normal page rendering; report them on the flags page and fall back to env-or-default
- do not add mutation yet

Expected outcome:

- committing `records/instance/feature-flags.txt` can affect runtime state
- env overrides still behave exactly as emergency/operator overrides
- invalid repository flag state is visible and non-fatal

Components likely touched:

- `src/ForumRewrite/Application.php`
- `src/ForumRewrite/View/TemplateRenderer.php`
- `src/ForumRewrite/Write/LocalWriteService.php`
- `src/ForumRewrite/SiteConfig.php`
- feature flag evaluator classes
- `tests/LocalAppSmokeTest.php`
- `tests/WriteApiSmokeTest.php`

Verification:

- with no env and no record, defaults are unchanged
- with no env and a valid record, effective values come from `site`
- with env and a conflicting valid record, effective values come from `environment`
- Unicode-authored write behavior follows the site-backed value when no env override is present
- version-notification layout behavior follows the site-backed value when no env override is present
- invalid site record does not take the site down

## Slice 5: Git-Backed Flag Write Service

Focus:

- add server-side plumbing to update site-mutable flags and commit the change to the content repository
- do not expose browser UI yet

Checklist:

- mark the intended public site flags as mutable from site content only after Slice 4 is stable
- add `LocalWriteService::setFeatureFlag()` or a small sibling service using the same execution lock and git commit behavior
- validate:
  - flag key is registered
  - flag is site-content mutable
  - requested value is boolean
  - env-overridden flags may still write the site value only if product wants that; recommended V1 behavior is reject with "currently overridden by environment"
- write or update `records/instance/feature-flags.txt`
- preserve unknown keys if the parser supports them
- commit with a deterministic message such as `Set feature flag FORUM_APP_VERSION_NOTIFICATION=true`
- return:
  - flag key
  - requested site value
  - effective value after write
  - source after write
  - commit SHA
  - timings
- add artifact invalidation for all statically materialized pages whose output can depend on flags
  - at minimum `/`, `/threads/`, `/tools/`, `/tools/feature-flags/`, compose pages if statically materialized later, and any layout-wide artifacts affected by app-version notification
  - if targeted invalidation becomes too broad, add an explicit "invalidate common shell artifacts" helper instead of silently doing nothing
- do not rebuild the read model; feature flags do not need read-model tables in V1

Expected outcome:

- tests can update a feature flag through server-side plumbing
- the content repository git log records the change
- no browser form or public API is exposed yet

Components likely touched:

- `src/ForumRewrite/Write/LocalWriteService.php` or new write service
- `src/ForumRewrite/Write/StaticArtifactInvalidator.php`
- feature flag canonical writer helper
- `tests/WriteApiSmokeTest.php`

Verification:

- setting a mutable flag creates or updates `records/instance/feature-flags.txt`
- git commit is created and returned
- effective state changes after write when no env override is present
- env-overridden flag writes are rejected or clearly reported according to the final product decision
- malformed values are rejected before file write
- failed git commit does not report success

## Slice 6: Mutation API And Form Handler

Focus:

- expose the server-side write through normal application routes
- keep the UI minimal and progressively enhanced later

Checklist:

- add a POST route such as `/tools/feature-flags/`
- optionally add JSON API route `/api/set_feature_flag` if browser enhancement needs it
- require an approved identity for mutation
  - recommended V1 policy: approval-seed/root-approved identities only for site settings
  - if identity signing is not available on this page yet, render the form read-only until the browser identity plumbing is present
- map validation errors to a message page or inline feedback
- include commit SHA in success feedback
- redirect back to `/tools/feature-flags/` after successful non-JS form submit
- keep env-overridden flags disabled/read-only in the form

Expected outcome:

- a permitted user can change a mutable flag through the site
- each successful change creates a canonical git commit
- non-JavaScript behavior remains usable

Components likely touched:

- `src/ForumRewrite/Application.php`
- `templates/pages/feature_flags.php`
- optional browser asset under `public/assets/`
- `tests/LocalAppSmokeTest.php`
- `tests/WriteApiSmokeTest.php`

Verification:

- GET page renders controls for mutable non-overridden flags
- POST rejects unknown flag keys
- POST rejects non-boolean values
- POST rejects unauthorized mutation
- successful POST commits and redirects or returns success
- commit SHA is visible in success feedback

## Slice 7: Browser Enhancement And Operator UX

Focus:

- make changing flags efficient while preserving the server as authority

Checklist:

- add small JavaScript enhancement for toggles if needed
- show pending/success/failure state per flag
- after a successful change, update displayed:
  - site value
  - effective value
  - source
  - default/non-default indicator
  - commit SHA
- keep no-JS form behavior as the baseline
- add copy that explains override state without exposing unrelated environment details
- avoid implying that env-overridden flags changed runtime behavior when only the site value changed

Expected outcome:

- flag changes feel immediate and understandable
- users can see whether a change is live or only recorded as site state beneath an override

Components likely touched:

- `templates/pages/feature_flags.php`
- optional `public/assets/feature_flags.js`
- `public/assets/site.css`
- browser-adjacent smoke coverage if existing tests make this practical

Verification:

- enhanced and non-enhanced flows both work
- failure leaves the displayed state consistent with the server
- env-overridden flags cannot be toggled as if they were live

## Slice 8: Documentation And Operations

Focus:

- document the flag model for operators and future implementers

Checklist:

- update `README.md`
- update `docs/examples/env.production.example`
- update `docs/runbooks/production_deploy.md`
- document precedence:
  - env/private override
  - git-backed site value
  - default
- document the canonical record path and how to inspect history:
  - `git log -- records/instance/feature-flags.txt`
  - `git show <sha>:records/instance/feature-flags.txt`
- document rollback:
  - use UI to set the prior value, or
  - revert the commit in the content repository
- document static artifact implications:
  - rebuild artifacts after changing flags if serving prebuilt static HTML
  - or rely on targeted invalidation when the web write path handles the change

Expected outcome:

- operators understand why an env override may make a UI-set site value non-effective
- repository history is the audit trail for site-level flag changes

Components likely touched:

- `README.md`
- `docs/examples/env.production.example`
- `docs/runbooks/production_deploy.md`

Verification:

- docs describe all current public site flags
- docs do not instruct operators to put private/secrets-adjacent flags in public site content

## Later Work

Not required for V1:

- read-model indexing of feature flag changes
- activity-feed entries for flag changes
- per-flag role-based permissions
- private config mutation through the public site
- secret-bearing config display or mutation
- feature flag change signing beyond the existing approved-identity gate
- migration of all feature flags away from environment variables

## Recommended First Implementation Path

Implement in this order:

1. Slice 1
2. Slice 2
3. Slice 3
4. Slice 4

Stop there if the immediate need is visibility and auditability. Then implement Slices 5 and 6 when the product decision for who may mutate site settings is final.

## Implementation Log

- Slice 1: Added a central public feature flag registry/evaluator for the two existing `FORUM_*` flags, kept `SiteConfig` compatibility helpers, and covered default plus environment override behavior.
- Slice 2: Added a read-only `/tools/feature-flags/` page, linked it from Tools navigation, rendered registered flag state with source/default/mutability details, and included it in static artifact generation.
- Slice 3: Added the `records/instance/feature-flags.txt` canonical record spec, parser/model, path resolver/repository loader, and parser coverage without wiring the record into runtime evaluation yet.
- Slice 4: Wired application-scoped feature flag evaluation to read `records/instance/feature-flags.txt`, preserve environment override precedence, report invalid site records without taking the site down, and pass repository-aware flags into rendering, authored writes, and agent reply normalization.
- Slice 5: Added git-backed `LocalWriteService::setFeatureFlag()` plumbing, made current public site flags site-mutable, preserved unknown canonical keys, rejected env-overridden writes, and invalidated common static artifacts after successful commits.
- Slice 6: Exposed feature flag mutation through `/api/set_feature_flag` and the `/tools/feature-flags/` form, requiring a root-approved identity hint, returning commit metadata, and keeping unauthorized or invalid changes server-rejected.
- Slice 7: Added progressive browser enhancement for feature flag forms with per-row pending/error/commit feedback while preserving the non-JavaScript form submit path.
- Slice 8: Documented site feature flag precedence, git-backed audit history, production env override semantics, rollback options, and static artifact implications.
- Follow-up: Accepted the feature flags UI's displayed `enabled`/`disabled` values as write inputs while continuing to store canonical `true`/`false` values.
- Follow-up: Added private read-only feature flag visibility for `DEDALUS_AGENT_REPLIES_ENABLED` and `DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED`, including private-config source reporting.
