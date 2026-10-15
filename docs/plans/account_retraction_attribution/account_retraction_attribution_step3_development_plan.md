> **Feature plan:** [Step 1](./account_retraction_attribution_step1_solution_assessment.md) · [Step 2](./account_retraction_attribution_step2_feature_description.md) · [Step 3](./account_retraction_attribution_step3_development_plan.md) · [Step 4](./account_retraction_attribution_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract
- Normal entry/outcome: existing operator post action → signed acceptance → consistent author wording for approved keys sharing a nonempty canonical username; all other attribution follows approved fallback rules.
- Recovery/release: existing retry and `./v3 rebuild` preserve wording, signatures, actual signer, operator classification, permissions, and suppression. Verify browser action, post views, and current/historical source 410 responses locally; deployment requires startup schema recovery and retained local canonical Git history, with no external service changes.

## Key Risks
- **High risk: unstable or untrusted evidence.** Current profiles cannot prove historical membership. Record a versioned account decision in the local acceptance commit, bound to the retraction bytes, actor, and target; read it only through existing local operator-acceptance checks. Missing/invalid evidence retains operator wording; imported claims never grant trust. Validate before dependent wiring.
- **High risk: schema/recovery divergence.** Keep display attribution separate from authorization class. Add nullable display-attribution fields to derived posts/threads, bump schema version, update the schema spec, and verify automatic prior-version recovery under CONTRIBUTING.md.
- Multiple data paths could disagree: inventory builder, queries, and source routes in Stage 1; use one resolver and test parity. No historical approval replay or current-profile backfill is planned.

## Stage 1
- Goal: deliver durable acceptance evidence for new operator retractions.
- Dependencies: approved Steps 1–2; create feature branch, organize/index plans, and commit approved planning artifacts before implementation.
- Expected changes: under the existing finalization lock, read fresh approved canonical account state and attach a versioned decision to the acceptance commit; preserve its subject and signed record. Reuse existing username normalization. Retry preserves the original decision.
- Verification approach: exercise actual finalization for same/different accounts, unapproved/empty names, and prepare-to-finalize approval changes; compare signed bytes and signer evidence.
- Risks or open questions: stale state could misattribute; validate account freshness against the accepted repository state before commit and conservatively use operator wording when evidence is unavailable.
- Canonical components/API contracts touched: LocalWriteService finalization and commit message; existing write API payloads unchanged.

## Stage 2
- Goal: make accepted attribution independently reproducible.
- Dependencies: Stage 1 evidence contract verified.
- Expected changes: shared resolver `acceptedAttribution(root, path, contents, record): string`; validate evidence against the same accepted commit used for operator authority. Carry a separate derived attribution on PostRetractionRecord without changing its signed format or classification.
- Verification approach: exact-key, absent/malformed/conflicting metadata, altered bytes, archive imports, later approval changes, and cache refresh after HEAD changes.
- Risks or open questions: evidence from different commits could be mixed; require one matching acceptance event and fail closed to operator wording.
- Canonical components/API contracts touched: RetractionAuthority, CanonicalRecordRepository, PostRetractionRecord; preserve existing suppression authority.

## Stage 3
- Goal: deliver consistent wording across post and source views.
- Dependencies: Stage 2 resolver verified.
- Expected changes: project separate attribution into posts/threads and every relevant query; extend RetractedPostNotice and source attribution lookup. Preserve effectivePostIds authorization semantics for other callers. Update schema version/spec and startup recovery coverage.
- Verification approach: prior-version startup, full rebuild, thread/post/quote/paned/authored views, current/historical source 410 parity, and unchanged operator audit classification.
- Risks or open questions: missing projections could silently fall back; enumerate all retraction_class consumers and verify every notice surface before proceeding.
- Canonical components/API contracts touched: ReadModelBuilder/Schema/Metadata, repositories and application queries, RetractedPosts, RetractedPostNotice, SourceFileController; reuse existing templates.

## Stage 4
- Goal: verify the complete operator flow and recovery before release.
- Dependencies: Stage 3 surface parity and schema recovery pass.
- Expected changes: extend existing browser/integration fixtures and document evidence retention, conservative legacy fallback, and verification in the implementation summary.
- Verification approach: browser same-account retraction and partial-update retry; normalization and no-direct-approval-link cases; unauthorized cross-key rejection; repeat after approval changes and `./v3 rebuild`; run affected PHP tests and existing retraction/privacy browser checks.
- Risks or open questions: retries could replace evidence or leak content; assert unchanged commit/signature on retry and retained suppression on all tested routes.
- Canonical components/API contracts touched: existing retraction browser/signing flow, write smoke tests, schema recovery tests; no new user action.

## Execution and review
- Each stage targets at most one hour; stop and revise the plan if a stage exceeds that bound. Complete verification and commit each stage with its summary update before beginning the next.
- Awaiting `Approved Step 3`; planning documents remain uncommitted. No implementation or feature branching before approval.
