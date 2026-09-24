# Documentation Audit — Findings & Checklist v1

Findings from a full review of `docs/` and the root-level docs
(`README.md`, `BLESSING.md`, `sfenc.md`, `todo.txt`), done shortly after
the Phase 2 `Application.php` decomposition (see
`codebase_cleanup_audit_plan_v1.md`) and the Phase 4 archive of completed
FDP cycles into `docs/plans/archive/`. Investigated via four parallel
passes: (1) root + operational docs (runbooks, CLI reference, examples),
(2) `docs/specs/*.md` against actual parsing/validation code, (3) the 91
active `docs/plans/*.md` files, (4) structural gaps + misc docs
(theme-menu spec, FDP methodology docs, CLI/script coverage).

Nothing here has been fixed yet — this is the tracking checklist for
that follow-up work.

## Outdated / actively wrong

- [ ] **`docs/runbooks/theme_development_guide.md`** — tells contributors
      to add new themes as scoped blocks inside `public/assets/site.css`.
      No longer true: themes now live in individual `theme-<name>.css`
      files (13 of them: `theme-word97.css`, `theme-dark.css`,
      `theme-light.css`, `theme-chouse.css`, `theme-arena.css`,
      `theme-chicago.css`, `theme-console.css`, `theme-forge.css`,
      `theme-lcd.css`, `theme-sticker.css`, `theme-thermal.css`,
      `theme-vapor.css`, `theme-whitehot.css`), swapped via a `<link>`
      `href` change driven by `themeStylesheetPaths` in
      `templates/layout.php` (~line 11, ~53-58). `site.css` itself now
      has essentially zero theme content (1 `data-theme=` match,
      verified). **High severity** — a contributor following this guide
      today edits the wrong file entirely. The internal "three blocks"
      structure (variable block, swatch rule, menu-row garnish) is still
      accurate *within* each theme file — only the file-location
      instructions are stale.
- [ ] **`docs/plans/forte_roadmap.md:37`** — claims
      `TemplateRenderer::renderFragment()` is dead code left in
      pending cleanup. It has 16 live call sites (`RouteServices.php`,
      `ForteContentAndUserDetailApiController.php`,
      `ForteActivityController.php`), including from this session's own
      Phase 2 extraction. One-line fix; otherwise this is the
      best-maintained doc in `docs/plans/`.
- [ ] **`docs/theme-menu-representative-options-spec.md`** — header says
      "Status: in progress on branch `theme-menu-representative-options`"
      but all 4 progress checklist items are `[x]` and that branch no
      longer exists. Verified the described sticker-garnish CSS
      (`border: 2px solid #0a0a0a; box-shadow: 2px 2px 0 #0a0a0a;`)
      shipped in `site.css` and fingerprinted build artifacts. Just needs
      the status line flipped to "shipped"/done.
- [ ] **`docs/specs/agent_reply_one_step_analyze_publish_contract_v1.md`**
      — repeatedly cites `Application::handleAnalyzePost()`,
      `Application::handleGenerateAgentReply()`,
      `Application::agentReplyResultForPost()`,
      `Application::agentReplyWorkForPost()`,
      `Application::agentReplyWorkByPostId()`,
      `Application::agentReplySummaryForAnalysisResponse()` as living on
      `Application`. This session's refactor moved all of them:
      `handleAnalyzePost`/`handleGenerateAgentReply` are now
      `PostWorkflowApiController::analyzePost()`/`generateAgentReply()`;
      the other four now live on
      `src/ForumRewrite/Agent/PostWorkflowService.php`. Described
      *behavior* still holds — only the class/method map is stale.
- [ ] **`docs/specs/php_forum_rewrite_spec_v1.md`** (the master spec) —
      two issues: (a) §11.1 lists activity views as `all`, `content`,
      `code`; actual code (`ActivityService.php:308`) supports `all`,
      `content`, `identity`, `bootstrap`, `approval`, `commits` — no
      `code` view exists, and the real ones aren't mentioned. (b) §2
      Non-Goals says "Do not add rich-text, voting, reactions" — a full
      post/thread reaction+tag system has since shipped
      (`post_reaction_record_v1.md`,
      `LocalWriteService::applyThreadTag()`/`applyPostTag()`). Reads as
      current but is really a pre-implementation baseline the product has
      outgrown; nothing marks it superseded. Also has no mention of
      `Application.php`, namespaces, or the post-refactor Http controller
      split (see the architecture-map gap below).
