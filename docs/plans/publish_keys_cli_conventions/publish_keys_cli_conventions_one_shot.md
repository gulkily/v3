Cycle: v3/publish_keys_cli_conventions; Mode: one-shot; Index: [plans](../README.md)
Debug mode: enabled
Effort study: railspike-effort / R1 / P2; Ledger: [cycle](../effort_ledger/publish_keys_cli_conventions.md)
Extended estimates: enabled

**Cycle estimate: 0.20**

Status: planned; next permitted action: planning commit, then bounded implementation.

Original request: "please make a post about this on zenmemes and then make the fixes and also add the one missing guideline we had". User reported hostname rejection and misleading source/output/help after reviewing the original publisher.
Authorization: 2026-11-22 America/New_York / 2026-11-23 UTC session request authorizes the public post, local fixes and guideline update. No merge, deployment or key publication. No pasted external request invokes the thread-to-PR procedure; push/PR and further public replies are outside this bounded task.
Parent: [original publisher](../publish_local_public_keys/publish_local_public_keys_step4_implementation_summary.md). Separate follow-up cycle, not a rewrite of its completed records or another observation of that scope.
Starting revision and branch: 245d1d072; fix/publish-keys-cli-conventions in /home/wsl/v3-publish-local-public-keys. New branch from the unmerged publisher, retaining its six commits; origin/main fetched at 8f106c3ce does not contain the publisher. No new worktree. Original ~/v3 remains unchanged.
Planning commit: pending.

## Outcome, approach and risks

Accept known instance names, bare hostnames (HTTPS) and explicit HTTP(S) URLs using the existing import resolver. Show the resolved canonical source repository. Split top-level usage into preview/publish lines; include a concrete example, defaults and actionable destination errors in detailed help. Preserve Key: value output, preview-only default, explicit reviewed selection and source/destination binding. No new output protocol, API, dependency, schema or web UI.

The missing guideline is interpreted as explicit hostname support, the convention previously identified as unwritten. An optional clarification was sent; absent a correction, proceed with that interpretation after allowing time to reply. Add it under Operator CLI in CONTRIBUTING.md and make the existing precedent/verification requirement concrete. Do not silently choose another checkout's source; print the selected repository and document --repository-root instead.

Precedent and reuse: scripts/import_instance.php resolves with src/ForumRewrite/Import/InstanceSourceResolver.php; reuse it in PublicKeyPublishDestination rather than adding hostname parsing. scripts/status.php and scripts/purge_history.php print Repository; match that label. v3 and scripts/publish_public_keys.php remain the canonical help/entry points. Existing tests/Support/PublicKeyPublishFixture.php provides ordinary CLI plus isolated HTTP; extend its tests. Existing publisher keeps all public-only, plan/hash and retry safeguards. No new component needed.

Material risks: hostname normalization must retain URL validation and bind plans to the normalized destination; directly compare resolver behavior without live production calls. A separate checkout can use different records: print its real path and replay the user's no-argument/hostname/help errors locally, then demonstrate explicit-source preview using isolated fixtures. Do not change default source ownership or transmit private data. The authorized public post is separate from command acceptance and is verified by read-back.

## Completion and verification

- Ordinary ./v3 help presents two readable usage lines. Detailed help documents accepted destinations, source/timeout defaults and an example.
- Same hostname input as import resolves identically; unsafe destinations fail with destination wording before network access. Explicit HTTP fixture preview still works and performs no POST.
- Output identifies the selected source. Fixture checks prove default source in this worktree and an explicit source remain distinct; never infer the usual local repository automatically.
- Reviewed publication and rerun checks remain passing. Run smoke plus PublishKeysCommandTest, PublicKeyPublisherTest, PublicKeyPublishHttpTest, InstanceSourceResolverTest and ScriptsIndexTest. No full suite; retain known baseline Unicode failures.
- Update docs/reference/v3_cli.md, docs/concepts/public_key_publication.md, CONTRIBUTING.md, feedback item PF-20261122-B04 and feedback index; verify scoped links, syntax and diff. CLI-only: UI/themes/icons and migration checks inapplicable.
- Handoff identifies branch, checkout, checks and remaining live-publication acceptance. No product production request is needed for this fix.

Concepts touched: update [public-key publication](../../concepts/public_key_publication.md); operator progress used unchanged. Existing posting client/docs used unchanged.

## Execution and study history

2026-11-23T01:05:27Z: Intake estimate unavailable: implementation had already been inspected in the preceding review and this session before recording a numeric intake. Execution-entry projection 0.20, same exposed Codex rater, scope v1, R1/P2; frozen hashes verified against the original cycle. Whole-cycle judgment includes post, comparison, fixes, scoped verification and documentation; not measured labor. No invented earlier score or independent rating. Extended mode enabled; no artificial stages/work units.

2026-11-23T01:05:19Z: Authorized lesson posted through the latest served Quill using the existing local identity. Post: https://zenmemes.com/threads/thread-20261123010519-0ef90ca45c2f3939b60720450f26ef65 . The writing-audit skill was read and applied to the draft; no extra trouble-report post. Quill Status accepted and subsequent check Status found. Posting precedes fixes as requested. This publication is not deployment or key-publication acceptance.

Outcome: pending implementation.
Maintainer comfortable-fit judgment and acceptance date: pending.
Measured active work: unknown.
Commit/merge/publication: new local branch, planning commit pending; public lesson accepted; no push/PR/merge/deploy.

Attribution: Codex, 2026-11-23 UTC.
