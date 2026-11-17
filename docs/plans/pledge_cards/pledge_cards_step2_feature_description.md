> **Feature plan:** [Step 1](./pledge_cards_step1_solution_assessment.md) · [Step 2](./pledge_cards_step2_feature_description.md) · [Step 3](./pledge_cards_step3_development_plan.md) · [Step 4](./pledge_cards_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_cards_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_cards`; [ledger](../effort_ledger/pledge_cards.md). Standing P2 enrollment. Parent: `v3/pledge_frontend`.

Extended estimates: enabled

Status: Step 1 approved by the maintainer 2026-11-17 (Option A). Step 2 approved by the maintainer 2026-11-17 ("approved step 2").

**Cycle estimate: 0.65**

# Step 2: Feature Description — Pledge cards

Cycle 10 in [pledges_checklist.md](../pledges_checklist.md). Base: `origin/main` at `6e82449d`; nothing since `964966c1` touches this work.

## Problem

A campaign thread shows each pledge as a tall card full of buttons, so many pledges read as a long argument. Any signed pledge counts, approved or not.

Approach (Step 1): Option A. One row per pledge, joined into a list, opening to the full pledge with its buttons behind "More". Compact cards for other replies on campaign threads. Only approved members' pledges count; others wait in a row of their own.

## User stories

- As a visitor, I want to see at a glance who pledged what, so that I can tell how much support the campaign has.
- As a pledger, I want to see my pledge arrive even before I am approved, so that I know it was not lost.
- As the campaign author, I want only approved members' pledges in the total, so that the number means something.
- As a reader, I want buttons only on the item I open, so that the thread is calm.

## Core requirements

- On a campaign thread, each pledge reply is one row: name, amount and status. Rows next to each other form one list; any other reply breaks it.
- Opening a row shows a short header (name and short date), the note, the fill lines and "More". "More" holds every button: Reply, the pledge buttons, Like, Flag, tagset states, Retract.
- Other replies on a campaign thread show the same short header and "More" in place of the byline and the action row.
- A pledge, fill, withdrawal or review counts only when its author's key is approved and its attribution counts. Approval is read when the thread is shown.
- A pledge that does not count shows as a row outside the total: "Waiting for approval" for a signed key not yet approved, or "Not counted: no name" for a post with no name.
- `FORUM_PLEDGES_COUNT_UNNAMED_ENABLED` is removed.

## Decisions on the Step 1 questions

Approval of Step 1 left these open; the suggested answers are used here, open to overrule.

1. **Campaign threads only.** Compact cards on every thread wait for their own cycle. The compact card is one switch on the existing post card, not a second template.
2. **No review sign on rows.** A delivery awaiting review reads "Delivered"; Confirm and Dispute are inside the opened row.
3. **No note in the row.** It is one press away.
4. **"(unapproved)" leaves the compact header.** The waiting row says it for pledges, and the profile page still shows it.
5. **The opening post's action row stays as it is** until the site-wide cycle.

One change from Step 1: a post with no name keeps its own reason ("Not counted: no name") instead of "Waiting for approval", because it can never be approved. Saying it waits would be untrue.

## Smaller rules

- A row's status uses the cycle 9 wording: Open, "5 of 20" (short for Part delivered), Delivered, Withdrawn. The totals block keeps its fuller words.
- A pledge from a key approved later starts counting on the next view; nothing is rewritten.
- Rows never merge into a same-author run. Two pledges in a row from one person are two rows.
- The post page, the Reply page and the API are unchanged.
- With the `pledges` flag off, every page is unchanged.

## Delivery scope

Work type: application change, including one flag's removal.

Not in this cycle: compact cards on other threads; the opening post's actions; the treasurer role.

## Completion boundary

- Entry: a campaign thread on a phone or desktop, signed in or not, with and without JavaScript.
- End-to-end outcome: with seven pledges from approved members and one from a member who is not approved, a visitor at 375 pixels sees the totals block and a list of eight rows. Seven count; one reads "Waiting for approval" and is not in the sum. Opening a row shows the note and "More"; pressing "More" shows Reply and the buttons. When the eighth pledger's key is approved, the next view counts it.
- Recovery: a press inside "More" that fails (Confirm, Withdraw, Like) shows its usual error and can be pressed again; the row stays open.
- Release: Ilya opens the demo thread on his iPhone SE, sees the list, opens a row and confirms a delivery from "More".

## Known user and environment

- The maintainer on an iPhone SE, on zenmemes.com, reported that the cards "look very busy". His next step is to open the demo thread. He verifies by seeing pledges as a list and using a button inside an opened row. A headless pass at 375 pixels does not satisfy this; it stays pending until he reports.
- The demo thread's posts are signed by the "claude" key. If that key is not approved on zenmemes.com, every demo pledge becomes "Waiting for approval" after release. Checking this is part of the release check, before deploying.

## Risks

- **The fold only exists on tagset threads.** Impact: a card collapses only where a tag like `#meta` unlocks Claim, Resolved and Closed, so pledge rows need their own trigger. On a `#meta` thread a pledge card could end up with two disclosures. Validate early: render a pledge on a `#meta` thread and a plain one; each shows one row. Mitigation: one disclosure per card, opened for either reason, reusing the fold's markup and CSS.
- **Same-author runs.** Impact: consecutive pledges by one person merge into one card today. Validate early: a fixture with two pledges in a row from one key renders two rows. Mitigation: a pledge row ends a run.
- **The approval rule changes totals on live threads.** Impact: the demo thread, and any real campaign, can drop to zero after release. Mitigation: the release check above; the waiting rows make the change visible rather than silent.
- **Removing a flag with stored values.** Impact: a stored flag record that names the removed flag could fail to load on settings or rebuild. Validate early: load a flag record that still names it. Mitigation: the evaluator ignores unknown flags, or the removal keeps the name as retired.
- **Buttons inside a closed disclosure.** Impact: the reaction, tagset and pledge scripts may look for their buttons only when the page loads. Validate early: the browser check presses Like, Confirm and Withdraw from inside "More". Mitigation: the scripts already bind by data attributes; any that do not are fixed in place.

## Shared component inventory

- Fold (`templates/partials/post_card.php`, `.post-resolution`, `PostStateView`): reused for rows, with a pledge trigger beside the tagset one.
- Post card (`templates/partials/post_card.php`): one switch for the compact header and "More" on campaign threads. Not copied.
- Disclosure for "More": the same `<details>` pattern as the composer's menu (`templates/partials/compose_action_menu.php`).
- Run building (`templates/pages/thread.php`, `$isThreadPostContinuation`): a pledge row ends a run.
- Totals reader (`src/ForumRewrite/View/PledgeCampaign.php`): reads `author_is_approved`, which posts already carry (`AuthoredContentRepository`, with `was_approved` for retired keys); drops the count-unnamed parameter.
- Pledge partials (`pledge_amount.php`, `pledge_totals.php`): the row's status and the waiting reason.
- Flag registry (`FeatureFlagRegistry`, `FeatureFlagEvaluatorTest`), the thread controller, `PledgeCampaignTest`, and the browser check's `--count-unnamed` mode: the flag removed.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md), changed (rows, approved-only counting, waiting rows, the flag's removal). The [cycle 1 decision](../pledge_campaigns/pledge_campaigns_step2_feature_description.md) that approval is not required is overruled; the spec and checklist record it. No matching item in `docs/feedback/items/`.

## Simple user flow

1. A visitor opens a campaign and sees the totals block, then a list: "mara 20 · 5 of 20", "okafor 30 · Delivered", "pat 10 · Open", "sam 12 · Waiting for approval".
2. They open mara's row and read her note.
3. Ilya, the author, opens okafor's row, presses "More" and confirms the delivery.
4. Sam's key is approved. On the next view, sam's row reads "Open" and the total rises by 12.

## Success criteria

- At 375 pixels, a thread with seven pledges shows all seven rows within two screens, in all 17 themes.
- Only approved members' pledges count; the others show their reason and stay out of every total.
- Every button reachable today is reachable from "More", and works there, with and without JavaScript.
- No pledge row merges into a same-author run, and no card has two disclosures.
- The removed flag leaves no setting, test mode or stored record that breaks.
- With the flag off, thread pages are unchanged.

## Enhancement requests

- **Request:** the six messages recorded in Step 1. **Disposition:** adopted as Option A. **Approval:** Step 1 approved.

## Study snapshots

- Step 2 handoff: **0.65** reaffirmed, scope v4. The fold's tagset-only trigger and same-author runs add work; reading approval needs no new query, which takes some away. Baseline `6e82449d`, R1, P2. Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
