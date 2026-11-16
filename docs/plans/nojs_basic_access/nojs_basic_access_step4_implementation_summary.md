> **Feature plan:** [Step 1](./nojs_basic_access_step1_solution_assessment.md) · [Step 2](./nojs_basic_access_step2_feature_description.md) · [Step 3](./nojs_basic_access_step3_development_plan.md) · [Step 4](./nojs_basic_access_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)
Effort study: `railspike-effort` / `v3/nojs_basic_access` ([ledger](../effort_estimation_trial.md#cycle-v3nojs_basic_access)); standing enrollment P2
Extended estimates: disabled (inherited)

**Cycle estimate: 0.35**

Branch `feat/nojs-basic-access` from `main` `32f884b1`; planning commit `58eefd2c`. `Approved Step 3` received 2026-11-16.

## Step 4 entry

- Projection: **0.35 reaffirmed** at entry (same scope v1, R1/P2, single exposed rater); no new understanding since the Step 3 handoff.
- Tooling: browser checks need the declared devDependency `playwright-core@1.58.1`. Its gitignored `node_modules` was copied from the sibling checkout `../v3-claude-3` (same pinned version; nothing new added).

## Stage 1 - No-script journey check

- Changes:
  - `tests/browser/nojs_basic_access.mjs`: scripts off from the first request. Covers entry, the "All" view, page 2 and back (pages disjoint, 55 seeded threads all covered), a thread, the inline board thread, standalone New post with an empty-body failure that keeps the title, inline reply, and standalone reply with a failure that keeps the thread context. Every post is checked after reload. It also checks for visible dead `type="button"` controls on board, thread and compose, and for honest members-only denial.
  - `tests/Support/nojs_browser_fixture.php`: seeds an isolated instance with N threads. Reuse decision: it is served by the existing `image_browser_router.php` (its `flags.json` already switches members-only). The image fixture itself was not reused because it requires an approved key and seeds no threads.
- Verification:
  - `node tests/browser/nojs_basic_access.mjs`: every journey step passes (entry, page 2 and back, thread, inline and standalone threads and replies, both failures keeping drafts, reload). It fails as the plan expected, on the gaps Stage 2 fixes:
    - board: "View query" `button[data-view-query]` visible but dead;
    - thread: Like/Flag `button.thread-reaction-button` visible but dead (new finding; the baseline only probed the home page);
    - members-only `/threads/`: 401, heading "Reconnecting";
    - members-only `/threads/`: no visible `/account/key/` link.
  - `node tests/browser/composers.mjs`: PASS (unchanged).
  - `board_compose_focus.mjs:61` timeout diagnosed in part: after the test releases its frozen scripts, one `/api/auth_status` request never completes, so `networkidle` never arrives. The endpoint answers in 75 ms on its own (401), so the problem is contention in that test's setup (single-threaded `php -S`, session), not the endpoint. The cause is not fully pinned down. Recorded as a follow-up; not fixed here.
- Notes:
  - Same-author reply runs collapse earlier posts' actions until hover or focus (`content-interactions.css:147`). That works without scripts, so the test uses the run's last visible Reply link.
  - Thread order ties within the same second, so the pagination check compares sets rather than specific thread numbers.

*— Claude (claude-opus-5-5) · 2026-11-16 UTC*
