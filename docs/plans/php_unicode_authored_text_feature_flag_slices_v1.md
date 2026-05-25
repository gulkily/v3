# PHP Unicode Authored Text Feature Flag Slices V1

This document outlines an atomic implementation plan for supporting readable non-Latin authored text, such as Cyrillic, while preserving inspector-friendly canonical records and rejecting deceptive Unicode.

## Goal

Allow human-authored post prose to use visible, normalized Unicode when an explicit feature flag is enabled.

The change should support non-Latin alphabets in fields users read as prose, while keeping machine identifiers ASCII-only and continuing to reject invisible, control, and direction-manipulation characters.

## Product Decision

The design frame for this change is:

- add a server-side feature flag, tentatively `FORUM_UNICODE_AUTHORED_TEXT=true`
- keep the default behavior ASCII-only until the flag is enabled
- allow Unicode only in authored prose fields:
  - `subject`
  - `body`
- keep machine-readable fields ASCII-only:
  - `post_id`
  - `thread_id`
  - `parent_id`
  - `board_tags`
  - profile slugs
  - identity IDs
  - routes and record paths
- normalize accepted Unicode prose to NFC before writing canonical records
- reject unsafe or non-transparent Unicode even when the flag is enabled
- do not transliterate Cyrillic, Greek, or other non-Latin scripts into ASCII

The intended rule is:

> Human-authored prose may be Unicode if every character is valid, visible, normalized, and inspectable. Machine identifiers remain ASCII tokens.

## Current State

Today:

- `LocalWriteService` enforces ASCII for authored subject and body text through `normalizeAsciiLine()` and `normalizeAsciiBody()`
- browser compose normalization rewrites a narrow punctuation set and approved Latin diacritics into ASCII
- remaining non-ASCII characters are treated as unsupported and can be explicitly removed
- canonical post records are effectively ASCII-only for new writes
- route identifiers, tags, profile slugs, and identity IDs are already constrained as ASCII tokens

Relevant current-state references:

- [docs/plans/php_non_ascii_compose_autocorrection_slices_v1.md](/home/wsl/v3/docs/plans/php_non_ascii_compose_autocorrection_slices_v1.md:1)
- [docs/plans/php_latin_diacritic_transliteration_slices_v1.md](/home/wsl/v3/docs/plans/php_latin_diacritic_transliteration_slices_v1.md:1)
- [docs/plans/php_latin_diacritic_transliteration_policy_v1.md](/home/wsl/v3/docs/plans/php_latin_diacritic_transliteration_policy_v1.md:1)

## Unicode Safety Policy

When the feature flag is enabled, authored prose may contain:

- valid UTF-8 only
- Unicode letters, including non-Latin letters
- Unicode combining marks after normalization
- Unicode decimal digits
- ordinary visible punctuation from an approved set or category policy
- plain spaces
- newlines in bodies

Authored prose must reject:

- invalid UTF-8
- control characters other than allowed line breaks
- zero-width characters
- bidirectional controls, overrides, isolates, and embeddings
- private-use characters
- surrogates
- noncharacters
- unassigned code points
- format characters
- emoji and pictographic symbols unless a later policy explicitly allows them

The policy should be implemented server-side as the authority. Browser validation is an early feedback layer only.

## Slice 1: Introduce The Server Feature Flag Boundary

Status:

- implemented
- added disabled-by-default `FORUM_UNICODE_AUTHORED_TEXT` config parsing
- routed authored subject/body writes through policy-named helpers while preserving current ASCII behavior
- documented the flag in the production env example

Focus:

- add the rollout switch without changing default write behavior

Checklist:

- add one configuration helper for `FORUM_UNICODE_AUTHORED_TEXT`
- default the flag to disabled
- thread the flag into the write path without changing existing ASCII behavior
- keep all existing tests passing with the flag unset
- document the flag name and default in the relevant local docs or examples if the repo has an established env-doc location

Expected outcome:

- production can ship the flag boundary safely before accepting any Unicode prose
- rollback is a config change rather than a code revert

Components touched:

- `src/ForumRewrite/SiteConfig.php` or the nearest existing config helper
- `src/ForumRewrite/Write/LocalWriteService.php`
- env docs or examples if appropriate

## Slice 2: Add Server-Side Visible Unicode Normalization

Status:

- implemented
- added `UnicodeTextPolicy` for flag-enabled subject/body normalization
- accepts visible UTF-8 letters such as Cyrillic while rejecting controls, format characters, noncharacters, unsupported spacing, and symbols such as emoji
- preserves the existing ASCII validators when `FORUM_UNICODE_AUTHORED_TEXT` is disabled
- added focused policy tests for Cyrillic acceptance and unsafe-character rejection

Focus:

