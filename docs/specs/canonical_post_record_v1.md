# Canonical Post Record V1

This document defines the canonical post record shape for the current implementation slice.

## Scope

- One ASCII text file represents one post.
- Canonical post files live in `records/posts/`.
- Sample files use the filename stem as the local `Post-ID`.
- This slice defines thread roots, replies, board tags, and typed root-thread metadata.

## File Structure

A post file has two parts:

1. A contiguous header block at the top of the file
2. A body separated from the headers by one blank line

Headers use this form:

```text
Key: Value
```

## Required Headers

- `Post-ID`: unique identifier for the post within the repository
- `Created-At`: RFC 3339 UTC timestamp such as `2026-04-10T12:00:00Z`
- `Board-Tags`: space-separated board tags

## Conditional Headers

- `Thread-ID`: required for replies, omitted for thread roots
- `Parent-ID`: required for replies, omitted for thread roots
- `Author-Identity-ID`: optional canonical identity reference for authored posts
- `Subject`: optional, mainly useful for thread roots
- `Thread-Type`: optional for thread roots, omitted for replies

## Typed Root Headers

Typed root metadata is reserved for thread roots. Replies must not include `Thread-Type`
or subtype-specific headers.

The first supported typed root is `Thread-Type: task`. Task roots add:

- `Task-Status`: short task state label such as `proposed`
- `Task-Presentability-Impact`: decimal rating from `0` to `1`
- `Task-Implementation-Difficulty`: decimal rating from `0` to `1`
- `Task-Depends-On`: optional space-separated root post IDs for task dependencies
- `Task-Sources`: optional semicolon-separated provenance references

## Body Rules

- The body is plain ASCII text.
- The body may include quoting and plain links.
- The body should remain understandable without rendering.

## Thread Semantics

- A thread root is a post with no `Thread-ID` and no `Parent-ID`.
- A reply references the thread root through `Thread-ID`.
- A reply references its immediate parent through `Parent-ID`.
- `Created-At` is authoritative canonical time for the post and must not be inferred from file mtimes or git history.
- When `Author-Identity-ID` is present, it must be an ASCII token matching the retained identity form such as `openpgp:<lowercase-fingerprint>`.
- When `Author-Identity-ID` is absent, readers fall back to anonymous author labeling such as `guest` unless another retained rule applies.
- A typed root thread is still a normal thread root. Replies remain ordinary replies.
- For this slice, authoritative task state lives on the task root post. Future slices may
  add append-only task-update records instead of overloading replies.

## Structured Approval Replies

The retained approval/vouching slice reuses normal replies rather than inventing a separate approval record family.

Approval replies use these additional conventions:

- `Board-Tags` include both `identity` and `approval`
- `Thread-ID` must match the target profile bootstrap thread
- `Parent-ID` must match the target profile bootstrap post
- `Author-Identity-ID` identifies the approving user
- the body contains a deterministic line using the form `Approve-Identity-ID: openpgp:<lowercase-fingerprint>`

These replies are canonical posts, but V1 readers treat them as hidden from board/activity feeds because they carry the `identity` tag.

## Example Thread Root

```text
Post-ID: root-001
Created-At: 2026-04-10T12:00:00Z
Board-Tags: general meta
Subject: First thread

Hello world.
```

## Example Task Thread Root

```text
Post-ID: T01
Created-At: 2026-04-10T12:05:00Z
Board-Tags: planning
Subject: Publish raw planning files and debug views in the web UI
Thread-Type: task
Task-Status: proposed
Task-Presentability-Impact: 0.94
Task-Implementation-Difficulty: 0.34
Task-Sources: todo.txt; docs/plans/forum_feature_splitting_checklist.md

Expose todo.txt, ideas.txt, and raw object/debug views from the interface.
```

## Example Reply

```text
Post-ID: reply-001
Created-At: 2026-04-10T12:06:00Z
Board-Tags: general meta
Thread-ID: root-001
Parent-ID: root-001
Author-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954

This is a reply.
```

## Example Approval Reply

```text
Post-ID: reply-approval-001
Created-At: 2026-04-10T12:07:00Z
Board-Tags: identity approval
Thread-ID: bootstrap-20260410120000-a1b2c3d4
Parent-ID: bootstrap-20260410120000-a1b2c3d4
Author-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954

Approve-Identity-ID: openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
```
