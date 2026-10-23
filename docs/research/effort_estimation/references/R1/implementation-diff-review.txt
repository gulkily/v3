# Implementation diff review for Railspike effort calibration

Review date: 2026-10-18. This supplements the [calibration report](./report.md) with static inspection of the work committed for its ten examples.

The implementation supports the initial broad size ordering, with one material correction: composer decluttering is a moderate extension of existing machinery, not good evidence of work above the cycle ceiling. Its proposed score changes from 1.10 to 0.60. Missing acceptance checks remain unresolved and are separate from the size judgment.

## Comparison boundaries

For each case, inspect the production patches across the 50 stage and follow-up commits identified in the original study. Screen the test patch inventory and inspect representative test bodies, including the installer and startup harnesses, private-history cryptography and browser journeys, and image publication/restore/Apache journeys. Compare the sum of selected stage changes with a combined diff from the parent of the first implementation commit to the final selected review commit. Planning commits are outside these implementation comparisons.

The combined diff is restricted to paths touched by those selected commits. Git history shows no other commits modifying these paths inside the comparison intervals. This avoids attributing unrelated intervening changes to a case. Broader integration and documentation-only follow-ups outside the selected 50 commits are not added to the sample. The original [source manifest](./source_manifest.json) remains unchanged; [diff evidence](./diff_review.json) retains full starting/ending revisions, commit IDs, categorized counts, patch hashes and both score versions.

This review reads committed code and representative tests; it does not rerun the historical applications or independently reproduce their reported outcomes. The two largest test suites were inspected selectively. A test's presence establishes intended coverage, not that it passed. Discarded local attempts, manual investigation and uncommitted test scripts remain outside the recoverable diff evidence.

## Descriptive implementation volume

The following are additions/deletions in the combined diffs. Production includes application source, scripts, templates, assets and the launcher. Tests include fixtures and harnesses. Documentation, plans, summaries and recorded JSON evidence are excluded from these two columns and retained separately in the JSON. Literal copy, comments and moved code still count as source lines; the figures are not normalized measures of difficulty.

| Case | Production additions/deletions | Test additions/deletions | What the implementation contains |
| --- | --- | --- | --- |
| Duplicate preview | 3 / 0 | 47 / 0 | One trim-and-compare conditional and three render scenarios |
| Club about copy | 94 / 13 | 0 / 0 | Profile constants, repeated default copy and template loops |
| Numeric permalinks | 37 / 11 | 122 / 0 | Existing-route fallback, one repository lookup and shared links |
| QDB score emphasis | 57 / 4 | 123 / 4 | Structured score nodes, sign styling and state restoration |
| Toolbar identity | 104 / 3 | 2 / 0 | Shared server resolver, badge/link and browser storage updates |
| Startup recovery | 197 / 66 | 451 / 24 | Runtime mapping, launcher continuation, consent and signal handling |
| Private-history sync | 600 / 5 | 579 / 9 | Crypto helper, separate store, APIs, donor/reader reconciliation |
| Image content | 782 / 64 | 538 / 10 | Signed uploads, private serving, complete backups and restore |
| Composer declutter | 191 / 41 | 60 / 3 | Disclosure/menu behavior and changes to existing form presentation |
| Explicit installer | 345 / 8 | 417 / 0 | Package review, privilege/process control and real-system harness |

These numbers explain implementation composition, not a score conversion. The about-page source is dominated by data rather than branching logic. Private-history code compresses cryptographic and concurrency behavior into long lines. Startup recovery also adds 1,168 lines of recorded JSON evidence, which would dominate a naive whole-commit count. No generated or vendored dependency bundle was added in the selected production changes.

## Findings by case

### Duplicate preview

Commits `40ce2f04` and `04d26333` confirm the narrow implementation: one comparison and an enclosing condition, with a direct-template test for matching, differing and no-subject cases. No committed production rewrite appears between stages. The abandoned full-page fixture described in the summary is not in these diffs, so its investigative cost cannot be reconstructed from code volume. Retain 0.10.

### Club about copy

Commits `5785d828` and `b4f11535` mostly add strings to the existing profile constants, expose them through the existing method and replace hardcoded paragraphs with loops. The template deliberately renders trusted constant HTML to preserve links. There is no new test code in these commits; byte-comparison and full-suite results remain historical reports. The patch reinforces that this is content integration rather than a new rendering subsystem. Retain 0.20.

### Numeric permalinks

Commits `fae0c63f` and `3bc60445` extend the existing last-resort route after exact-ID lookup, add one SQL lookup and switch the shared card link conditionally. Tests check success, missing numbers, non-QDB behavior, existing unnumbered links and several list surfaces. The click-through-style test asserts the redirect and compares two direct renders of the destination; it does not itself drive browser navigation. The summary separately reports a live local check. Retain 0.25 with existing quote numbering as a prerequisite.

### QDB score emphasis

Commits `f3f0bb17`, `31564ab4` and `59ff6bf7` preserve the outer reaction node and update score/count children. Capture/restore now saves those values and recomputes sign classes, while the old whole-text path remains for generic nodes. The test stage also adjusts neutral-score class spacing. The new interaction test executes the actual script in Node against a handcrafted DOM and successful fetch response; it neither launches Chromium nor forces the structured rollback path. Retain 0.30 and narrow the report's coverage wording accordingly.

