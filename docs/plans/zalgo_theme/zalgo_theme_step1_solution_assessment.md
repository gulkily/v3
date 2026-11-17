> **Feature plan:** [Step 1](./zalgo_theme_step1_solution_assessment.md) · continued as [one-shot record](./zalgo_theme_one_shot.md) (2026-11-16). Steps 2–4 not applicable.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/zalgo_theme`; host ledger [enrollment index after P2](../effort_estimation_trial.md#enrollment-index-after-p2). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure below is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.40

## Original Query

From the ZenMemes thread [thread-20261114052859-08a0e3e3](https://zenmemes.com/threads/thread-20261114052859-08a0e3e3) (ilyag, 2026-11-14), then in chat: "Are we ready to write step 1?"

The thread body, verbatim (the post's first two lines and last line are a chat assistant's wrapper and are not part of the plan):

> Here's a clean, ready-to-post version of the plan, written in the style that works well on ZenMemes (plain text, clear structure, no fluff).
> You can paste this straight into a new thread:
>
> Subject: Theme plan: Zalgo
> Body:
> Zalgo theme sketch for v3.
> Goal: a dark theme that feels like the page is slowly being corrupted. Text that wants to overflow, chrome that glitches, high-contrast red/cyan on near-black. Readable enough to use the board, but with a constant low-level unease.
> Registry entry
> ['name' => 'zalgo', 'label' => 'Zalgo', 'mode' => 'dark'],
> Core variables
> •Background: near-black with a faint sickly undertone (#0a0505 or #05080a)
> •Ink: high-contrast off-white that drifts toward red or toxic green on interaction
> •Soft/meta text: desaturated pinkish or greenish gray
> •Panels: slightly lighter black, thin uneven red or cyan border
> •Accent/links: pure or near-pure red (#ff1a1a-#e60000) or toxic cyan, soft outer glow
> •Buttons: dark fill + red outline that thickens or pulses on hover
> Primary type: system mono or a tight grotesque. Letter-spacing slightly condensed.
> Visual treatments
> •Headings and thread titles get stacked text-shadow that mimics diacritic overflow (tight vertical red/black offsets). No actual combining characters in content.
> •Cards: 1-2 px border with a second, lower-opacity, slightly offset copy that shifts on hover.
> •Subtle continuous micro-noise or vertical tick pattern in the background (CSS or tiny SVG data-URI, 2-4 % opacity).
> •Scrollbars: thin high-contrast red/cyan, deliberately a little broken-looking.
> •Swatch: black circle with a single vertical red slash or a tiny stack of red dots.
> Constraints
> •Pure CSS first. No heavy script required.
> •Must stay usable at ~380 px and keep focus/keyboard styles intact.
> •Body text contrast must remain solid; the theatrical corruption lives in headings, borders, and chrome.
> •Dark-mode only for the first version.
> Mood target: the forum is being rewritten by something that does not speak human. Not pure horror, not pure meme - just a constant quiet wrongness.
> Next steps if people like the direction:
> 1 Flesh out the full variable block and scoped rules.
> 2 Decide whether mild/severe intensity should be a palette or just one look.
> 3 Prototype the heading corruption and card glitch effects.
> Comments, pushback, or "make it worse" welcome.
>
> Want me to adjust the tone, make it shorter, add more technical detail, or change the subject line before you (or an agent) post it?

## Understood Intent

Add Zalgo, a new explicit dark theme, to the theme menu on ZenMemes and the other site profiles that carry the shared themes. Its look follows the sketch. The plan's open question is whether intensity is a choice or one fixed look; the sketch's many "red or cyan" choices raise the same question for colour.

## Problem

No current theme gives the "quietly corrupted" look, and the sketch leaves the variant question open. The answer decides whether Zalgo uses the existing palette picker (`'palettes'` in `ThemeRegistry::all()`), which Console, LCD and Gloss already use.

Concepts touched: [page theme](../../concepts/page_theme.md) (Zalgo becomes valid wherever a thread or page names a theme); [theme script](../../concepts/theme_script.md) (Option C only). No feedback item matches (searched `docs/feedback/items/` for zalgo, glitch and corrupt).

## Shared by every option

- A new registry entry `zalgo`, mode `dark`, and a new `public/assets/theme-zalgo.css` laid out like `theme-bubblegum.css`: a token block, the swatch, the theme-menu option, then element rules.
- `zalgo` joins `permittedThemes` on every profile that lists Bubblegum.
- Link text uses the bright end of the red range. `#ff1a1a` on `#0a0505` measures 5.2:1, but `#e60000` measures 4.2:1 and fails for body text. `tests/theme_contrast_audit.py` must report `zalgo OK`.
- The focus ring is the one solid, unglitched element, in a colour that the borders never use.
- Pulses and hover shifts stop under `prefers-reduced-motion`. Static heading shadows stay.
- Corruption comes from styles only; no combining characters enter content or titles.
- Usable at 380px; first paint is already in Zalgo; after deploy, `php scripts/build_static_artifacts.php` refreshes the theme allow-list in baked pages.

