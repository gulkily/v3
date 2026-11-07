> **Feature plan:** [Step 1](./site_purpose_step1_solution_assessment.md) · [Step 2](./site_purpose_step2_feature_description.md) · [Step 3](./site_purpose_step3_development_plan.md) · [Step 4](./site_purpose_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/site_purpose`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.45**

## Problem

A first-time visitor to zenmemes.com lands on a feed with no explanation. Nothing says that people and agents build the site together, starting from threads. Agents reading `/llms.txt` see the title "Forum" and miss the same point.

## User Stories

- As a first-time visitor, I want one sentence on the home page that says what this site is, so that I know whether to stay.
- As a curious visitor, I want the About page to show how a thread becomes a change to the site, so that the claim feels real.
- As an agent, I want `/llms.txt` to open with the same description, so that I understand I can help build the site.
- As a member, I want my home page unchanged, so that the intro never gets in my way.
- As the maintainer, I want the description written once per site profile, so that qdb, mitrapclub and other instances keep their own wording.

## Core Requirements

- One description per site profile, kept beside the existing About copy in `ProfilePresentationContent.php`. For zenmemes, it calls the site a garage where people and agents build it together, starting from threads.
- The home page shows the description above the thread list only to browsers with no saved identity. The browser decides this before paint, so the server HTML stays the same for everyone.
- A visitor can hide the intro, and it stays hidden in that browser. Creating an identity also hides it.
- The About page opens with the description, followed by one real thread that became a merged change, with links to both.
- `/llms.txt` opens with the site's name and the description instead of "Forum". A profile with no description keeps today's About page, home page and `/llms.txt` unchanged.

## Delivery Scope and Completion Boundary

- Work type: application change.
- Normal entry: a visitor opens `https://zenmemes.com/` in a fresh browser; an agent fetches `/llms.txt`.
- End-to-end outcome: the visitor reads the intro, follows it to About and sees the example thread and change. The agent reads the description first.
- Recovery: if browser storage is unavailable, the intro still shows and the hide control simply does not persist. A failed script leaves the intro visible, never broken layout.
- Release condition: deployed to zenmemes.com, checked in a fresh phone browser and a signed-in member browser.

## Shared Component Inventory

- Site copy: `src/ForumRewrite/ProfilePresentationContent.php` and `SiteProfileRegistry.php` hold per-profile About copy. Extend them with the description; no new content store.
- About page: `templates/pages/about.php` via `AboutPageController.php`. Add the description and example to the existing intro section.
- Before-paint visibility: `templates/layout.php` head script and `public/assets/member_approval.js` already read `forum_pki_*` keys from `localStorage` to set `data-member`. Reuse the same identity check to mark first-time visitors; do not add a second identity reader.
- Home page: `templates/pages/board.php`. Place the intro there, styled with existing card classes in `public/assets/site.css`.
- Agent entry: `src/ForumRewrite/Http/LlmsTxtRenderer.php`, which already links the source repository. Change its title and first lines only.

## Risks

- **Extra UI text against `PRINCIPLES.md`.** Impact: clutter for everyone. Validate early by drafting the sentence in Step 3. Mitigation: one sentence, first-time visitors only, hideable.
- **Flash on first paint.** Impact: members briefly see the intro. Validate with the layout head script before other work. Mitigation: hide by default for saved identities in the head script.
- **Wording that oversells.** Impact: "agents build it" may put some people off or overpromise. Validate by showing the maintainer two wordings in Step 3. Mitigation: the example thread carries the proof.
- **Other instances.** Impact: qdb or mitrapclub suddenly show zenmemes copy. Validate with each profile's About and home page. Mitigation: no description means no change.

## Simple User Flow

1. A visitor opens zenmemes.com for the first time.
2. Above the thread list, they read that ZenMemes is a garage where people and agents build the site together.
3. They tap the About link and see one thread that became a merged change.
4. They go back and either read on, hide the intro or start posting.
5. After they create an identity, the intro no longer appears.

## Success Criteria

- A fresh browser at phone width shows the intro on first paint, with no layout shift.
- A browser with a saved identity never shows it, including on first paint.
- Hiding the intro survives a reload; with storage blocked, the page still works.
- The About page and `/llms.txt` lead with the same description, and the example links resolve.
- qdb and mitrapclub profiles render as before.
- The intro reads correctly in every theme with icon or text navigation, including Word 97, Chicago and Midnight.

## Concepts Touched

- [Viewer session](../../concepts/viewer_session.md): used unchanged.
- [Navigation slots](../../concepts/navigation_slots.md): used unchanged (shares the layout head script).
- Proposed: *site description* (per-profile statement used by About, the home intro and `/llms.txt`).
- Proposed: *visitor intro* (first-visit home notice, hidden for saved identities or by choice).

## Study Checkpoint

2026-11-05T16:05:00Z; scope v1/B; same baseline `822d6134692883e145728db96b1a9aa62f1ca496`, R1/P2, single exposed Claude rater. Step 2 **0.45 projected total reaffirmed**. Per-profile handling and the About example were already inside the Step 1 scope. No implementation, measured labor or accepted-count change.

Step 2 approved on 2026-11-05. Planning records remain uncommitted.

Status: Step 2 approved; Step 3 drafted.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
