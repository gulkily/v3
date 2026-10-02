# `v3` Terminal Operator UI Step 1 Solution Assessment

## Problem Statement

The `./v3` operator command surface has grown to roughly 35 command forms.
It dispatches to many independently parsed PHP and Node scripts, so users must
remember command hierarchies, option names, positional arguments, configuration
locations, and destructive-action confirmations.

Configuration is especially difficult because its effective value may come from
several layers:

1. environment variables used as deployment overrides;
2. private PHP configuration, which includes LLM and other sensitive settings;
3. the versioned site feature-flags record for mutable site settings; and
4. code defaults.

The existing web feature-flags page already manages site-mutable flags. The
terminal UI should improve local operator work without duplicating that UI or
the command implementations.

## Current Constraints

- `v3` is a thin Bash dispatcher; it delegates to the existing scripts rather
  than owning a shared command-schema layer.
- Individual scripts retain their own argument parsers, help text, validation,
  confirmation behavior, and progress output.
- `PrivateConfig` recognizes more settings than the generated
  `private-config` template documents. Existing unknown and legacy values are
  preserved on template refresh, but the metadata is not centralized.
- Secrets must never be exposed in menu previews, logs, command history, or
  subprocess arguments.
- The runtime has `whiptail`, while PHP has no curses extension. A menu/form
  UI can be introduced without adding a large terminal framework.

## Options

### Option A: Maintain help text and direct configuration editing

Continue expanding `./v3 --help`, documentation, and `private-config edit`.

Pros:

- No new interactive-runtime dependency or UI test surface.
- Preserves scriptability and current operator habits.

Cons:

- Does not reduce option discovery or configuration-precedence complexity.
- Leaves validation and safe workflow assembly distributed across scripts.
- Does not provide an effective-configuration view suitable for routine
  operations.

### Option B: Config-first terminal operator UI

Add `./v3 tui` as a `whiptail`-based terminal interface. Start with a
read-only dashboard and command launcher, then add a guided private-config
editor. The UI builds arguments and invokes existing `./v3` commands; those
commands remain the execution and validation source of truth.

Pros:

- Addresses the highest-friction area—configuration—before attempting every
  operational workflow.
- Makes effective values, sources, environment locks, and flag dependencies
  visible in one place.
- Preserves existing non-interactive commands and their tested behavior.
- Gives a clear incremental path for rebuild, queue, Fastmod, and recovery
  workflows.

Cons:

- Requires extraction of shared configuration metadata before it is safe to
  edit settings through the UI.
- Needs terminal interaction tests and a non-interactive fallback when
  `whiptail` is unavailable.
- A complete guided surface remains materially larger than the first slice.

### Option C: Full terminal workflow console first

Build forms for every command, including imports, archive/delete flows,
recovery, Fastmod backfill, and production canaries before releasing a UI.

Pros:

- One uniform destination for all operator actions.

Cons:

- High complexity: each workflow needs its own typed inputs, confirmations,
  progress streaming, cancellation semantics, and error recovery.
- Delays the useful configuration/status experience.
- Risks duplicating command logic and drifting from the CLI contracts.

## Recommendation

Choose Option B.

Implement a config-first UI in phases:

1. Add `./v3 tui` with a read-only dashboard: operator status, a redacted
   effective-configuration summary with value sources/overrides, searchable
   command selection, and contextual help.
2. Extract a shared configuration schema covering defaults, types, validation,
   redaction, dependencies, precedence, and rendering. Have
   `private-config`, the UI, and documentation consume it.
3. Add a private-config editor with provider presets, masked secret input,
   field validation, atomic writing, preservation of permitted legacy/unknown
   values, and a reviewable diff before saving.
4. Add guided operational workflows later. For every state-changing command,
   show the generated command and impact before invoking it, stream output,
   allow safe cancellation, and require the command's current explicit
   confirmation token after review.

Do not initially rebuild the web-managed site feature-flags editor in the
terminal. The TUI can display its effective state and link users to the
existing management route/documentation.

## Guardrails

- Retain normal `./v3` commands as a fully supported, scriptable interface.
- The TUI must not implement a second copy of operation validation or business
  logic; it must invoke the existing dispatcher/scripts.
- Treat environment-origin values as read-only and clearly show that a restart
  or deployment configuration change is required.
- Mask secrets by default, use standard input or terminal-safe entry for them,
  and never include them in argv, previews, logs, or shell history.
- Preserve atomic file-write permissions for private configuration.
- Refuse interactive mode on non-TTY input and give a concise fallback to the
  equivalent CLI command.
- Add a dependency check for `whiptail`; retain a plain CLI fallback if it is
  not installed.

## Rough Estimate

| Slice | Estimate |
| --- | --- |
| Read-only status/config dashboard and command launcher | 2–4 days |
| Shared config schema plus private-config editor | 5–10 days |
| Complete guided operator console | Additional 2–4 weeks |

The estimates include focused test coverage, but exclude unplanned changes to
the underlying command contracts or a migration away from executable PHP
private-config files.
