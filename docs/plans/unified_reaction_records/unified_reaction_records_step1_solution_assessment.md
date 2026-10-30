> **Feature plan:** [Step 1](./unified_reaction_records_step1_solution_assessment.md) · [Step 2](./unified_reaction_records_step2_feature_description.md) · [Step 3](./unified_reaction_records_step3_development_plan.md) · [Step 4](./unified_reaction_records_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/unified_reaction_records; [standing enrollment P2](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
Relationship: separate follow-up to [signed_thread_label_records](../signed_thread_label_records/signed_thread_label_records_step4_implementation_summary.md)
Status: Step 1 proposed; implementation not authorized
**Cycle estimate: 0.75**

# Unified Reaction Records Step 1 Solution Assessment

## Original Query

From the todo.txt I pulled this; let's work on it:

```text
unify thread-label and post-reaction record formats
    use one canonical format with explicit thread/post target scope
    preserve distinct labeling, scoring, voting, and moderation behavior
    keep existing signed and unsigned records compatible without rewriting signed bytes
    account for imports, activity, signature companions, and duplicate detection
    separate follow-up to signed_thread_label_records
```

## Problem Statement

[Thread labels](../../specs/thread_label_record_v1.md) and [post reactions](../../specs/post_reaction_record_v1.md) are the same event (an author adds tokens to a target) written in two formats that differ only in target header, token header, directory and ID prefix, so every producer, verifier and reader carries two parallel paths.

Concepts touched: thread label and post reaction (proposed entry: *reaction record*, created); [effective tags](../../concepts/effective_tags.md) used unchanged.

## Option A: One format, existing directories

**Estimate: 0.75**

New records in both directories use one header set that names its scope, for example `Target-Kind: thread` or `post`, `Target-ID`, and `Tags`. The directory still routes behavior; the parser rejects a record whose declared scope disagrees with its directory. One parser reads the new format and the two legacy header sets, so existing signed and unsigned files are read exactly as stored.

- Pros: additive change; paths, import/archive catalogs and removal receipts keep their path rules; duplicate detection stays per target kind; signed legacy bytes untouched.
- Cons: scope is stated twice (header and directory); two directories remain.
- Driver: browser signing and server verification for the new bytes, plus legacy aliasing in one parser. Range 0.60–0.90.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Option B: One format, one new directory

**Estimate: 1.10**

New records of both kinds go to a single new family (for example `records/reactions/`) using the same scoped header set. The two old directories become read-only legacy families that every reader, importer, archive catalog, removal receipt and duplicate check must still scan.

- Pros: a single place for new records; scope lives only in the record.
- Cons: three families to read indefinitely; duplicate detection must span old and new directories; new path rules in imports, archives, high-traffic safety, removal receipts and scripts.
- Driver: path-dependent consumers across import, activity, removal and maintenance. Above 1.0, so it would need splitting.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Option C: Shared code, unchanged files

**Estimate: 0.40**

Keep both on-disk formats and replace the two parsers, record types, builders and duplicate checks with one shared implementation parameterized by kind.

- Pros: no format or compatibility risk; removes most duplicated code.
- Cons: does not deliver "one canonical format"; the record still does not state its scope independently of its location.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Recommendation

Option A. It delivers one canonical, self-describing format by adding to the existing ones, with no migration and no new directory, consistent with "change a format by adding to it" in [PRINCIPLES.md](../../../PRINCIPLES.md#keep-our-own-backward-compatibility). It is a releasable vertical slice: new Like, Flag, tag and QDB votes save in the new format, signed and unsigned, and existing records keep their counts, moderation effects and signature status through refresh, rebuild, import and restore.

Points to settle in Step 2: exact header names; whether hashtag-derived labels (`Source-Post-ID`/`Source-Post-SHA256`) move to the new format; whether the offline signed-intent path writes it; and how older code reading a new-format record should fail.

## Study checkpoint

Checkpoint: intake and Step 1 handoff, 2026-10-30T01:32:39Z
Scope: v1 / Option A; baseline 60d17174ceef711479a0cd3b20ad56d885a0f012; R1 / P2
Intake: 0.90, recorded after reading the todo item, workflow and study docs, before source inspection.
Projection: 0.75 whole scoped cycle for Option A. Lowered from intake after finding the two formats nearly identical and the path-dependent consumers (import, archive, removal receipts) unaffected under A. Alternatives B 1.10 and C 0.40 are complete-cycle estimates for different outcomes, not additive.
Rater: Claude (claude-opus-5-5), single exposed rater; predecessor plans, both specs, parsers, record builders and duplicate checks inspected before the handoff rating. All three frozen R1 hashes verified.
Authorization: User asked to work on the todo item; awaiting Approved Step 1. Planning and study records uncommitted.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*
