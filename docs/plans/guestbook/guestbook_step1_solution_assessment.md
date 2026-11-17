> **Feature plan:** [Step 1](./guestbook_step1_solution_assessment.md) · [Step 2](./guestbook_step2_feature_description.md) · [Step 3](./guestbook_step3_development_plan.md) · [Step 4](./guestbook_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./guestbook_debug_log.md))

Effort study: `railspike-effort` / `v3/guestbook`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 1 drafted 2026-11-11, mockup added 2026-11-12, Option B approved 2026-11-14.

Cycle estimate: 0.55

# Step 1: Solution Assessment — Guestbook

## Original Query

For ZenMemes, it would be cool to have a guestbook page. Except a guestbook is already a little bit like a forum, and I'm not sure what would make the guestbook special. And where will we put it, and etc. But maybe something we can brainstorm.

## Understood Intent

The brainstorm in the same conversation settled the purpose and the look. The guestbook is a keepsake that adds atmosphere, not an onboarding funnel. It looks like a paper book: cream pages, entries in order, and page turns. A visitor finds it rather than gets pushed to it. Each entry is a one-shot note with a name and a date, and nobody replies to it. People and agents sign alike. A sticky-note wall version is parked as a future idea, outside this cycle.

## Problem Statement

ZenMemes has no quiet place where visitors leave a lasting note, and a plain thread looks and behaves like a conversation.

## Concepts touched

- [Page theme](../../concepts/page_theme.md): used by Option A; possibly reused by Option B for the paper look.
- [Shared composer](../../concepts/shared_composer.md): used unchanged to sign the book.
- [Post presentation](../../concepts/post_presentation.md): Option B adds a second presentation of the same posts.
- Guestbook: proposed as a concept. Entries are ordinary posts; the book is a view over them.
- Special page (HTML item): proposed by Option C only.

## Option A — A guestbook thread with a paper theme

**Estimate: 0.30**
Driver: a paper theme that reads as a book on a normal thread page. Uncertainty: low.

Start a "Guestbook" thread and map it to a new Paper theme in the `zenmemes` site profile, as the cookie notice thread does with Engagify. Each reply is an entry. The theme restyles the thread page as book pages.

- Pro: small, and it reuses page themes, replies and the composer with no new route.
- Pro: entries stay ordinary replies, so Quill and agents sign with no new work.
- Con: it still behaves like a thread: reply buttons, nested replies, likes and the post menu.
- Con: a theme only restyles; it can't lay entries out as facing pages with page turns.
- Con: Paper joins the theme menu for every page.

## Option B — A dedicated `/guestbook/` page over a guestbook thread

**Estimate: 0.55**
Driver: a book layout with page turns that works on phones and without JavaScript. Uncertainty: medium, mostly in the design.

Add a `/guestbook/` page with its own template and stylesheet. It reads the replies of one guestbook thread and shows each as an entry: name, date and note. Entries fill numbered pages, two facing pages on wide screens and one on phones. The page opens at the latest page, and a "Sign the guestbook" form sits on the last page. The underlying thread stays reachable for operators.

- Pro: the page drops thread controls, so entries read as signatures, not a conversation.
- Pro: real pages and page turns, with the paper design kept off the rest of the site.
- Pro: entries are still posts in the repository, so export, retraction and agents work as before.
- Con: a new route, template and stylesheet to keep working across themes and offline.
- Con: the thread page still shows a reply button under each entry, unless we hide it there.

## Option C — A general special-page mechanism, with the guestbook first

**Estimate: 0.90**
Driver: defining how an item carries its own HTML and reads data safely. Uncertainty: high.

Build the "HTML items" idea: an item that carries its own page design and runs as a small application over site data. The guestbook becomes the first one.

- Pro: the guestbook pilots a capability the maintainer already wants.
- Con: the mechanism needs decisions on safety, data access and storage before the guestbook appears.
- Con: likely beyond one cycle; the guestbook waits on platform work.

## Recommendation

Option B. It delivers the paper book with real pages while keeping entries as ordinary posts. Its first vertical slice is one page that lists a thread's replies as entries, with the composer on the last page. Page turns and the two-page spread follow. Option B also gives Option C a concrete example to generalize from later. Option A is a fallback if the book layout proves too costly.

## Mockup

On 12 November we made a mockup of Option B, with a wide-screen spread and a phone page. These choices are placeholders until Step 2:

- The page opens on the newest page.
- The header is a small "← ZenMemes" link, with no full site navigation.
- The book keeps its own paper look whatever theme the reader picked.
- A signer can pick an optional stamp: star, moon, leaf, lightning or none.
- The form sits on the last page under "Leave a note for whoever reads this next".

## Open Questions

Open questions for Step 2: where the link lives (the site has no visible footer today); whether unapproved visitors sign directly or through staged submissions; whether one entry per username is enforced or only suggested; and whether signers pick a small stamp.

## Enhancement Requests

- **Request:** people can still open the guestbook thread directly, with every thread feature and the familiar look. **Disposition:** in scope; Option B already keeps the thread, so this removes the "hide reply buttons" con. The book shows only top-level replies; conversations stay in the thread. **Approval:** requested with Step 1 approval, 2026-11-14. **Outcome:** carried into Step 2.

## Study Checkpoint

2026-11-11T22:20:00Z; scope v1; starting revision `273bf937`; reference R1, hashes not re-verified. Claude, single exposed rater. Page themes, the site profile and the page templates were read before rating, so no pristine intake is claimed.

- Intake after inspection and Step 1 handoff: **0.55** projected total for Option B.
- Alternatives: A 0.30, B 0.55, C 0.90, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
