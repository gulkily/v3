# PHP Forum Rewrite Specification V1

This document defines a PHP-first rewrite of the forum application. It is intended to be concrete enough to guide a ground-up implementation without relying on the current Python web layer.

## 1. Goals

- Serve the public site primarily from PHP.
- Preserve the forum's core product shape: plain-text threads, replies, profile reads, project info, and activity.
- Keep canonical state in repository-backed ASCII text records.
- Shift all hot read paths onto PHP-native indexed reads.
- Make cache behavior, route ownership, and runtime boundaries explicit from the start.
- Minimize request-time work on public reads.

## 2. Non-Goals

- Do not preserve Python as a production dependency.
- Do not redesign the forum into a SPA.
- Do not add rich-text, voting, reactions, or database-owned canonical content.
- Do not require background workers or external queue infrastructure for V1.

## 3. Product Scope

### 3.1 Required Read Surfaces

- Board index: `/`
- Thread page: `/threads/<thread-id>`
- Post permalink: `/posts/<post-id>`
- Project info: `/instance/`
- Activity: `/activity/`
- Public profile: `/profiles/<profile-slug>`
- Self profile and account-key bootstrap pages
- Plain-text read APIs and RSS where already part of the product contract

### 3.2 Required Write Surfaces

- Create thread
- Create reply
- Username capture during browser keypair generation

### 3.3 Required Static/Asset Surfaces

- Shared CSS
- Browser-signing assets
- Shared nav/profile enhancement assets
- Favicon and static support assets

## 4. Core Architecture

### 4.1 High-Level Model

The system has three layers:

1. Canonical repository layer
   - ASCII text records in the repo are the source of truth.
   - Git commits are the canonical write history.

2. Derived read-model layer
   - SQLite indexes and snapshots are rebuilt from canonical records.
   - Public reads use these indexes directly.

3. PHP application layer
   - PHP owns routing, rendering, caching, validation, and write orchestration.
   - PHP never reparses the full repository on hot reads.

### 4.2 Runtime Boundary

- Production web requests are handled by PHP only.
- No Python CGI bridge, Python fallback renderer, or Python request-time snapshot generation.
- Any non-PHP tooling that remains during migration is treated as offline development tooling only and is not part of V1 runtime.

## 5. Canonical Data Rules

### 5.1 Source of Truth

- Canonical forum content lives in repository text records under `records/`.
- All canonical records are ASCII text with LF line endings and trailing LF.
- Detached signatures remain adjacent to signed records where applicable.
- Git is authoritative for accepted writes.

### 5.2 Canonical Record Families

V1 must support the existing conceptual record families:

- Posts
- Instance/public info
- Any existing canonical identity/bootstrap records needed by current product behavior

### 5.3 Repository Ownership

- PHP owns canonical path resolution, record validation, storage, and commit orchestration.
- Canonical file format behavior must remain stable and documented in spec files under `docs/specs/`.

## 6. Derived Read Model

### 6.1 Primary Store

- Use SQLite as the primary derived read model.
- The rewrite starts with SQLite as a first-class dependency, not an afterthought.

### 6.2 Read-Model Responsibilities

The read model must provide:

- Indexed posts
- Indexed threads
- Indexed root-thread metadata
- Profile summaries
- Activity feed items
- Snapshot-ready board/thread/profile payloads for PHP rendering

### 6.3 Required Properties

- Reads are deterministic for a given repo HEAD.
- Index rebuilds are idempotent.
- Incremental refresh after canonical writes is supported.
- A full rebuild path always exists.

### 6.4 Suggested Database Split

Use one or two SQLite databases:

- `state/cache/post_index.sqlite3`
  - canonical-derived posts, threads, profiles, and activity state
- `state/cache/php_views.sqlite3`
  - route/snapshot cache for pre-rendered or partially materialized PHP view payloads

## 7. Route Ownership

### 7.1 PHP-Owned Routes

All public HTML routes are PHP-owned:

- `/`
- `/threads/...`
- `/posts/...`
- `/instance/`
- `/activity/`
- `/profiles/...`
- `/compose/...`
- `/account/...`
- `/assets/...`
- `/favicon.ico`
- `/llms.txt`

### 7.2 Compatibility Rules

- Existing canonical URLs should be preserved wherever feasible.
- Redirect behavior must be explicit and tested.
- Route semantics should not depend on hidden server-side fallback layers.

## 8. Rendering Model

### 8.1 Rendering Approach

- Server-rendered HTML using PHP templates.
- Shared shell, header, nav, footer, and asset-loading contracts.
- Small progressive-enhancement JavaScript only where necessary.

### 8.2 Shared Layout Requirements

