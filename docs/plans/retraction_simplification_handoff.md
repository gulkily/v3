

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
