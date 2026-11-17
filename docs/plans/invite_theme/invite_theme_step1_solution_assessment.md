> **Feature plan:** [Step 1](./invite_theme_step1_solution_assessment.md) · [Step 2](./invite_theme_step2_feature_description.md) · [Step 3](./invite_theme_step3_development_plan.md) · [Step 4](./invite_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/invite_theme`; host ledger [enrollment index after P2](../effort_estimation_trial.md#enrollment-index-after-p2). Standing P2 enrollment. New cycle; follows the Bubblegum theme work (#200, #201), which was Direct work, not a cycle.

Extended estimates: enabled. Each option's figure below is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.35

## Original Query

"I also want to be able to do this for an invite landing page too. So when I'm generating an invite, I also want there to be like a theme selector."

## Understood Intent

When an approved member generates an invite on `/invites/`, they can pick a theme (for example Bubblegum) from the themes the site permits. The person who opens that invite link sees the lobby landing page in that theme from the first paint, so the invite feels made for them. Leaving the choice alone keeps today's behaviour.

## Problem

Invite links are `/lobby/#invite=<token>`. The lobby always renders in the visitor's own saved theme or the site default; nothing in an invite can carry a look. The token sits in the URL fragment, so the server never learns which invite is being opened, and the inspect call (`/api/inspect_invitation`) runs only after the page has painted.

Concepts touched: [page theme](../../concepts/page_theme.md) (extended by every option); [invitation lifecycle](../../concepts/invitation_lifecycle.md) (Option B adds an issuance header). Related open item, not addressed here: PF-20261021-F05 (QR code appearance). No feedback item matches this request.

## Shared by every option

- The selector on the Generate invite form lists only explicit themes this site permits, reusing `ThreadTheme::choices()`, with "Site default" first and selected.
- The lobby shows the chosen theme as a page theme: it never overwrites the visitor's saved theme, the theme menu still works, and an unknown or no-longer-permitted name falls back to the visitor's usual theme.
- The token stays in the fragment; nothing about redemption, expiry or Destination changes.
- First paint must already be in the chosen theme (AGENTS.md: pages look final on first paint).

## Option A — Theme in the invite link

**Estimate: 0.35**
Main driver: a readable query on the lobby URL read by the existing page-theme path, plus the form control. Uncertainty: low.

The link becomes `/lobby/?theme=bubblegum#invite=<token>`. The lobby route validates `theme` against the permitted explicit themes and passes it to the renderer as the page theme, the way a thread's own theme already is (`TemplateRenderer` `$threadTheme`), so `data-page-theme` is set in the server HTML and the pre-paint script applies it.

- Pro: right on first paint, with no new record, schema or API change.
- Pro: readable, hackable URL in the project's style; anyone can also hand-make a themed lobby link.
- Con: the theme is not recorded with the invite; the issuer cannot see it later except in the link.
- Con: the link and its QR code get slightly longer (about 16 characters).
- Con: the lobby is one path, so a visitor who exits a page theme there has exited it for every later invite in that browser (existing per-path exit); acceptable for a one-time landing page, noted for Step 2.

## Option B — Recorded on the invite, plus the link

**Estimate: 0.60**
Main driver: a new `Theme:` issuance header through the parser, write path, read model and inspect API, on top of Option A for first paint. Uncertainty: medium.

The issuance record gains `Theme: bubblegum` beside `Destination:`. The link still carries `?theme=` for first paint; after inspection the lobby trusts the record if they differ.

- Pro: the theme is part of the invite's history and exports, visible to the issuer and operators.
- Con: two sources for one value and a post-paint correction path that the first-paint rule discourages.
- Con: a record-format change and read-model work for a cosmetic choice.

## Option C — Theme follows the new member (add-on to A or B)

**Estimate (A + C): 0.40**
Main driver: one storage write at successful redemption. Uncertainty: low.

When the visitor redeems the invite, their browser saves the invite's theme as their own theme, so the site keeps looking the way the invite did until they change it.

- Pro: the welcome look continues past the lobby.
- Con: changes a newcomer's saved preference without them choosing it; some issuers may only want a themed doorway.

## Recommendation

Choose Option A. It meets the request with the smallest change, follows the existing page-theme path, and keeps the first paint correct. Option C is a product choice for the maintainer: recommend leaving it out for now, since the visitor can pick the theme from the menu on the lobby page, and adding it later is cheap. Option B can follow if invites ever need an auditable theme.

Vertical slice: pick Bubblegum on `/invites/`, copy the link, open it in a fresh browser and see the lobby in Bubblegum before paint, redeem, and land on the destination in the visitor's usual theme. The whole cycle is a single slice.

## Study snapshots and approval

- 2026-11-14 UTC, intake after code inspection (lobby and invite controllers, `invite_issuance.js`, `invite_redemption.js`, `InvitationRecordParser`, `ThreadTheme`, `TemplateRenderer` page-theme path): 0.35 projected total for the recommended scope. Claude single exposed rater; R1 hashes not re-verified this session; baseline `origin/main` at the time of drafting.
- Step 1 handoff: 0.35 for Option A (alternatives A 0.35, B 0.60, A + C 0.40; not summed). No implementation. Awaiting `Approved Step 1`.
- 2026-11-14: parked at the maintainer's request. Step 1 committed to `main` as-is so the cycle can be picked up later; it is still unapproved.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*
