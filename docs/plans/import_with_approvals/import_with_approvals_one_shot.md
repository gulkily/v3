# Import with approvals

Cycle: `v3/import_with_approvals`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`; no explicit request)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

**Cycle estimate: 0.35 (intake)**

Status: implemented, verified, PR opened; next action: maintainer review and merge.

## Original request

2026-11-16, in chat, after `./v3 approval suggest-roots` on an imported instance reported `approval_edges: 0` for 312 keys: the agent found that `import-instance` excludes approval and invitation posts and receipts, not just seeds, and proposed (1) analyzing during import, (2) an opt-in `--with-approvals` that imports approval records but never seeds, (3) interactive seeding. Maintainer: "when we do import-instance we can walk the user through doing this?", then "please make --with-approvals".

## Authorization

Builds item (2), plus printed guidance pointing to `./v3 approval suggest-roots` (the walk-through, without prompts). Excluded: interactive y/N seeding and analysis of the archive inside the import (items 1 and 3; follow-ups), and any change to seed, operator-grant, or key-retirement import.

## Outcome and work type

Application work. `./v3 import-instance <source> --with-approvals` also imports approval and invitation posts and validated `records/approval-acceptances/` receipts. Seeds and every other authority family stay excluded. Every import reports approvals: without the flag, how many approval posts were left out and how to restore them; with it, the next command (`./v3 approval suggest-roots`, carrying custom paths). `suggest-roots` warns when it finds no approval posts.

## Approach and risks

- Planner gains a `withApprovals` constructor flag; the runner passes it to both planning calls; the report records `with_approvals`, so `--resume` keeps the saved choice (the flag is refused with `--resume`/`--cancel`).
- Receipts are validated with `ApprovalAcceptance::read` against the exact grant bytes and depend on their post, so a receipt whose grant is rejected or tampered is not imported.
- Risk: imported approvals could grant authority. They count only through reachability from local seeds (`deriveApprovalState`), so local seeds stay the sole authority.
- Risk: unused, unexpired source invitations become redeemable locally once their issuer is approved. Documented in help and the concept doc; acceptable for an opt-in that trusts the source's approvals. A flag to drop open invitations is a possible follow-up.

## Reuse decisions

Existing planner exclusion and dependency machinery, `ArchiveRecordCatalog` (already knows `approval-acceptances` identity keys), `ApprovalAcceptance::read`/`grantPath`, the existing CLI progress output, and `./v3 approval suggest-roots` from `approval_suggest_roots`.

## Completion conditions

- Default import unchanged (approval posts, receipts, seeds excluded).
- With the flag: approval and invitation posts and valid receipts import; tampered receipts are invalid; seeds stay excluded.
- Guidance lines appear for both cases; `--resume --with-approvals` is refused.

## Concepts touched

Instance content import: updated (`docs/concepts/instance_content_import.md`, Approvals paragraph).

## Verification and recovery plan

`./v3 test --smoke ContentImportPlannerTest ImportInstanceCommandTest ContentImportRecoveryTest ImportAdmissionTest RepositoryArchiveImportCommandTest ImportedContentPublicationTest ApprovalRootSuggestionsCommandTest`. Full suite not run (maintainer runs it). Recovery: an import is one commit; revert it, or import without the flag.

## Starting revisions and branch

Base `0b1ebace` (origin/main); branch `feat/import-with-approvals`. Planning and implementation committed together after a short one-shot (record written before commit; no earlier implementation commit).

## Study history

- 2026-11-16 intake: 0.35, scope v1. Prior exposure: the agent had read the planner, runner, archive extractor and approval receipt code while diagnosing the zero-edge report.
- 2026-11-16 closeout: scope v1 delivered. Retrospective scope assessment: 0.30. Maintainer acceptance pending.

## Dated execution history

- 2026-11-16: planner/runner flag, receipt validation and dependency, CLI flag/help/guidance, `suggest-roots` empty-graph warning, concept doc, tests.
- 2026-11-16: test fixture corrections. A tampered receipt lacked a trailing LF (parser error, not the intended mismatch); the valid receipt's context did not match its post's thread/author. Both fixed in the test; validation behaved correctly.

## Outcome

Delivered scope v1. Not run against a real archive. Follow-ups: in-import analysis of the archive and interactive seeding; optional exclusion of open invitations.

## Verification

Command above: 499 run, 499 passed (plus `ContentImportPlannerTest` run: 484 passed). Full suite not run.

## Commit/merge/publication status

Single commit on `feat/import-with-approvals`; PR opened; not merged.

_Claude (Opus 5.5), 2026-11-16_
