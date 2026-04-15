# PHP ASCII Restrictions Current State V1

This document describes how the `v3` application currently handles ASCII restrictions in April 2026. It is intended as a factual brief for discussion with an advisor, not as a proposal for what the policy should become.

## Executive Summary

The current system is built around an ASCII-only canonical write contract:

- canonical posts are stored as ASCII text files
- canonical identity bootstrap records are stored as ASCII text files
- canonical public-key files are stored as ASCII-armored text files
- several identifiers are restricted further to ASCII token subsets

In practice, this means the backend remains the final enforcement point. The browser adds a limited compose-time compatibility layer for a few common punctuation characters, but it does not change the underlying storage rule.

The result is a hybrid system:

- repository state is intentionally ASCII-only
- the browser tries to smooth over a narrow class of accidental non-ASCII input
- unsupported non-ASCII content is still blocked from submission

## Core Policy Boundary

The key architectural decision is that canonical repository artifacts are plain ASCII files.

This is stated directly in the current specs:

- [docs/specs/canonical_post_record_v1.md](/home/wsl/v3/docs/specs/canonical_post_record_v1.md:1): one post file is one ASCII text record
- [docs/specs/identity_bootstrap_record_v1.md](/home/wsl/v3/docs/specs/identity_bootstrap_record_v1.md:1): one identity bootstrap file is one ASCII text record
- [docs/specs/public_key_storage_v1.md](/home/wsl/v3/docs/specs/public_key_storage_v1.md:1): one public-key file stores one ASCII-armored OpenPGP key

So the current question is not whether the system stores Unicode canonically. It does not. The only question is how much normalization and recovery the application provides before backend enforcement rejects non-ASCII content.

## Where ASCII Is Enforced Today

The main enforcement logic lives in [src/ForumRewrite/Write/LocalWriteService.php](/home/wsl/v3/src/ForumRewrite/Write/LocalWriteService.php:1).

There are three different restriction levels in that file.

### 1. Printable ASCII line fields

`normalizeAsciiLine()` applies to single-line human text fields such as thread subjects:

- trims leading and trailing whitespace
- allows only bytes in the printable ASCII range `0x20` through `0x7E`
- rejects everything else with `"<field> must be printable ASCII."`

Current use:

- `subject` in `createThread()`

Important consequence:

- tabs, newlines, smart punctuation, accented letters, Cyrillic, emoji, and all other non-ASCII characters are rejected

### 2. ASCII token fields

`requireAsciiToken()` applies to structured identifiers and route-like values:

- trims whitespace
- requires a non-empty token
- only allows `A-Z a-z 0-9 . _ : -`
- rejects anything else with `"<field> is required and must be an ASCII token."`

Current use includes:

- `thread_id`
- `parent_id`
- `bootstrap_post_id`
- `target_profile_slug`
- `author_identity_id` indirectly through identity validation

Important consequence:

- these fields are more restricted than general printable ASCII
- spaces are not allowed
- punctuation is heavily constrained

### 3. ASCII body fields

`normalizeAsciiBody()` applies to multiline text bodies and armored public keys:

- normalizes CRLF to LF
- trims outer whitespace
- requires a non-empty value
- allows LF plus printable ASCII bytes
- rejects other bytes with `"<field> must be ASCII only."`
- appends one trailing LF before storage

Current use includes:

- post `body`
- reply `body`
- `public_key`

Important consequence:

- ordinary multiline text is allowed, but only in ASCII
- non-ASCII letters and symbols are rejected even when the field is conceptually “free text”

## Field-By-Field Current State

### Thread creation

In `createThread()`:

- `board_tags` are normalized by `normalizeBoardTags()`
- `subject` must pass `normalizeAsciiLine()`
- `body` must pass `normalizeAsciiBody()`
- `author_identity_id` must pass retained OpenPGP identity rules

`board_tags` are a special case. They are not rejected as raw ASCII/non-ASCII in the same way as `subject` and `body`. Instead they are normalized aggressively:

- lowercased
- stripped to `a-z`, `0-9`, spaces, and hyphens
- whitespace collapsed
- defaulted to `general` if emptied out

This means `board_tags` are not merely ASCII-only. They are reduced to a small canonical subset.

### Reply creation

In `createReply()`:

- `thread_id` and `parent_id` must be ASCII tokens
- `body` must be ASCII-only multiline text
- `board_tags` go through the same restrictive normalization as thread creation

Replies do not have a `subject` field in the current implementation.

### Identity linking

In `linkIdentity()`:

- `public_key` must pass `normalizeAsciiBody()`
- the fingerprint is normalized to uppercase ASCII hex
- the stored identity ID uses lowercase ASCII hex
- the username is normalized separately, not accepted raw

The username normalization lives in [src/ForumRewrite/Security/OpenPgpKeyInspector.php](/home/wsl/v3/src/ForumRewrite/Security/OpenPgpKeyInspector.php:1):

- lowercases the source user ID
- converts runs of non `[a-z0-9._-]` characters to `-`
- trims leading and trailing `-`
- falls back to `guest`

So username handling is not “preserve ASCII if possible.” It is canonicalization into a narrow ASCII slug.

### Approval and identity-reference flows

