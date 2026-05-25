# PHP Unicode Risk Detection Slices V1

This document outlines an atomic implementation plan for detecting suspicious or hard-to-inspect Unicode in authored content after Unicode prose support is introduced.

## Goal

Add transparent Unicode risk detection for authored text without turning advisory analysis into the write gate.

The companion acceptance plan is [docs/plans/php_unicode_authored_text_feature_flag_slices_v1.md](/home/wsl/v3/docs/plans/php_unicode_authored_text_feature_flag_slices_v1.md:1). That plan defines what the server accepts. This plan defines how the system detects, explains, stores, and surfaces Unicode text that may deserve human attention.

## Why This Should Be Separate

Unicode acceptance and Unicode detection have different jobs:

- acceptance is deterministic and must be enforced before canonical write
- detection is advisory, explainable, and allowed to evolve as tooling improves
- acceptance should be stable enough that users know what can be posted
- detection can combine deterministic scanners, Unicode metadata, and LLM analysis
- detection output belongs in operational analysis state, not in canonical post records

Keeping these separate avoids using an LLM as a write-time authority and keeps rollback understandable.

## Product Decision

The detection system should:

- run only after a post exists or during explicit analysis
- never be the sole reason a write is rejected
- use deterministic scanners for objective Unicode facts
- use an LLM only for advisory interpretation, summarization, and review priority
- store results in SQLite analysis or a sibling operational table
- show results only in existing approved-viewer analysis/moderation surfaces
- keep canonical records unchanged

## Detection Categories

V1 should distinguish these categories:

- `unsafe_rejected`: characters that the write path would reject under the active Unicode policy
- `mixed_script`: multiple scripts in the same prose field
- `confusable_identifier_like_text`: text that looks like handles, domains, paths, commands, hashes, or identifiers and contains confusable characters
- `directionality_risk`: right-to-left text, bidirectional layout concerns, or script ordering that may confuse visual inspection
- `invisible_or_spacing_risk`: unusual spacing, separators, or characters close to invisible behavior
- `normalization_risk`: input that changed under normalization or contains combining-mark patterns worth surfacing
- `llm_review_note`: model-generated explanation or recommended human review priority

The names above are operational labels, not canonical record fields.

## Slice 1: Deterministic Unicode Inspection Helper

Focus:

- add a local scanner that produces objective Unicode facts for subject and body text

Checklist:

- create a small helper, tentatively `UnicodeRiskInspector`
- inspect post `subject` and `body` separately
- emit structured facts:
  - normalized form used for analysis
  - scripts present
  - code point counts by broad class
  - suspicious code points, if any
  - whether text changed under NFC normalization
  - whether right-to-left scripts are present
  - whether multiple scripts are present
- keep output bounded by summarizing repeated findings
- avoid adding a dependency unless the current PHP runtime lacks a required Unicode capability

Expected outcome:

- the app can explain Unicode text properties without calling an LLM
- analysis can be tested deterministically

Components touched:

- likely a new helper under `src/ForumRewrite/Analysis/` or `src/ForumRewrite/Support/`
- focused helper tests

## Slice 2: Add Operational Storage For Unicode Risk Results

Focus:

- persist detection results without changing canonical records

Checklist:

- decide whether Unicode risk data is stored inside existing `post_analyses` JSON or in a sibling table
- prefer a sibling table if the data should refresh independently from LLM post analysis
- include:
  - `post_id`
  - `content_hash`
  - `schema_version`
  - `status`
  - deterministic facts JSON
  - LLM review JSON, nullable
  - timestamps
- make writes idempotent for the same `post_id` and `content_hash`
- ensure failed LLM review does not erase deterministic facts

Expected outcome:

- Unicode risk findings are cached and tied to exact post content
- operational data can be rebuilt or discarded without altering canonical history

Components touched:

- `src/ForumRewrite/Analysis/*`
- read-model or analysis-store migration code
- tests around save, hydrate, and idempotency

## Slice 3: Integrate Deterministic Detection Into Post Analysis

Focus:

- run Unicode inspection where the app already performs post analysis

Checklist:

- add deterministic Unicode facts to the analysis context or result
- ensure `/api/analyze_post` computes and stores deterministic facts before any LLM call
- return Unicode risk details only to viewers who can already see post analysis
- keep anonymous responses unchanged except for existing high-level status fields
- make detection work in stub analysis mode
- do not trigger automatic agent replies based only on Unicode risk findings

Expected outcome:

- approved viewers can inspect Unicode risk data through the existing analysis path
- detection does not create a second write-time moderation system

Components touched:

- `src/ForumRewrite/Analysis/PostAnalysisService.php`
- `src/ForumRewrite/Analysis/SqlitePostAnalysisStore.php` or sibling store
- `src/ForumRewrite/Application.php`
- `tests/WriteApiSmokeTest.php`
- `tests/DedalusPostAnalyzerTest.php` if analysis schema changes

## Slice 4: Add LLM-Assisted Unicode Risk Review

Focus:

- use the model to explain and prioritize deterministic findings, not to discover basic facts from scratch

Checklist:

- extend the post-analysis prompt or add a dedicated Unicode-risk prompt section
- pass deterministic Unicode facts into the model context
- ask the model for a bounded JSON object:
  - `review_priority`: `none`, `low`, `medium`, `high`
  - `summary`: short human-readable explanation
  - `concerns`: list of stable labels
  - `recommended_action`: `none`, `watch`, `human_review`
  - `confidence`: numeric 0-1
