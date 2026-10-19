> **Feature plan:** [Step 2](./gpg_agent_leak_step2_feature_description.md) · [Step 3](./gpg_agent_leak_step3_development_plan.md) · [Step 4](./gpg_agent_leak_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 2)

# Step 3: Development Plan – GPG agent leak

## Completion Contract
- Normal entry: signature verification, key inspection, agent keypair generation, and the test suite.
- Outcome: each temp GPG home's agents are stopped before the home is deleted; `gpg-agent` count is flat after the operations and the full suite.
- Recovery: documented one-time command clears already-leaked agents; user reruns tests and compares counts.
- Deployment/external verification: none (no schema, UI, or config change); before/after `pgrep -c gpg-agent` recorded in the Step 4 summary.
- Release: tests pass, counts flat, merged to main.

## Key Risks
- **Agent socket is keyed to the home path:** impact: kill after delete misses the agent. Validate manually in Stage 1. Mitigation: kill first, delete second.
- **Added latency per call:** impact: slower verification. Validate with existing timing fields. Mitigation: one `--kill all`, bounded timeout.
- **Touching other gpg users:** impact: kills the user's own agent. Mitigation: always pass the explicit temp `--homedir`; never call without it.

## Stage 1
- Goal: confirm the kill command stops the leaked agent for a temp home.
- Dependencies: none.
- Expected changes: none (manual repro in scratch dir; record counts).
- Verification approach: create temp home, run `gpg --list-keys`, confirm agent appears, run `gpgconf --homedir X --kill all`, confirm it is gone.
- Risks or open questions:
  - Impact: wrong command invalidates the design.
  - Early warning / validation: agent still listed after kill.
  - Mitigation: try `gpgconf --kill gpg-agent` plus `dirmngr`/`keyboxd`; adjust helper design.
- Canonical components/API contracts touched: none.

## Stage 2
- Goal: add one shared helper that stops agents then removes a temp GPG home.
- Dependencies: Stage 1.
- Expected changes: new Security class (e.g. `GpgHomeCleaner::dispose(string $home): void`); swallows all failures; replaces the three duplicated recursive-delete routines' callers' logic.
- Verification approach: unit test that spawns gpg in a temp home, calls `dispose`, asserts directory gone and no process mentions the path.
- Risks or open questions:
  - Impact: helper throws and masks results.
  - Early warning / validation: test with missing gpgconf (PATH stripped).
  - Mitigation: catch everything, bounded timeout.
- Canonical components/API contracts touched: new helper only.

## Stage 3
- Goal: use the helper in `OpenPgpKeyInspector` and `OpenPgpSignatureVerifier`.
- Dependencies: Stage 2.
- Expected changes: both `cleanup` methods delegate to `dispose`.
- Verification approach: existing `OpenPgpKeyInspectorTest` passes; added assertion that agent count is unchanged after inspect and verify (success and failure paths).
- Risks or open questions:
  - Impact: timings or diagnostics shift.
  - Early warning / validation: compare timing keys in tests.
  - Mitigation: dispose runs after timings are captured.
- Canonical components/API contracts touched: `OpenPgpKeyInspector::inspect`, `OpenPgpSignatureVerifier::verify` (unchanged contracts).

## Stage 4
- Goal: use the helper in `AgentIdentityService::generateKeypair`.
- Dependencies: Stage 2.
- Expected changes: `finally` block calls `dispose` instead of `deleteTree`; remove unused `deleteTree` if no other caller.
- Verification approach: existing agent identity tests pass; agent count unchanged after keypair generation.
- Risks or open questions:
  - Impact: keys exported after home removal.
  - Early warning / validation: export tests.
  - Mitigation: disposal stays in `finally`, after exports.
- Canonical components/API contracts touched: `AgentIdentityService::generateKeypair` (private).

## Stage 5
- Goal: acceptance evidence on the reported environment and recovery guidance.
- Dependencies: Stages 3–4.
- Expected changes: record before/after `gpg-agent` counts for the full suite in the Step 4 summary; add the one-time cleanup command (`pkill gpg-agent` scoped to deleted-home agents) to the summary/`todo.txt` entry.
- Verification approach: run the full suite on this WSL host; counts flat; no agent with a deleted home dir.
- Risks or open questions:
  - Impact: leak persists via an unlisted path (e.g. other tests spawning gpg).
  - Early warning / validation: count grows during suite.
  - Mitigation: grep for remaining gpg call sites and extend.
- Canonical components/API contracts touched: none.
