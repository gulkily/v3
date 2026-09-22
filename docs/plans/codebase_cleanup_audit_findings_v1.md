# Codebase Cleanup Audit — Phase 0 Findings

Output of Phase 0 (inventory) from `codebase_cleanup_audit_plan_v1.md`. Read-only
pass — nothing has been changed yet. Findings are grouped by confidence:
**confirmed dead** (safe to remove, verify with `tests/run.php` first),
**load-bearing legacy** (looks removable but isn't — documented, not touched),
and **duplication** (not dead, but repeated enough to be worth collapsing).

## Confirmed dead code

All of the below have zero call sites anywhere in `src/`, `scripts/`,
`tests/`, or `templates/` (verified by grepping for `->name(`, `self::name(`,
and, for the class, every `use`/`new`/`::`/type-hint form). No dynamic
dispatch exists in `Application.php` (`handle()` routes via plain
`if ($path === ...)`/`preg_match` checks, not a string-keyed method table), so
a zero-grep-hit result reliably means zero callers, not a routing indirection.

| Item | Location | Note |
|---|---|---|
| `TemplateRenderer::renderFragment()` | `src/ForumRewrite/View/TemplateRenderer.php` | Already flagged in `forte_roadmap.md` — caller removed by `forte_board_reply_tree`. |
| `CanonicalRecordFamily` (whole class) | `src/ForumRewrite/Canonical/CanonicalRecordFamily.php` | 16-line constants-holder class (`POST`, `IDENTITY`, `PUBLIC_KEY`, `INSTANCE_PUBLIC`), private constructor. Zero references anywhere, including its own constants — not even `CanonicalRecordFamily::POST` appears elsewhere. Whole file is a delete candidate. |
| `Application::renderPage()` | `src/ForumRewrite/Application.php:2747` | Superseded by `renderPageTemplate()`, which every call site actually uses (30 call sites). `renderPage()` itself has none. |
| `Application::preview()` | `src/ForumRewrite/Application.php:5131` | Unrelated same-named methods exist and *are* used in `ReadModelBuilder.php` and `IncrementalReadModelUpdater.php` — don't confuse those with this one. This specific copy in `Application.php` has no callers. |
| `Application::analysisHash()` | `src/ForumRewrite/Application.php:6678` | Same situation: a differently-owned `analysisHash()` in `AgentReplyFulfillmentService.php` is real and used. This copy in `Application.php` is not. |
| `Application::agentReplyGenerationFromAnalysis()` | `src/ForumRewrite/Application.php:5995` | Zero callers. |
| `Application::generatedAgentReplyResponse()` | `src/ForumRewrite/Application.php:6706` | Zero callers. |
| `Application::approveUserBySlug()` | `src/ForumRewrite/Application.php:7266` | Zero callers. The two live approval routes (`/api/approve_user` → `handleApproveUserApi`, `POST /profiles/{slug}/approve` → `handleApproveUserSubmit`) don't call it — worth a quick check of whether those two handlers duplicate this method's logic inline (separate finding below) before deleting, in case the fix is "have them call this" rather than "delete this." |

Templates and JS assets: **no orphans found.** Every file in `templates/pages/`,
`templates/partials/`, and every tracked (non-fingerprinted) file in
`public/assets/` has at least one real reference. Two apparent false leads,
resolved:
- `openpgp.min.js` vs `openpgp.v5.11.3.min.js` looked like a duplicate pair at
  first grep, but both are genuinely used — `openpgp_loader.js` picks between
  them at runtime based on `window.isSecureContext` (v6 needs a secure
  context; v5 is the fallback). Not a cleanup target.
- The fingerprinted `*.{hash}.js` files in `public/assets/` (multiple stale
  hashes per source file) are gitignored build output, not tracked source —
  irrelevant to repo size, not a cleanup target.

## Load-bearing "legacy" code — do not remove

Grepping for `legacy` turned these up; all three are still doing real work:

- `LlmProviderConfig.php` / `scripts/write_private_config.php`'s
  `DEDALUS_*` → `LLM_*` fallback translation. This is an active backward-compat
  path for existing deployments' config files, not dead code.
- `CanonicalRecordRepository::resolveLegacyPostCreatedAt()` /
  `resolveLegacyPostCreatedAtFromGit()` — called on every post record parse
  that's missing a `created_at` header (records written before that header
  existed in the format), falling back to git history then filesystem mtime.
  Actively exercised, not removable.
- `Application.php`'s `'legacy unsigned'` string (line ~5055) is just a label
  value, not a code path — no action needed.

## Duplication worth collapsing (not dead, but repeated)

