> **Feature plan:** [Step 1](./agent_checkin_step1_solution_assessment.md) · [Step 2](./agent_checkin_step2_feature_description.md) · [Step 3](./agent_checkin_step3_development_plan.md) · [Step 4](./agent_checkin_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3; log [agent_checkin_debug_log.md](./agent_checkin_debug_log.md))

Effort study: `railspike-effort` / `v3/agent_checkin`; cycle ledger [agent_checkin.md](../effort_ledger/agent_checkin.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.65**

Branch `feat/agent-checkin`, based on `origin/main` `597f5328`; Step 3 was refreshed against `7d9b4b4d`. The 38 commits between them (zen_strip, hn_theme, thread_port) touch nothing this plan uses. Planning commit `c9c37fc9` holds Steps 1–3, the debug log and the plans index entry. Base smoke set: 480 run, 480 passed.

Enhancement requests: none pending. The two on record are in Step 3 (client rename, done; API refresh, done).

## Study snapshots

- 2026-11-20 UTC, Step 4 entry: **0.65** projected total, reaffirmed from the Step 3 revision. Claude, single exposed rater. Scope v1. Stage originals copied from Step 3 (not new ratings): Stage 1 0.10, Stage 2 0.10, Stage 3 0.08, Stage 4 0.07, Stage 5 0.05, Stage 6 0.05, Stage 7 0.08, Stage 8 0.05. The first-draft Stage 5 value, 0.12, is retained in Step 3's study checkpoint.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 1 - Public check-in route

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.10**

- Changes:
  - `Http/AgentCheckinController.php`: `GET /api/checkin` returns plain text with `Check-in`, `Version` and `Viewer: anonymous`, then one block per section. Each item has a `Read:` line, and each non-empty section ends with a `Next:` line. Only GET and HEAD are accepted (405 otherwise). It sends no-store headers, and reading it moves no mark.
  - Sections, in order: "Open #issue items" (claimed items show "claimed by NAME"; opening posts show "opening post of"), then "Newest threads" (by creation time). Each is capped at 10.
  - `Tools/MarkedPostsQuery.php`: new `open(pdo, parents, limit)` and `workParents(tagsets)`. The excerpt shaping moved into a shared `item()`, and `byState()` behaves as before. Section titles come from `resources/tagsets.txt` (parents whose unlocks include a work state), so `#issue` is not hard-coded. The query uses unqualified `threads`, so Fastmod's label view applies.
  - `FeatureFlagRegistry::AGENT_CHECKIN_ENABLED` (`FORUM_AGENT_CHECKIN_ENABLED`): on by default, site-mutable, in Experience. With it off, the route returns 404 with `error=The agent check-in is disabled on this server.` With tag unlocks off, the open section is left out.
  - `Application.php`: route, controller factory, and an `isApplicationRoute` entry.
  - `ApiRouteCatalog.php`: a `/api/checkin` entry.
- Verification:
  - `./v3 test AgentCheckinTest`: 6 run, 6 passed. Covered: sections and order, resolved, closed and #meta posts left out, claimed labelling, opening posts, readable links only, the cap of 10, `workParents`, case-insensitive parents, tag unlocks off, the flag off, and POST refused.
  - `./v3 test --smoke AgentCheckinTest FeatureFlagEvaluatorTest TagUnlocksTest FeatureFlagsBehaviorTest ApiRouteCatalogTest`: 504 run, 504 passed. Full suite not run.
- Notes:
  - Deviation, Step 3 Stage 1 "public read-only allowlist": `/api/checkin` is **not** added to the members-only allowlist, because it lists thread content. On a members-only instance only approved sessions reach it, like `/api/list_index`. `/skill.md`, which carries no content, goes on the allowlist in Stage 4.
  - Moved from Stage 4: the `/api/checkin` catalog entry. `ApiRouteCatalogTest` requires every routed path to be listed, so it had to land with the route.
  - Reuse: `RouteServices::pdo()` (with Fastmod labels), `ThreadRepository::fetchThreads`, `Application::displayThreadTitle`, `TemplateRenderer::requestOrigin()`, `Tagsets::forApplication()`. There is no new tag rule.
- Estimate details (extended mode only): original scope as Step 3 Stage 1; uncertainty low; no revisions.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*
