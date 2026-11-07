> **Feature plan:** [Step 1](./site_purpose_step1_solution_assessment.md) · [Step 2](./site_purpose_step2_feature_description.md) · [Step 3](./site_purpose_step3_development_plan.md) · [Step 4](./site_purpose_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/site_purpose`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.45**

## Completion Contract

- Normal entry: a visitor opens `https://zenmemes.com/` in a fresh browser; an agent fetches `/llms.txt`.
- End-to-end outcome: the visitor reads the intro, follows it to About, and sees the description, one example thread and its merged change. The agent reads the same description first.
- Required recovery: with storage blocked or the script failing, the intro stays visible and the page works. Members never see the intro, including on first paint.
- Deployment: deploy to zenmemes.com; check a fresh phone browser and a member browser there.
- Release condition: zenmemes shows the new copy; qdb, mitrapclub and other profiles render as before.

## Key Risks

- **High risk:** first-paint flash for members. Impact: every member sees the intro briefly. Early validation: Stage 3 checks first paint with a saved identity before any styling work. Mitigation: the layout head script decides visibility, reusing its existing `forum_pki_*` check.
- Extra UI text. Impact: clutter against `PRINCIPLES.md`. Early validation: the maintainer picks the wording in Stage 1. Mitigation: one sentence, first-time visitors only, hideable.
- Other instances. Impact: wrong copy on qdb or mitrapclub. Early validation: profile tests in Stages 1 and 2. Mitigation: no description means no change.

## Stage 1

**Estimate: 0.10**

- Goal: give the zenmemes profile a description and lead the About page with it, followed by one example thread and its merged change.
- Dependencies: none.
- Expected changes: add an optional description, example thread link and change link to the zenmemes entry in `ProfilePresentationContent.php`; expose them through `about()`; render them at the top of the About intro section. Offer the maintainer two wordings, for example "zenmemes is a garage where people and agents build this site together, starting from threads." Pick the example thread with the maintainer.
- Verification approach: About page tests for zenmemes and one profile without a description; `./v3 test --smoke` plus the changed tests; links resolve.
- Estimate details: driver: copy review with the maintainer; uncertainty: which example thread best shows the loop.
- Risks or open questions:
  - Impact: a weak example undercuts the claim.
  - Early warning / validation: the maintainer reviews the example before Stage 2.
  - Mitigation: choose a thread with a visible reply linking its merged change.
- Canonical components/API contracts touched: `src/ForumRewrite/ProfilePresentationContent.php`, `templates/pages/about.php`, `src/ForumRewrite/Http/AboutPageController.php` (extended, no new store).

## Stage 2

**Estimate: 0.06**

- Goal: `/llms.txt` opens with the site name and description.
- Dependencies: Stage 1.
- Expected changes: `LlmsTxtRenderer` reads the active profile; title becomes the profile's display name and the quote line becomes its description. A profile without a description keeps "# Forum" and today's line.
- Verification approach: extend `tests/LlmsTxtRendererTest.php` for both cases; fetch `/llms.txt` locally.
- Estimate details: driver: passing the profile into the renderer; uncertainty: low.
- Risks or open questions: agents caching the old file; acceptable, nothing breaks.
- Canonical components/API contracts touched: `src/ForumRewrite/Http/LlmsTxtRenderer.php` (title and first lines only; rest unchanged).

## Stage 3

**Estimate: 0.12**

- Goal: the home page shows the intro to browsers with no saved identity, decided before paint.
- Dependencies: Stage 1.
- Expected changes: render the intro card above the thread list in `templates/pages/board.php`, only when the profile has a description. The layout head script sets a root attribute when a saved identity exists or the intro was hidden; CSS hides the card under that attribute. Server HTML stays identical for all viewers.
- Verification approach: first paint in a fresh browser and in one with a saved identity; board tests for both profiles; smoke set.
- Estimate details: driver: before-paint correctness; uncertainty: interaction with the inline composer layout.
- Risks or open questions:
  - Impact: members see a flash.
  - Early warning / validation: check first paint with a saved identity before styling.
  - Mitigation: hide by default for saved identities in the head script.
- Canonical components/API contracts touched: `templates/layout.php` head script (extended, same identity check as `public/assets/member_approval.js`), `templates/pages/board.php`, `public/assets/site.css` card classes.

## Stage 4

**Estimate: 0.08**

- Goal: a visitor can hide the intro, and creating an identity hides it.
- Dependencies: Stage 3.
- Expected changes: a hide control on the card stores a flag in `localStorage` inside try/catch and sets the same root attribute. Saving a new identity already writes `forum_pki_*` keys, so the head script hides the card on the next page.
- Verification approach: hide, reload, still hidden; storage blocked, intro shows and the page works; create an identity, intro gone.
- Estimate details: driver: storage failure paths; uncertainty: low.
- Risks or open questions: none beyond Stage 3.
- Canonical components/API contracts touched: the Stage 3 root attribute; no new identity reader.

## Stage 5

**Estimate: 0.10**

- Goal: confirm the release across themes, phones and profiles, and record the concepts.
- Dependencies: Stages 1–4.
- Expected changes: add concept entries for *site description* and *visitor intro* under `docs/concepts/`; record checks in the Step 4 summary.
- Verification approach: render the home and About pages in every theme at phone and desktop width, including Word 97, Chicago and Midnight; check qdb and mitrapclub profiles; final diff review; deploy and check zenmemes.com in a fresh phone browser and a member browser. Full suite not run per `AGENTS.md`.
- Estimate details: driver: theme matrix; uncertainty: deployment timing.
- Risks or open questions: a theme with tight spacing may crowd the composer; fix spacing in that theme only.
- Canonical components/API contracts touched: `docs/concepts/` inventory; existing theme stylesheets only if a theme needs a spacing fix.

## Concepts Touched

- [Viewer session](../../concepts/viewer_session.md) and [navigation slots](../../concepts/navigation_slots.md): used unchanged.
- Created in Stage 5: *site description*, *visitor intro*.

## Study Checkpoint

2026-11-05T16:15:00Z; scope v1/B; same baseline/R1/P2, single exposed Claude rater. Step 3 **0.45 projected total reaffirmed**. Stage work originals: S1 0.10, S2 0.06, S3 0.12, S4 0.08, S5 0.10; independent annotations, not summed. No implementation or accepted-count change.

Step 3 approved on 2026-11-05.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
