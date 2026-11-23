Cycle: v3/publish_keys_direct; Mode: one-shot; Index: [plans](../README.md)
Debug mode: enabled
Effort study: railspike-effort / R1 / P2; Ledger: [cycle](../effort_ledger/publish_keys_direct.md)
Extended estimates: enabled

**Cycle estimate: 0.30**

Status: planned; next action: planning commit, then implementation.
Original request: "this is too much", "please again review how other commands work", then "please make the change" after the recommendation to publish by default and preview with --dry-run.
Authorization: 2026-11-23 UTC session authorizes that bounded local change. No live key publication, external post/reply, deployment, push, merge or PR. Existing user's appreciation of one-shot confirms mode, not additional scope.
Parents: [CLI conventions](../publish_keys_cli_conventions/publish_keys_cli_conventions_one_shot.md), [original publisher](../publish_local_public_keys/publish_local_public_keys_step4_implementation_summary.md). Separate follow-up cycle; earlier histories remain intact.
Branch and start: fix/publish-keys-direct from 85cf213d0 in the existing /home/wsl/v3-publish-local-public-keys checkout. Previous branches are local/unmerged; no new worktree or branch reuse. ~/v3 remains unchanged.
Planning commit: pending.
Feedback: PF-20261122-B04, matching publisher CLI conventions; extend its acceptance evidence for the workflow simplification without closing broader reverse import F03.

## Outcome and precedent

`./v3 publish-keys SITE` publishes eligible missing public identities. `--dry-run` previews without registration; repeated --key optionally limits either operation. No plan file, confirmation step or mandatory fingerprint copying. Old --plan/--publish options fail with migration guidance before requests; they must never silently turn an old preview invocation into publication.

Precedent: scripts/import_instance.php runs by default and previews with --dry-run. Reuse that meaning and the existing InstanceSourceResolver destination normalization. Reuse scripts/publish_public_keys.php, PublicKeyPublisher collection/name/retirement checks, PublicKeyPublishDestination transport/read-back and existing fixtures. Remove saved-plan serialization/parser from the publisher, use an in-memory source snapshot for immediate revalidation instead. Reuse existing actual FrontController API fixture; no new component, API, dependency, schema, browser interface or automation format.

Material risks: default publication now covers the entire eligible inventory, so reject private/escaped/malformed input and invalid explicit selections before network writes. Keep refused entries visible while eligible entries proceed, never treat unknown responses as missing, and revalidate source bytes/name/retirement before each POST. Avoid re-inspecting every key with GPG before every write; inspect the current key on revalidation while retaining complete initial inventory checks. Read back every attempt, never automatically retry POST, and skip accepted identities on a later command rerun. Destination/name/retirement behavior stays unchanged.

## Completion and verification

- Ordinary CLI without flags publishes on isolated fixtures; --dry-run issues GET only. Default publishes all eligible missing keys, optional full --key selects a subset, unknown selection aborts before writes. Show Repository/Destination/Mode and per-key outcomes.
- Rejected old flags produce actionable --dry-run guidance and zero requests. Empty/malformed keys fail before writes; normalize/deduplicate full fingerprints.
- Replay name/retirement/private/source-escape checks, mixed outcomes, concurrent registration, lost/error responses, source mutation during preflight and duplicate-free rerun. Actual isolated API proves unapproved registration, no-write dry-run and unchanged rerun HEAD.
- Update tests and run smoke plus PublishKeysCommandTest/PublicKeyPublisherTest/PublicKeyPublishHttpTest/InstanceSourceResolverTest/ScriptsIndexTest. No full suite. Retain parent-confirmed baseline Unicode failures honestly.
- Update v3 help, docs/reference/v3_cli.md, scripts/README.md, public-key publication concept and feedback/record/index. Verify docs links, syntax, diff and feedback check/index. UI/theme/icon and migration checks inapplicable: CLI only, no schema/dependencies. Live production key registration remains outside this authorization.
- Handoff provides the simple executable command and optional --dry-run in this exact checkout. No new mandatory workflow artifact replaces the removed one.

Concepts touched: [public-key publication](../../concepts/public_key_publication.md) changes from saved review to direct publication/dry-run; registration and approval separation used unchanged.

## Execution and study history

2026-11-23: Intake unavailable: prior review/parent implementation already exposed before this numeric checkpoint. Execution-entry whole-cycle projection 0.30, scope v1/R1/P2, exposed Codex rater, hashes verified. Covers direct orchestration, meaningful mutation/retry coverage, docs and ordinary CLI acceptance. Prior scores disclosed; no independent rating or measured labor. Extended estimates enabled; no artificial stages/work units.

Outcome: pending implementation.
Maintainer comfortable-fit judgment/acceptance date: pending.
Measured active work: unknown.
Commit/merge/publication: planning pending; no external publication/deployment authorized in this cycle.

Attribution: Codex, 2026-11-23 UTC.
