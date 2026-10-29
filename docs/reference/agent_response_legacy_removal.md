# Agent-response legacy reader removal

New generated scripture responses and expansions use [named headers](../specs/agent_response_metadata_v1.md).
Historical signed posts retain their original `Agent-Response: {…}` header and
signature. There is no conversion command: rewriting metadata would invalidate
the signature. Compatibility remains enabled; this document does not authorize
retirement or deletion.

## Inspect a live repository

```sh
./v3 agent-reply format-readiness --repository-root=/path/repository
./v3 agent-reply format-readiness --repository-root=/path/repository --json
```

The command reads existing flat and dated canonical posts without initializing
storage, modifying records or rebuilding the database. Default output uses named
text fields; `--json` exposes the same facts for automation. Exit codes:

- `0`: the inspected live post tree contains no legacy headers or scan blockers.
- `2`: legacy records remain. Keep the compatible reader; inspect the listed IDs,
  paths and expansions that depend on legacy source/initial records.
- `1`: malformed/ambiguous records, duplicate IDs, missing expansion dependencies,
  missing/unreadable input, symlinks, size limits or observable changes prevent a
  reliable conclusion. Resolve the listed blockers and rerun.

Stop writers for a reliable snapshot. Two content-hash passes detect observable
changes, but do not provide an atomic snapshot or detect a change reverted between
reads. File reads use existing canonical size bounds. The scanner validates post
structure, not signature trust, visibility or semantic expansion eligibility; it
lists dependencies conservatively, including hidden/stale records. A legacy header
counts even when empty. Invalid legacy records remain blockers.

Git history and external archives are always **unknown**, even on exit `0`.
Readiness does not decide whether an old signed post may be discarded or whether
restoring an old backup must remain supported. Keep a compatible reader available
for any retained old records. Record a maintainer-approved archive/history and
retained-post policy before proposing actual removal. Do not silently rewrite,
resign or remove signed records to make this check pass.

## Future code removal boundary

After a separate retirement decision, remove
`src/ForumRewrite/Canonical/LegacyAgentResponseMetadata.php`. In
`AgentResponseMetadata::fromHeaders()`, replace only the legacy-header dispatch
with an explicit error directing callers to a compatible reader for signed old
records. Retain mixed/unknown-header rejection. Do not silently ignore a legacy
header after removal.

Keep `AgentResponseMetadata::decode()` and `validate()`: the former reads normalized
machine JSON in the read model and offline snapshots. Keep `postHash()`,
`validExpansion()`, deterministic IDs and signing/verification behavior. These are
not legacy canonical format dependencies. No read-model schema change is needed.

The compatibility fixtures in `AgentResponseMetadataTest` and the historical
methods in `WriteApiSmokeTest` will become explicit rejection or archived-reader
fixtures under the approved retirement policy. Keep the new-format transfer and
readiness/removal tests. `AgentResponseFormatReadinessTest` already blocks adapter
loading in fresh PHP processes, including import subprocesses, while exercising
normal request/fulfillment, signed generation/expansion, rebuild, content transfer
and offline export. This proves separation, not permission to retire support.

Run the relevant parser, generation, source/transfer, offline and browser acceptance
checks after any removal; update this guide and the format checklist with the
actual decision and supported restore procedure.