- create the authoritative validation and normalization functions for authored prose

Checklist:

- add `normalizeVisibleUnicodeLine()` for subject-like one-line prose
- add `normalizeVisibleUnicodeBody()` for body prose
- keep existing `normalizeAsciiLine()` and `normalizeAsciiBody()` for flag-disabled behavior
- normalize accepted Unicode text to NFC before validation and write
- reject invalid UTF-8 with a clear error
- reject unsafe code point classes listed in the Unicode safety policy
- preserve existing line length and body trimming semantics unless a test proves they need adjustment
- keep newline handling explicit:
  - subject lines must not contain line breaks
  - bodies may contain `\n`
  - normalize `\r\n` and `\r` to `\n`

Expected outcome:

- the server can accept readable Unicode prose only when the flag is enabled
- invisible and direction-manipulation characters remain blocked

Components touched:

- `src/ForumRewrite/Write/LocalWriteService.php`
- possibly a new small helper under `src/ForumRewrite/Support/` if the code would otherwise crowd the write service

## Slice 3: Preserve ASCII-Only Machine Fields

Status:

- implemented
- added flag-enabled smoke coverage proving `board_tags` continue to canonicalize to ASCII
- added flag-enabled write-service coverage proving Unicode does not widen `thread_id`, reaction tags, or OpenPGP identity IDs
- relaxed the generic text parser from ASCII-only to valid UTF-8 text so flag-enabled prose can parse while family-specific parsers still enforce machine-field constraints
- trimmed board-tag canonicalization after non-ASCII stripping so removed characters cannot leave leading/trailing tag whitespace

Focus:

- make sure the Unicode flag cannot accidentally widen identifiers, tags, paths, or routes

Checklist:

- confirm `requireAsciiToken()` remains unchanged
- confirm `normalizeBoardTags()` and thread/post tag normalization remain ASCII-only
- confirm `Author-Identity-ID` validation remains ASCII-only
- confirm static artifact route normalization remains ASCII-safe
- add explicit tests that the flag does not allow Unicode in:
  - `thread_id`
  - `parent_id`
  - `board_tags`
  - reaction tags
  - identity IDs

Expected outcome:

- Unicode support is limited to human-authored prose
- canonical paths and inspector-oriented identifiers remain predictable

Components touched:

- `src/ForumRewrite/Write/LocalWriteService.php`
- `src/ForumRewrite/Canonical/PostRecordParser.php` if parser validation needs clarifying tests
- `src/ForumRewrite/Host/StaticArtifactBuilder.php` only if route tests reveal drift

## Slice 4: Update Browser Compose Normalization For Policy Awareness

Status:

- implemented
- compose roots now expose the active Unicode-authored-text policy to browser code
- browser normalization preserves readable Unicode in `subject` and `body` when the flag is enabled
- `board_tags` and other machine fields keep the ASCII normalization path
- unsafe Unicode characters remain blocking and explicitly removable

Focus:

- align browser feedback with the server policy while keeping the server authoritative

Checklist:

- expose or infer whether Unicode authored text is enabled for the rendered compose page
- when the flag is disabled:
  - keep current ASCII normalization behavior
  - keep non-ASCII characters on the existing unsupported-character path
- when the flag is enabled:
  - allow readable Unicode letters such as Cyrillic in `subject` and `body`
  - keep smart punctuation normalization if it remains a product requirement
  - stop treating non-Latin letters as removable unsupported characters
  - surface unsafe or invisible characters as blocking problems
  - rename browser helper concepts from "unsupported non-ASCII" toward "unsafe characters" where useful
- keep explicit removal limited to characters that are actually unsafe under the active policy
- ensure the text submitted is the text shown in the fields

Expected outcome:

- users get immediate feedback consistent with the feature flag
- readable Cyrillic text is no longer presented as something to remove when the flag is enabled

Components touched:

- `public/assets/browser_signing.js`
- compose templates that render normalization status or config
- focused browser-helper tests

## Slice 5: Extend Canonical Parser And Read-Model Coverage

Status:

- implemented
- added parser coverage for canonical post records containing Unicode subject and body prose
- added flag-enabled write smoke coverage for Cyrillic subject/body through canonical record creation, read-model refresh, thread/post rendering, activity/RSS rendering, and static artifact generation
- verified generated artifact paths remain ASCII record-id paths while artifact contents preserve Unicode prose

Focus:

- verify stored Unicode prose remains parseable and renderable through the existing derived-state pipeline

Checklist:

- add parser coverage for canonical post records containing Unicode subject and body text
- add read-model rebuild coverage for Unicode subject and body text
- add write smoke coverage with the flag enabled for examples such as:
  - subject: `Привет`
  - body: `Привет мир`