- [ ] **`docs/plans/php_forum_rewrite_answered_questions.md`** — lists
      ~12 "current routes," two of which (`/api/pow_requirement`,
      `/api/call_llm`) don't exist anywhere in the codebase anymore.
      Reads as a live reference; is actually a project-origin snapshot.
      Consider adding a "historical snapshot, not current" banner rather
      than updating the route list (cheaper, and honest about what the
      doc actually is).
- [ ] **`docs/plans/php_forum_rewrite_repo_self_sufficiency_todo.md`** —
      every link uses the absolute path `/home/wsl/v3/docs/...`, wrong
      for this checkout (`/home/wsl/agent/v3-claude/...`) — every link in
      "Ready For Implementation"/"Relevant Docs" is broken. Also claims
      moderation features aren't planned; `is_hidden`/`approved_flag`
      moderation already exists. One genuinely live, unresolved item
      buried at lines 89-106: no `LICENSE`, `CONTENT_LICENSE.md`, or
      `DATA_POLICY.md` exists (confirmed) — worth surfacing this
      separately since no one would think to look for it in this stale
      doc.
- [ ] **`docs/fdp/README.md`** + `FEATURE_DEVELOPMENT_PROCESS.md` —
      describes moving a feature's planning artifacts into
      `docs/plans/{feature_name}/` once 4+ accumulate, and maintaining a
      `docs/plans/README.md` index. Neither has ever happened —
      `docs/plans/` has always been flat, `docs/plans/README.md` doesn't
      exist. (The feature-branch-per-Step-4 convention described
      alongside this *is* actually followed, confirmed via real branches
      like `feature/chouse-theme` — so this is a partial mismatch, not a
      wholesale ignore.)

## Gaps

- [ ] **No architecture map of the post-refactor codebase.** The single
      biggest gap. `src/ForumRewrite/` has 17 top-level namespaces
      (Http: 28 files, Canonical: 24, ReadModel: 14, Analysis: 12,
      Agent: 8, Llm: 8, Support: 6, Host: 5, TaskQueue: 4, Write: 3,
      Codex: 3, Security/Invitation/Activity/View/Tools: 2 each) and
      nothing documents the current shape — not the master spec, not
      `README.md`, not any single doc. `codebase_cleanup_audit_plan_v1.md`
      and `activity_subsystem_extraction_plan_v1.md` are chronological
      commit narratives, not a current-state reference. Given this whole
      cleanup was explicitly "prepping for a demo to a code-review-literate
      audience," this is exactly the doc a reviewer reaches for first,
      and it doesn't exist. **Worth its own follow-up conversation** (new
      writing, not a correction) rather than folding into this checklist's
      other items.
- [ ] **`./v3 task-queue` (4 subcommands: `enqueue-rebuild`, `run`,
      `status`, `cron`) is completely undocumented** in
      `docs/reference/v3_cli.md`, despite being a real, wired-in operator
      command (`v3:164-167`, `scripts/task_queue.php`) that
      `docs/runbooks/production_deploy.md` and
      `docs/runbooks/operator_recovery.md` already reference and depend
      on. Found independently by two separate audit passes — high
      confidence. Most significant of the CLI gaps since it's a
      first-class subcommand, not a standalone script.
- [ ] **3 standalone operator scripts undocumented anywhere** (not in
      `README.md` or `docs/reference/v3_cli.md`):
      `scripts/audit_post_signatures.php` (186 lines — audits OpenPGP
      signatures on canonical post records),
      `scripts/build_sqlite_query_catalog.php` (65 lines — builds the
      SQLite query catalog for the SQLite viewer feature),
      `scripts/check_static_artifacts.php` (38 lines — validates static
      HTML artifacts for missing content).
- [ ] **No SQLite read-model schema reference.** The master spec
      describes the read model only in the abstract (design principles);
      no living doc of actual current table/column structure exists. The
      closest thing is `docs/plans/php_incremental_read_model_write_slices_v1.md`,
      a feature-history doc, not a maintained schema reference.
- [ ] **No spec for the CSS-splitting / asset-fingerprinting scheme**
      (`AssetFingerprint.php`, the per-page CSS split). `docs/specs/` is
      otherwise a thorough, durable record-format/contract layer with a
      blind spot for this newer infra.
