# Theme navigation icons

Purpose: Give the Word97, Chicago and Midnight themes a 16px pixel-art glyph on each navigation destination without changing markup or server HTML.
Scenario: A reader in one of those themes sees a glyph before the label of every main navigation link, Tools bar link, Tools launcher button, Bookmarklets button and launcher group heading. The glyph is chosen in CSS from the link's `href` (board filters use `data-nav-key`, bookmarklets `data-bookmarklet-kind`, launcher group headings `data-shape`), so adding or renaming a destination means adding or moving one rule keyed to its link, for both the launcher button and the Tools bar link where the destination is a tool. Every other theme shows the same markup as text only. The theme menu never receives icons.
Sources: [icon stylesheet](../../public/assets/theme-nav-icons.css) (the `:root:is(...)` theme list at the top adds a theme to the set), [the rule to keep icons current](../../AGENTS.md#navigation-icons), [launcher matrix test](../../tests/browser/tools_launcher.mjs) (asserts a non-empty glyph on each tool button, bar link and group heading in the three themes).

Group heading glyphs: a monitor (device), an open book (reading), the Users glyph (community) and a gear drawn mid-grey with a white ring so it reads on light and dark panels (operator). Account's Tools bar rule remains in the stylesheet although Account is no longer a tool; its main navigation rule still applies.

Tool glyphs added later keep the same 16px grid; Deploy (an arrow rising out of a tray) follows Operations in the operator group.
