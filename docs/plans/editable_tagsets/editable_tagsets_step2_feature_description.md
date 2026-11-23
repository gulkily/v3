> **Feature plan:** [Step 1](./editable_tagsets_step1_solution_assessment.md) · [Step 2](./editable_tagsets_step2_feature_description.md) · Step 3 · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/editable_tagsets`; [cycle ledger](../effort_ledger/editable_tagsets.md). Standing P2 enrollment; follow-up to `v3/tag_unlocks`.

Extended estimates: enabled

**Cycle estimate: 0.50**

Approved direction: Option A (maintainer, 2026-11-22: "approved step 1"). The three open questions were not answered separately, so this step uses Claude's suggested answers, marked **(assumed)** below. Correct any of them before approving.

## Problem

Tagset rules live only in `resources/tagsets.txt`, so changing or adding a tagset needs a code commit and a deploy. Nothing warns which threads lose their buttons when a parent tag changes.

## User stories

- As an operator, I want to change a tagset on the Tag Unlocks page so that a fix like the `meta` → `issue` rename needs no deploy.
- As an operator, I want to add a tagset (for example `event` unlocking attend and interested) so that new kinds of threads get their own buttons.
- As an operator, I want to see the threads that would lose their buttons before I save, so that I can retag them first.
- As an operator, I want "Reset to default" so that a bad edit is one tap from undone.
- As a member, I want the new buttons and states to appear without anyone touching the server.

## Core requirements

- Only approved operators may edit, as with feature flags **(assumed)**. Everyone else sees the page read-only, as today.
- The editor uses the same `Key: value` text as `resources/tagsets.txt`. Saving signs one record in the content repository, through the same flow as a feature-flag change.
- When the site record exists, it replaces the shipped file entirely **(assumed)**. "Reset to default" removes it. `resources/tagsets.txt` stays the default for new instances.
- Saving is refused until the preview shows no errors: bad tag names, reserved tags such as `like` and `flag`, a tag in two state groups, or a malformed block.
- The preview lists threads with marks whose parent tag no longer unlocks those tags. It only warns; it does not keep old marks working **(assumed)**. The marks themselves stay stored.

## Delivery scope

- Work type: application change.
- New tags show their name as written on the button (`Attend`). Per-tag labels stay out of scope.
- After a save, every place that reads tagsets uses the new rules: buttons, the write check, post states, the Tools page, the agent check-in and the text API.

## Completion boundary

- Normal entry: Tools → Tag Unlocks → Edit, as an operator, on phone and desktop.
- End-to-end result: after saving, a reply in a thread with the new parent tag shows the new buttons, and a tap writes a mark that counts.
- Recovery: a refused save keeps the operator's text in the editor with the error shown. "Reset to default" restores the shipped rules. A failed signature or commit leaves the old rules in force and says so.
- Release: deployed to zenmemes; the maintainer adds or changes a tagset there.

## Risks

- **Post states go stale.** States are worked out when the read model is built, so old states stay until it rebuilds. Impact: wrong folds and wrong "open work" lists. Earliest validation: time a rebuild on a copy of zenmemes data. Mitigation: queue the rebuild on save and show "Updating posts…" until it finishes.
- **Cached rules.** `Tagsets::forApplication()` caches the shipped file in memory, and eight places call it. Impact: some views keep old buttons after a save. Earliest validation: list the callers and check that a single loader change covers them all. Mitigation: one loader reads the site record; no caller reads the file directly.
- **Cached pages.** Server-rendered thread pages and the offline reader may hold old buttons. Impact: buttons for a removed tagset, which the server then refuses. Earliest validation: check which caches hold thread HTML. Mitigation: clear them as part of the rebuild.
- **A bad record breaks every thread page.** Mitigation: an invalid site record falls back to the shipped rules and the Tag Unlocks page shows the error, as the feature-flags record does.

## Shared component inventory

- Feature-flag signing: `LocalWriteService::prepareFeatureFlagChange` and `finalizePreparedFeatureFlagChange`, the `records/instance/feature-flags.txt` path rules in `CanonicalPathResolver`, `SourcePathValidator` and `ArchiveRecordCatalog`, and the Feature Flags page. Reuse the pattern for a new `records/instance/tagsets.txt`; operator check via `ApprovalPolicy::isOperator`.
- Tagset parser: `Support\Tagsets::parse`. Reuse for both the preview and the loader; extend it to report errors instead of silently skipping blocks.
- Tag Unlocks page: `templates/pages/tag_unlocks.php` via `ToolsPageController`. Extend with the Edit view; no new navigation entry, so no icon change.
- Marked posts: `Tools\MarkedPostsQuery`. Reuse to find threads whose marks would lose their buttons.
- Post states: `ReadModel\PostResolution`. Reuse unchanged; only its tagsets source changes.

## Concepts touched

- [tagset](../../concepts/tagset.md): changed. The site record replaces the shipped file when present.
- [feature flags](../../concepts/feature_flags.md): used as the pattern; unchanged.
- [reaction record](../../concepts/reaction_record.md): used; unchanged.
- Proposed new entry: *site tagsets record*.

## User flow

1. An operator opens Tools → Tag Unlocks and taps Edit.
2. The operator adds a block for `event`, then taps Preview.
3. The page shows the new rule, no errors and no affected threads.
4. The operator taps Save; the browser signs the change and the page shows "Updating posts…".
5. A reply in an `#event` thread now shows Attend and Interested.
6. Later the operator taps Reset to default, and the shipped rules return.

## Success criteria

- An operator adds, changes and removes a tagset on zenmemes without a deploy.
- A preview with an error cannot be saved, and the error names the line.
- Renaming `issue` lists every thread with marks under it.
- Git history shows each change as one signed record by the operator.
- A non-operator cannot open the editor or save.

## Enhancement requests

- Fix the tense of state buttons (Claim, Resolve, Close). Disposition: separate work, done first as one-shot `v3/state_button_verbs`; [record](../state_button_verbs/state_button_verbs_one_shot.md), PR #363. Approved 2026-11-22.
- Labels or colors per unlocked tag. Disposition: out of scope (from Step 1); the record format can grow later.
- Non-operator proposals for new tagsets. Disposition: noted follow-up (from Step 1).

## Study Checkpoint

2026-11-22T23:05:00Z; Step 2 handoff; scope v1; starting revision `92ef92501`; reference R1. Claude, single exposed rater; the Step 1 inspection plus the `Tagsets` callers and the feature-flag write path were read before this snapshot. Projected total **0.50**, reaffirmed: the eight callers share one loader, so the rebuild and the preview remain the main cost. Not hours or measured labor; no approval to advance.

Status: Approved Step 2 (maintainer, 2026-11-22).

*— Claude · 2026-11-22 UTC · Model: claude-opus-5-5*
