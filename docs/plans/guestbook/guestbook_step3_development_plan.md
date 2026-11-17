> **Feature plan:** [Step 1](./guestbook_step1_solution_assessment.md) · [Step 2](./guestbook_step2_feature_description.md) · [Step 3](./guestbook_step3_development_plan.md) · [Step 4](./guestbook_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./guestbook_debug_log.md))

Effort study: `railspike-effort` / `v3/guestbook`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 2 approved 2026-11-14; Step 3 drafted.

Cycle estimate: 0.60

# Step 3: Development Plan — Guestbook

Concepts touched: Shared composer (reused), Post presentation (reused helpers), Guestbook (new concept page `docs/concepts/guestbook.md`). Page theme is not used.

## Completion Contract

- Entry: `/guestbook/`, the "Open as a book" link on the guestbook thread, and the About page link.
- Outcome: a visitor reads the book, signs on the last page, lands back on the book and sees the entry; the same post appears in the thread.
- Recovery: a refused or offline post keeps its text in the composer, as other replies do. A retracted entry leaves the book.
- Deployment: Ilya creates the "Guestbook" thread on zenmemes.com; its ID goes in the `zenmemes` site profile; the flag is turned on.
- Release: Ilya signs on his phone and a wide screen and sees the entry in both views.

## Key Risks

- **High risk:** page addresses drift. Impact: an old page shows different entries tomorrow. Early validation: a test adds entries and reloads page 1. Mitigation: count pages from the oldest entry, a fixed number per page.
- Long entries, images and Markdown break the spread. Early validation: render a 2,000-character entry, a table and an image. Mitigation: a page grows to fit whole entries; images show small inside the page width.
- Signing lands on the thread instead of the book. Early validation: sign once in Stage 2. Mitigation: set the reply form's existing `return_to` to the book's last page.

## Stage 1

**Estimate: 0.15**

- Goal: `/guestbook/` lists the guestbook thread's top-level replies as dated, named entries on numbered pages, opening on the newest page.
- Dependencies: none.
- Expected changes: a `FORUM_GUESTBOOK_ENABLED` flag; a `guestbookThread` entry in the site profile; a route beside `/gallery/`; a controller and plain template. Pages use `?page=N`, counted from the oldest entry; no `page` means the last page. Retracted replies and replies to replies are left out.
- Verification approach: new `GuestbookPageTest` (order, page stability after new entries, nested replies excluded, flag off returns 404, missing thread shows an empty book); `./v3 test --smoke GuestbookPageTest`.
- Estimate details: driver: page numbering from the oldest entry; uncertainty: low.
- Risks or open questions: page drift, as in Key Risks.
- Canonical components/API contracts touched: `Application.php` routing (as `/gallery/`), `ThreadRepository::replyPostsByThreadId`, `PageNavigation::numbered`, `SiteProfileRegistry.php`, `FeatureFlagRegistry.php`.

## Stage 2

**Estimate: 0.12**

- Goal: the last page carries "Sign the book", and signing returns to the book with the new entry shown.
- Dependencies: Stage 1.
- Expected changes: render `reply_form.php` through the shared composer with the thread root as parent and `return_to` set to `/guestbook/`. The prompt reads "Leave a note for whoever reads this next". Earlier pages link to the last page instead.
- Verification approach: extend `GuestbookPageTest` for the form; replay signing in the local browser with `composers.mjs`; post one entry with `frm.py reply` and see it in the book.
- Estimate details: driver: `return_to` handling for a non-thread page; uncertainty: low.
- Risks or open questions: signing lands on the thread, as in Key Risks.
- Canonical components/API contracts touched: `templates/partials/composer.php`, `reply_form.php`, `browser_signing.js` (`canonicalReplyUrl`), `public/frm.py` (unchanged).

## Stage 3

**Estimate: 0.18**

- Goal: the paper look: cream lined pages, a spine, two facing pages on wide screens and one on phones, matching the mockup.
- Dependencies: Stage 2.
- Expected changes: `public/assets/guestbook.css`, loaded only on the book; the page ignores the site theme's colors. Entry bodies render as in the thread, kept inside the page width. Stamps are not included.
- Verification approach: new browser check `tests/browser/guestbook.mjs` at 390 px and 1280 px, with JavaScript off, and in every theme; screenshots compared with the mockup; a long entry, a table and an image.
- Estimate details: driver: the spread at both widths without JavaScript; uncertainty: medium.
- Risks or open questions: long entries and images, as in Key Risks.
- Canonical components/API contracts touched: `TemplateRenderer.php` stylesheet loading; post body rendering (`post_body_formatting`).

## Stage 4

**Estimate: 0.08**

- Goal: book and thread link to each other, entries show "n replies", and the About page links to the book.
- Dependencies: Stage 3.
- Expected changes: "Open as a thread" on the book; "Open as a book" on the guestbook thread page only; "n replies" under an entry links to that reply in the thread; an About page link when the flag is on. Add `docs/concepts/guestbook.md`.
- Verification approach: tests for each link with the flag on and off; the thread page otherwise matches its current output.
- Estimate details: driver: thread page touching a baked static page; uncertainty: low.
- Risks or open questions:
  - Impact: the static thread file lacks the "Open as a book" link until a rebuild.
  - Early warning / validation: build static artifacts locally and check the link.
  - Mitigation: note in the release steps that turning the flag on needs a rebuild.
- Canonical components/API contracts touched: `templates/pages/thread.php`, `templates/pages/about.php`, `StaticArtifactBuilder.php` (rebuild only).

## Stage 5

**Estimate: 0.05**

- Goal: release on zenmemes.com.
- Dependencies: Stage 4, PR merged and deployed.
- Expected changes: Ilya creates the "Guestbook" thread; its ID goes into the `zenmemes` profile in a small PR; the flag is turned on and a rebuild runs.
- Verification approach: Ilya signs on his phone and a wide screen; Claude signs once with Quill; both appear in the book and the thread.
- Estimate details: driver: waits on deploy; uncertainty: low.
- Risks or open questions: none beyond deployment timing.
- Canonical components/API contracts touched: `SiteProfileRegistry.php`.

## Study Checkpoint

2026-11-14T02:20:00Z; scope v2 unchanged; revision `b0b706d3`; reference R1, hashes not re-verified. Claude, single exposed rater. Step 3 **0.60 reaffirmed**. Stage work: S1 0.15, S2 0.12, S3 0.18, S4 0.08, S5 0.05; independent annotations, not summed.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*
