# PHP Forum Rewrite FDP Loop Recommendation

## Recommendation

Run **6 separate FDP loops** for this project.

One end-to-end loop for the whole rewrite is the wrong shape for FDP. The rewrite still spans architecture, indexing, route rendering, write orchestration, and regression coverage. But the planning assumptions have narrowed in three important ways:

- greenfield delivery rather than a production cutover from a running Python-owned stack
- two SQLite databases from the start, plus prebuilt HTML artifacts as the primary read-serving path

Under those assumptions, six loops is the better fit. It keeps the work reviewable without reserving separate loops for removed scope or a production cutover program.

Use **Step 1 only for loops with genuine implementation uncertainty**. With the planning questions answered, most loops can start at Step 2 unless execution reopens a design question.

## Loop Breakdown

### Loop 1 - Canonical Record Contract and PHP Parser Foundation

- Scope:
  - Confirm supported canonical record families for V1
  - Define PHP-side parsing and validation boundaries
  - Lock canonical path resolution and file-format invariants
  - Establish shared test fixtures for record parsing
- Why this is its own loop:
  - Every later loop depends on stable canonical read/write contracts
  - Errors here would cascade into the indexer, writes, and migration work
- FDP path:
  - Start with **Step 1** only if parser architecture or record-family sequencing becomes uncertain during implementation
  - Otherwise start at **Step 2**

### Loop 2 - SQLite Read Model and Rebuild/Recovery Framework

- Scope:
  - Design the two SQLite databases from the start
  - Define the split between canonical-derived state and route/view payload storage
  - Implement full rebuild and incremental refresh mechanics
  - Implement stale/mismatch detection and deterministic recovery hooks
  - Add observability around rebuild state and timing
- Why this is its own loop:
  - The read model is the core runtime dependency for all hot public reads
  - Startup and recovery behavior needs focused review before route work starts
- FDP path:
  - Start with **Step 1** only if schema or database-split decisions reopen during implementation
  - Otherwise start at **Step 2**

### Loop 3 - Core Public Reads: Board, Thread, and Post Routes

- Scope:
  - Migrate `/`, `/threads/<id>`, and `/posts/<id>` to PHP-owned indexed reads
  - Generate prebuilt HTML artifacts for static-safe queryless routes
  - Define PHP fallback when prebuilt HTML is missing
  - Implement shared shell/nav/template contracts for these pages
  - Add request tests and baseline cache eligibility tests
- Why this is its own loop:
  - These are the hottest and most visible user-facing routes
  - They validate the spec's central promise: no request-time full repo parse
- FDP path:
  - Start at **Step 2**

### Loop 4 - Project Info and Activity Surfaces

- Scope:
  - Migrate `/instance/`, `/activity/`, filters, pagination, and RSS
  - Materialize indexed activity feed data, including `all`, `content`, and `code`
  - Add compatibility and cache behavior tests for these routes
- Why this is its own loop:
  - Activity has distinct indexing, filter-contract, and feed-output complexity
  - It should not be hidden inside the board/thread migration loop
- FDP path:
  - Start with **Step 1** only if activity feed composition or code-feed sourcing becomes ambiguous during implementation
  - Otherwise start at **Step 2**

### Loop 5 - Profiles and Shared Account-Adjacent Read Surfaces

- Scope:
  - Public profiles
  - Self-profile pages
  - Username routes at `/user/<username>`
  - Account-key bootstrap pages
  - Shared nav/profile enhancement asset integration
- Why this is its own loop:
  - Profiles depend on indexed summaries plus identity-aware page behavior
  - This loop still includes username/account basics and the dedicated profile read contract
- FDP path:
  - Start at **Step 2**

### Loop 6 - Core Content Write Paths

- Scope:
  - Create thread
  - Create reply
  - Identity/bootstrap writes via the retained `/api/link_identity` flow
  - Username capture during browser keypair generation as an input to bootstrap
  - Lazy regeneration via targeted invalidation of affected prebuilt artifacts
  - Git commit orchestration and operator-visible failure handling for these flows
- Why this is its own loop:
  - This is the first end-to-end canonical write slice
  - It proves the write contract under the chosen "invalidate then rebuild on next read" model
  - It closes the loop between canonical identity records, profile reads, and browser-signing flows
- FDP path:
  - Start at **Step 2**

## Ordering

Run the loops in this order:

1. Loop 1 - Canonical Record Contract and PHP Parser Foundation
2. Loop 2 - SQLite Read Model and Rebuild/Recovery Framework
3. Loop 3 - Core Public Reads: Board, Thread, and Post Routes
4. Loop 4 - Project Info and Activity Surfaces
5. Loop 5 - Profiles and Shared Account-Adjacent Read Surfaces
6. Loop 6 - Core Content Write Paths

This sequence follows the revised implementation direction: canonical contract first, then the two-DB/read-artifact system, then hot reads, then remaining writes. Because this is being treated as a greenfield PHP project rather than a live Python-to-PHP cutover, a dedicated cutover loop is no longer necessary.

## Why Not Fewer Loops

- **1-3 loops** would be too large for FDP and would blur approvals across unrelated risk areas.
- **4-6 loops** would still combine route rendering, artifact regeneration, and write orchestration too early.
- **6** is the practical fit for the revised scope.

## Step 1 Guidance

Recommend **Step 1 solution assessments** only if a loop reopens a design question during execution. Under the current decisions, every loop can begin at **Step 2**.

## Preconditions Before Starting Loop 1

- Confirm the current product surface that defines "feature-complete relative to the current product slice"
- Confirm the authoritative local spec set under `docs/specs/`, including the profile read contract
- Decide the implementation baseline for PHP version, SQLite extension availability, and deployment model
- Review `php_forum_rewrite_answered_questions.md` and `php_forum_rewrite_trimmed_parity_suite_v1.md`, and reopen questions only if implementation discovers a real local conflict

## Exit Criterion For The Overall Program

The project should not be considered complete until the final loops verify the revised acceptance bar:

- PHP owns runtime routing and rendering fallback
- Prebuilt HTML serves the main anonymous hot routes directly where artifacts exist
- Indexed PHP-native reads and snapshots back all remaining hot fallback routes
- Canonical writes validate, store, commit, and read back through PHP
- Activity, RSS, profiles, and account/key-bootstrap basics remain functional
- Automated route and cache coverage exists for the main route set
- Removed V1 scope stays explicitly excluded rather than half-implemented
