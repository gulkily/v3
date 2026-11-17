> **Feature plan:** [Step 1](./image_metadata_step1_solution_assessment.md) · [Step 2](./image_metadata_step2_feature_description.md) · [Step 3](./image_metadata_step3_development_plan.md) · [Step 4](./image_metadata_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `v3/image_metadata` ([ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)); standing enrollment under protocol P2
Extended estimates: enabled

**Cycle estimate: 0.50**

Mockup: [ZenMemes photo details mockup](https://claude.ai/artifact/XuVzSMVXDwejTF9Rj3oVZZ) (collapsed Photo details row, posted view).

Revision 2026-11-15: maintainer wants the feature discoverable but mostly out of the way, like the other compose controls. Stage 2 now reuses the collapsed compose disclosure. Scope unchanged; 0.50 reaffirmed.

## Concepts touched

- **Shared composer** ([shared_composer.md](../../concepts/shared_composer.md)): add one line on the Photo details checklist.
- **Post body formatting** ([post_body_formatting.md](../../concepts/post_body_formatting.md)): link to the new entry.
- New entry **Photo details** (`docs/concepts/photo_details.md`): the line format, the fields, and why location starts unticked.

## Completion Contract

- Normal entry: Attach image in any composer (New post, board, reply), phone or desktop.
- End-to-end outcome: the poster ticks details, posts, and the post page and offline reader show exactly those details under the image.
- Recovery: no metadata means no checklist. Damaged metadata never blocks attaching or posting. A failed reply retries with the same choices.
- Deployment: no schema, read-model or dependency change. Old posts render unchanged.
- Release condition: smoke set plus affected tests pass; Chromium browser checks pass; the maintainer confirms on iPhone Safari on zenmemes.com.

## Key Risks

- **High risk:** accidental location sharing. Impact: reveals where someone was. Validation: Stage 2 browser test that nothing is ticked by default. Mitigation: no row is ever pre-ticked; location shows its value and a plain warning.
- Malformed EXIF. Impact: hang or error on attach. Validation: Stage 1 tests with truncated, looping and oversized EXIF. Mitigation: bounded reader that returns no details on any problem.
- Raw `Photo-` lines on surfaces that do not know them. Impact: clutter in feeds. Validation: Stage 3 checks of RSS and the text API. Mitigation: plain `Key: value` text reads fine where it is not rendered.
- iPhone Safari may remove location before the page sees it. Impact: a missing row looks like a bug. Validation: Stage 4 replay on iPhone. Mitigation: the checklist shows only what it found.

## Stage 1 — Read photo details in the browser

**Estimate: 0.15**

- Goal: `ForumImagePrepare.prepare` also returns the photo's details.
- Dependencies: none.
- Expected changes: extend the JPEG marker walk in `image_prepare.js` to read the EXIF block. The prepared result gains `details`, a list of `{key, label, value, line}` for Camera, Lens, Exposure, Taken and Location. PNG `eXIf` is read the same way when present.
- Verification approach: extend `tests/browser/image_prepare.mjs` with a photo carrying all five fields, one with none, and truncated, looping and oversized EXIF.
- Estimate details: driver: EXIF tag parsing (IFD0, Exif IFD, GPS IFD, both byte orders); uncertainty: low.
- Risks or open questions:
  - Impact: a bad offset could read past the buffer.
  - Early warning / validation: the malformed-EXIF test cases.
  - Mitigation: bounds checks on every read, a visited-offset set, and a catch that returns no details.
- Canonical components/API contracts touched: `ForumImagePrepare.prepare` result (extended, not replaced). No third-party EXIF library.

## Stage 2 — Choose details at attach time and sign them

**Estimate: 0.20**

- Goal: the Photo details checklist in every composer, with ticked lines added to the signed post.
- Dependencies: Stage 1.
- Expected changes: a "Photo details" row in `image_compose_fields.php`, built on the existing `details.compose-disclosure` used by Options, Tags and Event. It is collapsed by default and appears only when the photo has details. Its summary names the ticked details through `compose_disclosure.js`, like Options. `image_upload.js` fills it from `details`, all unticked. `imageSignedFields(fields, image, description, chosenLines)` writes ticked lines right after the `Image-v1:` line. The reply path in `browser_signing.js` and `reply_continuation.js` stores the chosen keys with the attachment, so a retry posts the same lines. Styles in `compose.css`.
- Verification approach: browser test in `tests/browser/image_compose_transport.cjs`: nothing ticked gives today's body; ticking camera and taken gives exactly those lines; a reply retry keeps them. Check Gloss, Light, Dark and Word97 at phone and desktop sizes.
- Estimate details: driver: the reply retry path; uncertainty: medium, because two code paths prepare images.
- Risks or open questions:
  - Impact: a retry could drop or change the choices.
  - Early warning / validation: the retry test case.
  - Mitigation: store chosen keys, not rendered text, next to the stored attachment.
- Canonical components/API contracts touched: `image_compose_fields.php` partial, `compose-disclosure` styles and `compose_disclosure.js` summary (new `photo` kind), `ForumImageUpload` state, `imageSignedFields`, `ForumReplyContinuation` storage. No new composer or disclosure style.

*— Revised by Claude · 2026-11-15 UTC · Model: claude-opus-5-5* (maintainer: discoverable but out of the way, like the other compose controls)

## Stage 3 — Show details under the image

**Estimate: 0.15**

- Goal: the post page and the offline reader show the details beneath the image.
- Dependencies: Stage 2 (line format).
- Expected changes: `ImageReference` gains `photoDetails(string $body): array`, reading `Photo-` lines that directly follow the `Image-v1:` line. `MediaEmbedRenderer` removes those lines from the body text and renders a definition list in the image card. `offline_reader.js` mirrors this. Unknown `Photo-` keys stay as plain text.
- Verification approach: `MediaEmbedRendererTest` and `ImageContentTest` cases for details, no details, and Markdown posts. Browser check of the offline reader. Confirm RSS and `ApiTextController` output stays readable.
- Estimate details: driver: keeping server and offline rendering identical; uncertainty: low.
- Risks or open questions:
  - Impact: a post that only quotes a `Photo-` line could be read as details.
  - Early warning / validation: test with a `Photo-` line away from the image line.
  - Mitigation: only lines directly after `Image-v1:` count.
- Canonical components/API contracts touched: `ImageReference`, `MediaEmbedRenderer` image card, `offline_reader.js` `nl2br`.

## Stage 4 — Document and verify the whole flow

**Estimate: 0.08**

- Goal: record the feature and check the Completion Contract end to end.
- Dependencies: Stages 1–3.
- Expected changes: new `docs/concepts/photo_details.md`; one line in `shared_composer.md`; Step 4 summary; effort ledger row.
- Verification approach: smoke set plus the tests above (`./v3 test --smoke …`); full suite not run. Final diff review. iPhone Safari replay with a real camera photo is recorded as pending until the maintainer confirms it on zenmemes.com.
- Estimate details: driver: verification across themes; uncertainty: low.
- Risks or open questions: none beyond the iPhone replay, which stays pending until confirmed.
- Canonical components/API contracts touched: concept inventory only.

## Approval

- 2026-11-15: maintainer replied "Approved. Step 3." after the collapsed-disclosure revision.

## Study snapshot

- 2026-11-15, Step 3 handoff: 0.50 reaffirmed, scope v1. Stage work: S1 0.15, S2 0.20, S3 0.15, S4 0.08; independent, not summed. Claude, single exposed rater.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