**`Application.php` repeats the same script-path array literal ~10 times.**
Every page-render call site builds its script list by hand, always starting
with the same two entries:

```php
[
    '/assets/openpgp_loader.js',
    '/assets/browser_signing.js',
    // + 0-3 page-specific extras
]
```

Seen at (non-exhaustive): lines 1907, 1979, 2035, 2305, 2571, 2604, 2624,
4125, 4158, 4179. A one-line helper —
`identityScripts(array $extra = []): array` returning
`array_merge(['/assets/openpgp_loader.js', '/assets/browser_signing.js'], $extra)`
— would collapse each 3-6 line literal into a single call and remove the
copy-paste risk of one call site drifting (e.g. missing `private_site_auth.js`
where it should have it, or vice versa). Low-risk, mechanical, good Phase 1
candidate alongside the confirmed-dead removals.

## Route-group breakdown for Phase 2 sizing

`Application.php`'s `handle()` dispatches by literal path prefix (no
framework router). Route count by top-level segment, for sequencing the
decomposition described in the plan's Phase 2:

| Prefix | Route count |
|---|---|
| `/api` | 50 |
| `/tools` | 17 |
| `/forte` | 10 |
| `/account` | 6 |
| `/users` | 4 |
| `/lobby` | 4 |
| `/downloads` | 4 |
| `/compose` | 4 |
| `/threads` | 3 |
| `/tags` | 3 |
| `/source` | 3 |
| `/profiles` | 3 |
| `/invites` | 2 |
| `/instance` | 2 |
| `/backup` | 2 |
| `/activity` | 2 |
| `/about` | 2 |
| `records/*` (canonical write endpoints) | 7 |

`/api` alone is half the route table and is almost certainly the most
entangled with shared auth/session state — per the plan's guidance to
extract the most isolated groups first, good candidates to start with are
`/downloads`, `/instance`, `/about`, `/backup`, and `/tags` (small, likely
read-only, low coupling), saving `/api` and `/forte` for later once the
extraction pattern is proven out.

## Phase 2, slice 1 — outcome and a correction to the sizing table above

Attempted the originally-proposed first slice (`/about` + `/instance`/`/backup`
+ `/downloads/*`, ~10 routes) and found the route-count heuristic above
doesn't predict coupling well. Only `/about` was actually isolated (one
dependency: `renderPageTemplate()`). The rest pull in shared infrastructure
and, more importantly, shared *business logic*:

- `/instance`+`/backup` → `fetchBackupSnapshot()` → `pdo()`,
  `readModelTableExists()`, and `fetchActivity()` — the same method the
  `/activity` feed route uses. That's real cross-group logic reuse, not just
  shared framework plumbing.
- `/downloads/*` (3 handlers) → `sendHtml()`, `sendDownload()`,
  `renderMessagePage()`, plus the `repositoryRoot`/`databasePath`/
  `projectRoot` properties directly.

Shipped just `/about` as `src/ForumRewrite/Http/AboutPageController.php`,
using `Application::renderPageTemplate(...)` passed as a bound closure
(PHP 8.1 first-class callable syntax) so the new class doesn't depend on
`Application` itself or require any new public methods on it. Verified with
`tests/run.php` (476 pass / 7 fail, matching the established baseline
exactly — see Phase 1 commits) and confirmed via `AuthNavigationTest` and
`LocalAppSmokeTest`'s existing `/about` coverage.

**Revised guidance for future slices:** don't pick the next slice by route
count alone. A route group is only cheap to extract if it doesn't call a
method that's *also* called from a different route group. Before starting a
slice, grep each candidate handler's call chain (not just its own body, one
level deep is not enough — `fetchBackupSnapshot()` looked self-contained
until its own call to `fetchActivity()` was checked). Groups like `/tags`,
`/threads`, `/profiles` likely share `renderPageTemplate()`, `pdo()`, and
probably board/thread read-model queries the same way — expect the same
result. Once several slices confirm which methods are genuinely
shared-by-everyone (`pdo()`, `renderPageTemplate()`, the `send*()` response
helpers), the right move is probably to deliberately extract *those* into a
small shared-services object first (the plan's originally-deferred "Option
C"), rather than re-discovering the same handful of shared dependencies on
every future slice.

## Recommended next step

Phase 1 as scoped in the plan: remove the confirmed-dead items above (one
class deletion + five dead methods + the already-known `renderFragment()`),
collapse the script-array duplication into a helper, each as its own small
commit, running `tests/run.php` after each. That's roughly 150-250 lines
removed with no behavior change — a concrete, demonstrable first result
before starting the larger `Application.php` decomposition in Phase 2.
