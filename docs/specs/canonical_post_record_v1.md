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
- `Body-Format`: optional, `markdown` or `plain`. Absent means plain, which is how every record written before this header existed renders. New posts made through the composer and the `create_*`/`prepare_*` routes record it, defaulting to `markdown` (`plain` for QDB quotes); the `body_format` input overrides the default. In client-built signed records it follows `Subject` (threads) or `Author-Identity-ID` (replies).
- `Theme`: optional, on thread roots and replies. A lowercase theme name (`[a-z0-9][a-z0-9_-]*`, at most 40 characters) or `none`. It sets the thread's page theme the same way a `theme:` line in the body does, without showing in the text; `none` clears it. Whether it applies (the thread's author or a current operator, a theme the site permits, the latest setting wins) is decided when the thread page is read, not when the record is written. Written from the `theme` input of the composer and the `create_*`/`prepare_*` routes. In client-built signed records it follows `Body-Format`, or takes its place when `Body-Format` is absent.

## Typed Root Headers

Typed root metadata is reserved for thread roots. Replies must not include `Thread-Type`
or subtype-specific headers.

The first supported typed root is `Thread-Type: task`. Task roots add:

- `Task-Status`: short task state label such as `proposed`
- `Task-Presentability-Impact`: decimal rating from `0` to `1`
- `Task-Implementation-Difficulty`: decimal rating from `0` to `1`
- `Task-Depends-On`: optional space-separated root post IDs for task dependencies
- `Task-Sources`: optional semicolon-separated provenance references

## Event Headers

A thread root may describe an event. All five headers are optional, root-only, and
written only while event support is on; replies must not include them.

- `Event-Date`: the event's first day, `YYYY-MM-DD`
- `Event-End-Date`: the inclusive last day of a multi-day event, `YYYY-MM-DD`; requires
  `Event-Date` and must not be before it. Absent means the event is one day.
- `Event-Time`: start time, 24-hour `HH:MM`
- `Event-Location`: one line of free text naming the place
- `Event-Link`: one line, usually a URL
- `Event-When`: the poster's own words for when the event happens, such as
  `next Friday at 7`; one line of 1 to 120 characters; requires `Event-Date`. It is
  written only while the Event phrases flag is on. The dates above stay the
  authority: the calendar reads them, and the phrase is shown beside them.

Writers emit them after `Subject` in this order: `Event-Date`, `Event-End-Date`,
`Event-Time`, `Event-Location`, `Event-Link`, `Event-When`. Parsers ignore root headers
they do not know, so a release that predates `Event-End-Date` reads a span as a one-day
event on `Event-Date`, and one that predates `Event-When` shows the dates without the
phrase.

## Body Rules

- The body is plain ASCII text.
- The body may include quoting and plain links.
- The body should remain understandable without rendering.
- With `Body-Format: markdown`, readers render the post-safe subset in the [Markdown syntax reference](../reference/markdown_syntax.md); the stored body is unchanged.

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

## Portable timestamps for legacy imports

Legacy posts without a `Created-At` header may have a companion file at
`records/post-timestamps/<Post-ID>.json`. It has exactly three string fields:
`post_id`, `record_sha256` (lowercase SHA-256 of the unchanged post bytes), and
`created_at` (RFC 3339 UTC, `YYYY-MM-DDTHH:MM:SSZ`). The metadata is at most 4 KiB.
The filename must match `post_id`, and the checksum must match the post.

The repository reader uses valid companion metadata before its legacy Git/mtime
fallback. Invalid companion metadata is an error. An explicit `Created-At` header
remains authoritative. The instance importer requires companion metadata for
legacy posts, recovering it from isolated source Git history when possible; it
never uses extraction mtime or the destination import commit date. Metadata and
its legacy post are imported together and participate in conflict/recovery checks.
The original post and detached signatures are not rewritten. These dates carry
source provenance, not a new cryptographic attestation.

## Optional image reference

An image attachment is one canonical `Image-v1: <sha256> <png|jpg> <bytes>
<width> <height> <percent-encoded-description>` line in the body (all on one
line). The lowercase SHA-256 identifies immutable original bytes, independent of
host or storage provider. Numeric values are positive decimal integers; the
description is optional; omit its preceding space when absent. When present,
it is UTF-8 encoded with RFC 3986 percent encoding, at most 500 bytes before
encoding. The signed body binds all metadata. Existing text readers can preserve
the reference without image support; no read-model schema change is required.

Only one reference is supported per post. Uploads require a verified approved
author and `FORUM_IMAGE_CONTENT_ENABLED`; JPEG/PNG are limited to 5 MiB and
4096 pixels per side. Storage is outside the repository, with a separate image
backup required for restoration. Images are not public presentation assets.

## Optional agent-response metadata

Generated scripture replies use [named agent-response headers](agent_response_metadata_v1.md).
Legacy signed JSON headers remain readable without rewriting their bytes.

## Client-signed posting subset

The additive single-request posting protocol uses existing records and signatures.
Its client IDs are `thread-YYYYMMDDHHMMSS-<32 lowercase random hex>`,
`reply-YYYYMMDDHHMMSS-<32 lowercase random hex>` or
`bootstrap-YYYYMMDDHHMMSS-<32 lowercase random hex>`. The timestamp must match
`Created-At` (UTC, whole seconds). Any time is accepted; when it is more than
600 seconds from server time, the server commits a receipt beside the record
(`<record>.txt.received`, one `Received-At:` line) and sorts by the receipt.
The signed bytes are unchanged.

Threads use headers in order: Post-ID, Created-At, Board-Tags,
Author-Identity-ID, optional nonempty Subject, optional Body-Format, optional Theme.
Replies use Post-ID, Created-At, Board-Tags, Thread-ID, Parent-ID,
Author-Identity-ID, optional Body-Format, optional Theme. A record without
Body-Format is plain text. The order above is the canonical order; a record
with the same headers in another order is stored exactly as signed, because
every reader parses headers in any order. Unknown or repeated headers,
protocol-only tags and image attachments are excluded from ordinary
submissions. Existing APIs and historical records retain their existing formats.

A separate bootstrap uses the thread header order, `identity internal` tags,
subject `account bootstrap` and body `Automatic account bootstrap anchor.`.
Both records carry the same signing identity and distinct IDs. The identity
continues to point to this dedicated bootstrap, never the ordinary post.

Signed bytes must already satisfy current authored-text policy and canonical
LF/newline rules. Unicode is supported when enabled by instance policy; the
historical ASCII-only descriptions above describe the original baseline.
The server never rewrites text after signing. A record from a registered key
whose signature verifies but which breaks one of these rules is staged, outside
the record, until the author signs a fix under the same post ID; from an
unregistered key it is rejected. Detached signatures cover the entire exact
record.