Approval-related fields such as `approver_identity_id`, `target_identity_id`, `thread_id`, `parent_id`, and `target_profile_slug` are all constrained to retained ASCII token forms.

In addition, `requireOpenPgpIdentityId()` further narrows identity IDs:

- must start with `openpgp:`
- fingerprint must be lowercase hex only
- referenced identity file must exist

## Browser Behavior Today

The browser-side compose logic lives in [public/assets/browser_signing.js](/home/wsl/v3/public/assets/browser_signing.js:1).

It does not make the system Unicode-capable. It only provides a narrow compatibility layer ahead of backend enforcement.

### Safe automatic replacements

`normalizeComposeAscii()` automatically rewrites a small fixed set of common characters:

- left single quote `‘` to `'`
- right single quote `’` to `'`
- left double quote `“` to `"`
- right double quote `”` to `"`
- en dash `–` to `-`
- em dash `—` to `-`
- ellipsis `…` to `...`
- non-breaking space to ordinary space

This is intentionally narrow. It is punctuation cleanup, not general transliteration.

### Unsupported character detection

After that replacement pass, any remaining non-ASCII character is treated as unsupported by compose validation.

Current compose behavior:

- unsupported characters remain visible in the field while the user types
- the field gets an inline error
- the form gets a compose-level error
- submit is blocked until unsupported characters are removed
- each affected field exposes an explicit `Remove unsupported characters` action

This is now a preservation-and-recovery flow, not a silent deletion flow.

### Draft preservation

The compose script also stores drafts in `localStorage` for user-editable fields. This is a resilience feature, not an encoding-policy feature, but it affects how ASCII restrictions are experienced:

- unsupported text can remain in the draft
- reload does not inherently destroy that text
- the user can recover and edit before cleanup

## Important Distinction: Browser Policy Versus Backend Policy

The browser currently implements a friendlier policy than the backend, but only at the edges.

Browser policy:

- automatically normalize a tiny set of punctuation
- preserve other unsupported characters in the form
- explain the problem inline
- require explicit cleanup before submit

Backend policy:

- reject non-ASCII content for canonical text fields
- reject non-token characters for token fields
- store only ASCII canonical artifacts

This means the browser is a UX layer around the ASCII rule. It is not the source of truth.

If browser JavaScript fails to load or is bypassed, the backend still enforces the ASCII restrictions directly.

## What “ASCII-Only” Means In Practice Here

The phrase “ASCII-only” is doing multiple jobs in the current system.

### Storage format

Canonical records are stored as plain text files that are meant to be:

- easy to inspect
- easy to diff in git
- simple to parse deterministically

ASCII is part of that simplification strategy.

### Canonicalization strategy

The system prefers normalized, low-variance textual forms:

- lowercase slugs for usernames
- lowercase or uppercase hex fingerprints depending on field
- restricted token alphabets for identifiers
- LF-normalized bodies

So ASCII is not just an encoding preference. It is tied to the broader desire for canonical low-ambiguity text.

### Product constraint

For end users, ASCII-only means:

- ordinary English punctuation often works after browser cleanup
- many non-English writing systems cannot be posted directly
- unsupported text can be drafted, but not submitted as-is

That is a significant product limitation, not just a storage detail.

## Places Where The Policy Is Uneven

The current implementation is not one perfectly uniform ASCII policy. It is a collection of related restrictions.

Examples:

- `subject` uses printable ASCII validation
- `body` uses multiline ASCII validation
- `board_tags` use a narrower normalization-and-strip rule
- identifiers use ASCII token validation
- usernames are slugified from user IDs rather than accepted literally
- compose pages get browser-side punctuation cleanup, but the backend itself does not normalize those characters

This matters for advisor discussion because “the app is ASCII-only” is true, but incomplete. The real system is a layered set of different ASCII-derived contracts.

## Current Benefits Of The Restriction

From an engineering standpoint, the current approach buys:

- simple plain-text canonical records
- deterministic parsing rules
- stable git diffs
- fewer encoding edge cases in repository-backed state
- simple identifier normalization rules

These benefits are real and currently embedded in the implementation.

## Current Costs Of The Restriction

From a product and UX standpoint, the current approach costs:

- inability to canonically store non-English text
- friction when mobile keyboards emit smart punctuation
- compose-time recovery logic that would be unnecessary in a Unicode-native system
- policy leakage from storage format into user experience
- need for special-case normalization and cleanup affordances

These costs are also real and visible in the current compose flow.

## Questions This Document Does Not Answer

This document does not recommend whether the system should remain ASCII-only. It only describes current behavior.

Questions for advisor discussion include:

- Should canonical posts remain ASCII-only?
- If yes, is the current browser normalization/recovery layer sufficient?
- If no, which record families should move to UTF-8 first?
- Are identifiers and usernames appropriately constrained today, or overly normalized?
- Is the current “simple git-friendly text” goal worth the product limitation on multilingual content?

## Bottom Line

Today, `v3` treats ASCII as a canonical storage rule, not just an implementation detail.

The browser softens that rule for a narrow set of punctuation and provides recovery UX for unsupported characters, but the backend still rejects non-ASCII canonical content. In other words:

- Unicode may appear temporarily in compose state
- Unicode does not survive into canonical post or identity storage
- token-like fields are restricted even more tightly than general ASCII text

That is the current state of the system.
