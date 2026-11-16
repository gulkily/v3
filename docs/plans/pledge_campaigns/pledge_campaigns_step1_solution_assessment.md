# Pledge campaigns Step 1 solution assessment

> **Feature plan:** [Step 1](./pledge_campaigns_step1_solution_assessment.md) · [Step 2](./pledge_campaigns_step2_feature_description.md) · [Step 3](./pledge_campaigns_step3_development_plan.md) · [Step 4](./pledge_campaigns_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/pledge_campaigns`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.45**

## Original Query

Can we talk about designing a funding platform on top of V3, please? The first idea that I have is that you post a thread with what you want to fund and how much you think it's going to take, and then people post pledges or commitments. They just post them. They don't have to transfer any money or anything. They just pledge an amount, and then later it can be figured out how they pay for it. They can pay for it directly. Let's say you're collecting for a thousand coffees. People can order 300 coffees, if that's what they pledged, separately.

Later in the same conversation: "I think the pledge should be in units, and you decide what the units are when you're writing the pledge, and then people pledge in those units, and that will make it easy to calculate it." Then, after the design was drafted: "Yes, please go ahead. And if there are multiple slices, then please make a checklist file."

## Understood Intent

Build the first slice of the design in [pledges_spec.md](../pledges_spec.md): a campaign thread with a goal, pledge replies that add up, and a way to pledge from the page. Withdrawal, fills, stages, board rows and configuration are later cycles in [pledges_checklist.md](../pledges_checklist.md).

## Problem

A thread cannot state a goal in a unit, replies cannot carry an amount toward it, and nothing adds them up.

Concepts touched: [thread title](../../concepts/thread_title.md) and [page theme](../../concepts/page_theme.md) (the directive-line pattern, used unchanged); [shared composer](../../concepts/shared_composer.md) (changed: pledge box); [post presentation](../../concepts/post_presentation.md) (changed: amount on a reply, totals block). A "pledge campaign" concept is proposed for the inventory. Option B would also change [post event metadata](../../concepts/post_event_metadata.md)'s neighbour, the canonical post record.

No matching item exists in `docs/feedback/items/`.

## Option A — Lines in the post, read when shown

**Estimate: 0.45** — driver: totals block and pledge box on the thread page across themes; uncertainty: cached thread pages in high-traffic mode.

The opening post carries `goal: 1000 coffees` and a reply carries `pledge: 300`. The thread page reads those lines from the posts each time, as it already does for `theme:`, `event:` and `title:`. Nothing new is stored. A pledge box on the page writes the line for the reader.

- Follows three existing directives, so the parsing, quoting and retraction rules are already settled.
- No change to the post record or the read-model schema, and no rebuild.
- Any client can pledge by typing one line: no JavaScript, agents, offline compose.
- A line that is not a number stays an ordinary reply, which fits "avoid rejecting submissions".
- Board rows for many campaigns will scan post bodies. `EventStatus::forThreads()` already does this in batches.

## Option B — Record headers and read-model columns

**Estimate: 0.80** — driver: post record format, signed bundle validation and a read-model schema change; uncertainty: migration and rebuild cost on production.

The composer writes `Goal:`, `Goal-Unit:` and `Pledge:` headers on the post record, as events do with `Event-Date:`. The read model stores them in columns.

- Totals, sorting and filtering are cheap queries.
- The amount is checked when written.
- A reader cannot pledge by typing a line; only clients that know the headers can.
- Touches the canonical record spec, the signed bundle validator and the schema, for a feature not yet tried.

## Option C — Lines, plus an optional hidden header

**Estimate: 0.60** — driver: two inputs with one precedence rule; uncertainty: whether a hidden pledge is ever wanted.

As Option A, and the composer may put the value in a header to keep it out of the text, as `Theme:` does beside `theme:`.

- Keeps typed lines working and gives clients a clean field.
- A pledge is meant to be seen, so the hidden form has no clear use yet.
- Carries Option B's record and validator changes for the header half.

## Recommendation

Option A. It is a complete vertical slice: write a campaign, pledge from the page, see the total. It needs no schema change and matches how v3 already reads meaning from posts. If board rows later prove slow, columns can be added then without changing what people type.

Questions for Step 2: whether a pledge counts when its post's attribution is unverified, and whether amounts may be decimals.

Enhancement requests: withdrawal, fills, stages and celebration, board rows, agent text output and configuration were discussed in the same conversation. Each is a separate cycle in the checklist, not part of this one.

## Study snapshots

- Intake (after a first code read; no pristine intake): 0.45 projected total for Option A. Baseline `9e5aa010ddff888248ca670c932e3b5f7ec69274`, scope v1, R1, P2; Claude single exposed rater, 2026-11-16 UTC.
- Step 1 handoff: 0.45 reaffirmed. R1 hashes not re-verified in this session.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
