# PHP Latin Diacritic Transliteration Slices V1

This document outlines a narrow follow-up plan to extend the current compose normalization behavior in `v3`.

## Goal

Keep the ASCII-only canonical write contract, but reduce unnecessary friction for Latin-script text by transliterating a small approved set of accented Latin characters into ASCII equivalents in the browser.

This plan is intentionally narrower than "support Unicode" and narrower than "support extended ASCII."

## Product Decision

The design frame for this change is:

- keep canonical records ASCII-only
- keep the existing smart-punctuation normalization
- add deterministic transliteration only for Latin letters with diacritics and a very small explicit set of ligatures
- do not introduce broader script transliteration
- keep unsupported non-Latin characters on the existing explicit recovery path

Examples of the intended direction:

- `é` -> `e`
- `ñ` -> `n`
- `ö` -> `o`
- `ç` -> `c`
- `æ` -> `ae`
- `œ` -> `oe`
- `ß` -> `ss`

Examples that remain out of scope in this plan:

- Cyrillic transliteration
- Greek transliteration
- emoji aliases
- symbol-name rewriting
- "extended ASCII" as a general compatibility target

## Why This Scope

The current browser normalization layer already rewrites a narrow punctuation set. Extending it to Latin diacritics is a policy expansion, but still a bounded one.

This is preferable to an "extended ASCII" strategy because:

- extended ASCII is not one well-defined standard
- many 8-bit characters are ambiguous across code pages
- broad symbol transliteration would be harder to explain and harder to test
- a narrow Latin-diacritic table is easier to document, reason about, and defend

## Current State

Today:

- the backend remains ASCII-only
- the browser normalizes only a small punctuation set
- accented Latin characters are still treated as unsupported
- unsupported characters remain visible and block submit until explicitly removed

Relevant current-state references:

- [docs/plans/php_ascii_restrictions_current_state_v1.md](/home/wsl/v3/docs/plans/php_ascii_restrictions_current_state_v1.md:1)
- [docs/plans/php_non_ascii_compose_autocorrection_slices_v1.md](/home/wsl/v3/docs/plans/php_non_ascii_compose_autocorrection_slices_v1.md:1)

## Slice 1: Define The Transliteration Policy

Focus:

- write down the exact approved character mapping before implementation

Checklist:

- create a compact policy document listing every supported transliteration pair
- group mappings by base letter where useful:
  - `a`, `e`, `i`, `o`, `u`
  - `c`, `n`, `y`
  - ligatures such as `æ`, `œ`, `ß`
- include uppercase handling expectations
- state explicitly what is out of scope
- state explicitly that this is transliteration for compose convenience, not a change to the canonical ASCII storage contract

Expected outcome:

- implementation is driven by an approved deterministic table instead of ad hoc decisions during coding

## Slice 2: Extend The Browser Normalization Helper

Focus:

- extend `normalizeComposeAscii()` in `public/assets/browser_signing.js` to apply the approved transliteration table

Checklist:

- add the approved Latin-diacritic transliteration map alongside the current punctuation replacement map
- ensure transliteration happens before unsupported-character detection
- preserve current newline handling
- preserve current explicit-removal behavior for still-unsupported characters
- keep server-side validation unchanged in this slice

Expected outcome:

- common accented Latin input becomes submit-compatible automatically
- non-Latin scripts still remain visible and explicitly unsupported

## Slice 3: Apply Transliteration Uniformly To Authored Compose Fields

Focus:

- make sure the transliteration behavior is consistent across the fields users can author

Checklist:

- apply the updated helper to:
  - `board_tags`
  - `subject`
  - `body`
- keep field-level inline error messaging for remaining unsupported characters
- keep explicit `Remove unsupported characters` actions for remaining unsupported characters
- confirm draft persistence still preserves current field contents correctly
- confirm submit-time normalization matches what the user sees in the fields

Expected outcome:

- accented Latin text is handled consistently across compose fields
- the remaining unsupported-character UX is reserved for cases this policy intentionally does not cover

## Slice 4: Add Focused Regression Coverage

Focus:

- lock the transliteration policy into tests

Checklist:

- extend helper tests for:
  - lowercase diacritics
  - uppercase diacritics
  - ligatures if approved
  - mixed punctuation plus accented Latin input
  - still-unsupported non-Latin input
- extend smoke coverage to confirm compose pages still render the field-level recovery hooks
- add at least one mixed-case regression example such as:
  - `Café — déjà vu`
  - `François`
  - `Smörgåsbord`
  - Latin text plus Cyrillic text in the same field

Expected outcome:

- the transliteration table is explicit, reproducible, and protected from accidental drift

## Guardrails

- Keep the transliteration table explicit and finite.
- Do not define the feature as "extended ASCII support."
- Do not introduce language-specific guesswork beyond the approved mapping table.
- Do not transliterate non-Latin scripts in this plan.
- Do not relax server-side ASCII enforcement.
- Do not silently remove still-unsupported characters during ordinary typing.
- Prefer deterministic one-to-one or one-to-few mappings over heuristic rewrites.

## Open Policy Questions For Slice 1

These should be resolved in the policy document before coding:

- Which Latin diacritics are included in V1? A: Include the Latin-script diacritics and ligatures explicitly listed in the approved mapping table. Seed that table from characters commonly encountered by English-speaking users in borrowed words and nearby Western/Northern European Latin orthographies. Anything not listed in the approved table remains unsupported in V1.
- Are ligatures such as `æ`, `Æ`, `œ`, `Œ`, `ß` included? Yes.
- Should `ø` map to `o` and `Ø` to `O`? Yes.
- Should characters such as `ł` map to `l`, or be left out of the initial table? Yes, map.
- Should transliteration apply to `board_tags`, or should that field keep its existing stricter normalization path only? Yes, apply transliteration to `board_tags` before the existing lowercase/strip/collapse normalization. `board_tags` remain a canonicalized field rather than a preserve-what-the-user-typed field, and collisions caused by transliteration plus normalization are acceptable in V1 if that reduces submission friction.

## Recommended Order

1. define and approve the transliteration table
2. extend the shared browser normalization helper
3. apply the behavior consistently across authored compose fields
4. add focused regression coverage

## Summary

This change should be treated as a narrow policy expansion, not a general encoding rewrite.

The intended result is:

- smart punctuation still normalizes automatically
- common Latin diacritics also normalize automatically
- non-Latin text remains visible but unsupported
- canonical storage remains ASCII-only

That gives us a more forgiving compose experience for a common class of Latin-script input without pretending to solve multilingual canonical storage.
