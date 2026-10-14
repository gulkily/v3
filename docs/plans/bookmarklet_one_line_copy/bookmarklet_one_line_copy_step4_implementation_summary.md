> **Feature plan:** [Step 1](./bookmarklet_one_line_copy_step1_solution_assessment.md) · [Step 2](./bookmarklet_one_line_copy_step2_feature_description.md) · [Step 3](./bookmarklet_one_line_copy_step3_development_plan.md) · [Step 4](./bookmarklet_one_line_copy_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Icon-only copying

- Changes: Added seven icon-only copy buttons with names/tooltips, keyboard focus, transient accessible feedback, and retry. Link and clipboard share a single encoded JavaScript URL; URL encoding preserves source and content without unsafe whitespace stripping.
- Verification: `BOOKMARKLET_BASELINE=/tmp/bookmarklet-baseline.js node tests/browser/bookmarklet_copy.cjs` passed all seven variants against original behavior, including quotes, percent signs, Unicode, content newlines, attribution, window modes, failed writes, and focus. The targeted PHP bookmarklet smoke test passed (1/1). Chromium live Tools → Bookmarklets pointer and Enter activation both copied the exact link source. qutebrowser 2.5.0 launched in an isolated temporary profile with a local debugging endpoint.
- Notes: Planning commit `02dfe203`; no production deployment. Stage 2 will verify actual qutebrowser binding execution and clipboard fallback. Its older QtWebEngine rejects Playwright context management, so acceptance will use its native commands and page debugging protocol.
