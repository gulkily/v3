# Offline Indicator Compaction Step 3 Development Plan

## Stage 1 - Compact offline freshness indicators
- Goal: Show archive freshness and reader revision compactly in the right side of the existing offline-mode bar.
- Dependencies: Existing snapshot `generated_at` metadata, reader-revision shell attribute, and offline-mode presentation.
- Expected changes: Replace the reader-details sentence with two labelled compact indicators in the mode bar; add responsive right-aligned bar styling and accessible indicator labels; retain unknown fallbacks.
- Verification approach: Reader shell and presentation tests verify the two indicator roles, populated/fallback values, removal of the standalone details element, and JavaScript/CSS syntax.
- Risks or open questions:
  - Long revision paths must remain readable without forcing the mode-bar label off screen.
- Canonical components/API contracts touched: Offline reader shell, `setReaderDetails(generatedAt)`, `.offline-mode-bar`.
