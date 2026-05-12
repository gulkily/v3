# PHP Latin Diacritic Transliteration Policy V1

This document defines the approved Latin-diacritic transliteration table for `v3` compose normalization.

It is the policy source of truth for the transliteration change described in [docs/plans/php_latin_diacritic_transliteration_slices_v1.md](/home/wsl/v3/docs/plans/php_latin_diacritic_transliteration_slices_v1.md:1).

## Goal

Reduce friction for common Latin-script text while keeping the canonical ASCII-only write contract unchanged.

This policy applies only to browser-side compose normalization. It does not change backend validation, canonical storage format, or the rule that unsupported non-ASCII characters still require explicit cleanup.

## Policy Boundary

This transliteration policy is intentionally narrow:

- it applies only to characters explicitly listed in this document
- it is meant for Latin letters with diacritics plus a small set of approved ligatures/special letters
- it is not a general Unicode transliteration system
- it is not an "extended ASCII" compatibility mode
- characters not listed here remain unsupported in V1

## Runtime Rule

During compose normalization:

1. existing smart-punctuation replacements still run
2. the transliteration table in this document runs
3. any remaining non-ASCII characters are treated as unsupported

## Field Scope

This policy applies to authored compose fields that already pass through browser normalization:

- `board_tags`
- `subject`
- `body`

For `board_tags`, transliteration runs before the existing lowercase/strip/collapse normalization. `board_tags` remain a canonicalized field, and collisions caused by transliteration plus existing normalization are acceptable in V1.

## Approved Mapping Table

### A

- `À Á Â Ã Ä Å Ā Ă Ą Ǎ` -> `A`
- `à á â ã ä å ā ă ą ǎ` -> `a`

### C

- `Ç Ć Ĉ Ċ Č` -> `C`
- `ç ć ĉ ċ č` -> `c`

### D

- `Ď Đ` -> `D`
- `ď đ` -> `d`

### E

- `È É Ê Ë Ē Ĕ Ė Ę Ě` -> `E`
- `è é ê ë ē ĕ ė ę ě` -> `e`

### G

- `Ĝ Ğ Ġ Ģ` -> `G`
- `ĝ ğ ġ ģ` -> `g`

### H

- `Ĥ Ħ` -> `H`
- `ĥ ħ` -> `h`

### I

- `Ì Í Î Ï Ĩ Ī Ĭ Į İ Ǐ` -> `I`
- `ì í î ï ĩ ī ĭ į ǐ` -> `i`

### J

- `Ĵ` -> `J`
- `ĵ` -> `j`

### K

- `Ķ` -> `K`
- `ķ` -> `k`

### L

- `Ĺ Ļ Ľ Ŀ Ł` -> `L`
- `ĺ ļ ľ ŀ ł` -> `l`

### N

- `Ñ Ń Ņ Ň` -> `N`
- `ñ ń ņ ň` -> `n`

### O

- `Ò Ó Ô Õ Ö Ø Ō Ŏ Ő Ǒ` -> `O`
- `ò ó ô õ ö ø ō ŏ ő ǒ` -> `o`

### R

- `Ŕ Ŗ Ř` -> `R`
- `ŕ ŗ ř` -> `r`

### S

- `Ś Ŝ Ş Š` -> `S`
- `ś ŝ ş š` -> `s`

### T

- `Ţ Ť Ŧ` -> `T`
- `ţ ť ŧ` -> `t`

### U

- `Ù Ú Û Ü Ũ Ū Ŭ Ů Ű Ų Ǔ` -> `U`
- `ù ú û ü ũ ū ŭ ů ű ų ǔ` -> `u`

### W

- `Ŵ` -> `W`
- `ŵ` -> `w`

### Y

- `Ý Ŷ Ÿ` -> `Y`
- `ý ŷ ÿ` -> `y`

### Z

- `Ź Ż Ž` -> `Z`
- `ź ż ž` -> `z`

### Ligatures And Special Letters

- `Æ` -> `AE`
- `æ` -> `ae`
- `Œ` -> `OE`
- `œ` -> `oe`
- `ẞ` -> `SS`
- `ß` -> `ss`

## Out Of Scope

The following are explicitly out of scope for V1:

- Cyrillic transliteration
- Greek transliteration
- transliteration for non-Latin scripts generally
- symbol-name rewriting such as `™ -> tm`
- currency-name rewriting
- emoji aliases
- punctuation not already covered by the existing smart-punctuation map
- any character not listed in the approved table above

## Notes On Coverage

This table is intended to cover characters commonly encountered by English-speaking users in borrowed words and nearby Western/Northern/Central European Latin orthographies.

It is intentionally not exhaustive for all Latin-script languages and not intended to express language-specific pronunciation or orthographic rules. It is a deterministic ASCII fallback table, not a linguistic transliteration engine.

## Examples

- `Café` -> `Cafe`
- `François` -> `Francois`
- `Smörgåsbord` -> `Smorgasbord`
- `Dvořák` -> `Dvorak`
- `Łódź` -> `Lodz`
- `Œuvre` -> `OEuvre`
- `straße` -> `strasse`

Examples that remain unsupported after this table:

- `Привет`
- `γειά`
- `東京`
- `🙂`

## Change Control

If a new transliteration pair is desired later:

- add it explicitly to this document
- justify why it belongs in the narrow approved set
- add regression coverage for it

The implementation must not transliterate characters that are not explicitly approved here.
