Cycle: v3/publish_keys_direct; Mode: one-shot; Index: [plans](../README.md)
Debug mode: enabled
Effort study: railspike-effort / R1 / P2; Ledger: [cycle](../effort_ledger/publish_keys_direct.md)
Extended estimates: enabled

**Cycle estimate: 0.30**

Status: bounded implementation locally complete; maintainer review/deployment acceptance pending.
Original request: "this is too much", "please again review how other commands work", then "please make the change" after the recommendation to publish by default and preview with --dry-run.
Authorization: 2026-11-23 UTC session authorizes that bounded local change. No live key publication, external post/reply, deployment, push, merge or PR. Existing user's appreciation of one-shot confirms mode, not additional scope.
Parents: [CLI conventions](../publish_keys_cli_conventions/publish_keys_cli_conventions_one_shot.md), [original publisher](../publish_local_public_keys/publish_local_public_keys_step4_implementation_summary.md). Separate follow-up cycle; earlier histories remain intact.
Branch and start: fix/publish-keys-direct from 85cf213d0 in the existing /home/wsl/v3-publish-local-public-keys checkout. Previous branches are local/unmerged; no new worktree or branch reuse. ~/v3 remains unchanged.
Planning commit: 857f25d1a (plan/index only, confirmed before implementation).
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

Outcome: simple publication/dry-run implemented and locally verified; mandatory plan files and fingerprint copying removed.
Maintainer comfortable-fit judgment/acceptance date: pending.
Measured active work: unknown.
Commit/merge/publication: planning 857f25d1a; implementation reference pending in the completion commit containing this record. No push/PR/merge/deploy or live key publication.

Attribution: Codex, 2026-11-23 UTC.

2026-11-23: Implemented default publication and --dry-run with optional normalized/deduplicated full fingerprints. Removed saved-plan IO/parser and mandatory selection. Legacy --plan/--publish reject with migration guidance before requests. Complete initial input validation remains before network; each pending key is reinspected against its in-memory source hash immediately before registration. Revalidation targets one identity to avoid quadratic GPG inspection across the full inventory. This is the recorded simplification, not new API/dependency work.

2026-11-23: Focused PublishKeysCommandTest/PublicKeyPublisherTest/PublicKeyPublishHttpTest/InstanceSourceResolverTest/ScriptsIndexTest: 25/25 passed. Added unsafe-unselected-source regression after smoke began; final publisher/command focused rerun: 16/16 passed. Covers default/all/optional selection, dry-run, local and remote mixed refusal, invalid/legacy flags, source-byte mutation during preflight, disguised secret packets, uncertain responses, concurrent acceptance and duplicate-free rerun. Actual API fixture verifies unapproved registration and unchanged dry-run/rerun HEAD.

2026-11-23: Replayed ordinary commands from the actual user checkout with its two real local public identities against an isolated localhost fixture. --dry-run reported refused/missing with zero POSTs; no-option publication reported refused/published with one POST; same-command rerun reported refused/existing and still one total POST. Expected exit 1 in all three because the local guest name remains mismatched. No live instance was contacted and no local source was redirected or modified. Source path visible throughout. This replay establishes the reported interaction, not production acceptance.

## Closeout

**Retrospective cycle scope assessment: 0.30**

2026-11-23: Whole-cycle assessment made separately from the retained entry projection 0.30, scope v1/R1/P2, same exposed Codex rater; no fabricated intake, stage sum, measured labor or acceptance. R1 references verified on entry. Measured work remains unknown; maintainer fit/acceptance date pending.

Verification passed: initial focused 25/25; final publisher/command focused 16/16 after adding the unsafe unselected-source test. Smoke plus the five named affected classes: 511 run, 507 passed, four parent-confirmed baseline failures. All selected/new tests passed. Baseline failures: AgentPostingApiTest::testSignedControlDiagnosticsBeforeGenericParserFailure; AgentPostingApiTest::testRulesErrorsAndLegacyCompatibility; SingleRequestHttpTest::testUnicodeRoundTripAndSymbolRejection; SignedPostBundleValidatorTest::testStrictSubsetAndExactBytes. Parent cycle reproduced these on untouched 8b00a78be with this environment's Unicode/PCRE limitation. Full suite not run by host policy.

Verification passed: PHP syntax of changed PHP, bash -n v3, CLI help, scoped docs/concept source paths and git diff --check; feedback check 357 items, zero problems, 36 existing source-review notices, regenerated index unchanged. Actual named checkout's source/default publication/dry-run/rerun condition replayed against isolated HTTP as recorded above. No browser/theme/icon, schema/migration or dependency changes; those checks inapplicable. No live production registration/deployment check performed or claimed.

Final diff review: reused canonical collection and destination transport, removed plan serialization instead of replacing it with hidden files or extra confirmation. Kept initial full public-only validation, source/name/retirement checks, exact response classification and read-back. No new API, source default, approval transfer or unrelated file. Old preview options reject before requests, preventing an old --plan command from unexpectedly publishing after the default changes. Current CLI reference/help/concept use direct publication; historical parent artifacts remain preserved.

Next unaided action: from /home/wsl/v3-publish-local-public-keys run ./v3 publish-keys zenmemes.com to publish, or add --dry-run to check first. Repository shows this checkout's data source; use --repository-root for another source. Retry the same command after resolving partial/unknown outcomes; local name mismatches remain refused and visible while eligible keys proceed. Broader reverse import, GUI, production acceptance and maintainer deployment remain separate.

Attribution: Codex, 2026-11-23 UTC.
