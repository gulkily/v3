> **Feature plan:** [Step 1](./site_favicons_step1_solution_assessment.md) · [Step 2](./site_favicons_step2_feature_description.md) · [Step 3](./site_favicons_step3_development_plan.md) · [Step 4](./site_favicons_step4_implementation_summary.md)

Debug mode: enabled

## Problem

`chouse` and `qdb` show the zenmemes "Z" favicon in browser tabs, bookmarks and the installed-app icon. The Z fits `zenmemes` but misrepresents the other sites. Only `mitrapclub` has its own icon today.

## User Stories

- As a `chouse` visitor, I want the tab and bookmark icon to look like chouse so that I can tell it apart from zenmemes.
- As a `qdb` visitor, I want the tab and bookmark icon to look like a quote database so that I can find the tab among others.
- As a `zenmemes` or `mitrapclub` visitor, I want my site's icon unchanged so that nothing familiar breaks.

## Core Requirements

- `chouse` and `qdb` each serve their own 32x32 `.ico`, used for the page icon link, the web manifest icon and the static build output, the same way `mitrapclub` does today.
- `zenmemes` keeps the existing Z, and `mitrapclub` keeps its current icon, both byte-identical.
- The default (Z) icon remains the fallback for any profile that does not opt in.
- Icon motifs, to be confirmed at Step 2 approval:
  - `chouse`: a lowercase "c" glyph on the chouse theme color. Open to a different motif if you prefer.
  - `qdb`: a quotation-mark glyph on the qdb theme color.
  - Both are simple, high-contrast marks that stay legible at 16px and 32px.

## Delivery Scope

- **Work type:** Application change (two new icon assets plus per-profile opt-in through the existing `favicon` presentation slot).

## Completion Boundary

- **Normal entry:** a visitor loads any page, or the web manifest, on `chouse` or `qdb`.
- **End-to-end outcome:** that site's own icon appears in the page head, the manifest and the static build output.
- **Needed recovery:** if a profile does not opt in, it falls back to the default Z as today.
- **Release condition:** `./v3 test` passes in full; `zenmemes` and `mitrapclub` output is unchanged.

## Risks

- **Icon legibility at 16px.** A detailed mark turns to mush in a tab. Earliest validation: view the candidate assets at 16px and 32px before Step 3 stages begin. Mitigation: single-glyph, high-contrast designs only.
- **Static build or cache not picking up the new paths.** The static builder and service worker enumerate favicon paths explicitly. Earliest validation: build the static artifact for `chouse` and `qdb` early in Step 4 and confirm the icon files are emitted. Mitigation: reuse the same resolver the build already calls, so there is no second path list.
- **Scope creep into a broader brand refresh.** Mitigation: icons only, with no theme color, name or layout changes.

## Shared Component Inventory

- `PresentationSlotRegistry` `favicon` slot: extend its choices; do not add a new mechanism.
- `FaviconRegistry`: add the two path entries; its resolver is already used by the layout, manifest renderer and static builder.
- `SiteProfileRegistry`: `chouse` and `qdb` profiles opt in through their `favicon` presentation slot.
- `public/assets/favicon-mitrapclub.ico`: the model for naming and location of the new assets.

## Simple User Flow

1. Visitor loads a `chouse` or `qdb` page.
2. The tab and bookmark show that site's icon.
3. The installed-app icon from the manifest matches.

## Success Criteria

- `chouse` and `qdb` pages, manifests and static builds reference their own distinct icons.
- `zenmemes` and `mitrapclub` render byte-identical to before.
- The default fallback is still the Z.
- `./v3 test` passes in full.

## Open Decisions For Approval

- Confirm the `chouse` and `qdb` motifs above, or name different ones.
- Confirm `zenmemes` keeps the current Z unchanged and the default fallback stays the Z (both assumed above).

Waiting for "Approved Step 2" before drafting Step 3.
