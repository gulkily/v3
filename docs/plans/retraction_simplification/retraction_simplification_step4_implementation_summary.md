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

## Stage 6 - Stateless artifact maintenance

**Original estimate: 0.30**

**Retrospective stage work: 0.30**

- Changes: sweep scans complete stores each run with only in-memory paging; the task queue owns five-minute discovery and failure/retry status. No saved generation/cursor/stack or publication guard remains. Successful runs retire recognized old sweep ledgers. Artifact traversal preserves assets, pinned independent Docs and symlink boundaries, including the actual high-traffic cache root.
- Verification: new 1,500-artifact test scans beyond the former per-run limit, verifies preview, repeat/no-op, late-output expiry and protected assets/outside symlink targets; high-traffic actual-flow regression remains passing. Larger QDB migration/cleanup acceptance remains Stage 8.
- Notes: ordinary file age replaces generation stamps: artifacts predating retained record files retire immediately; later stale output expires after five minutes on a successful subsequent scan. With retractions present this also expires fresh generated copies after five minutes, trading cache lifetime for no per-item cleanup state. Static-only deployments must rebuild/refresh; this operational consequence requires explicit runbook coverage. `--limit` now means in-memory SQLite batch size, not a partial sweep checkpoint. Full scan may be retried manually after worker timeout; no completion SLA. No estimate revision.

*— Codex · 2026-10-25 UTC*

## Stage 7 - Deferred LLM payload cleanup

**Original estimate: 0.20**

**Retrospective stage work: 0.20**

- Changes: exchange readers/recorders no longer enforce immediate retraction visibility. A completed scrub hides the payload; late provider responses are handled on a later full scan. Existing dependency manifests select affected exchanges; unknown legacy dependencies are conservatively scrubbed when accepted retractions exist instead of silently retained forever. Known unrelated payloads remain unchanged.
- Verification: RetractionSweepTest 8/8 and LlmExchangeRecorderTest/SqliteLlmExchangeStoreTest 6/6. Includes actual emitted cron execution, task-queue cadence, full traversal with batch size 1, late-result visibility/cleanup, malformed residual payload cleanup, WAL reader failure/retry, killed transaction recovery and unchanged unrelated rows. No sweep state file is created.
- Notes: conservative cleanup can remove unrelated old diagnostic payloads with unknown provenance; preserve this consequence in operator guidance. No changes to authored replies, external provider copies or Git history. No estimate revision.

*— Codex · 2026-10-25 UTC*

## Stage 8 - Acceptance, recovery and closeout

**Original estimate: 0.25**

**Retrospective stage work: 0.40**

