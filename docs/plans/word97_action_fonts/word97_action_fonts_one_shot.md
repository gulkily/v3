# Word 97 action fonts and Outbox status font — one-shot

Cycle: `v3/word97_action_fonts`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](word97_action_fonts_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/word97_action_fonts`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/word97_action_fonts.md))
Extended estimates: enabled (no real alternatives or work units worth estimating separately)

**Cycle estimate: 0.10**

Status: technically complete on `fix/word97_action_fonts`; pushed for review, not merged or deployed. Next action: maintainer review, merge, deployment and a look on the phone in Word 97.

## Request and authorization

Original request: feedback items [PF-20261104-B12](../../feedback/items/PF-20261104-B12.txt) ("Like and Flag in Word97 should be sans-serif and not underlined; agent follow-up includes Reply and other post actions") and [PF-20261104-B18](../../feedback/items/PF-20261104-B18.txt) ("'No local drafts or queued actions on this device.' should also be sans-serif").

Authorization: the maintainer, 2026-11-23: "Please work on these 2 v3 backlog items together as one Railspike cycle, one-shot or staged as AGENTS.md says. … Begin now. … Go all the way to an open pull request, or as close as you can get." Under [backlog to pull request](../../agents/backlog_to_pr.md) this authorizes the one-shot, pushing the branch and opening the PR. Not merging or deploying.

Outcome and work type: application change (CSS only). In Word 97, every control in a post's action row reads as a UI control: sans-serif UI font, no underline. On the Outbox page (Tools → Outbox), the status line ("Loading local work…", "No local drafts or queued actions on this device.") uses the UI font.

## Approach and risks

- B12 is mostly done already: PR #8 (merged 2026-11-03) gave Reply and every `.thread-reaction-button` (Like, Flag, Agent, unlocked state tags, pledge buttons, Show as Markdown, Retract post) the UI font and no underline. Controls added to the action row since are still in the inherited serif body font: the compact card's "More" summary (`.post-more-summary`), the "…" more-actions summary (`.post-more-actions > summary`), "Post analysis" (`.post-analysis > summary`) and the retraction "Details" summary (`.retraction-details > summary`). Add them to the existing Word 97 rule.
- B18: the source post is gone (404 on 2026-11-23), so the theme scope its note asked to confirm cannot be confirmed. Chosen reading: the Outbox status line is UI text, so it gets `var(--ui-font)` in `site.css` for every theme. Themes whose body and UI fonts match see no change; serif-body themes (Light, Dark, Word 97, Midnight, chouse) get their UI sans. Alternative considered: a Word 97-only rule; rejected because the same serif status line looks just as off in Light and Midnight. Easy to narrow in review.
- Risk: low. Font-only; no layout, markup or behaviour change.

Reuse decisions: extend the existing Word 97 post-action rule in `public/assets/theme-word97.css` (from PR #8) rather than add a new one; use the existing `--ui-font` variable; target the Outbox status through its existing `data-role="outbox-status"` hook in `templates/pages/outbox.php`. No new component.

Completion conditions: on a thread page in Word 97, Reply, Like, Flag, More, "…", Post analysis and Details compute to the UI font with no underline; on /tools/outbox/ the empty-state status computes to the UI font in Word 97 and Light. Deploy with Deploy now; no schema change.

Concepts touched: none.

Verification and recovery plan: `./v3 test --smoke` plus any test touching these stylesheets; headless Chrome computed-style checks on a local instance for the pages above. Recovery: revert the CSS.

Starting revisions and branch: `fix/word97_action_fonts` from `origin/main` `cdc8abf46`.

Planning commit: `8d6a1ba1a` (plan and index link only, before any implementation edit).

## Study history

- 2026-11-23 UTC, intake and execution entry: **0.10**, after reading both items, the Word 97 stylesheet and the post card templates (inspection preceded the estimate; no pristine intake). Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

- 2026-11-23 UTC, closeout: retrospective scope assessment **0.10**. Delivered as planned. Claude, single exposed rater. Maintainer judgment pending; measured time unknown.

## Dated execution history

- 2026-11-23: baseline in headless Chrome (local instance, `/threads/root-001`, Word 97): Reply, Like, Flag already Tahoma with no underline (PR #8 holds); More, "…", Post analysis and Details in Times New Roman. Outbox status in Times New Roman (Word 97, Midnight) and Georgia (Light). Confirms the plan's reading.
- 2026-11-23: `theme-word97.css`: the four summaries joined the existing post-action rule; comment updated.
- 2026-11-23: `site.css`: `[data-role="outbox-status"]` takes `var(--ui-font)`, placed with the other Outbox rules.
- 2026-11-23: `PostActionThemeTest` gained two assertions (Word 97 toggles, Outbox status rule).
- 2026-11-23: both item files updated (`Workflow: blocked` pending deployment, `Done when:` written, dated note).
- Discovery, not acted on: Midnight (Word 97's dark sibling, same Times body and Tahoma UI fonts) still shows Reply, Like and Flag in Times New Roman with an underline. Not named by either item; listed as a follow-up in the PR.

## Outcome

Delivered. In Word 97 every post-action control now computes to the UI font with no underline; the Outbox status line uses the UI font in every theme. Midnight's post actions are a follow-up, not done.

## Verification

- `./v3 test --smoke PostActionThemeTest Word97ThemeCoverageTest`: 492 run, 492 passed (includes the two new tests). The full suite was not run.
- Headless Chrome (system Chrome through Playwright) on a local instance, computed styles. Controls that need a signed-in viewer or a finished analysis (More, "…", Post analysis, Details) were injected into the action row with the templates' own markup. Word 97: all seven controls Tahoma, no underline. Outbox status: Tahoma (Word 97, Midnight), Trebuchet MS (Light), Inter (Gloss), unchanged in Chicago, Bubblegum and Console.
- Screenshots of the Word 97 thread card and Outbox page looked right.
- Not verified: the reporter's phone; production; the real signed-in "…" menu (markup injected instead).

## Final review

The diff touches `theme-word97.css`, `site.css`, `PostActionThemeTest.php`, the two item files, this record, the plans index, the ledger and the debug log. No new component, no markup or behaviour change, no dependency or schema change. Deploy now is enough.

Commit/merge/publication status: completion commit pending at time of writing; branch `fix/word97_action_fonts`; not merged or deployed.

Attribution: Claude · 2026-11-23 UTC · Model: claude-opus-5-5
