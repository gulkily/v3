# Reaction Record V1

This document defines the canonical format for thread-label and post-reaction
records: one author adds one or more tags to a thread or a post. It succeeds the
separate header sets in [Thread Label Record V1](thread_label_record_v1.md) and
[Post Reaction Record V1](post_reaction_record_v1.md); records in those forms
remain valid and are read as stored.

## Scope and location

- One ASCII text file represents one additive tag event.
- Thread-scope records live in `records/thread-labels/<record-id>.txt`; post-scope
  records live in `records/post-reactions/<record-id>.txt`. The filename stem must
  match `Record-ID`, which keeps its family prefix (`thread-label-` or
  `post-reaction-`).
- The declared scope must match the directory. A mismatch is rejected, never
  re-routed.

## Format

Headers in `Key: Value` form, then one blank line, then an optional (usually
empty) body. New records use this header order:

- `Record-ID` (required)
- `Created-At` (required): RFC 3339 UTC, for example `2026-10-30T15:30:00Z`
- `Target-Kind` (required): `thread` or `post`
- `Target-ID` (required): the root thread ID for `thread`; any post or reply ID for `post`
- `Operation` (required): `add`
- `Tags` (required): space-separated tokens using lowercase `a-z`, `0-9` and single internal `-`; duplicates collapse to one
- `Author-Identity-ID` (optional): `openpgp:` and 40 lowercase hex digits
- `Action-At` (optional): RFC 3339 UTC, milliseconds allowed
- `Intent-ID` (optional): offline signed intent that produced the record
- `Source-Post-ID` and `Source-Post-SHA256` (optional, together, thread scope only): the post whose hashtags produced the label
- `Reason` (optional): short ASCII explanation

A record uses either this header set or one legacy set, never a mix:
`Thread-ID`, `Post-ID` and `Labels` are rejected alongside `Target-Kind` or
`Target-ID`.

## Example

```text
Record-ID: thread-label-3f9c0e5d7a1b4c2e8f6a0b1c2d3e4f50
Created-At: 2026-10-30T15:30:00Z
Target-Kind: thread
Target-ID: root-001
Operation: add
Tags: like
Author-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954

```

## Behavior by scope

Scope selects behavior; the format does not change it. Thread scope keeps
labeling, board tags, thread scores and root-only eligibility. Post scope keeps
flags, post scores and moderation effects. A legacy record and a new record from
the same author, target and tag are the same vote.

## Signatures

A detached ASCII-armored OpenPGP signature may accompany the exact bytes as
`<record-id>.txt.asc`, verified against `Author-Identity-ID` as described in
[Signed Reaction Submission V1](signed_reaction_submission_v1.md). Legacy signed
records are verified over their original bytes and are never rewritten.

## Compatibility

Legacy header sets are deprecated. They are read only through
`LegacyReactionHeaders`, which also accepts the exact legacy bytes signed by
older cached browser clients. No producer writes them. Removing legacy support
later means deleting that class and its callers; what then happens to existing
legacy records, especially signed ones, is a separate decision.

Application versions released before this format read only the legacy header
sets. They reject a new-format record as unparseable rather than counting it;
importing new records into such an instance requires upgrading it first.
