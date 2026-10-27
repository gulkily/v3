> **Feature plan:** [Step 1](./unsigned_attribution_step1_solution_assessment.md) · [Step 2](./unsigned_attribution_step2_feature_description.md) · [Step 3](./unsigned_attribution_step3_development_plan.md) · [Step 4](./unsigned_attribution_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/unsigned_attribution · [Host ledger](../effort_estimation_trial.md) · Standing enrollment P2

Branch: `feat/unsigned-attribution` · Planning commit: `53e70490`

## Effort snapshot

- Technical closeout — 2026-10-26 after Stage 8; same study, cycle, scope v6, R1/P2 and exposed single rater (has seen the whole implementation). Projection **about 1.0 reaffirmed**; retrospective scope assessment **about 1.05**: delivered scope matches v6, plus a Stage 3 label gap fixed in Stage 4, the Word97 mark contrast fix and the schema renumbering on merge. Measured effort unknown. Maintainer acceptance pending; no accepted-count change.
- Step 4 entry — 2026-10-23; same study, cycle, scope v4, R1/P2 and rater. **About 0.95 reaffirmed** from Step 3; recorded after the planning commit and before implementation.
- Step 4 re-entry — 2026-10-23; same study, cycle, R1/P2 and rater; scope v6 after Approved Step 2 (revision 3) and Approved Step 3 (revision 2). **About 1.0 reaffirmed**; Stage 1 kept, later stages renumbered.

## Stage 1 - Measure the impact

- Changes:
  - `scripts/audit_post_signatures.php` also scans thread labels and post reactions with the existing `ReactionSignature::status` check and reports `reaction_verified`, `reaction_unsigned_named`, `reaction_invalid`, `reaction_anonymous` and `scored_label_unsigned_named` (unsigned labels naming an identity that include a scored tag; an upper bound, since author approval is not checked). Exit status still reflects posts only.
  - `tests/PostSignatureAuditCommandTest.php`: reaction counting test.
- Verification:
  - `php tests/run.php PostSignatureAuditCommandTest`: 2 passed.
  - zenmemes: downloaded the public backup `https://zenmemes.com/downloads/repository.tar.gz` (read-only GET; repository head `5b98332a`, 2026-10-23T07:21:09-07:00) into scratch space and ran the audit.
- Results (zenmemes, before):
  - Posts: 1,680 total; 666 signed and valid; 884 name an identity without a signature; 130 anonymous; 0 invalid; 0 unknown key.
  - Unsigned named posts by username: `ilyag` 589 (across several keys), `reply-agent` 215, then `chrome` 11, `guest` 9 and 28 other usernames with 7 or fewer each (32 usernames in all). (Correction: the Stage 1 commit said 515 for `ilyag`; per-username totals were rechecked after that commit.)
  - Unsigned named posts by month: April 158, May 226, June 272, July 126, August 51, September 37, October 13. Signed posts start in August (157, 147, 362).
  - Votes: 374 total; 17 signed; 357 name an identity without a signature, 174 of them with a scored tag; 0 anonymous; 0 invalid. Unsigned votes continue every month, 41 in October.
- Notes:
  - With the planned rule, about 57% of attributed posts and 95% of votes would become unverified, nearly all from the instance's own long-standing identities and the reply agent rather than impersonation.
  - Review gate: the maintainer reviews these counts before Stage 2.

## Stage 2 - The flag and its cutoff

- Changes:
  - `FORUM_VERIFIED_ATTRIBUTION` flag (Verified attribution; default off, site-mutable, Access group). It changes no counts yet; Stages 3–5 add the rule in the same release.
  - The cutoff comes from signed records, resolved by `Security/AttributionCutoff`: the latest operator-signed move in `records/attribution-cutoffs/`, else the `Created-At` of the earliest operator-signed feature flag change that turned the flag on, else none (everything counts). Off/on keeps it; two concurrent switch-ons resolve to the earlier.
  - Moving the cutoff: `/api/prepare_attribution_cutoff` and `/api/finalize_attribution_cutoff` (operator, browser-signed like flag changes); future cutoffs refused. Spec: `docs/specs/attribution_cutoff_record_v1.md`; parser, path, source-path and import exclusion follow the feature flag change records.
  - Feature Flags page: the cutoff beside the flag ("Cutoff: … UTC") and, for operators, a "Move to (UTC)" field with a "Move cutoff" button. Shown once a cutoff exists or the flag is on. Flag finalize responses include `attribution_cutoff_at`, so the first switch-on shows the cutoff without reloading.
- Deviation from Step 3 (implementation detail, same requirement): Step 3 named a server-written `records/instance/attribution-cutoff.txt`. Flag changes turned out to be operator-signed records with a server `Created-At`, so the automatic cutoff is taken from that signed switch-on record, and only moves need a new record family. This keeps the cutoff signed rather than adding an unsigned server write.
- Verification:
  - `php tests/run.php WriteApiSmokeTest::testVerifiedAttributionCutoffIsSetBySwitchOnKeptByToggleAndMovable`: real signed switch-on, off/on, move, future refusal, unauthorized refusal, commit contents, page rendering, unsigned move ignored.
  - `CanonicalRecordParsersTest` 48/48, `FeatureFlagEvaluatorTest` 18/18 (registry list updated), `FeatureFlagsBehaviorTest`, `FeatureFlagChangePageTest`, `ContentImportPlannerTest`, `PrivateMessageFeatureFlagTest`, `ApiRouteCatalogTest` pass.
  - `WriteApiSmokeTest`: 186 of 188 on the first run. `testHistoricalReactionSignaturesAreVerifiedOnRebuildAndImport` fails identically with the Stage 2 changes stashed (pre-existing). `testUnicodeAuthoredTextSurvivesWriteRenderRssAndStaticArtifacts` failed because a plan doc was edited during its docs build, and passed on rerun.
  - Browser: `node tests/browser/attribution_cutoff.mjs` (Chromium, production template fixture): display, move success and failure (entry and cutoff kept), first switch-on revealing the cutoff, read-only viewer without the form, no-JS display; Light, Word97, Chicago and Midnight at 375 and 1150 px without overflow or clipped controls; screenshots reviewed.
- Notes and limitations:
  - Cutoff moves add no Activity row: a rebuild replays only feature flag changes, so an incremental-only row would break parity. The signed record is visible in the source browser.
  - Layout: the move field and button stack full width at desktop; usable, could be compacted later.
  - Moving the cutoff only advances read-model metadata for now; Stage 3 recomputes verified totals on a cutoff change.

## Stage 3 - Verified vote totals

- Changes:
  - Schema 26: threads gain `claimed_score_total` and `verified_score_total`; posts gain claimed and verified `post_score_total` and `approved_flag_count`. Claimed is today's rule. Verified counts a vote naming an approved member only when its record is signed by that member, or was created (server `Created-At`, never `Action-At`) before the cutoff; with no cutoff everything counts. Imported score seeds count in both.
  - `ReadModel/VerifiedAttribution`: the existing effective columns (`score_total`, `post_score_total`, `approved_flag_count`, `is_hidden`, `hidden_reason`) copy one set, chosen by the flag. Every existing query, page, API response and offline snapshot keeps reading the same columns. Metadata records the set in use and the cutoff the totals were derived with.
  - Builder and incremental updater compute both sets with separate per-member deduplication (a later signed vote still counts when an earlier unsigned one by the same name did not).
  - Flag toggle: swaps the effective columns in the same transaction, no rebuild. The first switch-on (cutoff appears) and cutoff moves rebuild, so totals match a full rebuild exactly. Startup swaps in place when an environment override changed the flag.
  - Docs: `read_model_schema_v1.md` (columns, metadata keys, verified attribution section).
- Judgment call for review: approved-member flags also hide flagged reply-agent posts (`approved_flag_count`). The verified rule applies there too, so an unsigned flag naming a member after the cutoff no longer hides a reply when the flag is on. Step 2 names scores, post counts and profiles; this treats hiding as part of the approved score.
- Verification:
  - `WriteApiSmokeTest::testVerifiedTotalsAgreeAcrossRebuildIncrementalToggleCutoffMoveAndEnvironment`: unsigned like and flag before the cutoff count; switch-on changes no counts; after the cutoff an unsigned upvote is claimed only and a signed downvote counts; incremental equals a full rebuild after each step; toggling off and on swaps without a rebuild and keeps the cutoff; moving the cutoff before the first votes leaves only the signed vote verified; an environment override applies on the next request.
  - `LocalAppSmokeTest::testVersion25StartupRebuildsBeforeServingVerifiedTotals`: a version 25 cache missing the new column is rebuilt at startup before the thread page is served.
  - `./v3 rebuild` on the zenmemes backup copy (`5b98332a`): 1,680 posts, 881 threads; compared with a rebuild by the pre-Stage-3 code, 0 threads differ in score or vote count and 0 posts differ in score, flag count or hidden state; claimed equals verified everywhere (no cutoff); 3.8 s warm against 5.4 s before.
  - 41 read-model, scoring, offline snapshot, import, status and flag suites: all pass except 7 tests that fail identically on the Stage 2 commit (pre-existing): `ApprovalSeedCommandTest::testApprovalIsEffectiveForExistingAuthenticatedMemberOnNextRequest`, two `ReadModelProvenanceTest` broken-history tests, `ReadModelBuilderTimingTest::testProgressSeparatesParsingDerivationAndWritingWithBoundedCounts`, two `LocalAppSmokeTest` static-build progress tests. Two `ImportInstanceCommandTest` tests failed during the batch while plan docs were being edited and passed on rerun.
  - `WriteApiSmokeTest` parity test and Stage 2 cutoff test pass.

## Stage 4 - Posts and profiles by the same rule

- Changes (same schema version 26, not yet released):
  - Posts gain `attribution_verified` (signed by the named identity, created before the cutoff, anonymous, or a removed post) and the effective `attribution_counted`. With the flag on, an unverified post loses its profile link (`author_profile_slug` NULL; the claimed name stays in `author_label`) and drops out of profile post lists.
  - Profiles keep claimed and verified post and thread counts; the effective `post_count` and `thread_count` follow the flag. A bootstrap post always counts for its owner.
  - Labels written in a post's body follow that post: when the label's content-bound source is proven (`LabelOwnership`), it counts when the post would. This fixes a Stage 3 gap: body labels are server-written unsigned records, so labels from signed posts would otherwise have turned unverified after the cutoff.
  - Builder, incremental post writes, identity linking and profile refresh all apply the rule; the flag swap updates posts and profiles in place.
  - Docs: `read_model_schema_v1.md` (post and profile columns, body-label rule).
- Verification:
  - Parity test extended: after the cutoff a signed reply and its `#upvote` count; an unsigned reply naming the member is unlinked, uncounted, absent from the profile list, and its `#downvote` is claimed only; turning the flag off links and counts it again; every step equals a full rebuild.
  - zenmemes backup copy: compared with the pre-Stage-3 rebuild, 0 threads, 0 posts (score, flags, hidden, profile link) and 0 profiles (post and thread counts) differ; no post is unverified without a cutoff.

## Stage 5 - Show unverified items

- Changes:
  - The shared author renderer shows an uncounted item's claimed name unlinked, followed by `(unsigned)`, in the same style as the existing `(unapproved)` mark. It covers post, thread, quote and board cards, Forte views, profile lists and Activity. Counted items, including unsigned items before the cutoff, look as they do today.
  - Wording: Step 2 said to reuse the existing signature status wording. The existing word is "unsigned" (record details show "Record signature: legacy unsigned"); no "unverified" wording existed, so none was added.
  - Activity rows gain `attribution_verified` and `attribution_counted` (same schema 26): post rows follow the post; label and reaction rows follow their record (body labels follow their source post). Builder and incremental post, approval, label and reaction writes mark them; the flag swap updates them.
  - Word97: author marks inside the blue post title bar were gray on blue and nearly invisible; they now use light text. This also fixes the existing `(unapproved)` mark there.
  - Docs: `read_model_schema_v1.md` activity columns.
- Verification:
  - Parity test extended: with the flag on, the thread page marks only the unverified reply and Activity shows the mark; activity markers equal a full rebuild; with the flag off no mark renders.
  - Browser (Chromium, local server on a fixture read model with one reply marked uncounted): the thread card and Activity in Light, Word97, Chicago and Midnight at 375 and 1150 px show `(unsigned)` visibly, the name unlinked, no page overflow or clipped mark; screenshots reviewed before and after the Word97 fix.
- Limitations:
  - The public offline snapshot copies the cleared profile link but has no `(unsigned)` mark.
  - Same-author collapsing and the reply-agent card style still key on the author name, so an unsigned post naming the reply agent's identity gets agent styling, and one naming a member collapses with that member's posts. Recorded for the follow-up.

## Stage 6 - Tell unsigned writers

- Changes:
  - `create_thread`, `create_quote`, `create_reply`, `apply_thread_tag` and `apply_post_tag`: when the flag is on, a cutoff exists, and the write names an identity without a signature, the `key=value` response adds `attribution=unsigned` and an `attribution_notice` saying it does not count and how to sign (prepare and `create_prepared_post` for posts; `canonical_record` and `detached_signature` for votes). Anonymous and signed writes get nothing new.
  - Docs corrected: `/llms.txt`, the `/api/` catalog and `agent_api.md` said the unsigned create routes were "anonymous only"; the code has always accepted `author_identity_id` there and attributed the post. They now say the routes are unsigned and anonymous unless an identity is named, and `/llms.txt` reports an "Attribution" setting. `agent_api.md` gains an Attribution section and documents the vote record format, since votes have no prepare route.
- Verification:
  - Parity test extended: with the flag on, a named `create_reply` returns the notice, an anonymous one does not, an unsigned thread tag returns it, a signed post tag does not.
  - `LlmsTxtRendererTest` (Attribution fact follows the flag) and `ApiRouteCatalogTest` updated and passing.

## Stage 7 - The reply agent signs

- Changes:
  - `AgentIdentityService::signAsReplyAgent` signs a record with the agent's stored private key in a temporary GPG home (removed afterwards). A missing key throws "Reply-agent private key is missing; replies are not posted unsigned."; the task fails and can be retried.
  - `LocalWriteService::createReply` takes an optional signer. The writer builds the record, gets the detached signature, verifies it against the author's public key, and commits the `.asc` with the post. Agent replies and scripture expansions pass the agent signer; other callers are unchanged. Expansion recovery also commits a left-over `.asc`.
  - Applies regardless of the flag, so agent replies count as verified once an instance turns it on.
- Verification:
  - `AgentIdentityServiceTest::testReplyAgentSignsWithItsPrivateKeyAndRefusesWithoutIt`: signature verifies against the published key; temporary GPG home removed; missing key refused.
  - `WriteApiSmokeTest::testGenerateAgentReplyCreatesCanonicalReplyAndIsIdempotent`: the posted agent reply has status `verified` and its `.asc` is in the commit.
  - Scripture, agent reply and agent identity suites pass except `AgentIdentityServiceTest::testReplyAgentBootstrapCreatesApprovedIdentityAndPrivateKeyOutsidePublicArtifacts`, which fails identically before Stage 2 (pre-existing).
- Release check: the production reply agent's private key must exist in its private key directory; without it, agent replies fail instead of posting unsigned.

## Stage 8 - Acceptance and release evidence

- Changes:
  - Merged `origin/main` (`151905f1`) in `0888609c`. Main had moved the read model to schema 30, so verified attribution is now **schema 31** (was 26 on this branch); `read_model_schema_v1.md` and the prior-version startup test (`testVersion30StartupRebuildsBeforeServingVerifiedTotals`) follow. Main's changed-rows-only profile refresh now also sets and compares the claimed and verified counts.
- Verification:
  - Full suite on the merged tree: 1,544 passed, 9 failed. Full suite on `origin/main` (`151905f1`): 1,540 passed, 7 failed. The 7 fail on both: `ApprovalSeedCommandTest::testApprovalIsEffectiveForExistingAuthenticatedMemberOnNextRequest`, `AgentIdentityServiceTest::testReplyAgentBootstrapCreatesApprovedIdentityAndPrivateKeyOutsidePublicArtifacts`, `LocalAppSmokeTest::testThreadMergesSameAuthorQuickRepliesIntoContinuations`, `LocalAppSmokeTest::testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`, `PlatformDocsStaticTest::testStaticReleaseIncludesCompleteInventoryAndSafeAssets`, `ReadModelBuilderTimingTest::testProgressSeparatesParsingDerivationAndWritingWithBoundedCounts`, `WriteApiSmokeTest::testHistoricalReactionSignaturesAreVerifiedOnRebuildAndImport`. The other 2 are intermittent: `ImportInstanceCommandTest::testSourceErrorDetailIsBoundedAndSafe` passed on both reruns; `FrontendTargetResolverTest::testHungCandidateCannotEstablishUniquenessAndBudgetIsShared` (a wall-clock budget assertion in code this branch does not touch) failed 2 of 8 runs here and 0 of 8 on `main`.
  - zenmemes backup copy (`5b98332a`), rebuilt at schema 31 and compared with the pre-Stage-3 rebuild: flag off, and flag on with a cutoff of now (2026-10-26T23:00Z), each give 0 differing threads (score, vote count), posts (score, flags, hidden, profile link) and profiles (post and thread counts), and no uncounted post or activity row. The cutoff was supplied by a test-only override in a scratch copy of the code, since an operator-signed switch-on cannot be made on a copy; nothing was written to zenmemes.
  - Same copy with a cutoff of 2026-08-01 (illustration, not a release setting): 102 posts and 254 activity rows uncounted, 42 threads, 151 posts and 4 profiles change. Every uncounted post is one the Stage 1 audit lists as missing a signature (62 `reply-agent`, 26 `ilyag`). That rebuild took 28 s against 2.5–4 s for the others.
- Outstanding before release:
  - Maintainer review of the Stage 1 counts and of the Stage 3 judgment call (approved-member flags hiding reply-agent posts follow the verified rule).
  - The production reply agent's private key must exist (see `todo.txt`).

## Enhancement Requests

- **Request:** keep existing unsigned items counting with a cutoff (maintainer, at the Stage 1 review gate, 2026-10-23). / **Disposition:** changed scope; implementation paused after Stage 1; returned to Step 2 (revision 2). / **Approval status:** superseded by the next request's approvals. / **Outcome:** delivered (Stages 2–5).
- **Request:** keep today's behavior as the default; write the cutoff automatically and show it to operators (maintainer, 2026-10-23). / **Disposition:** changed scope; Step 2 revision 3 (default-off verified attribution flag; accept-unsigned-content flag to its own cycle) and Step 3 revision 2. / **Approval status:** Approved Step 2 (revision 3) and Approved Step 3 (revision 2). / **Outcome:** delivered (Stages 2–7); maintainer acceptance pending.