- One canonical page shell
- One canonical primary nav contract
- Reusable panels, grids, cards, and metadata rows
- Stable route-level `active_section` or equivalent nav-state contract

### 8.3 No Dual Rendering Stacks

- Do not maintain parallel Python and PHP HTML renderers after the rewrite.
- Avoid duplicated template ownership across route families.

## 9. Read Path Requirements

### 9.1 Board Index

Board reads must:

- Load root threads from SQLite
- Apply title/profile-derived overlays from indexed state
- Avoid reparsing canonical post files on request
- Be cache-friendly for anonymous users

### 9.2 Thread and Post Reads

Thread and post reads must:

- Materialize thread/post content from indexed rows
- Resolve title and author/profile display from indexed state
- Avoid request-time full-repo scans

### 9.3 Project Info and Activity

- `/instance/` reads from indexed/public metadata plus lightweight repo facts
- `/activity/` reads from indexed activity tables, not raw git history reconstruction per request
- Activity filters, pagination, and RSS are indexed views, not recomputed from scratch

### 9.4 Profiles

- Public profile pages read from indexed profile summaries and related indexed post metadata
- Self-profile/account-key pages may use dynamic reads initially if needed, but hot public profile reads should be fully indexed

### 9.5 Username Bootstrap

- V1 does not include a separate profile-update surface for changing usernames after identity creation.
- When the browser generates a new keypair, the UI should call standard `prompt()` to request a username.
- If `prompt()` is unavailable, dismissed, or returns an unusable value, the client must fall back to `guest`.
- The chosen value becomes the initial visible username tied to that keypair/bootstrap flow.

## 10. Write Path Requirements

### 10.1 Write Contract

Each write must:

1. Validate request payload
2. Validate signature or policy requirements
3. Materialize canonical record text
4. Store canonical files
5. Commit to git
6. Refresh affected read-model slices
7. Return deterministic success or failure output

### 10.2 Consistency Model

- Successful writes must be visible on immediate readback for core user-facing surfaces.
- Prefer synchronous incremental read-model refresh over eventual consistency for V1.

### 10.3 Failure Handling

- If canonical write or commit fails, derived state must not be advanced.
- If derived refresh fails after a successful commit, the system must mark the read model stale and trigger deterministic recovery on the next read or startup.

## 11. Activity System

### 11.1 Feed Composition

Activity must support:

- `all`
- `content`
- `code`

### 11.2 Backing Model

- Activity entries are indexed artifacts with enough materialized metadata for direct rendering.
- Activity rendering must not shell out to git repeatedly on every request for common views.

### 11.3 Outputs

- HTML feed
- RSS feed
- Pagination
- Stable filter query contracts

## 12. Project Info Area

### 12.1 Product Shape

- `Project info` is the umbrella area.
- `/instance/` is the canonical top-level project area route.
- `/activity/` remains a compatible subsection route unless explicitly retired in a later spec.

### 12.2 Required Content

- Public instance facts
- Project overview / operating assumptions
- Direct links into activity views

## 13. Identity and Account Model

### 13.1 Baseline

- Preserve the current key-first identity model.
- Browser-held OpenPGP keys remain supported.
- Public profile and account-key bootstrap semantics remain repository-backed.

### 13.2 PHP Ownership

- PHP owns cookie handling, identity-hint handling, account-flow routing, and read/write orchestration.
- Browser enhancement assets remain separate from canonical write validation.

## 14. Caching Strategy

### 14.1 Cache Levels

Use three explicit cache layers:

1. Asset cache
   - long-lived cache headers for immutable or versioned assets

2. Route microcache
   - short TTL cache for safe public HTML routes

3. Indexed snapshot cache
   - SQLite-backed route payloads derived from the read model for PHP fallback rendering

### 14.2 Cache Eligibility

Allowlist safe public GET routes only.

Examples:

- `/`
- `/instance/`
- `/activity/` default route only if queryless and cache-safe
- public thread pages
- public profile pages where personalization does not alter payload

### 14.3 Cache Invalidation

- Canonical writes invalidate affected route families deterministically.
- Prefer targeted invalidation over blanket cache clears.

## 15. Startup and Recovery

### 15.1 Startup Rules

- On startup, PHP must verify read-model readiness.
- If the read model is missing or stale, the app must either:
  - rebuild synchronously before serving, or
  - serve a clear maintenance/rebuild response and perform deterministic recovery

### 15.2 Recovery Rules

- Read-model mismatch detection must include:
  - record count mismatch
  - schema mismatch
  - repo HEAD mismatch
- Recovery behavior must be deterministic and observable.

## 16. Configuration

### 16.1 Environment

Use environment variables or a PHP config file for:

