> **Feature plan:** [Step 1](./account_key_revocation_step1_solution_assessment.md) · [Step 2](./account_key_revocation_step2_feature_description.md) · [Step 3](./account_key_revocation_step3_development_plan.md) · [Step 4](./account_key_revocation_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/account_key_revocation` — [P2 ledger](../effort_estimation_trial.md#cycle-v3account_key_revocation).

## Completion Contract

- Deliver approved Option A: User Access → exact-key signed review → terminal revocation → subsequent privileged-access denial → retained account/operator evidence and replacement-key recovery. Preserve username claims, content attribution and accepted sponsorships; reject root-seeded targets. Existing public access remains governed by site policy.
- Signed requests explicitly carry `revocation_cutoff` in UTC, defaulted to now during preparation and frozen for review/signing. Preserve it independently of submission, server acceptance and commit times. No custom/backdated/scheduled selector; access denial begins at accepted revocation and preserves previously accepted records.
- Include cancellation, changed-context refresh, duplicate/lost-response retry, committed-but-unrefreshed recovery and affected-device guidance. Rebuild/archive parity and phone/desktop acceptance are release gates; document backups/rollback limits. Local verification only; production deployment remains separate.
- Eight bounded stages, each targeted at ≤1 hour including focused verification; this is a sizing target, not measured effort. Stop and return to Step 2/3 if a risk cannot be resolved within this boundary or work requires more than eight stages/a day. Foundational stages are prerequisites, not independently releasable outcomes. One planning commit and a summary-bearing commit per completed stage; no new dependencies (delta zero). Keep `todo.txt` follow-ups separate from the planning-only commit.

## Key Risks

- **High risk:** revocation could cascade through sponsorship or rewrite past authority. Stage 1 fixtures must establish preserved legacy/direct/invitation grants before dependent work; current denial must not redefine historical acceptance.
- **High risk:** last-key removal or rename could release a username or enable takeover. Stage 1 validates retained claims and operator-only replacement; stop for rescoping if stable ownership requires a larger identity redesign.
- **High risk:** stale projections, concurrent writes, restored backups or older code could reopen access. Validate ordering and fail-closed behavior early; preserve committed security state, gate startup/rebuild and document unsafe rollback/recovery before release.

## Stage 1

- Goal: Resolve historical-authority and ownership risks with existing canonical evidence.
- Dependencies: Approved Step 3 and planning commit.
- Expected changes: Shared historical-grant/current-access contract; retained username-claim and replacement-authorization rules; signed revocation record contract with target, operator, explicit `revocation_cutoff`, separate acceptance time and exact review binding. Record the endpoint/write/cache inventory in Step 4.
- Verification approach: Focused fixtures for legacy/direct/invitation sponsorship, last-key removal, rename, replacement and same-time ordering; enumerate every privileged entry before denial wiring.
- Risks or open questions: Evidence must distinguish prior accepted grants from new attempts; invalid/unprovable authority must not be invented. Stop before Stage 2 if fixtures cannot demonstrate non-cascading preservation and retained ownership within scope.
- Canonical components/API contracts touched: ApprovalPolicy, ApprovalAcceptance, InvitationApprovalReplay, full/incremental approval derivation, username naming/claim paths; revocation specification.

## Stage 2

- Goal: Make revocation and retained claims durable across projections and restore.
- Dependencies: Stage 1 risk gate passed and committed.
- Expected changes: Canonical revocation validation and shared historical/current state derivation; preserve roots, sponsorship and claims; reject malformed evidence. Trusted archives retain revocations; remote imports cannot manufacture authority. Any changed core read-model table/index/query gets a schema-version bump and schema spec update.
- Verification approach: Full/incremental parity, signatures/cutoff tampering, distinct cutoff/acceptance/commit timestamps, legacy uncertainty, archive round-trip, terminal key state and last-key/rename fixtures; prior-schema startup rebuild before changed queries plus isolated `./v3 rebuild` if schema changes.
- Risks or open questions: Rebuild must not resurrect revoked keys or silently transfer claims; reserve new authority records from generic/content-import paths.
- Canonical components/API contracts touched: Canonical parsers/catalog, ReadModelBuilder, IncrementalReadModelUpdater, ReadModelMetadata/schema spec, archive validation.

## Stage 3

- Goal: Accept one authorized, target-bound revocation safely.
- Dependencies: Stage 2 committed.
- Expected changes: Prepare/finalize revocation through shared signing contracts; live operator/root-target/review checks under write lock; reviewed cutoff preserved unchanged without commit/submission-time fallback, deterministic acceptance order, idempotent retry and committed-outcome recovery. Reject revoked targets in reapproval, naming and invitation acceptance; require explicit operator approval for replacement into a retained claim.
- Verification approach: Operator/member/forged-hint matrix, root rejection, stale review, target substitution, concurrent grant/revoke, missing/malformed/substituted cutoff, delayed signing/acceptance, prepared-before-revoke requests, commit/refresh fault injection and exact retry.
- Risks or open questions: A failed response must not roll back a committed revocation; a permissive cross-account flag must not bypass terminal state or retained-claim recovery rules.
- Canonical components/API contracts touched: LocalWriteService, ApprovalPolicy, approval/invitation controller, API route catalog, protocol-write guards and invalidation.

## Stage 4

- Goal: Deny revoked-key privileges across sessions and signed requests.
- Dependencies: Stage 3 committed.
- Expected changes: Shared current-access guard for existing sessions, fresh authentication, direct signed writes, operator features and relevant private APIs; active recipient-key resolution excludes revoked keys. Preserve historical authored data and public browsing. Fail closed on stale/unavailable security state.
- Verification approach: Exercise the Stage 1 inventory, old cookies/fresh login, writes without cookies, messaging and pending preparations; stale-index/refresh failure and cache/static/offline boundaries.
- Risks or open questions: Identity proof is not access authority; no alternate route may restore privileges. Previously downloaded data and encrypted messages cannot be remotely erased.
- Canonical components/API contracts touched: Application/AuthApiController, write authorization, ApprovedUserKeyResolver, private-message access, front controller and cache/publication boundaries.

## Stage 5

- Goal: Operators can complete revocation from User Access.
- Dependencies: Stage 4 committed.
- Expected changes: Extend existing operator UI and signing/review controls; exact-key/account/cutoff review, consequence and last-key warning; cancellation, stale-review refresh, retry and committed-refresh recovery. No new navigation destination.
- Verification approach: Actual signed browser acceptance on phone/desktop; keyboard reachability, long keys, overflow, root exclusion and unauthenticated/member denial.
- Risks or open questions: Avoid suggesting same-key undo or hiding access-loss consequences; use existing controls and essential contextual copy only.
- Canonical components/API contracts touched: User Access/controller/templates, browser signing and shared review/recovery controls.

## Stage 6

- Goal: Preserve visible evidence and actionable affected-device recovery.
- Dependencies: Stage 5 committed.
- Expected changes: Extend shared classic/Forte account/profile history, profile API, operator Access history/security Activity and CLI audit with revoked state, explicit cutoff, separate acceptance time and actor/source evidence. Authentication/reconnection shows the revoked state and operator/replacement-key recovery without retry loops; maintain audience boundaries.
- Verification approach: Account/operator/anonymous audiences, pagination/long values, existing and fresh affected sessions, evidence links, CLI readable/JSON output and historical facts after rebuild.
- Risks or open questions: Current key counts must not rewrite acceptance snapshots; no public device/IP disclosure or implication that reporting/clearing keys revokes access.
- Canonical components/API contracts touched: ApprovalHistory, shared history partials/profile API, ActivityService, ApprovalAudit, auth status and reconnect clients.

## Stage 7

- Goal: Verify the complete security and recovery slice.
- Dependencies: Stages 1–6 committed.
- Expected changes: Extend existing isolated signed browser/API fixtures for operator revocation, terminal denial, retained claims, replacement, non-cascading membership and restart/rebuild/archive behavior; correct only in-scope defects.
- Verification approach: Focused permission/race/fault suites; actual phone/desktop success/cancel/stale/failure/retry; affected shared classic/Forte views/themes and cache/no-store boundaries; real application startup with prior schema when applicable. Record exact passes/failures/omissions.
- Risks or open questions: Release blocked by any bypass, history loss, username takeover or recovery gap; exceeding stage budget requires replanning rather than quietly expanding this stage.
- Canonical components/API contracts touched: Existing PHP regression suites, browser signing/auth/history fixtures and release rehearsal.

## Stage 8

- Goal: Document the locally releasable outcome and operational limits.
- Dependencies: Stage 7 acceptance passed and committed.
- Expected changes: Operator deployment/rebuild, backup freshness, last-key replacement and unsafe-rollback instructions; acceptance/revocation contract, checklist and Step 4 closeout/study ledger. Preserve separate Option B/cross-user follow-ups and stage commit evidence.
- Verification approach: Isolated restore/rebuild/deployment smoke, documentation links, diff/scope/dependency-inventory checks; clearly separate local verification from production deployment and maintainer acceptance.
- Risks or open questions: A pre-revocation restore or old application can reopen access; require writer/private-access maintenance until current revocation state is restored and verified. No claim of safe automatic rollback or remote data erasure.
- Canonical components/API contracts touched: Account-key safety runbook/specs/checklist, README as needed, cycle summary/debug log and effort ledger.

## Approval and study checkpoint

Step 2 explicitly approved; no new scope. Option B and possible non-operator cross-user authority remain deferred in `todo.txt` per the user's request. Step 3 awaits approval; implementation not started. Snapshot 2026-10-23T15:05:53.470092+00:00: scope v1, original baseline `82cef24f17de4b644497a7d9c38aaa54d27faf02`, R1/P2, Codex single exposed rater; total projection remains **unavailable pending exact frozen feedback recovery**. Earlier intake/Step 1/Step 2 snapshots preserved; no fabricated score, measured effort or accepted outcome. Planning/ledger updates remain uncommitted; accumulated ledger outcomes belong in authorized closeout.

## UI mockup review

Request: provide a UI mockup / disposition: in-scope planning aid, no implementation approval / approval: mockup creation requested; Step 3 still pending / outcome: [interactive static preview](./account_key_revocation_mockups/index.html), with account/review/last-key/revoked/stale/refresh-failure/affected-device states and sample data. The simulated flow targets key ending C4D2; links and setup action are placeholders. Browser checks at 390/1280px found no horizontal overflow in all seven states; cancellation and simulated revocation transitions passed. Mobile review screenshot visually inspected. No real signing/server authorization, application integration, full theme verification or production acceptance is claimed. Existing UI is the visual reference; this mockup is not a dependency or implementation change.

## Mockup retention and cutoff follow-up

- Request: retain `index.html` and do not check in screenshots / disposition: authorized artifact cleanup / outcome: removed generated mockup PNGs; retain the HTML preview only. Prior screenshot checks remain historical verification evidence, not retained deliverables. This does not approve Step 3.
- Question: are previous posts still valid after key revocation? / clarification: approved scope preserves existing posts, their signatures and historical attribution; revocation denies subsequent privileged actions. Current-access indicators must not imply historical signatures became invalid. This is planned behavior, not implemented acceptance evidence.
- Request/question: can a revocation cutoff be added later? / disposition: feasible future assessment, excluded from this cycle / approval: no implementation approval / follow-up: define whether a cutoff concerns access timing or trust/moderation of content attributed to a compromised key, who may set it, and which records it affects. A member-supplied creation timestamp alone cannot prove acceptance before a cutoff; use trustworthy acceptance/repository evidence and preserve uncertainty for legacy/imported records. Preserve original signed records; no retroactive deletion or signature rewriting implied. Do not backdate the current access-denial guarantee or promise recovery of already disclosed data.

## Explicit cutoff design revision — scope v2

Request: specify cutoff inside the revocation request / disposition: revised Step 2 timestamp contract and dependent Stage 1–3/5–6 requirements; no selectable historical cutoff or new authority / approval: design revision requested, revised requirements and Step 3 pending review / outcome: explicit signed `revocation_cutoff` replaces any inference from submission, acceptance or commit time. This supersedes deferral of the explicit timestamp field in the earlier cutoff follow-up; historical reassessment remains deferred. See [the complete timestamp contract](./account_key_revocation_step2_feature_description.md#explicit-revocation-cutoff).

Step 3 revision snapshot 2026-10-23T17:34:33.303738+00:00: scope v2; original baseline/R1/P2/exposed rater unchanged, projected total unavailable pending frozen feedback recovery. Retain all v1 checkpoints. The prior “no new scope” handoff describes v1; this revision records the added explicit signed timestamp requirement. Implementation not started.
