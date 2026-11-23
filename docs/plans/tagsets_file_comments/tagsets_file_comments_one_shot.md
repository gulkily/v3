# Tagsets file comments

Cycle: `v3/tagsets_file_comments`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_ledger/tagsets_file_comments.md). Standing P2 enrollment. Follow-up to `v3/editable_tagsets` (PR #369, merged).

Extended estimates: disabled (one approach, no stages)

**Cycle estimate: 0.03 (intake after reading the file)**

Status: plan written; next permitted action: planning commit, then implementation.

## Original request

Maintainer, 2026-11-22, after PR #369 merged: "can you please improve the explanation comments in the file, they're very hard to undersatnd". Taken to mean `resources/tagsets.txt`, whose comments operators now see in the Tag Unlocks editor.

## Authorization

The request above covers rewriting those comments through an open PR. It does not cover changing the rules, the format or the parser.

## Outcome and work type

Documentation-only change to a shipped configuration file. The comments say what a block does in plain words, give each key's default, and show a worked example. The rule itself (`issue` unlocks claimed, resolved, closed, open on replies and the opening post, exclusive) is unchanged.

## Approach and risks

Rewrite the comment lines only.
- Risk: a comment line could change the parsed rules or fail `Tagsets::check()`. Mitigation: every new line starts with `#`; `TagsetsTest` parses and checks the shipped file.
- Risk: a stated default could be wrong. Mitigation: the defaults come from the code (`Target` defaults to `reply` in `Tagsets::parse`; `Exclusive` is no unless `yes`).

## Reuse decisions

No code. The key list matches `Tagsets::KEYS`.

## Completion conditions

- The file parses to the same rules, and `Tagsets::check()` reports no errors.
- An operator who opens Edit on a new instance can read the comments without the code.
- Release: PR open; the maintainer merges.

## Concepts touched

- [tagset](../../concepts/tagset.md): used; unchanged.

## Verification and recovery plan

- `./v3 test --smoke TagsetsTest TagUnlocksTest`. Runtime UI checks are not applicable: the page shows this text unchanged inside a text box.
- Full suite not run.

## Starting revisions and branch

Branch `tagsets-file-comments` from `origin/main` at `8f106c3c`.

Planning commit: pending.

## Dated execution history

- 2026-11-23: record written.

## Study history

- 2026-11-23T00:55Z intake **0.03**, scope v1, R1, P2. Single exposed Claude rater; the file and its parser were read before this estimate. Not hours.

## Enhancement requests

None yet.

*— Claude · 2026-11-23 UTC · Model: claude-opus-5-5*
