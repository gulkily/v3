# Approval root suggestions

Cycle: `v3/approval_suggest_roots`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on` present; no explicit request)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: enabled (new enrolled cycle default); no real alternatives or work units, so no unit estimates

**Cycle estimate: 0.30 (intake)**

Status: planned; next permitted action: implement on `feat/approval-suggest-roots`.

## Original request

2026-11-16, in chat: "let's say i import the public site, but not the root approvals, is there a way to analyze the existing graph and (optimistically) zero in on the users i most need to approve to restore the graph locally without knowing what the root approvals were on the original server". After the agent described the approach (source components of the approval graph, acceptance receipts as direct operator evidence, risks) and offered a read-only `./v3 approval suggest-roots` analyzer, the maintainer replied "yes please, that sounds amazing", adding "(eventually will probably be brought up to the gui, like into the welcome screen or something)".

Feedback items: none match.

## Authorization

The reply authorizes building the offered read-only analyzer, carried to a PR. Excluded: any change to approval state (the command prints suggested `./v3 approval seed` commands but never runs them), GUI/welcome-screen work (future), and changes to how the read model derives approval.

## Outcome and work type

Application work. `./v3 approval suggest-roots [--repository-root=…] [--database-path=…] [--json]` reads an existing read model and repository, and reports the smallest set of keys to seed as roots so that every key that ever wrote a counted approval (and everyone downstream) becomes approved again. Each suggestion carries its evidence: acceptance receipts that name the actor as an operator, receipts that contradict root status, how many keys it would restore, and its first approval time. Keys already covered by existing seeds are reported as covered, not suggested.

## Approach and risks

- Edges come from the same rules `ReadModelBuilder::deriveApprovalState` uses: `identity` + `approval` posts with an `Approve-Identity-ID:` line, replying to the target's bootstrap post in its bootstrap thread, not self-approval; plus `InvitationApprovalReplay::candidates`. Approval state itself is a fixpoint of reachability from seeds, with no time ordering, so reachability over these edges reproduces it.
- Roots: collapse strongly connected components; each component with no incoming edge from another component and at least one outgoing edge needs one seed. Within a component prefer a key whose receipts say `actor_is_operator: true`, then most approvals given, then earliest approval.
- Risk: a forged approval reply by a never-approved key becomes a source and would be suggested. Mitigation: label each suggestion's evidence (operator receipt / no receipt / receipt says non-operator) and print, never run, the seed commands.
- Risk: a root that never approved anyone leaves no edge. Reported as a limitation; not inferable from the unseeded read model, whose approved-only counters are empty.
- Risk: drift from the builder's edge rules. Mitigation: the builder's target extraction becomes a shared static method used by both.
- Receipt reads go through `ApprovalAcceptance::read`; a receipt that fails validation becomes a warning instead of aborting.

## Reuse decisions

- CLI shape, path resolution, read-only PDO (`mode=ro`, `query_only`), stale-marker warning and text/JSON output follow `scripts/audit_approvals.php` / `ForumRewrite\Support\ApprovalAudit`. New class `ForumRewrite\Support\ApprovalRootSuggestions` beside `ApprovalAudit`, since the audit reports current grants and cannot see unseeded edges; kept free of CLI concerns so a later welcome-screen panel can call `collect()` directly.
- Edge filters: extract `ReadModelBuilder::extractApprovalTargetIdentityId` into `ForumRewrite\Security\ApprovalTarget::fromPost` and call it from both.
- Invitation edges: `ForumRewrite\Invitation\InvitationApprovalReplay::candidates`.
- Receipts: `ApprovalAcceptance::read` with the post-path resolution `ApprovalHistory::forProfile` uses (`CanonicalPathResolver::datedPost` / `postCandidates`).
- Seeds: `CanonicalRecordRepository::loadApprovalSeed`, as in `ApprovalAudit`.
- Tests mirror `tests/ApprovalAuditCommandTest.php` fixtures.

## Completion conditions

- `./v3 approval suggest-roots` (and `--json`, `--help`) runs against an existing read model and repository without changing any file.
- On a fixture with two independent approval trees, a cycle, a forged approval, an existing seed and a receipt naming an operator, it suggests exactly one key per uncovered source component, prefers the receipt-backed operator, reports the seeded tree as covered, and labels the forged source as unverified.
- Missing inputs fail with exit 1 and usage, without initializing state.
- `./v3` usage text lists the command.

## Concepts touched

Approval graph / root seeds: used unchanged. No inventory changes proposed.

## Verification and recovery plan

`./v3 test --smoke ApprovalRootSuggestionsCommandTest ApprovalAuditCommandTest` plus the read-model approval tests that exercise the extracted method (`ApprovalHistoryTest`, `UserAccessTest`). Full suite not run (maintainer runs it). Recovery: the command is read-only; a bad suggestion is undone by not running the printed seed command.

## Starting revisions and branch

Base `65b98d00501abd914902e73bc3be75ba9797b4c6` (origin/main); branch `feat/approval-suggest-roots`.

Planning commit: pending.

## Study history

- 2026-11-16 intake: 0.30, scope v1. Prior exposure: before planning, the agent read `ApprovalPolicy`, `ReadModelBuilder::deriveApprovalState`, `ApprovalAcceptance`, `ApprovalReceiptText`, `ApprovalAudit` and `ApprovalHistory` to answer the original question.
- 2026-11-16 execution entry: 0.30 reaffirmed.

_Claude (Opus 5.5), 2026-11-16_

## Dated execution history

- 2026-11-16: plan written.
