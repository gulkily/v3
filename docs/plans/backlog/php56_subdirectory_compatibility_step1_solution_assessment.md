# PHP 5.6 and Subdirectory Hosting — Step 1 Solution Assessment

## Problem

Make the application runnable from PHP 5.6 under a user-directory URL such as `https://www.mit.edu/~igulko/`, while preserving operation on modern PHP and, where practical, PHP versions between 5.6 and current releases.

## Current assessment

- Difficulty: **high / multi-cycle**.
- PHP 5.6 cannot parse the current source: the repository uses `declare(strict_types=1)`, scalar/return/nullable/`mixed` type syntax, arrow functions, `match`, nullsafe access, and other newer syntax.
- Runtime APIs also need compatibility handling, including `str_contains`/`str_starts_with`/`str_ends_with`, `JSON_THROW_ON_ERROR`, and `random_bytes`.
- The application is not currently path-rooted: routes, assets, redirects, generated HTML, JavaScript URLs, and Apache rewrite rules contain root-relative assumptions such as `/assets/...` and `/api/...`.
- PHP 5.6 hosting also changes the operational boundary: the web process, SQLite/PDO support, filesystem permissions, Git-backed writes, OpenSSL/GnuPG behavior, and any optional LLM/cron tools must be assessed independently rather than assumed available.
- “SQLite3 is not installed” must be clarified: the standalone `sqlite3` shell is different from PHP’s `pdo_sqlite` extension. The application currently requires PHP PDO SQLite at runtime and uses SQLite-specific schema/query behavior throughout the read model, writes, search, analysis/agent queues, downloads, and browser viewer.
- If PHP PDO SQLite is also unavailable and cannot be enabled, this host is not currently a drop-in target. Replacing SQLite with another backend would be a separate persistence-porting project, not a compatibility adapter.
- “Modern PHP” needs an explicit supported ceiling and test matrix; preserving behavior across PHP 5.6 and modern PHP will constrain future language/API upgrades.

## Hosting decision gate

Before implementation, verify separately on the target host:

- PHP version and whether `pdo_sqlite` is loaded (`phpinfo()`/a minimal web probe, because CLI and web PHP can differ).
- SQLite library version and supported features.
- Whether the `sqlite3` command-line tool is needed for operations, or whether PHP scripts are sufficient.
- Whether a newer PHP handler, a user-installed PHP binary/extension, or another host is permitted.

The preferred outcome is enabling PHP PDO SQLite on the target. If that is impossible, either select a host with the required extension or explicitly re-scope the project to a new storage backend.

## Options

### Option A — One compatibility rewrite

- Pros: one coherent target architecture; no long-lived compatibility branch or split deployment model.
- Cons: large parser-breaking rewrite across most PHP files; broad regression risk; difficult to keep within one FDP cycle; deployment/path work adds a second cross-cutting concern.

### Option B — Separate PHP 5.6 and modern implementations

- Pros: each runtime can use its natural language and APIs; PHP 5.6 limitations stay isolated.
- Cons: duplicated behavior and tests; feature parity will drift; future fixes must be ported twice; does not satisfy a single portable application cleanly.

### Option C — One deliberately conservative source baseline plus compatibility adapters **(recommended)**

- Pros: one application and one behavior contract; shared path/configuration layer benefits every runtime; compatibility adapters can replace newer APIs without database migration; intermediate PHP versions can be validated incrementally.
- Cons: substantial initial refactor; all source must remain parseable by PHP 5.6, including code paths not used by the web request; modern-only tooling/features may need explicit optional degradation; PHP 5.6 CI/container coverage is required.

## Recommendation and FDP sizing

Choose Option C, but do not treat this as one FDP cycle. Split it into at least **three FDP cycles**:

1. Compatibility inventory and support contract: define the feature/runtime matrix, identify PHP 5.6 web-critical versus optional CLI/LLM functionality, and establish baseline smoke tests on PHP 5.6 and modern PHP.
2. Source/runtime compatibility: remove parser-incompatible constructs, add narrowly scoped adapters/polyfills, and preserve the existing behavior with cross-version tests.
3. Subdirectory deployment: introduce one configured base path, update links/assets/redirects/cookies/static artifacts, and validate Apache/user-directory hosting end to end.

If the full application—including all maintenance scripts, browser identity/OpenPGP behavior, Git writes, and external-provider integrations—must work identically on PHP 5.6, expect a fourth cycle for unsupported-extension fallbacks and production-host validation. If PDO SQLite cannot be enabled, add a separate storage-backend assessment and likely one or more implementation cycles. A single cycle is appropriate only for the assessment and contract, not for implementation.
