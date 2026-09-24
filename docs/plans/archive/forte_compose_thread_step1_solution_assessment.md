# Forte Compose Thread — Step 1: Solution Assessment

## Problem
The board toolbar's "New" button exists in Forte's markup but is permanently `disabled`, wired to nothing — there's no way to start a new thread from within Forte. Classic already has a canonical way to do this via `thread_compose_form.php`, but it's not directly reusable as-is inside Forte's toolbar-driven chrome without adaptation, the same "native vs. bolted-on" question `forte_reply` already answered once for `reply_form.php`.

## Scope note
Board-view-only — the single-thread reader is gone (`forte_deprecate_single_thread_view`). This wires the already-present, already-disabled "New" toolbar button in `forte_board.php`; it doesn't touch classic's own `/compose/thread` page or `board.php`'s own inline composer.

## Finding that shapes the options
- `thread_compose_form.php` already supports two rendering modes via a `compact` flag: a full labeled form (subject/board_tags/body all visible) and a minimal mode (body-only textarea, with `subject`/`board_tags` passed through as hidden fields) — the same kind of reusable shape `reply_form.php` had for `forte_reply`.
- Forte's board already has an exact, already-shipped sibling pattern for this: the toolbar's Reply button (`data-paned-board-reply`) toggles a hidden panel (`paned_board_compose_panel.php`) that reuses `reply_form.php` unmodified inside Forte-specific panel chrome, with `paned_board_reader.js` handling the toggle, target-field sync, and a `return_to`-aware compose-return URL (`composeReturnToUrl()`). Reusing this same mechanism for New Thread would be the direct sibling of an already-proven, already-verified approach rather than a new pattern.
- **A gap that applies no matter which UI option is picked:** `POST /compose/thread` (`handleComposeThreadSubmit()`) has no `return_to` handling at all today — it unconditionally redirects to classic's `/threads/{new_thread_id}` page. That's correct for classic's own board (leaving the board to view your new thread is the expected behavior there), but it's exactly the "silently bounced out of Forte" defect this project has already had to fix twice: once for anonymous replies (`forte_board_reply_restore`) and once for signed replies (`forte_identity_signing` Stage 3). Whichever wiring option is chosen, closing this gap is required work — and it's actually simpler than the reply case, since the server always knows the new thread's ID at redirect time and doesn't need the client to supply a `selected=` value up front.
- A link straight to classic's existing `/compose/thread` page (mirroring `board.php`'s own "New Post" nav link) is a live, working shortcut, but it directly reopens "no navigation between Forte and classic pages, either direction" — an explicit, still-active non-goal (`forte_roadmap.md` Section B). Reversing that is its own deliberate decision, scoped separately as `forte_classic_nav_bridge`, not something to fall into here as a side effect.

## Option A: Reuse the paned toolbar-panel pattern
A new `paned_board_new_thread_panel.php` wraps `thread_compose_form.php` (`compact => true`), toggled by the "New" button exactly like Reply's panel; `handleComposeThreadSubmit()` gets a `return_to`-aware redirect extension mirroring `resolveComposeReplyReturnTo()`.
- Pros: the direct sibling of `forte_reply`/`forte_board_reply`'s own already-proven approach; `thread_compose_form.php`'s existing `compact` mode is reused with zero forking; keeps the reader in Forte on success, consistent with every compose flow Forte has shipped so far.
- Cons: two independently-toggleable toolbar panels (New, Reply) raises a small UX question — whether opening one should close the other, or both can be open together. Real, but small; a Step 2 decision, not a blocker.

## Option B: Link "New" to classic's `/compose/thread` page
Same page `board.php`'s own "New Post" link already points at.
- Pros: zero new panel/JS; reuses a fully working page verbatim.
- Cons: reverses the active Forte/classic navigation non-goal without the deliberate decision that reversal is supposed to require; a successful submission strands the reader on classic's thread page with no path back into Forte's board state (tag, selection) — the exact silent-downgrade pattern this roadmap keeps having to fix elsewhere.

## Option C: A Forte-native modal/dialog for thread creation
Built separately from the toolbar-panel pattern Reply already uses.
- Pros: could look more like a native OS "New" dialog.
- Cons: invents a second UI pattern for composing in Forte for no functional gain over Option A's already-shipped, already-verified panel mechanism — more code to write and maintain for a purely stylistic difference.

## Recommendation
**Option A.** It's the direct sibling of `forte_reply`'s own approach, reuses `thread_compose_form.php`'s existing compact mode without forking anything, and needs only incremental `return_to` work using a mechanism this project has already extended twice before — not a new pattern.

## Decision
**Option C**, per explicit user direction, overriding the initial recommendation (Option A). A native `<dialog>`-driven "New" window is a closer fit to the paned reader's whole skeuomorphic mail-client framing (menubar, toolbar, "File > New") than an inline toggle panel would be — Reply stays a panel since it's contextual to a selected thread, but thread creation isn't tied to any particular selection, which is more consistent with a standalone window. The `return_to` gap identified above still applies unchanged — it's a server-side redirect fix independent of which UI surfaces the form — and Option C still reuses `thread_compose_form.php` unmodified, so none of Step 1's other findings change.