- [ ] **`docs/runbooks/operator_recovery.md`** — the "Important fields"
      list for `/api/read_model_status` is missing `task_queue_status`,
      which the endpoint actually returns
      (`CodebaseStateController.php:107,173-176`), alongside every other
      field that *is* documented.
- [ ] **`docs/plans/php_production_deployment_checklist_v1.md`** — the
      one doc `README.md` actually links to as "Production Deployment
      Checklist." "Last reviewed: 2026-04-10," over 5 months stale.
      Chouse.club hosting, multi-site config, CSS splitting, and this
      session's `Application.php` decomposition have all landed since.
      Needs a re-review pass (confirm what's still accurate), not
      necessarily a rewrite.
- [ ] **`docs/plans/session_reauthentication_reference.md`** — reads as
      an open design doc with unresolved "Session Strategy Options," but
      the described approach already shipped
      (`Application::shouldResumeViewerSession()`/`resumeViewerSession()`,
      wired into `handle()`). Never recorded that a decision was made or
      what shipped.

## Lower-priority / worth a quick look

- [ ] Flip 3 fully-completed-but-not-archived `docs/plans/` files to
      `docs/plans/archive/` manually (they didn't match the strict
      4-step naming the Phase 4 sweep used): `codebase_state_feature_plan_v1.md`
      (all 4 slices marked Completed), `php_template_extraction_plan_v1.md`
      (Status: completed), and one more flagged in the same pass.
- [ ] `docs/sigtbd_fault_tolerant_governance_*` (5 files, an academic
      paper + slides + PDF by the same author) — parked directly
      alongside operational docs/specs with no separator. Not wrong, just
      possibly disorienting to someone browsing `docs/` expecting only
      technical material. Consider a `docs/research/` subdirectory if it
      bothers you; purely cosmetic.
- [ ] `docs/plans/page_asset_performance_inventory.md` contains an
      embedded "Autonomous Continuation" instruction block — a stored
      agent-prompt fragment, not project documentation. Worth a glance to
      confirm it's intentional/harmless where it sits.
- [ ] `docs/plans/php_ascii_restrictions_current_state_v1.md` — self-dated
      "April 2026" advisor brief; underlying fact has since changed (a
      `UNICODE_AUTHORED_TEXT` feature flag now exists). Low severity
      since the doc is honest about its own shelf life, but worth a
      pointer to what superseded it.
- [ ] `docs/plans/tag_scoring_implementation_plan_v1.md` appears
      superseded by a later revision of the same feature in
      `tag_scoring_outline_v1.md` — two docs for one feature, worth a
      "superseded by" pointer or a merge.

## Reviewed, no action needed

For completeness — these were checked and found current:

- `README.md` — every command/linked file verified against
  `scripts/rebuild_read_model.php`, `scripts/build_static_artifacts.php`,
  `scripts/init_local_repository.php`, and the `./v3` dispatcher.
- `BLESSING.md`, `sfenc.md` — timeless/self-contained, nothing to check
  against code.
- `todo.txt` — informal scratch notes; its "clean up the failing tests"
  item still accurately describes the 6 known pre-existing test
  failures.
- `docs/runbooks/production_deploy.md` — thorough and current
  (task-queue, LLM provider config, site profiles, feature flags,
  invitations, Unicode rollout, agent-reply all correctly documented).
- `docs/reference/v3_cli.md` — every subcommand/flag *except* the
  `task-queue` gap above verified against the actual `v3` dispatcher.
- `docs/examples/apache_vhost.conf`, `env.production.example`,
  `secrets.php.example` — consistent with each other and with
  `src/ForumRewrite/Support/PrivateConfig.php`.
- `docs/specs/canonical_post_record_v1.md`, `identity_bootstrap_record_v1.md`,
  `post_reaction_record_v1.md`, `thread_label_record_v1.md`,
  `user_approval_seed_record_v1.md`, `public_key_storage_v1.md`,
  `site_feature_flags_record_v1.md`, `profile_read_contract_v1.md` —
  every documented header/field/route/filename-pattern verified
  byte-for-byte against the actual parser/dispatch code.
- ~30 of the 91 active `docs/plans/*.md` files are genuinely still-open
  proposals (no action needed — they're supposed to describe unbuilt
  work) and ~35 more are historical-by-design and don't claim to be
  current (fine as-is). Full per-file classification available in this
  session's transcript if a complete list is ever needed.
