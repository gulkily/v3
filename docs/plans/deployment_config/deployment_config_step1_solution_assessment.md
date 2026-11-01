> **Feature plan:** [Step 1](./deployment_config_step1_solution_assessment.md) · [Step 2](./deployment_config_step2_feature_description.md) · [Step 3](./deployment_config_step3_development_plan.md) · [Step 4](./deployment_config_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/deployment_config; [standing enrollment P2](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
Status: Step 1 proposed; implementation not authorized
**Cycle estimate: 0.85**

# Deployment Config Step 1 Solution Assessment

## Original Query

> Keep deployment-specific configuration outside tracked files. A `git reset --hard` already wiped QDB's profile setting. Fixing this means one persistent configuration source shared by the web app, CLI and cron, with instructions for migration and recovery. It probably needs the full Step 1–3 planning.

Please review any updates to the code that may have fixed it and then write a plan for whatever remains to be done.

## What has already changed

- **Fixed:** the high-traffic page renderer now receives the web request's site profile instead of falling back to ZenMemes (`0cb81f11`, 2026-10-25; [diagnosis](../qdb_high_traffic_profile_diagnosis.md)). This fixed QDB's cached homepage turning into the generic Board page.
- **Partly there:** a private settings file already lives outside the checkout (`../forum-private/secrets.php`, with `./v3 private-config` and the TUI editor), so it survives `git reset --hard`. It holds LLM, Fastmod and feature settings only.
- **Not fixed:** site identity (`FORUM_SITE_ID`) and instance paths (`FORUM_REPOSITORY_ROOT`, `FORUM_DATABASE_PATH`, `FORUM_STATIC_HTML_ROOT`, `FORUM_PUBLIC_ARTIFACT_ROOT`, `FORUM_IMAGE_ROOT`) are read only from environment variables, in about 35 places. On a shared host like QDB's, the only place to set them for the web app is the tracked `public/.htaccess` (`SetEnv`), which a reset erases. The task-queue cron line (`./v3 task-queue cron`) passes none of them, so cron jobs use ZenMemes and the default paths unless the operator adds them by hand.

## Problem Statement

A deployment's identity and data paths live in process environments that differ between Apache, the CLI and cron and, on shared hosts, can only be set in a tracked file, so an ordinary code reset or a cron run can silently switch the site's profile or data.

Concepts touched: *deployment configuration* (proposed, created: site identity and instance paths, where they come from and their precedence). Private config (existing, no inventory entry; changed under A).

## Option A: Extend the existing private settings file

**Estimate: 0.80**

Add site identity and the instance paths to the private settings schema, so they can live in `../forum-private/secrets.php`. One resolver answers "which site and which paths" for the web app, CLI, cron and child processes, with the precedence environment variable, then private file, then default. `./v3 status` shows each value and where it came from. The runbook covers moving `SetEnv` lines out of `.htaccess`, plus recovery.

- Pros: one persistent location that already exists, with an editor, a template and a survives-reset property; web, CLI and cron read the same file; environment variables keep working.
- Cons: secrets and identity share one file; the default location (`../forum-private/`) would collide if two sites ran from sibling checkouts in the same parent directory.
- Driver: replacing about 35 scattered environment reads with one resolver without changing defaults. Range 0.65–1.00.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Option B: A separate plain-text deployment file

**Estimate: 0.95**

Same resolver and precedence, but site identity and paths go in a new `Key: value` file beside the private settings (for example `../forum-private/deployment.txt`), keeping them apart from secrets.

- Pros: readable and editable as plain text; keeps non-secret identity separate from credentials.
- Cons: a second configuration location, with its own parser, editor support, template and status reporting; more for operators to learn.
- Driver: everything in A plus a new file format and tooling.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Option C: Keep environment variables; detect and document

**Estimate: 0.35**

No new source. `./v3 status` warns when tracked `.htaccess` has local edits, and when the CLI's profile or paths differ from what the web app last used. The cron reference line includes the current values. The runbook says to put settings in the vhost or crontab.

- Pros: smallest change; no new precedence rules.
- Cons: shared hosts still have nowhere safe to put web settings; a reset still erases them, now with a warning afterwards rather than prevention.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Recommendation

Option A, plus C's status reporting of each value's source. It reuses the persistent location operators already have, and it closes both gaps: the reset on shared hosts, and cron's missing settings. It is a releasable slice: a QDB-style deployment configured only in the private file serves the QDB profile from the web app, CLI and cron, survives `git reset --hard`, and existing environment-variable deployments keep working unchanged.

Points to settle in Step 2: whether the private settings format stays PHP (existing) or this cycle is the moment to add a plain-text form; and whether `./v3 status` should refuse to run, or only warn, when tracked files carry local configuration edits.

## Revision 1 — per-checkout private file lookup

User request (2026-10-30): add per-site private file lookup to the recommendation.

The private file location is a fixed rule, `../forum-private/secrets.php`, repeated in `PrivateConfig`, `write_private_config.php` and `terminal_llm_editor.php`; only the `FORUM_SECRETS_PATH` environment variable overrides it. Revised Option A replaces the three copies with one lookup:

1. `FORUM_SECRETS_PATH`, when set (unchanged)
2. `../forum-private/<checkout folder name>/secrets.php`, when it exists
3. `../forum-private/secrets.php` (unchanged default)

Existing single-site setups keep working with no change. Sites whose checkouts share a parent folder (for example `~/sites/qdb` and `~/sites/zenmemes`) get separate settings without needing an environment variable. The app, `./v3 private-config` and the TUI editor all use the same lookup, and `./v3 status` reports which file was used.

- New risk: renaming or re-cloning a checkout under a different folder name silently switches to the shared default. Mitigation: status shows the file in use, and the runbook says to keep the folder name or set `FORUM_SECRETS_PATH`.
- Revised estimate for A: 0.85 (from 0.80), for the shared lookup, the editor changes and their tests. B and C would need the same lookup to cover side-by-side sites; their estimates are not revised.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Study checkpoint

Checkpoint: intake and Step 1 handoff, 2026-10-30T20:47:01Z
Scope: v1 / Option A (+ status reporting from C); baseline 4abda3dbd144370a3353781b29e8690e46d47d88; R1 / P2
Intake: 0.80, recorded from the todo text before reading code.
Projection: 0.80 for A, reaffirmed after inspection: the renderer fix removed one subproblem, offset by about 35 scattered environment reads. Alternatives B 0.95 and C 0.35 are complete-cycle estimates for different outcomes, not additive.
Rater: Claude (claude-opus-5-5), single exposed rater; diagnosis doc, renderer fix, private-config loader/schema, runbook and call sites inspected before the handoff rating. All three frozen R1 hashes verified.
Authorization: user asked for a review and a plan; awaiting Approved Step 1. Planning and study records uncommitted.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

Revision snapshot, 2026-10-30 UTC: scope v1.1 / revised A (adds per-checkout private file lookup at the user's request; scope change, not new understanding). Projection 0.85 supersedes 0.80 for the revised scope; original 0.80 retained above. Same baseline, R1 / P2 and rater. Still awaiting Approved Step 1.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*
