# Site Feature Flags Record V1

This document defines the canonical site feature flags record used to store site-level boolean feature flag settings in the content repository.

## Scope

- One optional UTF-8 text file stores site-level feature flag values.
- The file lives at `records/instance/feature-flags.txt`.
- The record is public site content and is committed to the content repository git history.
- Environment and private configuration overrides may still take precedence at runtime.

## File Structure

The file has two parts:

1. A header block
2. A body separated from the headers by one blank line

Headers use this form:

```text
Key: Value
```

The body contains one flag assignment per line:

```text
FLAG_KEY: true
FLAG_KEY: false
```

## Required Headers

- `Schema`: must be `site-feature-flags-v1`

## Optional Headers

- `Updated-At`: RFC 3339 UTC timestamp for the last site-level update

## Body Rules

- Each non-empty line must use `FLAG_KEY: boolean`.
- `FLAG_KEY` must match `[A-Z][A-Z0-9_]*`.
- Boolean values must be exactly `true` or `false`.
- Duplicate keys are invalid.
- Unknown well-formed keys may be preserved for forward compatibility, but runtime evaluators should ignore them until they are registered.
- A missing file is equivalent to an empty set of site-level overrides.

## Example

```text
Schema: site-feature-flags-v1
Updated-At: 2026-06-27T00:00:00Z

FORUM_APP_VERSION_NOTIFICATION: true
FORUM_UNICODE_AUTHORED_TEXT: false
```

