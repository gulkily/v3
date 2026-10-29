# Human-readable format audit

Policy: [CONTRIBUTING](../../CONTRIBUTING.md#human-readable-formats). Canonical
records and human-facing files use `Key: value` headers and readable text bodies.
JSON inside a header is still JSON; changing the extension alone is not a fix.

Source audit: 2026-10-25, baseline `2a07313a738af9e0a0f15796e8155d301d949ed5`.
Includes repository records, source routes, exports, operator configuration,
reports, CLI output, and browser templates. This is a static source inventory,
not runtime verification or a claim that every diagnostic path was exercised.
Priorities below identify follow-up work. Retraction replacement (F01/R04) and shared removal receipts (F02) are
complete; the remaining conversions are not authorized by this checklist.

Latest source review: 2026-10-27, baseline
`085c47701f57334164f7fe1c562454443cd65596`. At that review, 2 of 16 audit items were complete
and 14 remained open: 11 confirmed conversions (F02–F12) and 3 boundary checks
(R01–R03). This review rechecked the listed format producers and boundary evidence;
it is not a fresh exhaustive inventory or runtime verification.

2026-10-29 conversion checkpoint: 3 complete (F01/F02/R04), 13 open
(10 conversions F03–F12 and 3 boundary checks R01–R03). F02 verification and
F01 shared-format integration are recorded in the [implementation summary](unified_removal_format/unified_removal_format_step4_implementation_summary.md).

## Prioritization rubric

**Priority score = 2 × consequence + exposure + friction (0–12; higher first).**
Scores are source-based judgments, not measured usage or effort estimates.
Canonical readability gets extra weight because it is a platform contract.

| Factor | 0 | 1 | 2 | 3 |
| --- | --- | --- | --- | --- |
| Consequence | Internal protocol only / human role unconfirmed | Informational or diagnostic | Setup, moderation, import decisions or recovery | Durable canonical fact or signed public record |
| Exposure | Human access unconfirmed | Exceptional recovery or fallback | Deliberate source/archive/document inspection | Normal human command output or manual input |
| Friction | No demonstrated JSON burden | Simple scalar fields or list | Structured values mixed into text/output | Nested records or escaped multiline evidence |

Each item shows its score and **C/E/F** inputs so the ranking is reviewable.
Within each section, work highest score first; ties use the existing item ID.
IDs stay stable and are not ranks. Scores prioritize value, not implementation
sequence: F01 and its companion R04 were completed together. Among the remaining
canonical conversions, F03 is now highest priority; this
ranking does not select or approve a new implementation cycle.
Boundary-check scores prioritize **investigation only**, not an unconfirmed
conversion; do not combine their queue with confirmed rewrites. Re-score when
actual user evidence or a clarified human-facing contract changes an input.

## Completed guidance

- [x] Record the rule in `CONTRIBUTING.md` and make it discoverable in `AGENTS.md`.
- [x] Identify format consumers and separate human-facing conversions from machine data.
- [x] Record the maintainer's retraction scope: ignore previous retractions; no migration or backward-compatibility work. This exception does not automatically apply to other record families.
- [x] Record the separate 2026-10-27 maintainer decision for F02: abandon existing JSON source/label removal records; no migration or backward-compatible reader is required for their replacement. The project is young and not meaningfully in use. This exception is specific to F02 and does not extend to other record families or backups. It records the compatibility decision, not implementation or physical file deletion.

- [x] Record the 2026-10-29 unified-format scope revision: the maintainer confirms there is no existing data to save and authorizes abandonment of previous F01 text receipts as well as F02 JSON records. The unified-format cycle needs no legacy readers, migration, historical replay or old pending-state recovery. New-format signing, authorization and retry correctness remain required. No data deletion or implementation is performed by this decision; other families and backups are unaffected.

## Canonical records — convert

- [x] **F01 · Priority 11/12 (C/E/F 3/2/3) — Retraction acceptance.** Replaced JSON with a signed text request, detached signature and separate text acceptance receipt. Preserved new-request signing, actor/author attribution, structure, deletion/retry, rebuild, import protection and cleanup. Evidence: [AcceptedRetraction](../../src/ForumRewrite/Canonical/AcceptedRetraction.php), [source paths](../../src/ForumRewrite/Canonical/SourcePathValidator.php), [import planner](../../src/ForumRewrite/Import/ContentImportPlanner.php), [sweep](../../src/ForumRewrite/Retraction/RetractionSweep.php). [Current assessment](retraction_text_format/retraction_text_format_step1_solution_assessment.md). Existing retractions need not be converted or supported. The subsequent [shared-format cycle](unified_removal_format/unified_removal_format_step4_implementation_summary.md) replaces the original F01 receipt codec for new records.
- [x] **F02 · Priority 11/12 (C/E/F 3/2/3) — Source/label removal acceptance.** Replaced JSON with `records/post-removals/*.txt`, sharing the readable receipt codec and interpretation with signed F01 retractions. Retains source/label hashes, conversation structure and distinct local/signed authority; compatible same-post bindings combine without losing signed evidence. Previous F01 text receipts and F02 JSON are unsupported, with no migration or old-state recovery. Evidence: [shared contract](../specs/removal_acceptance_record_v1.md), [LabelRemoval](../../src/ForumRewrite/Canonical/LabelRemoval.php), [verification and limitations](unified_removal_format/unified_removal_format_step4_implementation_summary.md).
- [ ] **F03 · Priority 11/12 (C/E/F 3/2/3) — Approval acceptance receipts.** `records/approval-acceptances/*.json` contains nested reviewed context and is source-readable. Use named text fields; preserve grant/context hash bindings and the distinction between a signed grant and local acceptance. Decide historical digest compatibility explicitly before changing serialization. Evidence: [ApprovalAcceptance](../../src/ForumRewrite/Security/ApprovalAcceptance.php), [receipt specification](../specs/approval_acceptance_record_v1.md).
- [ ] **F04 · Priority 9/12 (C/E/F 3/2/1) — Legacy timestamp companions.** `records/post-timestamps/*.json` contains three scalar fields. Replace with text headers and retain the exact original-record hash binding. Evidence: [LegacyPostTimestamp](../../src/ForumRewrite/Canonical/LegacyPostTimestamp.php), [post specification](../specs/canonical_post_record_v1.md).
- [ ] **F05 · Priority 10/12 (C/E/F 3/2/2) — Agent response metadata inside posts.** `Agent-Response: {…}` embeds JSON in canonical signed replies. Replace with individually named headers; update post parsing, response/expansion writers and offline/read-model consumers without losing attribution or version relationships. Explicitly define existing signed-record handling; do not rewrite historical signed bytes merely to rename metadata fields. Evidence: [AgentResponseMetadata](../../src/ForumRewrite/Canonical/AgentResponseMetadata.php), [LocalWriteService](../../src/ForumRewrite/Write/LocalWriteService.php), [PostRecordParser](../../src/ForumRewrite/Canonical/PostRecordParser.php).

## Human-facing configuration, exports and output — convert

- [ ] **F06 · Priority 8/12 (C/E/F 2/3/1) — Import source aliases.** `import-instance --sources` requires a manually supplied JSON name-to-URL map. Use readable key/value configuration and update examples/errors. Evidence: [InstanceSourceResolver](../../src/ForumRewrite/Import/InstanceSourceResolver.php), [CLI](../../scripts/import_instance.php).
- [ ] **F07 · Priority 6/12 (C/E/F 2/1/1) — Fastmod rubric selection/recovery.** `state/private/fastmod_rubrics.json` is normally UI-managed, but recovery explicitly asks operators to edit a JSON array. Use a readable ordered selection format or remove manual JSON recovery through an equivalent supported control. Evidence: [FastmodSelection](../../src/ForumRewrite/Scoring/FastmodSelection.php), [operator recovery](../reference/fast_post_scoring.md).
- [ ] **F08 · Priority 10/12 (C/E/F 2/3/3) — Import review report.** The CLI directs operators to `report.json`. Produce a human-readable review report; keep the private resumable import journal separate. Evidence: [CLI](../../scripts/import_instance.php), [ContentImportRunner](../../src/ForumRewrite/Import/ContentImportRunner.php).
- [ ] **F09 · Priority 9/12 (C/E/F 2/2/3) — Backup/archive inventories.** Thread archives contain `manifest.json`; image backups contain `manifest.json` and paired repository backups contain `repository-snapshot.json`. Convert project-owned inventories into readable records, retaining all file/hash/pairing checks and restore behavior. Evidence: [archive-thread](../../scripts/archive_thread.php), [ImageBackup](../../src/ForumRewrite/Images/ImageBackup.php), [RepositorySnapshot](../../src/ForumRewrite/Images/RepositorySnapshot.php). Restore compatibility needs its own decision; the retraction exception does not authorize discarding backups.
- [ ] **F10 · Priority 9/12 (C/E/F 2/3/2) — Image restore/verify output.** `./v3 images verify|restore` prints JSON unconditionally. Provide readable default results and an explicit machine-output option. Evidence: [restore_images.php](../../scripts/restore_images.php).
- [ ] **F11 · Priority 9/12 (C/E/F 2/3/2) — Fast-score and worker output.** Meta commands, retry/invalidate and inspect output include unconditional JSON; progress includes JSON scores/signals. Use named values or readable lists by default, with explicit JSON for automation. Evidence: [fast_score.php](../../scripts/fast_score.php), [task_queue.php](../../scripts/task_queue.php).
- [ ] **F12 · Priority 7/12 (C/E/F 2/1/2) — Nested values in other human CLI output.** Non-JSON `delete-record` and `high-traffic` branches still serialize arrays as JSON; provider-test and agent-status formatting also falls back to JSON. Replace those human display fallbacks while preserving explicit machine modes. Evidence: [delete_record.php](../../scripts/delete_record.php), [high_traffic.php](../../scripts/high_traffic.php), [agent_reply_status.php](../../scripts/agent_reply_status.php), [test_agent_reply_provider.php](../../scripts/test_agent_reply_provider.php).

## Boundary checks — resolve before assigning a conversion

- [ ] **R01 · Priority 5/12 (C/E/F 1/1/2) — LLM exchange viewer.** Human prompt/response views already exist, but structured fallbacks and errors display JSON. Improve those displays; determine whether explicitly labeled raw request/response disclosures should remain exact protocol diagnostics. Do not rewrite provider payloads merely to change their presentation. Evidence: [llm_exchange.php](../../templates/pages/llm_exchange.php).
- [ ] **R02 · Priority 2/12 (C/E/F 0/0/2) — Signed operational payloads.** Offline intents embed `Payload: {…}`; Fastmod/high-traffic preparation signs JSON. Determine whether these are inspectable human records or exclusively internal request protocols, then convert human records with signing verification. Evidence: [outbox_intent.js](../../public/assets/outbox_intent.js), [LocalWriteService](../../src/ForumRewrite/Write/LocalWriteService.php). No confirmed human preview is claimed by this source audit.
- [ ] **R03 · Priority 7/12 (C/E/F 2/1/2) — Checkout recovery.** The CLI guide tells operators to inspect `<id>.result.json`; registry/preview/result files are otherwise managed internal state. Provide a readable recovery inspection path if direct JSON inspection is required; do not convert all registry/cache files by default. Evidence: [CheckoutManager](../../src/ForumRewrite/Checkouts/CheckoutManager.php), [CLI guide](../reference/v3_cli.md).
- [x] **R04 · Priority 5/12 (C/E/F 1/2/1) — Retraction attribution documentation.** Reconciled the schema spec with the text receipt and removed the internal JSON-trailer round trip and historical acceptance reconstruction. No legacy-record migration. Evidence: [LocalWriteService](../../src/ForumRewrite/Write/LocalWriteService.php), [read-model spec](../specs/read_model_schema_v1.md).

## Machine data reviewed — retain unless its human-facing contract changes

JSON API/LLM transport, explicit `--json`, SQLite JSON columns, browser storage,
prepared-write/recovery journals, caches, offline/PWA/search manifests and query
catalogs are machine interfaces or state. Encrypted key/history envelope JSON is
an internal serialization, not the readable outer backup format. QDB import JSONL
is an intermediate pipeline format. Dependency manifests, third-party datasets,
test fixtures and frozen study evidence are not canonical platform record formats.
Do not rewrite exact protocol diagnostics or frozen evidence to satisfy this audit.

## Compatibility simplification recommendations — 2026-10-27

Recorded at the maintainer's request. These recommendations guide future scoped
planning; they do not approve implementation, deletion of existing data, or blanket
abandonment of other formats. F02's explicit compatibility exception above remains
separate. No additional audit item is complete and priority scores are unchanged.

| Items | Recommended approach | Conditions and retained requirements |
| --- | --- | --- |
| F06 — Import source aliases | Require manual recreation in the new readable configuration format; avoid an automatic migration or legacy reader. | Update examples and errors so operators can recreate their mappings. |
| F07 — Fastmod rubric selections | Prefer recreation through the supported UI over migrating the old JSON selection file. | Confirm loss of the current selection is acceptable; preserve ordering and a usable recovery path. |
| F08 — Import review reports | Emit the readable report for future runs; leave old reports as historical artifacts. | Keep private resumable import journals separate and preserve resume behavior. No retrospective report conversion is recommended. |
| F10–F12 — CLI output | Change future human-facing output directly; no stored-record migration is needed. | Preserve existing explicit machine modes and provide explicit JSON output where needed for automation. |
| F03 — Approval acceptance receipts | Consider abandoning old receipts only if the associated identities/grants can also be discarded or re-established. | Decide explicitly whether existing account/key approval evidence must survive; preserve signed grant/context bindings for retained records. |
| F04 — Legacy timestamp companions | Consider abandoning old companions only if their dependent posts can be discarded or reimported with valid timestamp evidence. | Removing companions while retaining dependent legacy posts can break loading. Establish disposability or a reimport path first. |
| F05 — Agent response metadata | Consider supporting only new headers if old agent replies and their dependent relationships can be discarded or regenerated. | Do not rewrite historical signed replies in place; preserve attribution and version relationships for retained records. |
| F09 — Backup/archive inventories | Make old-archive support a separate decision; no abandonment recommendation yet. | Dropping old formats gives up restoration from affected archives. F01/F02 exceptions do not authorize this. |

For F03–F05, decide the fate of the underlying records before treating their JSON
metadata as disposable. R01–R03 remain boundary investigations; this recommendation
does not turn their internal state or signed protocols into conversion targets.

## Closing each conversion

Apply these checks separately to each conversion and link its verification from
the corresponding item above. These reusable checks are not additional open audit
items; F01/R04 completion evidence is in their implementation summary.

- [ ] Specify fields, repeated values and multiline content under `docs/specs/` for canonical formats; include a readable example and malformed-input behavior.
- [ ] Verify actual writers, readers, source/download routes, import/export and recovery; preserve signed bytes and trust boundaries. Explicitly decide compatibility for that family.
- [ ] Update affected tests/docs and inspect the human result. UI changes follow `CONTRIBUTING.md`; record evidence here or link their implementation summary.

Initial audit verification: source references and Markdown local links checked;
no runtime formats changed at that audit checkpoint. F01/R04 implementation and
verification are recorded in the [implementation summary](retraction_text_format/retraction_text_format_step4_implementation_summary.md). F02 subsequently closed through the shared-format cycle; other conversions remain open.

*— Codex · 2026-10-25 UTC*

Initial prioritization verification (before F01/R04 completion): all 16 then-open
audit items scored; component ranges and
weighted totals checked. Existing IDs, evidence, completion states and conversion
boundaries preserved. Documentation only; no new Railspike cycle or runtime tests.

*— Revised by Codex · 2026-10-25 UTC*

## 2026-10-27 review

- Confirmed F01's retained request/signature/text-receipt implementation in
  `AcceptedRetraction`; F01/R04 verification remains linked to the existing
  implementation summary. No implementation tests were rerun for this review.
- Rechecked F02–F12 against the listed record codecs, writers, configuration
  reader, export inventories and CLI output. Their JSON formats remain present;
  no additional conversion is marked complete and scores are unchanged.
- Rechecked R01's viewer fallbacks, R02's signed payload serialization and R03's
  documented direct result-file inspection. Their boundary questions remain open;
  source inspection alone does not establish a human-facing contract for R02.
- Corrected the planning-only introduction and distinguished the original scoring
  checkpoint from current completion counts. Clarified per-conversion closeout
  and existing signed-record handling for F05.
- Verification: local Markdown link targets, item counts, priority arithmetic and
  diff whitespace checked. Documentation only; no runtime/UI verification, new
  Railspike cycle or dependency changes.

*— Reviewed by Codex · 2026-10-27*

## 2026-10-29 shared-format closeout

F02 and F01 integration are implemented locally, with new signed and local receipts
using one readable format. No previous records are converted or supported.
CLI/API/browser, source/archive, rebuild/import, label-only resolution and new-action
recovery checks are recorded in the linked implementation summary. No production
data deletion, deployment or history purge; zero dependency delta. Other item
scores and the earlier static-review checkpoint remain historical and unchanged.

*— Codex · 2026-10-29 UTC*
