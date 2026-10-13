> **Feature plan:** [Step 1](./forte_dark_theme_step1_solution_assessment.md) · [Step 2](./forte_dark_theme_step2_feature_description.md) · [Step 3](./forte_dark_theme_step3_development_plan.md) · [Step 4](./forte_dark_theme_step4_implementation_summary.md)

## Original Query

I want the Forte interface to respect the user's dark mode settings. Please write Step 1 of `docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md` to give it a classy dark theme.

## Understood Intent

- Forte (the paned reader: board, thread, activity, profile, and user directory pages) should render in a dark appearance when the viewer prefers dark, instead of always showing its light classic-newsreader look.
- Checked the current code: Forte pages use a standalone layout that does not load the site theme stylesheet or the theme-selection script, and `forte.css` declares a fixed light palette (`color-scheme: light`), so neither the OS setting nor the site's saved theme choice has any effect today.
- "Classy" means a deliberate, cohesive dark palette that keeps Forte's windowed-chrome character, not an inverted or washed-out light theme.

## Problem Statement

Forte always renders in a fixed light palette, ignoring the viewer's dark mode preference and leaving dark-mode users with a bright page that clashes with the rest of the site.

## Solution Options

- **Option A: Follow the OS dark-mode setting only.** Add a dark variant of Forte's palette that applies automatically when the viewer's system prefers dark; light systems are unchanged.
  - Pros: smallest change; stylesheet-only; no new scripts or markup; respects the system-level setting with no extra control.
  - Cons: ignores the site's own theme choice, so a viewer who picked Light on the site but runs a dark OS sees Forte go dark; no way to override per site.

- **Option B: Follow the viewer's existing site theme choice, falling back to the OS setting.** Add the same dark palette, but apply it when the viewer's resolved site theme is a dark one (Dark, Console, Vapor, etc.) or when they are on Auto and the system prefers dark. Forte gets no new control; it simply honors the setting the viewer already made elsewhere.
  - Pros: respects both the OS setting and the explicit choice already stored for the site, so Forte matches the pages the viewer came from; no new UI to design or maintain; palette work is shared with Option A.
  - Cons: the standalone layout must learn the viewer's resolved theme, which adds a small shared bootstrap and a flash-of-wrong-theme check; one more place where theme resolution must stay in sync with the main layout.

- **Option C: Dedicated Forte theme control.** Give Forte its own light/dark/auto toggle in its toolbar, stored separately from the site theme.
  - Pros: viewer control inside Forte itself; independent of site theme.
  - Cons: duplicates the site theme mechanism and creates two settings that can disagree; new UI and persistence; largest scope for what was asked.

## Recommendation

**Option B.** It is the only option that honors what "the user's dark mode settings" actually are on this site (OS preference plus the saved theme choice) without adding a second control. Option A is the fallback if sharing theme resolution with the standalone layout proves costly; its palette work carries over unchanged. Option C is a separate feature.

**Vertical-slice viability:** Yes. Entry is any viewer opening a Forte page with a dark system or dark site theme; outcome is a cohesive dark Forte across board, thread, activity, profile, and user directory pages; recovery is trivial, since light viewers and unsupported cases fall back to today's palette unchanged.

Waiting for "Approved Step 1" before drafting Step 2.