- require the model to cite only facts present in the deterministic scan or visible post text
- reject or sanitize malformed model output
- keep LLM review unavailable when provider config is missing
- keep deterministic findings available when LLM review fails

Expected outcome:

- LLM output helps humans understand why a post may be confusing
- deterministic scanner remains the source of objective truth

Components touched:

- `prompts/dedalus_post_analysis_system.txt` or a new prompt file
- `src/ForumRewrite/Analysis/DedalusPostAnalyzer.php`
- `src/ForumRewrite/Analysis/StubPostAnalyzer.php`
- decoder and schema tests

## Slice 5: Surface Findings In Approved Analysis UI

Focus:

- make findings visible to inspectors without alarming normal readers

Checklist:

- add a compact Unicode risk section to the existing post-analysis details panel
- show:
  - risk level
  - scripts detected
  - short explanation
  - any blocked/suspicious code point names when available
  - LLM review summary when present
- keep the normal public thread/post UI unchanged
- avoid showing raw invisible characters in a way that can affect layout
- prefer escaped code point notation such as `U+202E` for suspicious characters

Expected outcome:

- approved users and inspectors can review Unicode risk clearly
- public readers are not shown internal analysis metadata

Components touched:

- `templates/partials/post_card.php`
- `public/assets/post_analysis.js` if dynamic analysis rendering needs updates
- smoke tests for approved and anonymous rendering

## Slice 6: Add Backfill And Recheck Tooling

Focus:

- allow existing posts to be inspected after the feature ships

Checklist:

- add a script or command to scan all current posts for Unicode risk
- support rechecking only posts whose content hash changed or whose risk schema version is stale
- print a concise summary:
  - scanned count
  - changed count
  - high/medium/low finding counts
  - provider failures if LLM review is enabled
- allow deterministic-only mode for offline or no-provider environments
- keep the tool read-only with respect to canonical records

Expected outcome:

- operators can audit existing content after enabling Unicode prose
- detection can be refreshed as the policy evolves

Components touched:

- `scripts/`
- analysis store
- runbook docs

## Slice 7: Regression And Abuse-Case Coverage

Focus:

- prove detection catches the cases it is meant to surface

Checklist:

- add deterministic scanner tests for:
  - plain ASCII text
  - plain Cyrillic text
  - mixed Latin and Cyrillic prose
  - right-to-left script text
  - confusable identifier-like text
  - unusual combining-mark patterns
  - unsafe characters that should already be rejected by write validation
- add LLM decoder tests for expected Unicode review shape
- add store hydration tests
- add `/api/analyze_post` tests confirming:
  - anonymous viewers do not receive details
  - approved viewers receive Unicode risk details
  - deterministic findings persist even if LLM analysis is unavailable

Expected outcome:

- detection behavior is repeatable and inspectable
- LLM failure modes do not break deterministic risk reporting

Components touched:

- new helper tests
- `tests/DedalusPostAnalyzerTest.php`
- `tests/WriteApiSmokeTest.php`

## Guardrails

- Do not use an LLM to decide whether a canonical write is valid.
- Do not block posts solely because the LLM assigns risk.
- Do not expose Unicode risk details to anonymous viewers in V1.
- Do not store LLM analysis in canonical records.
- Do not treat mixed-script prose as automatically malicious.
- Do not let suspicious characters render raw inside inspector UI if they can affect layout.
- Do not hide deterministic facts behind model prose.
- Do not make agent replies respond publicly to Unicode risk findings in V1.

## Open Policy Questions

These should be resolved before Slice 4:

- Should Unicode risk review be part of the existing post-analysis model call or a separate optional model call? Recommendation: start inside existing post analysis to avoid another provider path, but keep the stored schema separable.
- Should mixed-script prose be a low-priority signal by default? Recommendation: yes, unless identifier-like text is present.
- Should LLM review run automatically for all Unicode posts or only when deterministic signals are non-empty? Recommendation: only when deterministic signals are non-empty in V1.
- Should approved viewers see exact code point names for all non-ASCII characters? Recommendation: no, show exact names only for suspicious or rejected classes.
- Should the backfill tool call the LLM by default? Recommendation: no, deterministic-only by default with an explicit provider-enabled option.

## Recommended Order

1. add deterministic Unicode inspection
2. add operational storage
3. integrate deterministic results into post analysis
4. add LLM-assisted review using deterministic facts as input
5. surface findings to approved viewers
6. add backfill and recheck tooling
7. add regression and abuse-case coverage

## Acceptance Criteria

This work is complete when:

- Unicode risk detection is stored outside canonical records
- deterministic scanner output is available without provider configuration
- LLM review is advisory and cannot block writes
- approved viewers can inspect Unicode risk details
- anonymous viewers do not receive internal Unicode risk details
- detection handles plain Cyrillic without treating it as inherently malicious
- mixed-script, confusable, directionality, spacing, and normalization risks are represented as distinct findings
- backfill can scan existing posts without modifying canonical data

## Summary

Detection should make Unicode support more transparent, not more brittle.

The intended result is:

- deterministic rules enforce the hard safety boundary
- deterministic inspection explains Unicode facts
- LLM review helps humans prioritize ambiguous cases
- canonical records remain unchanged
- Unicode prose support remains usable for legitimate non-Latin text
