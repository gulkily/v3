# Markdown renderer: pre-Step 3 evaluation

Planning evidence, 2026-10-19. No application files changed; disposable probes ran under `/tmp/markdown-parser-evaluation` and `/tmp/markdown-parser-package-probe`.

## Selection and packaging
Select **league/commonmark 2.10.4**, behind `PublicMarkdownRenderer`. The [upstream release](https://github.com/thephpleague/commonmark/releases/tag/2.10.4) and installed package metadata were checked. Its PHP constraint is `^7.4 || ^8.0`, with `ext-mbstring`. Composer resolved and installed it successfully on local PHP 8.1.2. `check-platform-reqs` passed. A second directory reconstructed the production bundle from the lock using `install --no-dev --prefer-dist --optimize-autoloader`; Composer audit reported no known advisories at evaluation time. This does not establish future advisory status.

Plan a self-contained, tracked `third_party/markdown/` bundle: manifest, lock, production vendor files/autoloader, upstream notices and update instructions. Composer is a maintainer/build tool; ordinary checkout/deployment receives runnable dependencies. Evaluation vendor size was about 2.5 MB. Preserve the existing application autoloader and load the dependency bundle at the docs-rendering boundary, so a missing bundle can produce a controlled docs error instead of breaking unrelated routes. Verify a copied package without Composer during implementation.

The eight locked packages were CommonMark 2.10.4, league/config 1.2.0, dflydev/dot-access-data 3.0.3, nette/schema 1.3.6, nette/utils 4.0.10, psr/event-dispatcher 1.0.0, symfony/deprecation-contracts 3.7.1 and symfony/polyfill-php80 1.43.0. Installed notices identify BSD-3-Clause or MIT terms; both Nette packages explicitly offer BSD as an alternative to GPL. Retain all notices and record the BSD selection for Nette. No legal-compliance guarantee is implied by this technical review.

## Representative rendering probe
Configured CommonMark core, GFM and heading permalinks; raw HTML escaped, unsafe links disabled, nesting/delimiter bounds enabled, heading IDs applied directly with no visible permalink control or prefix, document-scoped unique slugs.

Passed: nested lists, disabled task checkbox, reference link, duplicate headings (`same`, `same-1`), escaped script, and rejected JavaScript href. The actual retraction guide produced four tables and one ordered list; the linked `v3_cli.md` produced `delete-a-canonical-record`. These are parser-level checks, not browser or application acceptance evidence.

[Upstream GFM documentation](https://commonmark.thephpleague.com/2.x/extensions/github-flavored-markdown/) confirms the formatting extensions; [heading documentation](https://commonmark.thephpleague.com/2.x/extensions/heading-permalinks/) describes anchor configuration. [Security documentation](https://commonmark.thephpleague.com/2.x/security/) explains that safe settings must be explicit. Built-in URL filtering alone is insufficient for project file boundaries and image policy; application policy must also reject unsupported destinations, traversal/encoded variants, protocol-relative URLs and unapproved images. No Embed or Attributes extension is needed. [Document events](https://commonmark.thephpleague.com/2.x/customization/event-dispatcher/) provide a policy hook before rendering.

## Remaining implementation evidence
Adversarial URL fixtures, document-context wiring, front-matter/source fallbacks, copied-package operation, missing-package recovery, actual local browser acceptance, static parity/regeneration and rollback remain required. No production deployment or static activation was performed during evaluation.
