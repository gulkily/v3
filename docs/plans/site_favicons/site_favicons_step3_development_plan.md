> **Feature plan:** [Step 1](site_favicons_step1_solution_assessment.md) · [Step 2](site_favicons_step2_feature_description.md) · [Step 3](site_favicons_step3_development_plan.md) · [Step 4](site_favicons_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- **Normal entry:** a visitor loads any page, or the web manifest, on `chouse` or `qdb`.
- **End-to-end outcome:** each site serves its own icon in the page head, the manifest and the static build output.
- **Required recovery:** a profile that does not opt in falls back to the default Z; `zenmemes` and `mitrapclub` stay byte-identical.
- **Deployment/external verification:** run the dev server and fetch each site's page head, manifest and icon file; build the static artifact and confirm the icon files are present.
- **Release condition:** `./v3 test` passes in full.

## Key Risks

- **Icon illegible at 16px.** Impact: tab icon looks like a smudge. Early validation: render both assets at 16px and 32px in Stage 1. Mitigation: single-glyph, high-contrast designs; redraw before Stage 2.
- **Static build or service worker misses the new paths.** Impact: icon works live but not offline or in the static build. Early validation: Stage 3 builds the static artifact and inspects the service worker precache list. Mitigation: reuse the existing resolver; add the paths to the precache list only if the check shows they are missing.
- **Slot change breaks other profiles.** Impact: `zenmemes` or `mitrapclub` icon shifts. Early validation: Stage 2 tests assert their resolved paths. Mitigation: add choices only; do not touch existing entries.

## Stage 1
- Goal: produce the two icon assets.
- Dependencies: Step 2 motifs ("c" for `chouse`, quotation mark for `qdb`), each on its profile's theme color.
- Expected changes: new `public/assets/favicon-chouse.ico` and `public/assets/favicon-qdb.ico`, each a single 32x32 icon; no source changes.
- Verification approach: `file` reports a 32x32 icon for both; render at 16px and 32px and review them visually.
- Risks or open questions:
  - Impact: illegible or off-brand icon.
  - Early warning / validation: visual check at 16px.
  - Mitigation: redraw before moving on.
- Canonical components/API contracts touched: none; follows the naming of `favicon-mitrapclub.ico`.

## Stage 2
- Goal: make `chouse` and `qdb` resolve to their own icons.
- Dependencies: Stage 1.
- Expected changes: extend the `favicon` slot choices with `chouse` and `qdb`; add the two paths to the favicon path map; set `'favicon'` in the `chouse` and `qdb` presentation slots; extend existing favicon/slot tests to assert all four profiles resolve as expected and the default stays the Z.
- Verification approach: targeted favicon and presentation-slot tests, then `./v3 test`.
- Risks or open questions:
  - Impact: invalid slot selection fails profile validation at boot.
  - Early warning / validation: profile-registry tests fail immediately.
  - Mitigation: choices and selections change together in one commit.
- Canonical components/API contracts touched: `PresentationSlotRegistry` `favicon` slot, `FaviconRegistry::resolve`, `SiteProfileRegistry` profiles.

## Stage 3
- Goal: prove the full slice works in the manifest, static build and offline paths.
- Dependencies: Stage 2.
- Expected changes: none expected; add the new icon paths to the service worker precache only if the check shows they are needed.
- Verification approach: fetch the page head, manifest and icon for `chouse` and `qdb` on the dev server; build the static artifact for each and confirm the icon file exists; confirm `zenmemes`/`mitrapclub` output is unchanged; run `./v3 test` in full.
- Risks or open questions:
  - Impact: offline or static visitors still see the Z.
  - Early warning / validation: missing file in the static output, or no precache entry.
  - Mitigation: fix the precache list in this stage and re-verify.
- Canonical components/API contracts touched: `BrowserRuntimeAssetRenderer` manifest, `StaticArtifactBuilder` favicon paths, `public/service_worker.js` precache list.

Waiting for "Approved Step 3" before starting Step 4.
