# QDB Quote Numbering — Step 3: Development Plan

> **Feature plan:** [Step 1](./qdb_quote_numbering_step1_solution_assessment.md) · [Step 2](./qdb_quote_numbering_step2_feature_description.md) · [Step 3](./qdb_quote_numbering_step3_development_plan.md) · [Step 4](./qdb_quote_numbering_step4_implementation_summary.md)

## Completion Contract

- **Normal entry point:** the existing "Submit a quote..." compose form
  on the qdb site — both its plain HTML submit (`createThread()`) and its
  default JS client-keypair-signed submit (`prepareThread()` +
  `createPreparedPost()`) go through the same form.
- **Observable end-to-end outcome:** after submitting, the visitor lands
  back on the board and sees their new quote's card showing `#<N>`,
  exactly one past the previous highest number.
- **Failure/recovery:** if existing input validation rejects the
  submission, no number is consumed — the number lookup is a pure read
  that happens before anything commits, so a corrected resubmission still
  gets the correct next number.
- **Deployment/external-system boundary:** none — uses the per-vhost
  read-model SQLite database already live for the qdb instance; no new
  env var, migration, or external service.
- **Release condition:** complete after Stage 3; no follow-up cycle is
  needed for the feature to be usable.

## Key Risks

- The write-time suffix and `quote_card.php`'s display regex drift out
  of sync, silently reverting new quotes to raw-ID display.
  - Impact: numbering appears broken for all new submissions.
  - Early warning / validation: Stage 1's manual mint check.
  - Mitigation: reuse the exact existing `-qdb-(\d+)$` suffix convention,
    don't invent a new format.
- There are two real submission entry points (`createThread()` for the
  plain form, `prepareThread()` for the JS client-signed flow) that
  independently mint a thread ID today; patching only one would leave the
  other entry point unnumbered.
  - Impact: numbering silently fails for whichever path isn't patched —
    likely the common case, since client-keypair signing is the default.
  - Early warning / validation: Stage 1 verifies both paths explicitly.
  - Mitigation: factor numbering into one shared private helper called
    from both methods, so they cannot drift apart.
- The site-profile check is scoped incorrectly, numbering zenmemes/chouse
  threads too, or failing to number real qdb submissions.
  - Impact: a shared-code regression reaching the other two live
    instances.
  - Early warning / validation: Stage 2's non-qdb regression test.
  - Mitigation: gate strictly on `SiteProfileRegistry::active()['name']
    === 'qdb'`, the same check already used for theme selection.

## Stage 1
- Goal: assign a sequential `-qdb-<N>` suffixed thread ID at submission
  time on the qdb site, from both real submission entry points.
- Dependencies: none.
- Expected changes:
  - New private helper on `LocalWriteService`, e.g.
    `nextQdbQuoteNumber(): int` — runs the verified MAX-extraction query
    (`MAX(CAST(substr(root_post_id, instr(root_post_id,'-qdb-')+5) AS
    INTEGER))`) against the read-model `threads` table, returns
    `COALESCE(result, 0) + 1`.
  - New private helper, e.g. `mintThreadPostId(): string` — returns
    `sprintf('thread-%s-qdb-%d', gmdate('YmdHis'), $this
    ->nextQdbQuoteNumber())` when `SiteProfileRegistry::active()['name']
    === 'qdb'`, otherwise falls back to the existing
    `generateRecordId('thread')`.
  - `createThread()` and `prepareThread()` both call `mintThreadPostId()`
    in place of their current direct `generateRecordId('thread')` call.
- Verification approach:
  - With `FORUM_SITE_ID=qdb`: submit via the plain compose form
    (`createThread` path) and confirm the minted ID is
    `thread-<timestamp>-qdb-<N>` with `N` = previous max + 1.
  - Exercise `prepareThread` (directly or via `/api/prepare_thread`) and
    confirm the same numbering behavior.
  - With a non-qdb `FORUM_SITE_ID`: confirm the minted ID is unchanged
    (original random-suffix form, no `-qdb-` number).
- Risks or open questions:
  - Impact: see Key Risks (suffix/regex drift; two entry points;
    site-scope mistake).
  - Early warning / validation: the three checks above, before Stage 2.
  - Mitigation: single shared helper; reuse of the already-verified query
    and site check.
- Canonical components/API contracts touched: `LocalWriteService
  ::createThread()`, `LocalWriteService::prepareThread()` (internal
  behavior only — no public method signature changes).

## Stage 2
- Goal: lock in Stage 1's behavior with automated tests.
- Dependencies: Stage 1.
- Expected changes: new test cases in the existing thread-creation test
  coverage for `LocalWriteService`:
  - First qdb submission after existing imported quotes receives
    max + 1.
  - Two sequential qdb submissions receive two distinct, increasing
    numbers.
  - A qdb instance with zero `-qdb-` rows yet mints number `1` (confirms
    the `COALESCE` fallback).
  - A non-qdb profile submission's ID format is unchanged.
- Verification approach: run the targeted test class, then the full
  suite (`./v3 test`); confirm the new cases pass and no new failures
  appear beyond the existing tracked pre-existing/order-dependent ones.
- Risks or open questions:
  - Impact: an untested edge case (e.g. empty table) could crash a fresh
    qdb instance's first submission.
  - Early warning / validation: the zero-rows test case above.
  - Mitigation: covered directly in this stage before it's considered
    done.
- Canonical components/API contracts touched: test suite only.

## Stage 3
- Goal: confirm the full Completion Contract end-to-end on a real local
  qdb instance, and close the long-deferred `qdb_todo.txt` item.
- Dependencies: Stage 1, Stage 2.
- Expected changes: no further production code changes expected; update
  `qdb_todo.txt` item 2 to note this is resolved (it had explicitly
  deferred the work until after the archive importer, which has since
  landed).
- Verification approach: with `FORUM_SITE_ID=qdb` locally, submit one
  quote through the live compose form with a saved browser keypair
  (`prepareThread` path) and one without (`createThread` path); confirm
  each new quote's board card shows `#<N>` immediately after redirect,
  with the second submission showing `N+1`.
- Risks or open questions:
  - Impact: leaving `qdb_todo.txt` stale misleads future work on the same
    file.
  - Early warning / validation: part of this stage's own checklist.
  - Mitigation: update it in this stage's commit.
- Canonical components/API contracts touched: none (verification and a
  doc-note update only).
