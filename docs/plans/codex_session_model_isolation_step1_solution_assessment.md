> **Feature plan:** [Step 1](./codex_session_model_isolation_step1_solution_assessment.md) · [Step 2](./codex_session_model_isolation_step2_feature_description.md) · [Step 3](./codex_session_model_isolation_step3_development_plan.md) · [Step 4](./codex_session_model_isolation_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

I'm running multiple concurrent Codex CLI instances under the same WSL user. I frequently switch models mid-session using `/model`.

**Problem:** Codex instances share `~/.codex/config.toml`, and `/model` appears to persist model selections to that shared file. This means changing models in one instance can affect the defaults used by other instances, particularly newly started sessions. Existing running sessions should normally retain their active models.

**Goal:** Allow every Codex instance to switch models and reasoning effort freely during a session, without interfering with other instances, while preserving authentication, session history, and resumability.

**Potential solutions:**
1. Investigate whether Codex supports session-only model changes without writing to shared configuration.
2. Consider separate `CODEX_HOME` directories per instance, initialized with the existing configuration and authentication. However, this may fragment session history and complicate resuming sessions.
3. Consider a lightweight wrapper or alternative solution that isolates model settings without unnecessarily isolating other Codex state.

**Relevant issue:** https://github.com/openai/codex/issues/26472

**Please:**
- Inspect my installed Codex version, its configuration behavior, and any relevant source code or documentation.
- Verify whether `/model` actually modifies shared configuration and whether running instances pick up those changes.
- Identify the simplest reliable solution, preferably preserving normal `codex resume` behavior.
- Avoid unnecessarily copying authentication credentials or creating abandoned configuration directories.
- Explain your findings and proposed implementation before making changes.

## Assessment

Problem: persistent picker selections change shared defaults even when the intended change belongs to one active session.

### Option A — Built-in session selection (recommended)

Use the installed 0.162.1 CLI's `s session` action at the final model/reasoning choice. This updates the active thread without writing model defaults.

- Pros: no installation or configuration changes, no credential copies, shared history and normal resume remain available.
- Cons: Enter still saves defaults; users must select the session action.

### Option B — Separate homes

Give each instance a separate Codex home and arrange access to shared state.

- Pros: independent configuration files.
- Cons: unnecessary state fragmentation or complex sharing rules; credential and cleanup concerns.

### Option C — Isolated configuration wrapper

Introduce a wrapper with instance-specific profiles or configuration isolation while retaining one shared home.

- Pros: could support a policy against accidentally changing common defaults.
- Cons: extra lifecycle and compatibility work; command-line model overrides alone do not disable picker writes and can override restored thread settings.

## Recommendation and evidence

Adopt Option A as a complete operational solution; no implementation is needed. Existing runtime config and authentication were not changed. Normal resume restores thread model settings unless explicit model/provider/profile overrides select current configuration instead.

Source inspection of release `rust-v0.162.1` confirms distinct persistent and session-only event paths. Upstream tests assert byte-identical config after session-only changes and unchanged defaults for a fresh thread. Tests were inspected, not executed; no interactive live-session experiment was performed. Active-thread updates target a thread ID rather than broadcasting a saved default to every running thread; this is source-based verification, not a two-process runtime test.

Sources: [session action](https://github.com/openai/codex/blob/rust-v0.162.1/codex-rs/tui/src/chatwidget/session_model_selection.rs), [picker persistence](https://github.com/openai/codex/blob/rust-v0.162.1/codex-rs/tui/src/chatwidget/model_popups.rs), [thread update and default write](https://github.com/openai/codex/blob/rust-v0.162.1/codex-rs/tui/src/app/model_defaults.rs), [verification tests](https://github.com/openai/codex/blob/rust-v0.162.1/codex-rs/tui/src/app/tests/model_defaults_tests.rs), [resume policy](https://github.com/openai/codex/blob/rust-v0.162.1/codex-rs/tui/src/app/config_persistence.rs), [reported issue](https://github.com/openai/codex/issues/26472).
