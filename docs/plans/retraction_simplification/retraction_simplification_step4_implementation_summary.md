> **Feature plan:** [Step 1](./retraction_simplification_step1_solution_assessment.md) · [Step 2](./retraction_simplification_step2_feature_description.md) · [Step 3](./retraction_simplification_step3_development_plan.md) · Step 4

Debug mode: enabled.
Effort study: `railspike-effort` / `v3/retraction_simplification`; [ledger](../effort_estimation_trial.md#cycle-v3retraction_simplification). Extended estimates: enabled.

**Cycle estimate: 1.00**

## Entry — 2026-10-25 UTC

Step 3 explicitly approved. Planning commit `fd5c6849`, branch `feature/retraction-simplification`. Scope v2/release 1, original baseline `22134259876481199aacff6f6b2cd39a811236a9`, R1/P2 and single exposed Codex rater retained. Three frozen hashes reverified at activation; independently reaffirm 1.00 before implementation. Stage estimates first recorded at activation, not historical originals: S1–S8/v1 = 0.20, 0.20, 0.30, 0.25, 0.25, 0.30, 0.20, 0.25. Scopes/drivers/uncertainties inherited from Step 3; Stage 8 owns shared verification/closeout. Exact runtime model/settings and measured labor unavailable. History purge, deployment and maintainer acceptance remain outstanding. No new dependencies authorized or planned.

*— Codex · 2026-10-25 UTC*

## Stage 1 - Durable acceptance contract

**Original estimate: 0.20**

**Retrospective stage work: 0.20**

- Changes: `AcceptedRetraction` stores one JSON record with exact signed-request/signature bytes and whitelisted conversation structure; no post prose or cleanup state. It is a local acceptance record, never self-authenticating imported authority. Existing import catalog does not admit this new JSON family; Stage 4 must validate incoming claims before allowing them.
- Verification: `php tests/run.php AcceptedRetractionTest` 2/2; target mismatch rejected, signed bytes/structure round-trip, prose metadata rejected. Existing action-time signature/operator checks remain the authority boundary; Stage 2 wires them before record creation.
- Notes: existing signed text is embedded unchanged, so migration needs no replacement signature. Read does not invoke Git. No stage estimate revision; original S1/v1 scope retained. Production/ingestion integration remains pending dependent stages.

*— Codex · 2026-10-25 UTC*

## Stage 2 - Delete and record atomically

**Original estimate: 0.20**

**Retrospective stage work: 0.20**

- Changes: author/operator finalize preserves signature and privilege checks, then commits scoped post/signature removal plus JSON acceptance. Retry uses the retained record without the original post. Failed commits restore files; already accepted records produce no extra commit. Recovery after process interruption uses the same record/transaction, not a cleanup journal.
- Verification: AcceptedRetractionTest 3/3 includes failed Git hook rollback, surviving reply and idempotent transaction; two focused WriteApiSmokeTest cases pass for signed browser/API deletion, invalid signature and unauthorized actor. PHP syntax passes.
- Notes: broader operator flow test reaches successful acceptance but fails obsolete Forte commit display expectations; reader/display compatibility belongs to Stage 4 and remains pending. Existing presentation refresh is retained until the publication/cleanup stages. No estimate revision. Markdown hard-break whitespace from entry removed.

*— Codex · 2026-10-25 UTC*

## Stage 3 - Legacy migration

**Original estimate: 0.30**

**Retrospective stage work: 0.30**

- Changes: `retraction-sweep migrate` resolves legacy author/operator acceptance once, preserves signed request bytes, recovers absent targets from historical source without writing prose back, and commits conversions independently. Unresolved records are reported individually. Existing accepted source-removal records remain in place with versioned local acceptance; imports already reject unfamiliar removal receipts.
- Verification: AcceptedRetractionTest 4/4, including absent-target migration, unresolved neighbor, preserved request and repeat migration. CLI help includes migrate; human output provides rebuild/cleanup/recovery steps and JSON returns per-record results. Failed source-removal conversion restores prior evidence.
- Notes: unconverted operator records still require legacy authority during explicit migration; Stage 4 removes recurring checks from normal readers. Migration is an operator command, not automatic live mutation. No estimate revision.

*— Codex · 2026-10-25 UTC*

## Stage 4 - Readers and archive imports

**Original estimate: 0.25**

**Retrospective stage work: 0.25**

- Changes: existing placeholder fields now derive from retained acceptance records; legacy suppression-only requests no longer hide content during ordinary rebuild. Current source routes recognize the accepted target/record. Ordinary archive imports skip deleted content; unfamiliar JSON acceptance claims are explicitly rejected, matching the existing local-removal trust policy. No repeated Git acceptance reconstruction in ordinary placeholder readers. Signed legacy requests remain available to explicit migration.
- Verification: 6 focused checks passed covering record/import rejection, absent migration, root/reply normal pages, schema-25 startup rebuild and actual `./v3 rebuild`; archive end-to-end test updated from physical resurrection expectations to non-resurrection. No core columns changed; schema identity 26 forces correct rebuild on code-only deployment, documented in schema spec.
- Notes: broader display/signature assertions and legacy policy tests remain for Stage 8 reconciliation. Retained malformed/unconverted evidence does not globally prevent publication. No estimate revision.

*— Codex · 2026-10-25 UTC*

## Stage 5 - Publication without retraction gates

**Original estimate: 0.25**

**Retrospective stage work: 0.25**

- Changes: normal HTML/static/offline publishers no longer create or check retraction-generation tokens. Ordinary publication/access-mode serialization remains in `PublicationLock`; retraction writes no longer disable high-traffic serving. High-traffic rendering uses the ordinary live model and does not rebuild or hash all canonical files; the separate absent-target model path is retired.
- Verification: HighTrafficPublicationTest, HighTrafficTest and PublicOfflineSnapshotBuilderTest 12/12. Real cold front-controller and manual CLI refresh succeed with the two previously absent production target IDs; missing-model instructions and renderer-timeout diagnostics pass. Concurrent changed retraction evidence is tolerated; access-mode changes and failed workers still reject publication. Stage 4's archive non-resurrection end-to-end check also passes after treating detached signatures as part of the skipped deleted post.
- Notes: production qdb.us not changed or verified. Old guard remains temporarily only for the sweep being replaced in Stage 6. Ordinary Fastmod/access integrity remains enforced. No estimate revision.

*— Codex · 2026-10-25 UTC*
