> **Feature plan:** [Step 1](./theme_directive_step1_solution_assessment.md) · [Step 2](./theme_directive_step2_feature_description.md) · [Step 3](./theme_directive_step3_development_plan.md) · [Step 4](./theme_directive_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/theme_directive`; [host ledger](../effort_estimation_trial.md#cycle-v3theme_directive). Standing P2 enrollment. Split from [`v3/engagify_theme`](../engagify_theme/engagify_theme_step1_solution_assessment.md), whose page themes this cycle builds on.

Extended estimates: enabled

Cycle estimate: 0.75 (Step 1 0.70)

## Problem

Only an operator editing the site profile can give a thread a page theme. A thread's author cannot choose one, either when posting or later.

Concepts touched: [page theme](../../concepts/page_theme.md) (extended: a thread can set its own); [thread title](../../concepts/thread_title.md) and [calendar](../../concepts/calendar.md) (precedents for reply directives, used unchanged). This cycle updates the page theme concept entry. No matching feedback item in `docs/feedback/items/`.

## User stories

- As a thread author, I want to give my thread a theme when I start it, so that readers see it the way I meant.
- As a thread author, I want to add or change the theme later in a reply, so that existing threads can have a theme too.
- As a poster, I want to choose whether the theme setting shows in my post's text, so that it reads as part of the conversation or stays out of the way.
- As an operator, I want to set a theme on any thread, so that I can theme threads without editing site settings.
- As a reader, I want Exit to work as it does for any page theme, so that a thread's theme never traps me.

## Core requirements

- **Two forms, one meaning.** A visible `theme: <name>` line in the post text, or a hidden theme setting chosen in the composer, which is stored in the record's header and does not appear in the text. Either form works on a thread's opening post and on any reply.
- **Authority.** Only the thread's author or a current operator can set a thread's theme. From anyone else, a visible line stays plain text and a hidden setting is ignored.
- **Latest wins.** The thread's theme comes from the latest valid setting in either form, from an unretracted, verified post. Retracting that post withdraws it, and the previous setting, if any, takes over. `theme: none` clears the theme.
- **Valid names only.** The name must be a permitted, explicit theme on that site. An unknown name is ignored.
- **Precedence.** A thread's own theme beats the site profile's page theme list for that thread. A reader's remembered Exit on that page still wins, as it does for page themes today.

## Delivery scope

- Work type: application change.

## Completion boundary

- Normal entry: the thread author or an operator posts a thread or reply with a `theme:` line, or picks a theme in the composer's hidden setting.
- End-to-end outcome: the thread page shows that theme by default to every reader, before first paint. Other pages are unchanged.
- Recovery: retracting the post, or posting `theme: none`, restores the previous theme or none. Readers can Exit as for any page theme. Records without the new header read exactly as before.
- Release condition: smoke and changed tests pass. The thread is checked by hand with both forms, from the browser composer and from the signed-post API, and the baked thread page carries the theme. The full suite is not run.

## Risks

- **Signed record format.** The client-signed posting subset rejects unknown headers, so the hidden form needs a spec change to the canonical post record and its validator. Impact: older clients cannot send it and an incomplete change rejects valid posts. Earliest validation: read `SignedPostBundleValidator.php` and the browser record builder, and post one signed reply with the header. Mitigation: follow `Body-Format`, which already appears on threads and replies in a fixed position; the header stays optional.
- **Baked thread pages.** Thread pages are served from prebuilt files. Impact: a theme set by reply does not show until the thread's file is rebuilt. Earliest validation: check that a new reply already invalidates its thread's baked page. Mitigation: reuse `StaticArtifactInvalidator.php`; retraction must invalidate the same way.
- **Reading the hidden form cheaply.** The visible form can be found in post bodies, as `event:` is. The hidden form lives in the header, which the read model may not keep. Impact: reading every record file per page view would be slow. Earliest validation: check which header fields the read model stores. Mitigation: store the header's value with the post, as for the body format; Step 3 decides the details.

## Shared component inventory

- `src/ForumRewrite/Calendar/EventStatus.php`: reuse its pattern (latest valid line, authority checked on read) for the theme lookup; do not change it.
- `src/ForumRewrite/Support/TitleDirective.php`, `EventStatusDirective.php`: reuse their line-parsing shape for a new theme directive reader.
- `src/ForumRewrite/Security/TitleDirectiveAuthority.php`: reuse its author-or-operator rule.
- `src/ForumRewrite/SiteProfileRegistry.php`: reuse `pageThemeFor()` and its permitted-theme check; extend so a thread's own theme takes precedence.
- `src/ForumRewrite/View/TemplateRenderer.php`, `templates/layout.php`: reuse the `data-page-theme` mark and the pre-paint choice unchanged; only the source of the page theme changes.
- `public/assets/theme_toggle.js`: reuse unchanged; Exit already works per page.
- `docs/specs/canonical_post_record_v1.md`, `PostRecordParser.php`, `SignedPostBundleValidator.php`, `LocalWriteService.php`: extend with one optional header on threads and replies, positioned like `Body-Format`.
- `templates/partials/composer.php` and the browser record builder: extend with a theme choice beside the Markdown toggle.
- `src/ForumRewrite/Write/StaticArtifactInvalidator.php`: reuse to rebuild the thread page after a theme change or retraction.
- `docs/concepts/page_theme.md`, `docs/runbooks/theme_development_guide.md`: update to describe thread-set themes.

## Simple user flow

1. A thread author opens a reply to their own thread.
2. They either type `theme: engagify` in the text or pick Engagify in the composer's theme choice.
3. They post the reply.
4. Readers opening the thread see it in Engagify. With the hidden form, the reply's text shows no theme line.
5. Later the author retracts that reply or posts `theme: none`, and the thread returns to its usual look.

## Success criteria

- A visible `theme:` line from the author or an operator, on the opening post or a reply, gives the thread that theme for every new reader.
- A hidden setting does the same, and the post's text shows no theme line.
- A `theme:` line from anyone else changes nothing and stays as plain text.
- The latest valid setting wins across both forms; retraction and `theme: none` each restore the previous state.
- A thread's own theme beats the site profile list for that thread, and Exit still keeps a reader on their own theme.
- Records without the new header, and posts from clients that do not send it, behave exactly as before.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-07T15:13:01Z, `railspike-effort` / `v3/theme_directive`, Step 2 handoff. Step 1 explicitly approved with Revisions 1–2 (Option D). Scope v2 unchanged, plus `theme: none` as a way to clear a theme without retracting. Starting revision: `feat/engagify_theme` at `9276410b`, with page themes built. Reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Before rating, the rater read the canonical post record spec, the post format record spec and `EventStatus.php`. Projected total 0.70 → **0.75**, from new understanding: the client-signed subset rejects unknown headers, so the hidden form needs a spec and validator change, and the read model must keep the header's value. No implementation, measured labor or accepted outcome.

Step 2 approved by the maintainer on 2026-11-07 ("approved step 2").

## Enhancement Requests

Carried from Step 1: setting a theme later by reply (Revision 1) and the visible or hidden choice (Revision 2), both in scope.
- Clear a thread's theme with `theme: none`. Disposition: in-scope refinement; approved with Step 2.