- repo root
- cache directories
- static HTML directory if used
- site title
- feature flags
- GitHub link configuration
- security secrets

### 16.2 Principles

- No hidden behavior based on implicit machine-local state.
- Every production dependency should be inspectable from config and operator docs.

## 17. Security Requirements

### 17.1 Input Handling

- All request input must be validated server-side.
- HTML output must be escaped by default.
- Query parameters for filters/pagination must be normalized and bounded.

### 17.2 Write Safety

- Signature verification and policy checks happen before canonical storage.
- Git commands must use non-interactive invocation only.
- No user-controlled shell interpolation.

### 17.3 File Safety

- Canonical paths must be resolved against explicit allowlists.
- No request may write outside configured repository/state directories.

## 18. Git Integration

### 18.1 Requirements

- PHP must support the canonical write flow directly.
- Use non-interactive git invocations for status, add, commit, and metadata reads.
- Git failures must surface stable operator-readable errors.

### 18.2 Scope

- Reads should not depend on live git subprocesses on hot paths except for lightweight repo-fact metadata where unavoidable.
- Activity and history views should prefer indexed git-derived data over on-demand git parsing.

## 19. APIs

### 19.1 Public HTML Routes

- Primary user-facing interaction remains HTML-first.

### 19.2 Plain-Text APIs

- Preserve the current plain-text API philosophy where still part of the product.
- PHP owns both request parsing and response rendering.

### 19.3 API Design Rules

- Stable text envelopes
- Deterministic success bodies
- Deterministic machine-readable error codes
- No hidden dependency on Python subprocess APIs

## 20. Template and Asset Organization

### 20.1 Templates

- Keep templates in a dedicated PHP-owned templates directory.
- Shared shell and route templates should be clearly separated from assets.

### 20.2 Assets

- Asset manifest remains explicit.
- Asset routes are PHP-served.
- Shared enhancement assets should be generic and container-driven, not page-fragment-specific where avoidable.

## 21. Testing Requirements

### 21.1 Test Layers

The rewrite must include:

- unit tests for parsing, indexing, policy, and helper logic
- request tests for PHP-owned HTML routes
- cache tests for PHP microcache/static behavior
- write-path tests for canonical storage and git commit flow
- integrated tests for route readback after writes

### 21.2 Mandatory Regression Areas

- nav active-state correctness
- board/thread/profile read correctness
- activity filters, pagination, and RSS
- project-info framing
- cache eligibility and cache headers
- identity/profile-nav enhancement coexistence
- immediate read-after-write for key write flows

## 22. Performance Requirements

### 22.1 Public Read Goals

For hot public routes:

- no full repository parse on request
- no request-time full git log reconstruction
- bounded query count against SQLite
- bounded template-rendering cost

### 22.2 Operational Goals

- cold start can rebuild indexes deterministically
- warm reads should be materially faster than the current Python-backed stack
- hotspots should be measurable via named timing steps

## 23. Observability

### 23.1 Required Signals

- request timing
- cache hit/miss source
- read-model rebuild/recovery events
- write-path step timing

### 23.2 Operator Visibility

- operator-facing pages or text outputs should show read-model state and cache state

## 24. Migration Strategy

### 24.1 Phases

Recommended rewrite order:

1. Canonical record/parser library in PHP
2. SQLite read model in PHP
3. Hot public reads: board, thread, post, project info, activity
4. Public profiles
5. Write paths
6. Remove Python production dependency

### 24.2 Cutover Rule

- Do not run indefinite dual ownership of the same public route in both PHP and Python.
- During migration, route ownership should be explicit and temporary.
- Final state for this spec is PHP-only production ownership.

## 25. Open Design Constraints

- Preserve canonical repository compatibility unless a separate versioned record spec says otherwise.
- Prefer additive derived-state evolution over canonical-format churn.
- Avoid introducing a second canonical datastore.
- Favor explicit route and cache allowlists over inference-heavy behavior.

## 26. Acceptance Criteria

This rewrite specification is satisfied when:

- PHP exclusively owns production web routing and rendering.
- Public reads use indexed PHP-native data instead of Python rendering fallbacks.
- Canonical writes are validated, stored, committed, and read back through PHP.
- Board, thread, post, project-info, activity, and profile routes are feature-complete relative to the current product slice.
- Activity filters, RSS, project-info framing, and account/key-bootstrap basics remain functional.
- Automated request and cache tests cover the main route set.
- Production no longer depends on Python request handling.

## 27. Suggested Deliverables For Implementation Planning

Before coding the rewrite, create:

- a route ownership matrix
- a canonical record compatibility matrix
- a SQLite schema spec for the read model
- a write-path failure-mode table
- a cache invalidation matrix
