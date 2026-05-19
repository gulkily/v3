# Post Reaction Record V1

This document defines the canonical append-only post-reaction record family for post and comment flagging.

## Scope

- One ASCII text file represents one post-reaction mutation event.
- Canonical post-reaction files live in `records/post-reactions/`.
- V1 supports additive tag events only.

## File Structure

A post-reaction file has two parts:

1. A contiguous header block at the top of the file
2. A body separated from the headers by one blank line

Headers use this form:

```text
Key: Value
```

The body is optional and is usually empty in V1.

## Required Headers

- `Record-ID`: unique identifier for the post-reaction record
- `Created-At`: RFC 3339 UTC timestamp such as `2026-04-15T15:30:00Z`
- `Post-ID`: target post or comment ID
- `Operation`: `add`
- `Tags`: space-separated validated tag tokens

## Optional Headers

- `Author-Identity-ID`: canonical identity reference for the reacting author
- `Reason`: short ASCII explanation

## Tag Token Rules

Each tag token must:

- use ASCII only
- use lowercase only
- contain only `a-z`, `0-9`, and single internal `-`
- not begin or end with `-`

## Semantics

- `Post-ID` may target a root post or a reply/comment.
- `Created-At` is authoritative canonical time for ordering.
- `Operation` is limited to `add` in V1.
- duplicate tags in one record are tolerated and collapse to one normalized tag in parser and reducer behavior.
- when `Author-Identity-ID` is present, it must use the retained lowercase OpenPGP identity form.

## Canonical Path Rule

- the file path must be `records/post-reactions/<record-id>.txt`
- the filename stem must exactly match `Record-ID`

## Example

```text
Record-ID: post-reaction-20260415153000-ab12cd34
Created-At: 2026-04-15T15:30:00Z
Post-ID: reply-001
Operation: add
Tags: flag
Author-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954
Reason: Approved user flagged reply-agent content

```