- verify rendered thread and post pages display the Unicode text correctly
- verify static artifact generation handles Unicode text in HTML content without using Unicode in artifact file names
- verify RSS or activity views continue to escape and encode output correctly if they include subject/body excerpts

Expected outcome:

- Unicode prose survives the full write, parse, read-model, render, and artifact pipeline
- file names and routes remain ASCII even when page content is Unicode

Components touched:

- `tests/CanonicalRecordParsersTest.php`
- `tests/WriteApiSmokeTest.php`
- read-model tests as needed
- static artifact tests if existing coverage is convenient

## Slice 6: Add Unsafe Unicode Regression Tests

Focus:

- lock down the anti-deception guarantees

Checklist:

- add server tests that reject:
  - zero-width space
  - zero-width joiner
  - right-to-left override
  - bidirectional isolate/control characters
  - private-use characters
  - invalid UTF-8
  - control characters in subjects
  - control characters in bodies except normalized newlines
- add browser-helper tests that identify the same unsafe characters when the flag is enabled
- test that explicit removal removes unsafe characters but preserves readable non-Latin letters
- test that the flag-disabled path still blocks or removes non-ASCII according to the current behavior

Expected outcome:

- the feature cannot regress into "allow all Unicode"
- inspectors are protected against invisible or directionally misleading text

Components touched:

- `tests/BrowserSigningNormalizationTest.php`
- `tests/WriteApiSmokeTest.php` or a narrower write-service test
- any new Unicode policy helper tests

## Slice 7: Rollout Documentation And Operational Checks

Focus:

- make the feature safe to enable, monitor, and roll back

Checklist:

- document the flag:
  - name
  - default
  - intended scope
  - rollback procedure
- document that the flag only affects authored prose, not identifiers or tags
- add a short operator note explaining that canonical records may contain UTF-8 prose after the flag is enabled
- verify repository tooling, git diffs, rebuild scripts, and static artifact scripts work under a UTF-8 locale
- decide whether production examples should keep the flag commented out until rollout

Expected outcome:

- operators understand exactly what changes when the flag is enabled
- rollback does not require data deletion, but new Unicode-containing records should remain parseable after rollback

Components touched:

- `README.md` or production runbook if appropriate
- `docs/examples/env.production.example`
- any local env example used for deployment

## Guardrails

- Do not broaden machine identifiers beyond ASCII.
- Do not transliterate non-Latin scripts.
- Do not silently remove unsafe characters during ordinary typing.
- Do not treat browser validation as authoritative.
- Do not allow bidirectional controls or zero-width characters in authored prose.
- Do not allow arbitrary Unicode symbols just because they are valid UTF-8.
- Do not make the feature default-on until write, rebuild, render, and artifact paths have coverage.
- Keep the flag boundary narrow enough that rollback is understandable.

## Open Policy Questions

These should be resolved before implementation reaches Slice 2:

- Should emoji remain blocked in V1? Recommendation: yes.
- Should mathematical symbols and currency symbols be blocked or allowed? Recommendation: block in V1 unless there is a concrete use case.
- Should tabs be allowed in bodies? Recommendation: no, normalize or reject them unless existing behavior already permits them.
- Should combining marks be accepted only after NFC normalization? Recommendation: yes.
- Should mixed scripts be restricted in prose? Recommendation: no for body and subject prose, yes for future display names or identifiers.
- Should existing Latin-diacritic ASCII transliteration keep running when Unicode authored text is enabled? Recommendation: keep smart punctuation normalization, but stop transliterating readable letters in subject/body when the user expects Unicode preservation.

## Recommended Order

1. introduce the disabled-by-default server feature flag
2. add server-side visible Unicode validation and normalization
3. prove machine fields stay ASCII-only
4. update browser compose validation for the active policy
5. cover parser, read-model, render, and artifact paths
6. add unsafe Unicode regression tests
7. document rollout and rollback

## Acceptance Criteria

This work is complete when:

- `FORUM_UNICODE_AUTHORED_TEXT` defaults to disabled
- with the flag disabled, current ASCII behavior remains unchanged
- with the flag enabled, `subject` and `body` accept readable Cyrillic text
- unsafe Unicode characters are rejected server-side
- browser compose feedback matches the active policy
- machine identifiers, tags, routes, profile slugs, and identity IDs remain ASCII-only
- Unicode prose survives write, parse, rebuild, render, and static artifact generation
- focused tests cover both accepted readable Unicode and rejected deceptive Unicode

## Summary

This change is a canonical text policy expansion, not a general Unicode free-for-all.

The desired endpoint is:

- readable non-Latin prose works
- canonical machine fields remain ASCII and inspectable
- invisible and direction-manipulation characters are blocked
- rollout is controlled by a feature flag
- rollback is operationally straightforward
