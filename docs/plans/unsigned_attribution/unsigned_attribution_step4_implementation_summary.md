> **Feature plan:** [Step 1](./unsigned_attribution_step1_solution_assessment.md) · [Step 2](./unsigned_attribution_step2_feature_description.md) · [Step 3](./unsigned_attribution_step3_development_plan.md) · [Step 4](./unsigned_attribution_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/unsigned_attribution · [Host ledger](../effort_estimation_trial.md) · Standing enrollment P2

Branch: `feat/unsigned-attribution` · Planning commit: `53e70490`

## Effort snapshot

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

## Enhancement Requests

- **Request:** keep existing unsigned items counting with a cutoff (maintainer, at the Stage 1 review gate, 2026-10-23). / **Disposition:** changed scope; implementation paused after Stage 1; returned to Step 2 (revision 2). / **Approval status:** pending renewed Approved Step 2 and Approved Step 3. / **Outcome:** pending.
- **Request:** keep today's behavior as the default; write the cutoff automatically and show it to operators (maintainer, 2026-10-23). / **Disposition:** changed scope; Step 2 revision 3 (default-off verified attribution flag; accept-unsigned-content flag to its own cycle) and Step 3 revision 2. / **Approval status:** Approved Step 2 (revision 3) and Approved Step 3 (revision 2). / **Outcome:** pending.
