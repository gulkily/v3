# Offline Indicator Compaction Step 2 Feature Description

## Problem

The archive timestamp and reader revision currently appear as a full sentence
below the offline-mode indicator, taking more reading space than their
diagnostic value requires during offline use.

## User stories

- As an offline reader, I want compact freshness indicators in the offline-mode bar so that I can identify saved content and reader age at a glance.
- As an operator, I want those indicators to remain visible without competing with reader status or thread content.

## Core requirements

- Replace the standalone reader-details sentence with exactly two compact indicators: archive generation time and reader revision.
- Place the indicators inside the existing offline-mode bar, right-aligned from the `offline mode` label.
- Keep safe unknown-value fallbacks and preserve the health page as the detailed diagnostic surface.
- Do not change snapshot contents, cache policy, routes, or online-only boundaries.

## Shared component inventory

- **Offline-mode bar:** extend the canonical reader-state indicator rather than introduce a second status element.
- **Offline-reader shell:** reuse its existing archive timestamp and fingerprinted reader-revision inputs.
- **Offline Reading health page:** remains unchanged as the detailed comparison and recovery interface.

## User flow

1. Reader opens saved content offline.
2. The offline-mode bar appears with compact archive and reader indicators on its right.
3. Reader opens Offline Reading health only when fuller freshness diagnosis is needed.

## Success criteria

- Offline content shows two concise indicators in the right side of the offline-mode bar.
- No standalone archive/revision sentence consumes reader-body space.
- Unknown metadata remains understandable and existing offline rendering continues to work.
