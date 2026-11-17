# Railspike for every committed change

Cycle: `v3/railspike_for_every_commit`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on` present; no explicit request)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: enabled (new enrolled cycle default); no real alternatives or work units, so no unit estimates

**Cycle estimate: 0.05 (intake)**

Status: implemented and verified locally; PR open, awaiting maintainer review and merge.

## Original request

On 2026-11-14 the maintainer reviewed [PR #216](https://github.com/gulkily/v3/pull/216), which comes from cycle `v3/agents_md_source_line` and adds an AGENTS.md line to every source file together with this AGENTS.md change. The maintainer then wrote: "ok, i am now not sure this is necessary if we just make your suggested agents.md update. can you please do that in a separate pr?" Feedback items: none match.

## Authorization

That message authorizes the AGENTS.md paragraph change on its own branch and PR. It does not authorize closing, changing or merging PR #216, and it does not cover the per-file line.

Parent: `v3/agents_md_source_line` ([record on its branch](https://github.com/gulkily/v3/blob/chore/agents_md_source_line/docs/plans/agents_md_source_line/agents_md_source_line_one_shot.md)). This is a split child cycle, not a new independent outcome for that work.

## Outcome and work type

Documentation (agent instructions). Allowed path: the `AGENTS.md` Railspike paragraph, plus this record, the plans index, the ledger row and the debug log.

Reader outcome: an agent working in v3 runs every committed change as a Railspike cycle, one-shot by default and staged when review is needed. Direct work is limited to questions, uncommitted exploration, or a task the user explicitly opts out. The agent names the chosen mode and reason in its first reply.

## Approach and risks

Replace the paragraph's "Small, straightforward changes can proceed directly" with the rule above. The existing pointers to RAILSPIKE.md and Approved Step gates stay.

- Risk: the wording could conflict with "Implementation standards apply to every workflow", which still mentions Direct work. It does not conflict, because Direct remains available for the stated exceptions.
- Risk: the change is identical to the AGENTS.md part of PR #216. Merging both gives the same text, but whichever merges second may need a trivial conflict resolution.

## Reuse decisions

Existing AGENTS.md Railspike section; no new section or file.

## Completion conditions

The paragraph reads as above on `main` after merge; documentation checks pass.

## Concepts touched

None.

## Verification and recovery plan

- `git diff --check`.
- `PlatformDocsCatalogTest` and `LlmsTxtRendererTest`, since the platform docs render AGENTS.md.
- Runtime checks are inapplicable otherwise.
- Recovery: revert the PR.

Starting revisions and branch: `origin/main` at `59d2d64609d8b3ff98b3484df20dd4648a067f8a`; branch `chore/railspike_for_every_commit`.

Planning commit: `a27cc98`.

## Study history

- 2026-11-14T17:34Z intake: **0.05** whole-cycle projection, scope v1, R1, P2. This is below the smallest R1 reference: one paragraph, already drafted in the parent cycle, so the rater (Claude) had full prior exposure. No measured labor.
- 2026-11-14T17:34Z execution entry: **0.05 reaffirmed**, same scope.

- 2026-11-14 closeout: scope v1 delivered with no corrections. Retrospective scope assessment: 0.05. Maintainer acceptance pending.

## Dated execution history

- 2026-11-14: replaced the AGENTS.md Railspike paragraph, using the same text as PR #216. Addition, not correction.

## Outcome

AGENTS.md now runs every committed change as a Railspike cycle and requires the first reply to name the mode. PR #216 is left as it is for the maintainer to decide on.

## Verification

- `git diff --check`: passed.
- `./v3 test PlatformDocsCatalogTest LlmsTxtRendererTest`: passed (22 of 22).
- Runtime, UI, migration and deployment checks: inapplicable, since this is an instruction-only change.
- Full suite: not run, per AGENTS.md.

## Final review

The diff is the AGENTS.md paragraph plus this record, the index entry, the ledger row and the debug log. No duplication, no dependencies, no concepts touched.

## Commit/merge/publication status

- Planning commit: `a27cc98`.
- Completion commit: reported in the PR.
- Not merged.

*— Claude (Opus 5.5), 2026-11-14*
