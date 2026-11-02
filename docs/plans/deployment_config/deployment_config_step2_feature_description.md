> **Feature plan:** [Step 1](./deployment_config_step1_solution_assessment.md) · [Step 2](./deployment_config_step2_feature_description.md) · [Step 3](./deployment_config_step3_development_plan.md) · [Step 4](./deployment_config_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/deployment_config; [standing enrollment P2](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
Status: Step 1 revised Option A approved; Step 2 proposed; implementation not authorized
**Cycle estimate: 0.85**

# Deployment Config Step 2 Feature Description

## Problem

A deployment's site profile and data paths come only from environment variables, which differ between Apache, the CLI and cron. On shared hosts they can only be set in the tracked `.htaccess`, so `git reset --hard` or a cron run can silently switch a site's profile or data.

Concepts touched: *deployment configuration* (proposed, created: site profile and instance paths, their single lookup, precedence and private file location); private config (changed: gains these keys and a per-checkout location).

## User Stories

- As an operator on a shared host, I want my site's profile and paths in a file outside the checkout so that updating or resetting the code never changes which site I'm running.
- As an operator, I want the web app, CLI commands and cron jobs to use the same settings so that background work never runs against the wrong profile or data.
- As an operator running two sites side by side, I want each checkout to have its own private settings so that they don't share or overwrite each other's configuration.
- As an operator, I want `./v3 status` to show each setting's value and source so that I can confirm a deployment before and after a change.

## Core Requirements

- **New keys in the private file:** site profile (`FORUM_SITE_ID`) and instance paths (repository, database, static HTML, public artifacts, images). The existing PHP private file format and editor are kept; no new file format.
- **One lookup used everywhere:** the web front controller, every CLI command and script, cron workers and child processes resolve these settings the same way. Precedence: environment variable, then the private file, then today's built-in default. With no private-file values, behavior is unchanged.
- **One private file location rule:** `FORUM_SECRETS_PATH` if set; otherwise `../forum-private/<checkout folder name>/secrets.php` if it exists; otherwise `../forum-private/secrets.php`. The app, `./v3 private-config` and the TUI editor all use it.
- **Clear failures, never silent fallbacks:** an unknown site profile or an unreadable private file is reported with what to fix. `./v3 status` lists each setting with its value and source (environment, which private file, or default), and warns, without refusing to run, when tracked deployment files such as `public/.htaccess` carry local edits.
- **Migration and recovery documented:** the runbook explains moving `SetEnv` lines from `.htaccess` into the private file, adding a per-checkout file for side-by-side sites, verifying with status, and recovering after a reset or a folder rename.

## Delivery Scope

**Work type:** application change, plus runbook, private-config template, CLI reference and study ledger updates. No canonical record or read-model schema change, and no new dependency. Changing the private file's format is out of scope. Production servers are not changed by this cycle; the QDB acceptance below is an operator-run step.

## Completion Boundary

- Normal entry: the operator edits the private file (by hand, with `./v3 private-config` or with the TUI), then uses the site and runs `./v3` commands and cron as usual.
- Outcome: a deployment configured only in its private file serves its profile and data from the web app, CLI, cron and cache workers, and keeps them after `git reset --hard`.
- Recovery: a missing or mistyped value shows in status with its source; environment variables still override for emergencies; removing the new keys returns to today's behavior.
- Release: focused tests for precedence, location and every reader; full suite with no failures beyond the recorded baseline; an Apache test where the profile comes only from the private file and `.htaccess` is unmodified.

## Known User and Environment

QDB on its shared host (`~/v3`, Apache with `.htaccess`, cron), whose `SetEnv FORUM_SITE_ID qdb` was lost to a reset.

- Ordinary entry: the operator SSHes in, puts the site profile and paths in the private file, removes the `SetEnv` line, and runs `./v3 status`.
- Failure to handle: after `git reset --hard origin/main`, the site keeps the QDB profile and data on the web, in the CLI and in cron.
- Next unaided action and verification: `./v3 status` shows `FORUM_SITE_ID qdb` from the private file; the homepage title is QDB's Welcome and the theme is QDB's; the next cron run logs against the QDB repository.
- Evidence: this must come from the QDB host. Local Apache tests do not satisfy it; until the operator runs these checks there, QDB acceptance stays pending.

## Risks

- **High risk — wrong data after upgrade.** Impact: a deployment whose private file already contains a stray path key would start using it. Early validation: inventory current template and known private files for these keys. Mitigation: the new keys are absent from templates by default; status shows the source of every value.
- **Missed reader.** Impact: one of about 35 call sites keeps reading only the environment, so the CLI or cron silently disagrees with the web app. Early validation: a repository-wide search for direct reads, then a test that fails on new direct reads. Mitigation: route all of them through the lookup.
- **Folder rename switches files.** Impact: a renamed or re-cloned checkout falls back to the shared default file. Early validation: status shows the file in use. Mitigation: runbook guidance; `FORUM_SECRETS_PATH` still pins the file.
- **Production verification is outside the agent's reach.** Impact: QDB acceptance can't be shown locally. Mitigation: an operator checklist with exact commands; record acceptance as pending until it is run.

## Shared Component Inventory

- **Private settings:** extend `PrivateConfig`, `PrivateConfigSchema`, `write_private_config.php` (`./v3 private-config`) and `terminal_llm_editor.php` (TUI); no new store.
- **Readers:** route `SiteProfileRegistry::active()`, `public/index.php`, `LocalRepositoryBootstrap` defaults, `PresentationPathResolver`, `ImageStore`, high-traffic, retraction and task-queue paths, and the CLI scripts through the shared lookup.
- **Operator surfaces:** extend `./v3 status` (`scripts/status` and its formatter) and the task-queue cron reference; no web UI change.
- **Documentation:** the production deploy runbook, the CLI reference and the private-config template text.

## Simple User Flow

1. The operator adds `FORUM_SITE_ID` and paths to the site's private file and removes the `SetEnv` lines from `.htaccess`.
2. `./v3 status` shows each value coming from that file.
3. The site, CLI and cron all run as that site.
4. After `git reset --hard`, status and the site are unchanged.

## Success Criteria

- With only the private file set, the web app (normal and cached pages), CLI commands and the task-queue worker all report the same profile and paths.
- With no private-file values, behavior and output match today's.
- An environment variable still overrides the private file for each key.
- Two checkouts with different folder names under one parent use their own per-checkout files.
- Status reports every value's source, and flags local edits in tracked `.htaccess`.
- No code outside the shared lookup reads these keys directly from the environment.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Approval and study checkpoint

Checkpoint: Step 2 handoff, 2026-10-30 UTC; 0.85 reaffirmed
Scope: v1.1 / revised Option A; baseline 4abda3dbd144370a3353781b29e8690e46d47d88; R1 / P2
Rater: Claude (claude-opus-5-5), single exposed rater; Step 1 reference verification retained. Defining the QDB host acceptance as operator-run (pending until done) keeps the estimate unchanged; no other new information.
Authorization: Approved Step 1 received for revised Option A. Awaiting Approved Step 2; no implementation or commits.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*