### Toolbar identity

Commits `31d30388` through `d3d4fc98` show shared server seeding, small template/CSS changes and a short browser script. Stage 4 (`e2f27c5b`) adds one storage-event listener. The later review changes the badge to an icon link and adds URL/tooltip updates. The only committed test changes supply two newly required constructor arguments; the summary's simulated behavior checks are not preserved as regression tests in this series. Retain 0.35 as an effort judgment including reported review work, without treating stage count or the existing smoke suite as new behavioral coverage.

### Explicit dependency installer

Commits `f58f25e9` through `f9a30c78` build a real coordinator: capability facts, runtime/package provenance, resolved and pinned transaction review, consent, fresh reassessment, optional selection and interrupt handling. Acceptance commit `9fc56c49` changes the earlier process-control implementation so sudo authenticates in the caller's terminal before APT enters a separate session; a PID file and `setsid --wait` support reliable completion and cleanup. The new harness performs real package operations inside a disposable chroot, while pure fixtures cover transaction decisions. This is substantial verification infrastructure, not just package-name mapping. Retain 0.90 and the separately documented user-recovery gap.

### Startup dependency recovery

Commits `f20b1a6d` through `097ff5a6` parameterize the existing installer for two PHP/Ubuntu mappings, add actionable recovery and integrate it into the normal launcher. They preserve caller signal handlers and validate startup arguments before offering changes. Stage 4 modifies cancellation behavior; Stage 5 changes the cancellation traps again to exit at prompt boundaries and makes runtime ownership verification fail closed on verification-command errors.

The added tests exercise real startup, caller UID, original address/log path, HTTP writes and shutdown; separate fixtures repeatedly exercise prompt-boundary cancellation. Test corrections also scope process assertions to a chroot and distinguish launcher control sequences from recovery output. These are observable implementation, harness and acceptance costs. Retain the provisional 1.00 reference, conditional on the installer and shared runtime harness already existing. Neither the code nor the commit timestamps establish a measured day of active work.

### Private message history synchronization

Commits `8ab040a2` through `ed36a069` corroborate a new subsystem despite compact source: account/key/message/digest bindings, browser-side session-key transfers, independent SQLite configuration, retained ciphertext, authorization, checkpoints, partial donors and original-signature verification in the canonical reader. Reconciliation changes request concurrency/timeouts and handles missing retained transfers rather than treating receipts as recovery proof. Representative tests use real OpenPGP keys; the browser harness publishes identities and signed approvals before separated donor visits, recovery and restore checks.

The stage series records 663 production lines added and 68 removed, versus 600 added and 5 removed in the combined result. One concrete reason is the 31-line global status script, five-line partial and associated wiring built in Stage 7 and removed in the review commit. That is requested presentation revision, not evidence of a broken cryptographic protocol. Stage totals expose such committed discarded work, but cannot quantify unseen attempts. Retain 1.20 as a conservative scope judgment requiring a split discussion, not an empirically measured overrun.

### Image content

Commits `3f5f16cb` through `f84fb41f` add private immutable storage, signed image metadata, retryable finalization, protected routes, feature-state checks for static output, revision-bound archives and a bounded tar reader with staging before restore activation. This is materially more work than adding an upload field. It reuses the existing signer, writer, lock and renderer contracts while extending their failure paths.

Final acceptance commit `1e6ee048` makes concrete corrective changes: base64 transport preserves signed canonical bytes through multipart newline conversion, oversized empty PHP requests receive a specific error, and storage inspection clears PHP's stat cache. The browser harness tests lost-response retry and fresh-instance restoration; the Apache harness exercises actual routing and request-size behavior. The final description change is a requested capability refinement with corresponding format/parser updates. Retain 1.20, with the existing browser-surface and fault-injection limitations.

### Composer decluttering

Commits `439314d0` through `b2480422` reorder/wrap existing form controls, add native disclosure elements, a summary/warning observer and a small action menu. The image uploader change hides or reveals an existing description label; it does not rebuild image preparation, upload transport, storage or recovery. Tests mostly add form-render assertions, a browser fixture that toggles warning visibility directly and adaptations to the existing image journey.

There is modest committed correction: optional wording is removed after being added, the outbox button is moved into the menu during closeout, and the final CSS change gives the menu a stable order. Seven stages do not make this comparable to the two new subsystems. Revise 1.10 to **0.60**, still allowing work for the planned surface/keyboard/mobile/recovery verification. The incomplete checks support a completion qualification and possibly a simpler split; they do not prove excessive size.

## Effect on calibration

Use semantic implementation breadth, existing infrastructure, required failure handling and verification construction as the main comparison. Retain line counts as descriptive evidence only. Track committed rework separately from new requirements and keep evidence quality separate from estimated size. The revised references remain hypotheses for prospective calibration, not measurements of labor or a formula derived from code volume.

To reproduce a comparison, use the full revisions and selected paths in `diff_review.json`: `git -C /home/wsl/v3 diff BASE END -- PATHS`. Inspect an intermediate stage with `git -C /home/wsl/v3 show COMMIT -- PATHS`. The recorded commit IDs remain the attribution boundary even if later source files move or change.
