# Retraction simplification: agent handoff

2026-10-24. User-directed coordination from the conversation in
`/home/wsl/agent/v3-codex`; review of this checkout at `2d91c8029` on
`fix/profile-registration-cost`, including its uncommitted work.

## User's intended direction

The user considers the current retraction feature overengineered and wants:

1. Delete the content from the canonical repository and commit immediately.
2. Later, sweep the content from Git history.

The user explicitly clarified: “We should be saving a record of the retraction,
but not this ledger type stuff.” Keep a durable retraction record; the proposed
shape is to commit it together with the content deletion. Delayed unavailability
is acceptable. Do not assume a requirement for immediate suppression of every
derived copy or continuous proof that no copy can reappear.

This is a direction and coordination note, not an implemented change or approval
to remove current protections, rewrite history, deploy, or start paused work.
Exact record fields, migration, derived-copy cleanup and history-purge mechanics
still need definition. The record should contain no deleted prose and remain
useful after the original content has been purged.

## What changes about ongoing work

- The user requested deletion of `retraction_sweep_diagnostics`. Its Step 1,
  Step 2 and debug log were deleted in `/home/wsl/agent/v3-codex`; its study
  entry was marked canceled with historical scores retained. Do not revive that
  plan or implement its structured evidence-diagnostics subsystem. The existing
  investigation report remains historical evidence, not an active work order.
- Registration indexing, targeted profile/activity updates, bounded approval
  work, and ordinary status/lock recovery improvements remain useful. Continue
  only within their existing authorization; this note does not start the paused
  registration checklist.
- Revisit REG-17, REG-20 and REG-F01/F02 before treating today's retraction
  checks/publication protections as permanent requirements. Preserve correct
  authorization and transactions; distinguish those from the stronger immediate
  retraction freshness policy the user is relaxing.
- Treat the retraction retrospective's corrective requirements as historical
  proposals requiring reassessment. In particular, do not expand provenance,
  evidence reconstruction, publication gating or sweep diagnostics simply to
  satisfy R02, R09 or R19 under the old contract.
- The recent high-traffic fixes still depend on current retraction evidence:
  `HighTrafficPublication` repeatedly captures/validates it, and
  `HighTrafficRenderInput::safetyIdentity()` explicitly permits ordinary content
  staleness but forbids retraction/absence staleness. These are likely
  simplification targets. Retain useful no-rebuild rendering and performance
  improvements; do not blindly revert the recovery commits.

## Findings behind the direction

Current browser retraction commits a signed suppression record while retaining
the original post. The current `RetractionSweep` cleans selected generated
artifacts and LLM exchanges; it explicitly excludes canonical files and history.
Neither implements the user's two steps as stated.

The separate `delete-record`/`SourceLabelRemoval` path already deletes and commits
content, but also has removal receipts, recovery journals and history-dependent
validation. Reuse useful behavior without carrying that entire design forward.
Simply deleting post files inside the existing retraction flow is insufficient:
some readers expect the original post or accepted removal evidence, and operator
acceptance is reconstructed from Git history.

The recommendation discussed was simplification in place, retaining the
retraction record, authorization and conversation structure. A new history purge
must be deliberate; deleting the diagnostics plan does not supply that feature.
Keep ordinary actionable deletion/commit/purge errors without rebuilding the
canceled evidence-diagnostics project.

No application changes, commits, tests, deployment or history rewrite were
performed as part of this handoff. No dependencies added. Existing unrelated
changes and `todo.txt` are untouched.


## 2026-10-25T19:15:34Z — Approved release-1 implementation available

The user approved Steps 1–3. Local implementation now lives on
`feature/retraction-simplification` in `/home/wsl/agent/v3-codex`, through
`471b8111` (planning `fd5c6849`, eight stage commits). Read that checkout's
`docs/plans/retraction_simplification/retraction_simplification_step4_implementation_summary.md`
for verification and limitations. This supersedes the earlier planning-only status,
not its historical findings. Nothing is merged, pushed or deployed by this handoff.

Author/operator acceptance deletes the post/signature and commits one JSON record
with preserved signed evidence and content-free structure. Legacy migration is
explicit (`retraction-sweep migrate`, then ordinary rebuild). Readers/imports use
accepted records without repeated Git authority reconstruction; read-model identity
is now 26. `PublicationGuard` is removed; ordinary serialization is retained as
`Support/PublicationLock`. Do not reintroduce retraction generations/gating in
registration or publication work. Sweep uses full repeatable scans and ordinary
task-queue status, without saved cursors/ledger. History purge remains a separate,
unimplemented release 2.

Operational consequence: generated artifacts also expire after five minutes while
accepted retractions exist, including clean copies; ordinary refresh jobs must keep
running and static-only deployments must rebuild after retirement. Unknown-provenance
legacy LLM payloads are conservatively scrubbed. See operator references before
integration. Live qdb.us recovery remains unverified; local QDB-derived migration,
rebuild and 18,931-artifact CLI sweep passed. Final focused regression suite 122/122;
full-suite inherited failures and environment assumptions are documented.

Reviewed this checkout through `d468c9059`; no newer Host/Write/Retraction commits
since `63314e6cf`. Its beta.4 upgrade and unrelated work are untouched. This note is
left uncommitted for the agent here; it does not authorize merging or deploying.