## Option A — One look, CSS only

**Estimate: 0.35**
Main driver: one stylesheet with several layered effects, tuned for contrast and phones. Uncertainty: low.

Zalgo ships as a single look with red accents and one fixed intensity. Everything else in the sketch that says "or" gets one answer, picked while tuning.

- Pro: smallest change, and exactly what "pure CSS first" asks for.
- Pro: one look to tune and check, so the contrast and phone checks stay short.
- Con: the cyan variant from the sketch is lost until a later cycle.

## Option B — One look with Red and Cyan palettes

**Estimate: 0.40**
Main driver: Option A plus a second accent set through the existing palette picker. Uncertainty: low.

Option A, plus `'palettes' => ['red' => 'Red', 'cyan' => 'Cyan']`. Each palette swaps only the accent, border and glow tokens; Red is the default. Intensity stays one look.

- Pro: uses the picker for what it already means on Console, LCD and Gloss: a colour choice.
- Pro: answers the sketch's red-or-cyan split without a second theme.
- Con: two accent sets double the contrast and focus-ring checks.
- Con: the focus colour must differ from both accents.

## Option C — Corruption that grows (theme script)

**Estimate: 0.65**
Main driver: a theme script that adds glitches over a visit, plus its motion and performance limits. Uncertainty: high.

Option A, plus `'script' => true`. A small `theme-zalgo.js` raises the effect intensity the longer a visitor stays, and occasionally adds a brief glitch to the chrome. This is the literal reading of "slowly being corrupted".

- Pro: the most memorable result, and a demo that works in a few seconds on a phone.
- Con: the sketch asks for no heavy script; timed motion conflicts with reduced-motion users and with the "readable enough" goal.
- Con: random behaviour is hard to check and hard to screenshot for the feature tour.

## Recommendation

Choose Option B. It ships the sketch as one look and settles the colour split with the picker that already exists. The answer to the sketch's step 2 is "one look": palettes stay a colour choice across the site. A mild/severe choice would make the picker mean two different things. Intensity lives in one internal token, so a later cycle can expose it, or Option C can grow from it, without new theme entries. Option A is the fallback if only red is wanted.

Vertical slice: choose Zalgo, then Cyan, from the theme menu on a phone. Board, a thread and the composer render corrupted at first paint, body text stays clear, and the contrast audit passes for both palettes. The whole cycle is a single slice.

## Enhancement requests

- Hex colours in a post (`#0a0505`) are read as hashtags; this thread got the labels `05080a` and `0a0505`. Out of scope here; it belongs in its own fix.

## Study snapshots and approval

- 2026-11-15 UTC, intake after code inspection (`ThemeRegistry`, `SiteProfileRegistry`, `theme-bubblegum.css`, `theme-console.css` palette rules, Bubblegum direct record, `theme_contrast_audit.py`): 0.40 projected total for the recommended scope. Claude single exposed rater; R1 hashes not re-verified this session; baseline `origin/main` `c54c3375`.
- Step 1 handoff: 0.40 for Option B (alternatives A 0.35, B 0.40, C 0.65; not summed). No implementation. Awaiting `Approved Step 1`.
- 2026-11-16 UTC: not approved as written. The maintainer shelved `v3/operator_theme_choice` and asked for Zalgo behind an on/off feature flag; the cycle continues as a one-shot ([record](./zalgo_theme_one_shot.md)).

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
