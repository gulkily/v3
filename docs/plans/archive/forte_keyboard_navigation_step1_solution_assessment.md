# Forte Keyboard Navigation Step 1 Solution Assessment

## Problem Statement

As a keyboard user, I want to Tab between Forte's panes (and clickable elements in the content pane), and use arrow keys to move the selection within the tags and thread lists when one of them has focus.

## Option A: Roving tabindex with real per-item focus (listbox pattern)

Each list (tags, threads) exposes exactly one item as `tabindex="0"` at a time (the rest `tabindex="-1"`); arrow keys move real DOM focus to the next/previous visible item, reusing the existing click-selection logic on activation. Tab moves between panes as single stops (into/out of whichever item currently holds focus).

Pros:
- Matches the standard, broadly-supported WAI-ARIA listbox authoring pattern — real per-item focus works well with screen readers and browser defaults.
- Arrow-key semantics (wrap or clamp at ends, skip hidden/filtered rows) are well-established conventions to follow, not invented from scratch.

Cons:
- More bookkeeping: the "current" item's tabindex must be kept in sync as tag filtering hides/shows rows or as selection changes via mouse.

## Option B: Single focusable pane, virtual focus (`aria-activedescendant`)

Each pane container itself is the one Tab stop; arrow keys move a virtual "active" pointer (`aria-activedescendant`) without moving real DOM focus, with CSS marking the active row.

Pros:
- Simpler tabindex management — only one real focusable element per pane, regardless of how many rows are hidden/shown by filtering.

Cons:
- `aria-activedescendant` has historically had less consistent screen-reader support than real per-item focus for custom widgets.
- Still requires the same ARIA role/state wiring as Option A, without gaining the more robust focus model.

## Option C: Minimal keyboard shortcuts, no listbox semantics

Add `tabindex="0"` to each pane as a single Tab stop and a keydown handler that moves the existing "selected" CSS class among visible rows on arrow keys, with Enter/Space activating — no ARIA roles beyond what already exists.

Pros:
- Smallest change; reuses existing click-selection code almost as-is.

Cons:
- Weaker for actual assistive-technology users (no exposed selectable-list semantics) — meets the letter of "arrow keys move a selector" but not real accessibility intent behind a keyboard-user request.

## Recommendation

Recommend Option A.

Brief justification:
- It's the well-supported, standards-based pattern for exactly this interaction (a filterable list where arrow keys move selection), so screen reader users benefit, not just sighted keyboard users.
- The extra tabindex bookkeeping is bounded and touches code we already have to update when filtering changes visibility (Stage 2 of the tag-filter work already recomputes per-row state on filter changes).
