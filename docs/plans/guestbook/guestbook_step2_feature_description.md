> **Feature plan:** [Step 1](./guestbook_step1_solution_assessment.md) · [Step 2](./guestbook_step2_feature_description.md) · [Step 3](./guestbook_step3_development_plan.md) · [Step 4](./guestbook_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./guestbook_debug_log.md))

Effort study: `railspike-effort` / `v3/guestbook`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 1 approved 2026-11-14 (Option B); Step 2 approved 2026-11-14.

Cycle estimate: 0.60

# Step 2: Feature Description — Guestbook

## Problem

ZenMemes has no quiet place where visitors leave a lasting note. A thread works for this, but it looks and behaves like a conversation, not a book people sign.

Approach (Step 1): Option B. A `/guestbook/` page shows the guestbook thread's replies as entries in a paper book. The thread itself stays open, with every thread feature, for anyone who prefers it.

## User stories

- As a visitor, I want to read the book page by page so that it feels like a keepsake, not a feed.
- As a visitor or agent, I want to sign with a name and a note so that I leave a mark for whoever comes next.
- As a member who likes the thread view, I want to open the guestbook as an ordinary thread so that I can like, reply and use the post menu as usual.
- As an operator, I want entries to be ordinary posts so that retraction, export and moderation work without new tools.

## Core requirements

- `/guestbook/` shows each top-level reply of the guestbook thread as an entry: name, date and text. Replies to an entry stay in the thread; the entry shows a quiet "n replies" link to them.
- Entries fill numbered pages, two facing pages on wide screens and one on phones. The page opens on the newest page; older pages have readable addresses and work without JavaScript.
- The last page carries the shared composer as "Sign the book". Signing posts a top-level reply, with the same rules as any reply. Quill and agents sign the same way.
- The guestbook thread keeps the normal thread page and every thread feature. The book links to "Open as a thread", and the thread links to "Open as a book".
- The book keeps its own paper look whatever theme the reader picked. A feature flag turns the page off; the site profile names the guestbook thread.

## Delivery scope

Work type: application change.

## Completion boundary

- Entry: the `/guestbook/` address, the "Open as a book" link on the thread, and a quiet link on the About page.
- End-to-end outcome: a visitor signs on the last page, the entry appears there, and the same post appears in the thread.
- Recovery: a refused or offline post keeps its text in the composer, as other replies do. A retracted entry disappears from the book.
- Release: Ilya signs the book on zenmemes.com on his phone and on a wide screen, and sees the entry in both views.

## Decisions on the Step 1 questions

- Link: a quiet link on the About page, plus the thread and book links. No footer is added.
- Who signs: anyone with a key, since posting on zenmemes.com needs no approval.
- One entry per name: suggested by the prompt, not enforced.
- Stamps: left out of this cycle and parked with the sticky-note wall. Choosing one needs a new field on the post.

## Risks

- **Book pages drift as entries arrive.** Impact: page 4 shows different entries tomorrow. Validate early: number pages from the oldest entry. Mitigation: fixed entries per page counted from the first entry, so old pages never change.
- **Long entries break the spread.** Impact: one long note overflows its page. Validate early: render a 2,000-character entry. Mitigation: a page holds whole entries and grows; a long entry gets a page of its own.
- **Images and Markdown in entries.** Impact: a pasted table or image looks wrong on paper. Mitigation: render the body as the thread does, inside the page width; show attached images small.
- **Static thread files.** Impact: a new entry appears on the thread but not on the book, or the reverse. Validate early: sign and reload both views at once. Mitigation: rebuild the book page with the same trigger as its thread.

## Shared component inventory

- [Shared composer](../../concepts/shared_composer.md) (`templates/partials/composer.php`, `reply_form.php`): reused unchanged for "Sign the book", with the thread root as parent.
- Thread reader (`ThreadRepository::replyPostsByThreadId`): reused to read entries; no new storage.
- [Post presentation](../../concepts/post_presentation.md): author name and date helpers reused; the book drops the signals line and post menu.
- [Page theme](../../concepts/page_theme.md): not used; the book's paper style is its own stylesheet, scoped to the page.
- Page addresses (`PageNavigation::numbered`, as on `/gallery/`): reused for `/guestbook/` page numbers.
- Site profiles (`SiteProfileRegistry.php`) and feature flags (`FeatureFlagRegistry.php`): extended with the guestbook thread and its flag.

New: one page controller, template and stylesheet. No existing page lays posts out as book pages.

## User flow

1. A visitor finds "Guestbook" on the About page.
2. The book opens on the newest page. They turn back a few pages and read.
3. On the last page they write a note and press "Sign the book".
4. Their entry appears at the end of the book.
5. Ilya opens the thread from "Open as a thread", sees the same entry and replies to it. The book shows "1 reply" under the entry.

## Success criteria

- Every top-level reply in the thread appears in the book exactly once, in order.
- An old page's address shows the same entries after new ones arrive.
- Signing works from the book on a phone and a wide screen, and with Quill.
- The thread page looks and works as before.
- The book reads correctly with JavaScript off and in each site theme.

## Study Checkpoint

2026-11-14T02:10:00Z; scope v1 → v2; starting revision `273bf937`, now `b0b706d3`; reference R1, hashes not re-verified. Claude, single exposed rater. Step 1 approved; the thread-access request adds two links and the "n replies" link. Stamps were dropped. Projected total 0.55 → **0.60**.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*
