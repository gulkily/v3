# Thread Label Record V1

This document defines the canonical append-only thread-label record family for the current PHP implementation slice.

## Scope

- One ASCII text file represents one thread-label mutation event.
- Canonical thread-label files live in `records/thread-labels/`.
- V1 supports additive label events only.

## File Structure

A thread-label file has two parts:

1. A contiguous header block at the top of the file
2. A body separated from the headers by one blank line

Headers use this form:

```text
Key: Value
```

The body is optional and is usually empty in V1.

## Required Headers

- `Record-ID`: unique identifier for the thread-label record
- `Created-At`: RFC 3339 UTC timestamp such as `2026-04-15T15:30:00Z`
- `Thread-ID`: target root thread ID
- `Operation`: `add`
- `Labels`: space-separated validated label tokens

## Optional Headers

- `Author-Identity-ID`: canonical identity reference for the labeling author
- `Reason`: short ASCII explanation

## Label Token Rules

Each label token must:

- use ASCII only
- use lowercase only
- contain only `a-z`, `0-9`, and single internal `-`
- not begin or end with `-`

Valid examples:

- `bug`
- `needs-review`
- `v1`

Rejected examples:

- `Needs-Review`
- `needs_review`
- `customer bug`
- `-urgent`

## Semantics

- `Thread-ID` must target a root thread
- `Created-At` is authoritative canonical time for ordering
- `Operation` is limited to `add` in V1
- duplicate labels in one record are tolerated and collapse to one normalized label in parser and reducer behavior
- when `Author-Identity-ID` is present, it must use the retained lowercase OpenPGP identity form

## Canonical Path Rule

- the file path must be `records/thread-labels/<record-id>.txt`
- the filename stem must exactly match `Record-ID`

## Example

```text
Record-ID: thread-label-20260415153000-ab12cd34
Created-At: 2026-04-15T15:30:00Z
Thread-ID: root-001
Operation: add
Labels: bug needs-review
Author-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954
Reason: Triage labels applied

```
