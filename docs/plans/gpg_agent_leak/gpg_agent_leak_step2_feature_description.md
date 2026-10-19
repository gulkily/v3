> **Feature plan:** [Step 2](./gpg_agent_leak_step2_feature_description.md) · [Step 3](./gpg_agent_leak_step3_development_plan.md) · [Step 4](./gpg_agent_leak_step4_implementation_summary.md)

Debug mode: enabled (project default via `.railspike_debug_on`)

# Step 2: Feature Description – GPG agent leak

## Problem
Each GPG call uses a fresh temporary home directory, and gpg auto-starts a `gpg-agent` (and sometimes `dirmngr`/`keyboxd`) for it. The directory is then deleted without stopping that agent, so every call and every test run leaves a process behind. Reported: 13,114 agents, 11,788 with deleted home directories.

## User stories
- As an operator, I want finished GPG operations to leave no background processes so that the host does not exhaust memory or process slots.
- As a developer, I want repeated test runs to leave no stray agents so that the machine stays usable.

## Core requirements
- Every code path that creates a temporary GPG home (`OpenPgpKeyInspector`, `OpenPgpSignatureVerifier`, `AgentIdentityService::generateKeypair`) stops that home's agents before deleting it, on success and on failure.
- Cleanup failure (gpg missing, agent already gone) never changes the operation result or throws.
- Existing verification, inspection, and keypair behavior and output are unchanged.
- A test proves no agent remains for a used home directory after each operation.

## Delivery scope
- Work type: application change (small; shared cleanup helper reused by the three call sites).
- Not in scope: killing the ~13k agents already running (one-time manual cleanup, noted for the user), changing the GPG workflow itself.

## Completion boundary
Normal entry: any signature verification, key inspection, or agent keypair generation, including test runs. Outcome: process count of `gpg-agent` is unchanged after the operation. Recovery: the leaked agents already present are cleared by a documented one-time command. Release: tests pass and a before/after agent count is recorded.

## Known environment
Operator's WSL/Linux host with GnuPG 2.x. Mismatch: agents outliving deleted homes. Next unaided action: run the documented one-time cleanup, then rerun tests. Verify: `pgrep -c gpg-agent` before and after a test run stays flat.

## Risks
1. Agent socket location may be keyed off the home path, so `gpgconf --homedir X --kill all` must run before the directory is removed. Validate early with a manual run. Mitigation: kill first, delete second.
2. The extra `gpgconf` call adds latency to every verification. Validate by comparing timings; mitigation: single `--kill all`, short timeout.
3. Other gpg users on the host (commit signing, the user's own keyring) must not be touched. Mitigation: only target the temp home we created.

## Shared component inventory
No UI/API surface renders this. Three duplicated private cleanup routines exist (`cleanup` in the inspector, `cleanup`/`deleteTree` in the verifier, `deleteTree` in the agent identity service). Extend by introducing one shared helper rather than patching each copy.

## Simple user flow
1. Code creates a temp GPG home and runs gpg.
2. Operation finishes (or fails).
3. Helper kills agents for that home, then deletes it.
4. No agent process remains.

## Success criteria
- Zero new `gpg-agent` processes after running the three operations and the full test suite.
- No agent references a deleted home directory.
- Existing tests still pass.
