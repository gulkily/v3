# Feedback Item Record V1

One file per requested outcome from site feedback: a feature, a bug, or other
actionable work. Items are the current authority for feedback status; dated
summaries and the production feedback register are history.

## Location and identity

- Files live in `docs/feedback/items/<ID>.txt`; the filename stem must equal `ID`.
- `ID` is `PF-YYYYMMDD-<letter><number>`: intake date, `F` for feature, `B` for
  bug, `O` for other, and a number of two or more digits. The letter must match
  `Kind`. IDs are permanent: never renumbered or reused, including after closure.

## Format

`Key: value` headers, one blank line, then a plain-text body. UTF-8, LF line
endings, trailing newline, no control characters except tab (the canonical text
rules in `GenericTextRecordParser`). Each header appears once.

Headers:

- `ID` (required)
- `Title` (required): a few words naming the outcome.
- `Kind` (required): `feature`, `bug` or `other`.
- `Workflow` (required): `untriaged`, `ready`, `planned`, `in-progress`,
  `blocked`, `done`, `declined`, `superseded` or `duplicate`. Meanings follow
  [production feedback management](../reference/production_feedback_management.md).
- `Created` (required): intake date, `YYYY-MM-DD`.
- `Evidence` (optional): `unknown` (default), `locally-verified`, `deployed` or
  `production-accepted`.
- `Estimate` (optional): a nonnegative decimal on the Railspike cycle scale, or
  `none`. Clear it when scope changes.
- `Aliases` (optional): comma-separated earlier labels, such as `2026-10-21/F01`.
- `Duplicate-Of`, `Superseded-By` (required with those workflow values, otherwise
  absent): the ID of the item that replaces this one.

Body sections, each introduced by its label alone on a line and separated by a
blank line, in this order:

- `Request:` (required): what was asked, in plain words, preserving qualifiers
  such as "maybe" or "explore".
- `Sources:` (required): one `- <url>` line per supporting post, using the site's
  `/posts/<post-id>` or `/threads/<thread-id>` URL. Several sources live here
  because headers cannot repeat.
- `Done when:` (optional): observable completion checks.
- `Notes:` (optional): current evidence, next action and links to history.

## Checks

`./v3 feedback check` reports, per file: unreadable or malformed text, unknown or
missing headers, invalid values, an `ID` that does not match its filename or
kind, a missing `Request:` or `Sources:` section, and a source line that is not a
post or thread URL. Across files it reports duplicate IDs and `Duplicate-Of` or
`Superseded-By` targets that do not exist. Any of these fails the check.

It also lists, without failing, a post or thread ID shared by two items. That is
expected when one post asked for two independent outcomes and they were split;
otherwise it points at a missed duplicate. Items with `Workflow: duplicate` or
`superseded` keep their sources and are left out of this listing.

## Example

```
ID: PF-20261103-F01
Title: Summary field on threads
Kind: feature
Workflow: untriaged
Created: 2026-11-03

Request:
In addition to the title, threads should have a summary field. Brainstorm.

Sources:
- https://zenmemes.com/threads/thread-20261103051750-7f138dfb
- https://zenmemes.com/threads/thread-20261103051823-6a0ec599
```
