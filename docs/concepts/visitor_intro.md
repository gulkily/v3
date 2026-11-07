# Visitor intro

Purpose: Show the [site description](site_description.md) to someone who has never posted, and to nobody else.
Scenario: a fresh browser opens the board and sees one card above the board controls with the description, an About link and a small × in the top right corner that hides it. A browser with a saved identity, or one that pressed ×, never sees the card, including on first paint.
Sources: [card and × button](../../templates/partials/visitor_intro.php), [before-paint check](../../templates/layout.php), [styles](../../public/assets/site.css).

Server HTML is the same for every viewer. The layout head script sets `data-visitor="known"` from the saved `forum_pki_*` keys or `forum_visitor_intro_hidden`; CSS hides the card under it. If storage is unavailable, the card shows and × lasts only for the current page.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
