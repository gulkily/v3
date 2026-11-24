Cycle: v3/publish_keys_cli_conventions; Mode: one-shot; Index: [plans](../README.md)
Debug mode: enabled
Effort study: railspike-effort / R1 / P2; Ledger: [cycle](../effort_ledger/publish_keys_cli_conventions.md)
Extended estimates: enabled

**Cycle estimate: 0.20**

Status: bounded implementation locally verified; awaiting maintainer review/deployment acceptance.

Original request: "please make a post about this on zenmemes and then make the fixes and also add the one missing guideline we had". User reported hostname rejection and misleading source/output/help after reviewing the original publisher.
Authorization: 2026-11-22 America/New_York / 2026-11-23 UTC session request authorizes the public post, local fixes and guideline update. No merge, deployment or key publication. No pasted external request invokes the thread-to-PR procedure; push/PR and further public replies are outside this bounded task.
Parent: [original publisher](../publish_local_public_keys/publish_local_public_keys_step4_implementation_summary.md). Separate follow-up cycle, not a rewrite of its completed records or another observation of that scope.
Starting revision and branch: 245d1d072; fix/publish-keys-cli-conventions in /home/wsl/v3-publish-local-public-keys. New branch from the unmerged publisher, retaining its six commits; origin/main fetched at 8f106c3ce does not contain the publisher. No new worktree. Original ~/v3 remains unchanged.
Planning commit: 515a8bcc5 (plan and index only, confirmed before implementation).

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

Outcome: lesson posted and checked; hostname parity, source visibility, help/errors and missing guideline implemented and locally verified.
Maintainer comfortable-fit judgment and acceptance date: pending.
Measured active work: unknown.
Commit/merge/publication: planning 515a8bcc5; implementation reference pending in the completion commit that contains this update. Public lesson accepted; no push/PR/merge/deploy.

Attribution: Codex, 2026-11-23 UTC.

2026-11-23: No clarification changed the missing-guideline interpretation after a reasonable reply interval; explicit hostname support documented. CONTRIBUTING now also makes precedent comparison, repository visibility and reported-environment verification concrete. New code delegates normalization to InstanceSourceResolver, prints Repository before profile checks, improves help and wraps unsafe destination errors without echoing credentials. Existing publication/hash/retry logic is unchanged. Four meaningful CLI tests cover parity with import inputs, invalid targets, help and two distinct selected sources.

2026-11-23: Focused command ./v3 test PublishKeysCommandTest PublicKeyPublisherTest PublicKeyPublishHttpTest InstanceSourceResolverTest ScriptsIndexTest: 23/23 passed. Syntax, bash syntax and diff checks passed. Manual ordinary CLI replay in the actual user checkout against an isolated HTTP destination displayed Repository /home/wsl/v3-publish-local-public-keys/state/local_repository and two identities (exit 1 for the expected name refusal); explicit fixture source displayed its separate path and one identity (exit 0). Zero preview POSTs. No silent switch to ~/v3. The hostname normalization was compared without contacting production; no live key-publication acceptance claim.

## Closeout verification

**Retrospective cycle scope assessment: 0.20**

2026-11-23: Same v1/R1/P2, exposed Codex rater and independent whole-cycle assessment; projection 0.20 retained. Missing intake remains missing. Measured work unknown; no extra stage observations. Maintainer comfortable-fit judgment/acceptance date pending.

Passed: focused 23/23; smoke plus PublishKeysCommandTest/PublicKeyPublisherTest/PublicKeyPublishHttpTest/InstanceSourceResolverTest/ScriptsIndexTest 509 run, 505 passed, four failures. All new and selected tests passed. Failed baseline cases retained: AgentPostingApiTest::testSignedControlDiagnosticsBeforeGenericParserFailure, AgentPostingApiTest::testRulesErrorsAndLegacyCompatibility, SingleRequestHttpTest::testUnicodeRoundTripAndSymbolRejection, SignedPostBundleValidatorTest::testStrictSubsetAndExactBytes. Same Unicode/PCRE failures reproduced on untouched 8b00a78be in the parent cycle; no fix attempted outside scope. Full suite not run by host policy.

Passed: actual checkout/default-source and explicit-source replay against isolated HTTP, zero preview POSTs; Quill post read-back; PHP syntax for changed PHP, bash -n v3, feature links, concept paths and git diff --check. Feedback check 357 items, zero problems, 36 existing source-review notices; index regenerated. No new dependencies/schema. Runtime authorization and publication checks remain in the unchanged publisher; actual registration/retry and restricted-instance tests pass.

Inapplicable: browser/theme/nav and migration verification, because no such surfaces/contracts changed. Production key publication/deployment are outside this task's authority, not established by fixture tests. The reported bare production hostname is verified through normalization parity and ordinary local HTTP entry; it was not rerun against the live instance during fix verification. Original ~/v3 is unchanged; the selected worktree's two-record default is now visible, not silently redirected.

Final diff review: changes remain within the recorded CLI, tests, guideline/docs and feedback/record scope. Shared resolver reused; no new URL parser, payload, API or publisher behavior duplicated. Canonical human-readable output remains Key: value. Public post contains the observed lesson and announced fixes, not secret keys, local private records or claims of production deployment.

Unaided next action: in /home/wsl/v3-publish-local-public-keys run ./v3 publish-keys --help, then choose a destination and inspect Repository before reviewing key states. To use the usual local source, pass --repository-root=/home/wsl/v3/state/local_repository explicitly. Default is preview; publish still requires a reviewed plan and full selected fingerprints. Follow-ups: maintainer review/deployment and original broader reverse import remain separate; no GUI or new automation format added.

Attribution: Codex, 2026-11-23 UTC.
