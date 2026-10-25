# Human-readable format audit

Policy: [CONTRIBUTING](../../CONTRIBUTING.md#human-readable-formats). Canonical
records and human-facing files use `Key: value` headers and readable text bodies.
JSON inside a header is still JSON; changing the extension alone is not a fix.

Source audit: 2026-10-25, baseline `2a07313a738af9e0a0f15796e8155d301d949ed5`.
Includes repository records, source routes, exports, operator configuration,
reports, CLI output, and browser templates. This is a static source inventory,
not runtime verification or a claim that every diagnostic path was exercised.
Priorities below identify follow-up work; only retraction replacement is being
planned now. No bulk conversion of other families is authorized by this checklist.

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
sequence: F01 is already selected, and R04 belongs with it regardless of score.
Boundary-check scores prioritize **investigation only**, not an unconfirmed
conversion; do not combine their queue with confirmed rewrites. Re-score when
actual user evidence or a clarified human-facing contract changes an input.

## Completed guidance

- [x] Record the rule in `CONTRIBUTING.md` and make it discoverable in `AGENTS.md`.
- [x] Identify format consumers and separate human-facing conversions from machine data.
- [x] Record the maintainer's retraction scope: ignore previous retractions; no migration or backward-compatibility work. This exception does not automatically apply to other record families.

## Canonical records — convert

- [x] **F01 · Priority 11/12 (C/E/F 3/2/3) — Retraction acceptance.** Replaced JSON with a signed text request, detached signature and separate text acceptance receipt. Preserved new-request signing, actor/author attribution, structure, deletion/retry, rebuild, import protection and cleanup. Evidence: [AcceptedRetraction](../../src/ForumRewrite/Canonical/AcceptedRetraction.php), [source paths](../../src/ForumRewrite/Canonical/SourcePathValidator.php), [import planner](../../src/ForumRewrite/Import/ContentImportPlanner.php), [sweep](../../src/ForumRewrite/Retraction/RetractionSweep.php). [Current assessment](retraction_text_format/retraction_text_format_step1_solution_assessment.md). Existing retractions need not be converted or supported.
- [ ] **F02 · Priority 11/12 (C/E/F 3/2/3) — Source/label removal acceptance.** `records/post-removals/*.json` is durable evidence and visible through source routes. Convert metadata, repeated label bindings and conversation structure to readable records; retain source hashes and local acceptance authority. Evidence: [LabelRemoval](../../src/ForumRewrite/Canonical/LabelRemoval.php), [schema contract](../specs/read_model_schema_v1.md).
- [ ] **F03 · Priority 11/12 (C/E/F 3/2/3) — Approval acceptance receipts.** `records/approval-acceptances/*.json` contains nested reviewed context and is source-readable. Use named text fields; preserve grant/context hash bindings and the distinction between a signed grant and local acceptance. Decide historical digest compatibility explicitly before changing serialization. Evidence: [ApprovalAcceptance](../../src/ForumRewrite/Security/ApprovalAcceptance.php), [receipt specification](../specs/approval_acceptance_record_v1.md).
- [ ] **F04 · Priority 9/12 (C/E/F 3/2/1) — Legacy timestamp companions.** `records/post-timestamps/*.json` contains three scalar fields. Replace with text headers and retain the exact original-record hash binding. Evidence: [LegacyPostTimestamp](../../src/ForumRewrite/Canonical/LegacyPostTimestamp.php), [post specification](../specs/canonical_post_record_v1.md).
- [ ] **F05 · Priority 10/12 (C/E/F 3/2/2) — Agent response metadata inside posts.** `Agent-Response: {…}` embeds JSON in canonical signed replies. Replace with individually named headers; update post parsing, response/expansion writers and offline/read-model consumers without losing attribution or version relationships. Evidence: [AgentResponseMetadata](../../src/ForumRewrite/Canonical/AgentResponseMetadata.php), [LocalWriteService](../../src/ForumRewrite/Write/LocalWriteService.php), [PostRecordParser](../../src/ForumRewrite/Canonical/PostRecordParser.php).

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

## Closing each conversion

- [ ] Specify fields, repeated values and multiline content under `docs/specs/` for canonical formats; include a readable example and malformed-input behavior.
- [ ] Verify actual writers, readers, source/download routes, import/export and recovery; preserve signed bytes and trust boundaries. Explicitly decide compatibility for that family.
- [ ] Update affected tests/docs and inspect the human result. UI changes follow `CONTRIBUTING.md`; record evidence here or link their implementation summary.

Initial audit verification: source references and Markdown local links checked;
no runtime formats changed at that audit checkpoint. F01/R04 implementation and
verification are recorded in the [implementation summary](retraction_text_format/retraction_text_format_step4_implementation_summary.md). Other conversions remain open.

*— Codex · 2026-10-25 UTC*

Prioritization verification: all 16 open audit items scored; component ranges and
weighted totals checked. Existing IDs, evidence, completion states and conversion
boundaries preserved. Documentation only; no new Railspike cycle or runtime tests.

*— Revised by Codex · 2026-10-25 UTC*
