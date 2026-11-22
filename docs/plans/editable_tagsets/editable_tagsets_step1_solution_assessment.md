> **Feature plan:** [Step 1](./editable_tagsets_step1_solution_assessment.md) · Step 2 · Step 3 · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/editable_tagsets`; [cycle ledger](../effort_ledger/editable_tagsets.md). Standing P2 enrollment; follow-up to `v3/tag_unlocks`.

Extended estimates: enabled

**Cycle estimate: 0.50**

## Original Query

Please write and commit Step 1 for making it editable.

Including new tagsets.

## Understood Intent

"It" is the read-only Tag Unlocks page under Tools (`/tools/tag-unlocks/`), which shows what `resources/tagsets.txt` unlocks. Today that file sits in the code repository, so any change, even renaming a parent from `meta` to `issue`, needs a code commit and a deploy. The goal is to change existing tagsets and add new ones from the site itself.

## Prior Art

- **Feature flags.** Site-mutable flags are kept in a signed record in the content repository (`records/instance/feature-flags.txt`). An operator changes them on the Feature Flags page; the browser signs the change (`prepareFeatureFlagChange` / `finalizePreparedFeatureFlagChange`) and one commit saves it. The shipped registry stays the default.
- **Tag unlocks.** A post's state (claimed, resolved, closed) is worked out when the read model is built, using the exclusive groups in the tagsets. Marks are ordinary post reactions, so they survive any tagset change.
- **The `issue` rename (quickfix, 2026-11-17).** Renaming the parent from `meta` to `issue` meant retagging the three threads that already had marks before the deploy, or their buttons would have disappeared.

## Problem Statement

Changing or adding a tagset needs a code change and a deploy, and nothing warns which threads lose their buttons when a parent tag changes.

## Concepts touched

- All options: [tagset](../../concepts/tagset.md) (changed: the site copy replaces the shipped file), [feature flags](../../concepts/feature_flags.md) (used as the pattern for signed site settings), [reaction record](../../concepts/reaction_record.md) (used; states are recomputed after a change).
- Options A and B: proposed *site tagsets record* (signed instance record, like feature flags).
- Option C: [shared composer](../../concepts/shared_composer.md) (used: tagsets written as posts).

## Solution Options

### Option A: Signed site record, edited as text on the Tag Unlocks page

**Estimate: 0.50**

Operators get an Edit view on the Tag Unlocks page with the tagsets in the same `Key: value` text as `resources/tagsets.txt`. Before saving, the page checks the text and shows a preview: the resulting rules, any errors (bad tag names, a tag in two state groups, a reserved tag like `like` or `flag`), and the threads with marks that would lose their buttons. Saving signs one record in the content repository, like a feature-flag change. When that record exists it replaces the shipped file; "Reset to default" removes it. Post states are then recomputed in the background. Adding a tagset is adding a block.

- Pros: reuses the feature-flag signing flow and record pattern; the text is the format already documented, so the page, the file and agents read the same thing; adding, changing and removing all work the same way; changes are in git history with who made them.
- Cons: hand-editing text on a phone is fiddly; a typo is only caught by the preview, not prevented.
- Driver: recomputing post states after a change, and the affected-threads preview. Uncertainty: rebuild time on production data.

### Option B: Signed site record, edited with a form

**Estimate: 0.70**

Same record and recompute as A, but the page shows a form: one row per tagset with a parent tag field, Replies / Opening post checkboxes, an unlocked-tags field and a "one state" switch, plus Add tagset and Remove buttons.

- Pros: easiest on a phone; invalid input is harder to type; the same affected-threads preview.
- Cons: more UI to build and check in sixteen themes; the form and the file format can drift apart; ordering and future fields (labels, colors) each need form work.
- Driver: the form itself across themes and phone widths. Uncertainty: medium.

### Option C: Tagsets as posts in a `#tagsets` thread

**Estimate: 0.75**

Each tagset is a post by an operator in a dedicated thread, written in the same `Key: value` text. The latest operator post per parent tag wins, and a reply can change or retire a tagset, like the theme directive.

- Pros: discussion and history live next to the rules; no new editor UI; anyone can propose a change in a reply.
- Cons: rules are scattered across posts, so the current set is harder to read; ordering and retirement rules are new; parsing posts at build time adds a path the read model does not have today.
- Driver: deciding which post is in force. Uncertainty: medium-high.

## Recommendation

**Option A.** It reuses the feature-flag signing flow, keeps one format for the file, the page and agents, and covers editing and adding tagsets in one slice. A form (Option B) can come later on top of the same record if text editing on the phone proves annoying.

Questions for Step 2:

- Who may edit: operators only (as with feature flags), or any approved key?
- When a parent tag changes, should the page offer to keep old marks working (for example by leaving the old parent unlocking nothing visible), or only warn?
- Should the shipped `resources/tagsets.txt` stay as the default for new instances, with the site record replacing it entirely?

**Vertical-slice viability:** on a test instance an operator opens Tools → Tag Unlocks → Edit, adds a block giving `event` the tags attend and interested on replies, sees the preview, and saves. A reply in an `#event` thread then shows Attend and Interested, the page lists the new rule, and git history shows the operator's signed change. Changing `issue` to `work` in the preview lists the threads that have marks. Reset to default brings back the shipped rules. Verify on phone and desktop.

## Enhancement Requests

- Labels or colors per unlocked tag (for example, a friendlier button name than the tag). Disposition: out of scope; the record format can grow later.
- Non-operator proposals for new tagsets. Disposition: noted; Option C's idea could return as a follow-up.

## Study Checkpoint

2026-11-18T02:55:00Z; scope v1; starting revision `92ef92501`; reference R1 (frozen hashes as recorded in the origin ledger; local copies present). Claude, single exposed rater; tagsets code, the Tools page and the feature-flag write path were inspected before this snapshot, so no pristine intake is claimed. Recommended Option A: projected total **0.50**. Alternatives B 0.70 and C 0.75, non-additive. Not hours or measured labor; no approval to advance.

Status: Approved Step 1 (maintainer, 2026-11-22).

*— Claude · 2026-11-18 UTC · Model: claude-opus-5-5*