- Changes: reconciled obsolete immediate-suppression tests and concise retraction/sweep/LLM/recovery guidance. Acceptance checks corrected JSON source-route parsing, retained source hashes for exact label ownership, interrupted migration recovery from the existing receipt, legacy retry conversion, and offline update exclusions derived from the normal read model. No new cleanup state. Migration takes ordinary write/publication locks. UI notice describes deletion and delayed copies without claiming history erasure.
- Verification: final consolidated PHP regression run **122/122**; signed author/operator API scenarios **18/18**; Docs publication/recovery checks **15/15**; final public-instance offline CLI diagnostics **3/3**. Final parser/list checks are included in the 122. Browser retraction flow covers author/operator, account attribution and partial refresh/retry; all 13 themes at phone/desktop sizes pass overflow, Word97 phone screenshot inspected. Keyboard activation, Escape/focus restoration and touch checks pass. Offline update browser cases **16/16** pass. Schema-25 startup recovery and actual normal rebuild were verified in Stage 4. These are local fixture/CLI/browser results, not production acceptance.
- Scale: disposable clone from the QDB archive analysis repository migrated one absent-target legacy retraction, preserving its signed evidence, with zero unresolved records. Actual `./v3 rebuild` processed 18,930 source posts and 4,016 identities in 27.051 seconds, producing 18,931 structural/content rows. Generated 18,931 HTML artifacts from that model; actual CLI preview/run with batch size 1 examined and retired all 18,931 without writing a ledger. Repeat migration and sweep both complete with zero changes. Real LLM SQLite traversal, WAL/interruption and worker retry are covered by fixtures; the QDB scale run had no exchange database.
- Full suite: initial feature run **1,510 tests, 1,495 passed, 15 failed**, followed by focused corrections above. Untouched `df313cf0` baseline: **1,525 tests, 1,516 passed, 9 failed**. Seven feature failures reproduce on baseline: ApprovalSeedCommandTest profile-copy expectation; AgentIdentityServiceTest username-copy expectation; LocalAppSmokeTest continuation and removed archive-filename helper; PlatformDocsStaticTest anonymous-context requirement; ReadModelBuilderTimingTest phase expectation; WriteApiSmokeTest historical reaction signature header. Two parser failures and three documentation-structure assumptions are corrected and pass focused reruns; renderer tests retain table/code/list coverage using stable fixtures or another document. Three offline-diagnostic failures reflect this checkout's private-site configuration and pass with `FORUM_APPROVED_MEMBERS_ONLY=0`. Baseline additionally fails two broken-history provenance tests which pass here. No final all-green full-suite claim; no unrelated baseline repairs made.
- Operational tradeoffs: without generation tracking, clean generated copies also expire after five minutes while retractions exist; retain ordinary refresh/publication jobs and rebuild static-only deployments after retirement. Unknown-provenance legacy LLM payloads are conservatively scrubbed. Existing suppression-only text records require explicit migration; malformed/unresolved evidence does not block unrelated publication. No history purge, remote/provider erasure or browser-downloaded-copy guarantee.
- Coordination: reviewed newer `~/v3` through `d468c9059`; no new commits touching Host/Write/Retraction since prior inspection `63314e6cf`. Its newer Railspike beta.4 and unrelated work remain untouched. Existing agent-discovery handoff remains the coordination point.
- Checks: diff/syntax and authorized-file scope pass; zero dependencies added, no `todo.txt` changes, no new navigation/icon mappings. UI verification is recorded in the shared audit checklist. Original stage estimate retained; the larger retrospective reflects shared scale/browser/baseline comparison and recovery corrections, not measured time or a revised historical prediction.

*— Codex · 2026-10-25 UTC*

## Study closeout and release limits

2026-10-25 UTC: all eight approved release-1 stages complete locally on `feature/retraction-simplification`. Planning `fd5c6849`; Stages 1–7 `dcdd09cc`, `2f55dc1c`, `7b78d723`, `4ae11274`, `e31acf0d`, `1b7862c7`, `3dce9d79`; Stage 8 is this summary's commit. Each includes its stage record. Final diagnostic report follows in a separate log-only commit.

**Original cycle projection: 1.00. Retrospective scope assessment: 1.10.** Independent whole-cycle judgment; do not sum stage scores. Stable cycle `v3/retraction_simplification`, scope v2/release 1, original baseline `22134259876481199aacff6f6b2cd39a811236a9`, R1/P2 and single exposed Codex rater retained. Original v1 full-outcome projection 1.50 and all estimate checkpoints remain historical. Exact three R1 hashes verified at entry; no reference changes. Extended stage estimates/retrospectives are supplemental, not independent outcomes. Measured labor and exact runtime model/settings unavailable; maintainer comfortable-fit judgment and acceptance pending. No accepted-count increment or invented independent rating.

**Pending:** merge/push, deployment, live qdb.us cold-page/cache-hit/refresh/cron and migration acceptance, physical-device checks. Local replay verifies absent-target rendering and timeout diagnostics, not repair of the live user's failure. Follow the deployed-host recovery steps in [cleanup guidance](../../reference/retraction_sweep.md#recovery-on-the-deployed-host), including ordinary read-model preparation, manual refresh and real worker retry. History purge is separately planned release 2 and remains unimplemented; the original two-release outcome is incomplete. Full suite retains reproduced baseline failures. No production repositories were changed.

Local evidence logs are temporary files under `/tmp/retraction-*`, notably `final-regressions.log`, `api-final.log`, `browser-final.log`, `offline-browser.log`, `menu-check.log`, `full-suite.log`, `full-baseline.log`, `baseline-failures.log`, `offline-public-diagnostics.log`, and `qdb-*.json`; their outcomes are retained above because temporary files are not durable artifacts.

*— Codex · 2026-10-25 UTC*
