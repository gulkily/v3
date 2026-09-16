# Forte Profiles — Step 4: Implementation Summary

## Stage 1 - Forte-target author-link helper
- Changes:
  - `TemplateRenderer::renderAuthorHtml()`: gained an optional `bool $forteTarget = false` param, switching the base path from `/profiles/`/`/user/` to `/forte/profiles/`/`/forte/user/` when true; default unchanged.
  - `renderFile()`: new `$forteAuthor` closure exposed alongside the existing `$author` closure, calling `renderAuthorHtml($record, $e, true)`.
- Verification:
  - `php -l` clean.
  - `curl` classic's `/threads/root-001` and Forte's `/forte?selected=root-001`: author links on both still point at `/profiles/`/`/user/` — no template calls `$forteAuthor` yet, so this stage is purely additive as planned.
- Notes: none identified.
