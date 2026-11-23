# Word 97 action fonts and Outbox status font — one-shot

Cycle: `v3/word97_action_fonts`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](word97_action_fonts_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/word97_action_fonts`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/word97_action_fonts.md))
Extended estimates: enabled (no real alternatives or work units worth estimating separately)

**Cycle estimate: 0.10**

Status: plan written; next permitted action: implementation on `fix/word97_action_fonts`.

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

Planning commit: pending.

## Study history

- 2026-11-23 UTC, intake and execution entry: **0.10**, after reading both items, the Word 97 stylesheet and the post card templates (inspection preceded the estimate; no pristine intake). Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

## Dated execution history

## Outcome

## Verification

## Final review

Commit/merge/publication status: planning commit pending.

Attribution: Claude · 2026-11-23 UTC · Model: claude-opus-5-5
