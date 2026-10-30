> **Feature plan:** [Step 1](./unified_reaction_records_step1_solution_assessment.md) · [Step 2](./unified_reaction_records_step2_feature_description.md) · [Step 3](./unified_reaction_records_step3_development_plan.md) · [Step 4](./unified_reaction_records_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/unified_reaction_records; [standing enrollment P2](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
Relationship: separate follow-up to [signed_thread_label_records](../signed_thread_label_records/signed_thread_label_records_step4_implementation_summary.md)
Status: Step 1 Option A approved; Step 2 proposed; implementation not authorized
**Cycle estimate: 0.75**

# Unified Reaction Records Step 2 Feature Description

## Problem

Thread labels and post reactions record the same kind of event in two formats, so every producer, signer, verifier and reader keeps two parallel paths, and a record does not state its own target kind apart from where it is stored.

Concepts touched: *reaction record* (proposed, created: one tag event on a thread or post, its scope, signature companion and legacy forms); thread label and post reaction (changed: become the two scopes of a reaction record); [effective tags](../../concepts/effective_tags.md) (used unchanged).

## User Stories

- As a participant, I want Like, Flag, tag and QDB votes to work exactly as before so that the format change is invisible to me.
- As a reader or operator, I want each reaction record to state whether it targets a thread or a post so that I can understand it without knowing which directory it came from.
- As a steward moving a community, I want existing signed and unsigned records to import, verify and count exactly as before so that no history is lost or re-signed.

## Core Requirements

- **One format for every new record.** Every producer of thread labels and post reactions (online buttons, signed and keyless; hashtag-derived labels; offline signed-intent replay; operator scripts) writes one canonical `Key: value` format that names the target kind (thread or post), the target ID and the tags. Optional fields (author, reason, action time, intent, hashtag source) keep their meaning; the hashtag source applies only to thread scope.
- **Scope matches location.** Records stay in their existing directories with their existing ID prefixes. A record whose declared scope disagrees with its directory is rejected everywhere it is read, never re-routed.
- **Legacy records read as stored.** Both existing header sets remain valid indefinitely. No existing file is rewritten, renamed or re-signed; signed legacy records verify against their original bytes.
- **Behavior unchanged per scope.** Thread-scope records keep labeling, board tags, thread scores and root-only eligibility; post-scope records keep flags, post scores and moderation effects. Duplicate detection treats a legacy and a new-format record from the same author, target and tag as the same vote.
- **Signatures, imports and activity cover both forms.** Browser signing, server verification, the clock-skew fallback, import/restore/archive pairing, signature audit and activity/source detail accept the new form alongside the legacy forms with the same verified/unsigned/invalid outcomes.

## Delivery Scope

**Work type:** application change, with both format specs, the signed-reaction submission spec and the effort-study ledger updated. No data migration, new directory or read-model schema change. Older application versions are not changed: an instance on older code that receives a new-format record rejects it as unparseable rather than miscounting it; this limitation is documented.

## Completion Boundary

Enter through existing thread, post/reply and QDB voting/tag controls and through posting with hashtags. Each saves one new-format record (with its signature when a key is available) that counts and displays as before after refresh, full rebuild, import and archive restore. Recovery: clock-skew fallback, interrupted-write recovery and retries behave as today without double counting. Release requires focused format/compatibility tests, the existing signed-reaction browser checks against the new bytes, and the full suite with no failures beyond the recorded baseline.

## Risks

- **Signed byte mismatch** (signed votes fail): the browser and server must build identical new-format bytes. Validate first by tracing the browser builder and server verifier; mitigate with shared fixtures used by both.
- **Double counting across forms** (inflated scores): a retry or duplicate check that only knows one header set could admit a second vote. Validate by listing every duplicate/ownership check; mitigate by having all of them read through the unified parser.
- **Raw-header readers** (silent omissions): code that reads `Labels`/`Tags` or `Thread-ID`/`Post-ID` directly, rather than through the parsers, would skip new records. Validate early with a repository-wide inventory; mitigate by routing those through the parser.
- **Cross-version imports** (older instances reject new records): cannot be fixed without changing old code. Mitigate by documenting it and keeping the rejection explicit.

## Shared Component Inventory

- **Format:** extend the two existing parsers/records into one shared reaction parser and record type; legacy header sets map onto it. No new component beyond that shared parser.
- **Producers:** extend `LocalWriteService` builders (thread tag, post tag, signed reaction, hashtag labels) and the browser builder in `thread_reactions.js`; reuse the existing signing, skew-fallback and recovery flow.
- **Readers:** reuse full and incremental read-model builders, viewer-tag lookup, signature index and verified attribution through the parser; extend raw-header readers (activity detail, `audit_post_signatures.php`, `thread_attributes.php`, `archive_thread.php`, `delete_record.php`, high-traffic safety) where they bypass it.
- **Lifecycle:** reuse import planner, archive catalog, label ownership/removal and removal receipts unchanged in path rules; confirm they accept new-format contents.
- **Surfaces:** HTML, Forte, text API, offline snapshot and static artifacts render from the read model and need no change; verify output parity.

## Simple User Flow

1. Select Like, Flag, a QDB vote or a tag, or post with a hashtag.
2. A new-format record is saved, signed when a key is available.
3. Counts, tags and moderation update as before.
4. Activity/source detail shows the record, its scope and its signature status.

## Success Criteria

- Every producer writes the new format; no producer writes a legacy header set.
- Existing fixture repositories (signed and unsigned, both families) give identical scores, tags, moderation outcomes and signature status before and after the change, through full and incremental rebuild.
- New-format signed records verify in the browser flow, including the skew fallback; altered bytes or mismatched scope are rejected.
- A legacy and a new-format vote from the same author on the same target and tag count once.
- Import, archive restore and signature audit handle mixed legacy/new repositories without loss.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Approval and study checkpoint

Checkpoint: Step 2 handoff, 2026-10-30T01:58:00Z; 0.75 reaffirmed
Scope: v1 / Option A; baseline 60d17174ceef711479a0cd3b20ad56d885a0f012; R1 / P2
Rater: Claude (claude-opus-5-5), single exposed rater; Step 1 reference verification retained; additional reader inventory inspected (most readers go through the two parsers; about six raw-header readers). New understanding offsets: hashtag and offline producers are in scope (up), parser-centered reads (down). No scope change.
Authorization: Approved Step 1 received (Option A). No enhancement requests. Awaiting Approved Step 2; no implementation or commits.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*
