> **Feature plan:** [Step 1](./website_redesign_step1_solution_assessment.md) · [Step 2](./website_redesign_step2_feature_description.md) · [Step 3](./website_redesign_step3_development_plan.md) · [Step 4](./website_redesign_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- Normal entry: the existing site URL, served from `master` by GitHub Pages. Outcome: a visitor reads the Original explanation, optionally switches voices by mouse or keyboard, follows install/docs links, and sees the new logo in the header, tab, and shared-link preview.
- Allowed changes: `index.html`, the optimized logo asset at the repository root (same canonical filename; README reference changes only if the name changes), and this feature's plans, summary, debug log, organization under `docs/plans/website_redesign/`, and `docs/plans/README.md` entry. No CI, deployment settings, scripts, or workflow instructions; `temp/` is not published.
- Recovery: the Original remains visible without scripting; a bad release is reverted by restoring the previous `index.html` and logo.
- External verification: maintainer reviews the rendered page (light/dark, phone and desktop widths) and the live Pages URL after merge. Release condition: maintainer approval, then merge.

## Key Risks
- **Real-person pastiche:** voices imitating named people could read as fabricated quotes or endorsement. Impact: reputational. Validate: maintainer confirms the voice list in Stage 4 before content is added. Mitigation: label each "In the style of…"; ship a curated subset if in doubt. This blocks Stage 4 until decided.
- **High risk: unusable page without scripting:** a selector that hides content breaks the core explanation. Validate: render with scripting disabled in Stages 3–5. Mitigation: Original is plain HTML; scripting only enhances.
- **Logo asset:** the ~1.3 MB icon could bloat the page or change color. Validate: size and visual check in Stage 1. Mitigation: export a small optimized copy; keep the current file until replaced.
- **Third-party requests:** prototype fonts load externally. Validate: network/source check in Stage 5. Mitigation: system font stacks only.

## Stage 1
- Goal: New logo asset ready for the site (≤1 hour).
- Dependencies: Approved Step 3; feature branch; organized plans and planning commit; maintainer confirms the icon color is final.
- Expected changes: Export a web-optimized copy of the cross-section icon (target <100 KB) as `railspike-logo.png`, replacing the current file.
- Verification approach: Check file size and dimensions, view the image, confirm README still renders the logo.
- Risks or open questions:
  - Impact: a blurry or oversized logo spoils every placement.
  - Early warning / validation: visual check at header (42–54 px) and 96 px README sizes.
  - Mitigation: re-export from the source at a higher resolution.
- Canonical components/API contracts touched: `railspike-logo.png`; referenced by `index.html` and `README.md`.

## Stage 2
- Goal: Modernized QDB look applied to the existing page without changing its content (≤1 hour).
- Dependencies: Stage 1 verified and committed.
- Expected changes: Update theme tokens (amber accent, monospace type, light/dark), header with logo and compact slash navigation, bordered blocks, responsive rules; no external fonts.
- Verification approach: Render at 360 px and desktop in both color schemes; check no horizontal scroll and visible keyboard focus.
- Risks or open questions:
  - Impact: low contrast of amber text or cramped mobile nav.
  - Early warning / validation: contrast and wrapping check at 360 px.
  - Mitigation: use amber for accents and bars, not body text.
- Canonical components/API contracts touched: `index.html` header/nav/footer and shared styles.

## Stage 3
- Goal: "What is Railspike" section carries the Original essay, plain and complete (≤1 hour).
- Dependencies: Stage 2 verified and committed.
- Expected changes: Replace the current explanation with the Original text from the Readings, edited only to fit; keep install/start-here content and links below it.
- Verification approach: Compare text against the Readings source; render with scripting disabled; check all links in the section.
- Risks or open questions:
  - Impact: a rewritten explanation drifts from the maintainer's wording.
  - Early warning / validation: diff against `temp/Railspike Readings.html`.
  - Mitigation: copy verbatim; fix only layout, links, and typos.
- Canonical components/API contracts touched: `index.html` hero and explanation sections.

## Stage 4
- Goal: Voice selector with maintainer-approved voices (≤1 hour).
- Dependencies: Stage 3 committed; maintainer confirms which voices ship.
- Expected changes: Add a keyboard-usable tab selector defaulting to the Original, labeled "In the style of…" for imitations, with the Original selected and visible when scripting is off.
- Verification approach: Exercise every shipped voice by mouse and arrow/Tab keys; check ARIA tab roles; render with scripting disabled.
- Risks or open questions:
  - Impact: hidden voices or broken tabs hurt access and trust.
  - Early warning / validation: keyboard walkthrough and no-script render.
  - Mitigation: fall back to showing Original only.
- Canonical components/API contracts touched: `index.html` selector component; reuse the Readings tablist structure, not its page layout.

## Stage 5
- Goal: Final polish and release readiness (≤1 hour).
- Dependencies: Stages 1–4 verified and committed.
- Expected changes: Align title, description, og tags, favicon and canonical URL with the new copy; fix any defects found.
- Verification approach: Run the repository link check; confirm no third-party requests; check widths, color schemes, and logo placements; hand the rendered page to the maintainer for review.
- Risks or open questions:
  - Impact: broken links or stale previews after release.
  - Early warning / validation: link check plus live-URL check after merge.
  - Mitigation: fix before the final commit; revert via the previous `index.html`.
- Canonical components/API contracts touched: `index.html` metadata; the Step 4 summary.
