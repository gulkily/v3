> **Feature plan:** [Step 1](./post_records_step1_solution_assessment.md) · [Step 2](./post_records_step2_feature_description.md) · [Step 3](./post_records_step3_development_plan.md) · [Step 4](./post_records_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/post_records`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.85

## Original Query

When I'm viewing a post, I would like to see all the records under it so that I can view them and retract them.

## Understood Intent

The request came right after the maintainer asked how to remove a like and learned that v3 has no way to do it. A like is a signed reaction record. Once a member likes a post, the button reads "Liked" and is disabled for good.

"Records under a post" here means the canonical records that target the post. Today that set includes the post record itself, likes and flags in `records/post-reactions/`, likes and labels on a root in `records/thread-labels/`, title records, format changes, the timestamp record and any retraction receipt. Replies are not included; each reply has its own page.

The post page already shows the post record's source path, commit and signature through `source_metadata.php`. Only the post record has a retraction flow, through `retract_post_control.php`. Every reaction record says `Operation: add`, and no other operation exists.

Problem: A reader cannot see which records affect a post, and cannot take back their own like, flag or label.

Concepts touched: [reaction record](../../concepts/reaction_record.md) (changed in Options A and B); [post retraction](../../concepts/post_retraction.md) (used unchanged in A, extended in B); [source history links](../../concepts/source_history_links.md) (used unchanged: each listed record links to its source and signature); [post presentation](../../concepts/post_presentation.md) (changed: a new section on the post page). Candidate new concept: **post record list**, the records that target one post.

Related feedback: none found that asks for this directly. PF-20261103-F01 (visible likes and flags) is nearby.

## Prior art: Pollyanna

The maintainer asked for a review of how Pollyanna handles this. Reviewed at `gulkily/pollyanna` revision `7a5666d3`.

- **Everything is an item.** A like is a small signed reply, `>>parent #like`, with no other text. Its labels point back at the vote item that produced them.
- **Records sit under their target.** The item page lists replies, including vote-only replies, inside an "advanced" view (`item_page.pl`, `replies_listing`). An attributes table shows everything indexed about the item (`GetItemAttributesDialog`).
- **One retraction for every kind.** A signed reply `>>hash #remove` retracts any item, vote or post (`index_text_file.pl`). The target's author or an admin may send it. Pollyanna then drops every index row the item produced, so a removed vote's label disappears from its target. The setting `admin/token/remove` is off by default.
- **Removal is destructive.** It deletes the item's page and its index rows. It also deletes the `#remove` request itself unless `admin/logging/record_remove_action` is on.
- **Undo vote never shipped.** `doc/todo.txt` lists it, with the idea "submit another action" and an open question about decrementing counters.

What carries over: one retraction that works on any record, and records listed under their target. What does not: deleting the evidence. v3's existing retraction already keeps the signed request and a receipt, so reusing it keeps the trail Pollyanna loses.

*— Claude · 2026-11-08 UTC · Model: claude-opus-5-5*

## Shared by every option

- The post page gains a Records section listing every record that targets the post.
- Each row shows the record kind, its author, its time, and links to its source file and signature.
- Each record gets its own page at `/records/<Record-ID>`, for example `/records/thread-label-3f9c0e5d…`. The page shows the record's fields in readable form, its target with a link back, its author, its signature status and whether it is retracted. It also links to the raw file under `/source/current/` and to the signature. The Retract button lives here as well as on the list row. Added 2026-11-11 at the maintainer's request.
- The existing "Retract post" action stays where it is and also appears on the post's own row.
- A feature flag hides the section, default off, like other new features.

## Option A - retract a reaction with a signed "remove" record

**Estimate: 0.75**
Main driver: a new reaction operation that the read model, scores and moderation must all respect. Uncertainty: medium, in how flag removal interacts with moderation.

A member retracts their own like, flag or label by signing a second reaction record with `Operation: remove`. For each author, target and tag, the latest record wins. The list shows both records, so the history stays visible. The Like button turns back into "Like".

- Pro: fits the signed, append-only record model; no file is deleted.
- Pro: re-liking later just adds another record.
- Pro: offline signed intents and imports keep working without new deletion paths.
- Con: scores, board tags, the Liked view and moderation must all apply the latest-wins rule.
- Con: the list grows with every like and unlike.

## Option B - reuse the existing retraction for any record

**Estimate: 0.85**
Main driver: widening the signed retraction request and the removal path from posts to any record under `records/`. Uncertainty: medium. The removal receipt is already shared, and the 2026-11-09 retraction speed work made removal update only the affected thread. What's left is checking that each record family's readers stop counting a removed record.

v3 already has a single removal method. A signed retraction request (`Operation: retract`) deletes the target record and its signature in one commit and keeps a [removal acceptance](../../specs/removal_acceptance_record_v1.md) receipt. Operators use `./v3 delete-record`, which already accepts any canonical record, including thread labels. This option widens the signed request so it can name any record by `Record-ID`, not only a post by `Post-ID`. It adds no new record family and no new receipt format. Author and operator permissions work as they do for posts.

After a like is retracted, its record page and row show the removal notice from the receipt, the same way a retracted post's path does today. The Like button returns to "Like".

- Pro: one removal method for every record, and it is the one already built and tested.
- Pro: the receipt keeps a trail of who retracted what and when, without keeping the record itself.
- Pro: `delete-record` already covers the operator side.
- Con: the retracted like's own bytes leave the current tree. The receipt records the removal, but not the original record.
- Con: liking again creates a new record, and the old receipt stays.

## Option C - list only

**Estimate: 0.45**
Main driver: one read-only page section. Uncertainty: low.

Build the Records section without any reaction retraction. Only the post record stays retractable.

- Pro: smallest change; useful for inspection on its own.
- Con: does not let anyone take back a like, which prompted the request.

## Option D - one retraction record for any record (Pollyanna-style)

**Estimate: 0.80**
Main driver: a new record family plus a "skip retracted records" check in each reader that honors it. Uncertainty: medium, in how many readers the first slice must cover.

A member signs a retraction record that names one exact record by its `Record-ID`. Readers skip any record that has a valid retraction from its own author. Both files stay in the repository, and the list shows the retracted record struck through. This is Pollyanna's `#remove` without the deletion. Posts keep their existing retraction path.

- Pro: one Retract button works for every row in the list, not just reactions.
- Pro: retracts the exact record, so liking again simply adds a new record.
- Pro: nothing is deleted, and the evidence stays signed and committed.
- Con: every record family that honors it needs the same check.
- Con: two retraction paths exist, one for posts and one for everything else.

## Recommendation

Revised 2026-11-11 after the maintainer pointed out that v3 already has a removal method and doesn't need another: choose Option B. Option D would have added a second retraction record family next to the existing one. Option B reuses the signed retraction request, the shared removal receipt and `delete-record`, widened from posts to any record. That keeps one method across the whole site. It is also the closest match to Pollyanna's `#remove`, but here the receipt keeps the trail.

First vertical slice: the Records section on a post page, a page for each record, and retracting your own like from either place. Retraction goes through the existing path, and the Like button returns to "Like". Flags, labels and other record families follow in later slices. Step 2 decides how moderation treats a retracted flag.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

Superseded recommendations: Option A, a `remove` operation on reaction records (2026-11-08); Option D, a new generic retraction record (2026-11-08, after the Pollyanna review).

**Approval:** Approved Step 1 by the maintainer, 2026-11-11T06:27:00Z, with Option B and all three pieces in one slice: Records list, per-record pages and Retract.

## Study checkpoint

2026-11-08T16:20:00Z, `railspike-effort` / `v3/post_records`, intake and Step 1 handoff. Scope v1: Option A as recommended above. Starting revision `d1052b3ff716c55b8d6bb53118d00aa585294b0e`. Reference R1, protocol P2; frozen R1 hashes not re-verified for this checkpoint. Single exposed Claude rater. Before rating, a read-only search mapped reaction records, record directories, the post page, its source metadata and the post retraction control. Pristine intake is missing. Intake and Step 1 projected total **0.60**. Option estimates 0.60, 0.85 and 0.30 are alternatives and do not add up. No implementation, measured labor or accepted outcome. Ledger entry pending: this checkout is a fresh clone, so the ledger update waits for the maintainer's working copy.

Revision, 2026-11-08T16:30:00Z. The maintainer asked for a review of Pollyanna. Scope v2: Option D, one retraction record that names any record, with the same first slice. Projected total 0.60 → **0.65**, reason changed scope (a generic record family replaces a reaction-only operation). Option estimates now 0.60, 0.85, 0.30 and 0.65; they do not add up. Same baseline, R1, P2 and single exposed Claude rater.

Revision, 2026-11-11T04:15:00Z. The maintainer asked for a viewable page per record. Scope v3: Option D plus a shared `/records/<Record-ID>` page. Projected total 0.65 → **0.80**, reason changed scope (one new route and template, shared by every option, so each option estimate rises by 0.15: 0.75, 1.00, 0.45 and 0.80). Same baseline, R1, P2 and single exposed Claude rater.

Revision, 2026-11-11T04:35:00Z. The maintainer asked whether this reuses the existing removal method and said a new one is not needed. Scope v4: Option B, the existing signed retraction and `delete-record` widened to any record, plus the per-record page. Projected total 0.80 → **0.85**, reason changed scope (recommended option changed). Option B re-estimated from 1.00 to 0.85, reason new information: the removal receipt is already shared and targeted removal landed on 2026-11-09. Option estimates now 0.75, 0.85, 0.45 and 0.80. Same baseline, R1, P2 and single exposed Claude rater.

## Enhancement Requests

- Operator retraction of other members' reactions, matching `viewerCanRetractOthers` for posts. Disposition: Step 2 decides; otherwise a follow-up cycle.
- Per-instance retraction mode, raised by the maintainer on 2026-11-10 after confirming Pollyanna undoes a vote with `#remove`. There would be three levels: keep both records and strike the retracted one through (Option D's default), delete the target from the current tree (git history keeps it), or purge it from history too. Disposition: superseded by the 2026-11-11 choice of Option B, which ships the delete-from-tree level with a receipt, matching existing post retraction. A keep-both level would need its own cycle, and could become a feature flag for presets such as a high-auditability board. Related brainstorm: https://zenmemes.com/threads/thread-20261110171842-0dece695
- Show replies in the Records section. Disposition: out of scope; replies already have their own pages.
