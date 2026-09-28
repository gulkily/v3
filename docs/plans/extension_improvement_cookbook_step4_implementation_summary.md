# Extension/Improvement Cookbook Step 4 Implementation Summary

## Stage 1 - Cookbook entry point
- Changes:
  - Added the developer-facing cookbook under `docs/examples/` with scope, use, and safety expectations.
  - Linked the cookbook from the README Examples section.
- Verification:
  - Reviewed the README link and cookbook Markdown locally.
  - Ran `git diff --check`.
- Notes:
  - The cookbook intentionally contains only orientation at this stage; the shared lifecycle and recipes land in later stages.

## Stage 2 - Shared pattern and extension map
- Changes:
  - Added the reusable trigger-to-audit lifecycle for safe extensions.
  - Added a selection map linking per-post analysis, Fastmod, task queue, Codex handoff, and LLM operations to their canonical documentation.
- Verification:
  - Reviewed each relative Markdown link and its destination heading locally.
  - Ran `git diff --check`.
- Notes:
  - The map links to canonical references instead of restating their configuration or API contracts.

## Stage 3 - Per-post agent-work recipes
- Changes:
  - Added the high-moderation-signal NVC reply recipe with an advisory-score and reviewed-output boundary.
  - Added the short bug-report structured-follow-up recipe with explicit assumptions and review expectations.
- Verification:
  - Reviewed both recipes against the shared lifecycle and their linked canonical documentation.
  - Ran `git diff --check`.
- Notes:
  - Neither recipe claims that the described extension is already automatic; each is a safe design pattern for future feature work.
