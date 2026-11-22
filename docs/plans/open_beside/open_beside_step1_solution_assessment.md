> **Feature plan:** [Step 1](./open_beside_step1_solution_assessment.md) · [Step 2](./open_beside_step2_feature_description.md) · [Step 3](./open_beside_step3_development_plan.md) · [Step 4](./open_beside_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./open_beside_debug_log.md)). Source: the `.railspike_debug_on` marker at the repository root.

Effort study: `railspike-effort` / `v3/open_beside`; [host ledger](../effort_ledger/open_beside.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 1 approved by the maintainer 2026-11-17 UTC (Option A, as recommended).

**Cycle estimate: 0.50**

# Step 1: Solution Assessment — Open beside

## Original Query

On ZenMemes, 2026-11-16, [thread-20261116070645-8b937ee9](https://zenmemes.com/threads/thread-20261116070645-8b937ee9), with a screenshot of the Prompts page at 2048px wide and the reading margins at 5 and 41:

> @claude this would be an amazing place to add another column, don't you think?

In the Claude session, 2026-11-16 and 2026-11-17:

> This is a possible future direction, but I was talking about a second pane, from any other page. Look for prior art in Pollyanna, please. Do not push yet, let's talk some more.

> I think it's just a normal click but with the special mode enabled?
>
> Yes. [The pane stays as you navigate in the first one.]
>
> Maybe scroll them over to the side to make more space? Idk, let's talk about it?

> For nojs we can skip this feature for now, please integrate the other notes.

> Please update step 1.

## Understood Intent

On a wide screen the space beside the content column is empty. The maintainer wants a mode in which a normal click opens the link in a second pane beside the current page, and the pane stays while he moves around in the first. Browsers without JavaScript are skipped for now. No Step 1 existed when "update step 1" was asked, so this is the first one; it builds on the [open beside design notes](../open_beside_design_notes.md), which hold the Pollyanna prior art and two rounds of review recommendations. Whether there is one extra pane or a strip of panes is not decided, and the options below differ on exactly that.

## Problem

With the reading margins narrowed on a desktop, more than half the page is empty, and reading a second page means leaving the first.

Pages are baked to static files, so a pane cannot ask the server for a version without the site header; a framed page has to hide its own header before paint, as `layout.php` already does for reading margins. Nothing in v3 stops a page framing another page of the same site. Pages must work unchanged with JavaScript off, with the mode off, and on phones.

Concepts touched: a new concept, "open beside", in every option. [Window controls](../../concepts/window_controls.md), [no-script presentation](../../concepts/no_script_presentation.md) and [static publication](../../concepts/static_publication.md) are used unchanged in every option. [Theme script](../../concepts/theme_script.md) is unchanged in A and B and at risk in C.

Feedback item: [PF-20261117-F10](../../feedback/items/PF-20261117-F10.txt), filed for this request.

## Option A — One extra pane, list and detail

**Estimate: 0.50**

With the mode on and room beside the column, a link clicked in the main page opens in one pane to its right. The pane is a frame showing the real page with its site header hidden. A link clicked inside the pane loads in the pane. The main page changes only through the nav bar or by pressing "open alone" on the pane. The browser remembers the pane's address, so it is still there after the main page changes. The mode is a toggle at the end of the reading-margins ruler and a row in Preferences, behind a new feature flag.

- Covers the three tasks named in the design notes: board and thread together, reply while reading another thread, compare two threads.
- Likes, reply forms and theme scripts work in the pane with no changes, because it is a whole page.
- Back steps through the pane's pages the way the browser already handles frames; no history code of our own.
- The pane frame, the hidden header, the mode switch and the remembered address are all reused by Option B, so this is its first slice.
- When the main page changes, the pane reloads. It returns to its scroll position only as well as we can restore it.
- One pane only. A long trail of pages is not kept, and there is no pin, pane list or stale hint yet.
- Estimate details: driver: hiding the header before paint in a framed page and catching clicks without breaking Ctrl-click, forms and external links; uncertainty: scroll restore after the main page changes.

## Option B — A strip of panes, capped at two

**Estimate: 0.85**

The review recommendation. The page becomes a strip that owns every click: a link opens in the pane to the right of the one clicked in, and a click in the last pane slides everything left. Panes never navigate themselves. Each change to the strip is one history entry, so Back undoes it. The address bar follows the active pane. Closed panes go to a list with "Reopen closed". The cap of two is lifted in a later cycle, with spines for panes that slid away.

- The fuller model: a path through the site, not just list and detail, and the same rules scale past two panes.
- The main page never reloads under the reader, so pane scroll positions survive.
- Needs its own history handling, a pane list, and a rule for panes holding unsent text, all before it is safe to use.
- The strip replaces normal page navigation while the mode is on, so a bug there affects every click.
- Hard to judge on paper. The review itself asked for a rough two-pane trial before committing to these rules.
- Estimate details: driver: owning navigation and history for every pane; uncertainty: how the nav speed work (preloading, cached copies) behaves when the top page stops navigating.

## Option C — Fetch the page and insert it, as Pollyanna does

**Estimate: 0.95**

No frames. The clicked page is fetched, its content is cut out and inserted as a second column in the current document, the way Pollyanna's `FetchDialogFromUrl` inserts a dialog.

- One document: one theme, one set of scripts, no second header to hide, lighter than a frame.
- Inserted content arrives without its page scripts. Every script that sets up likes, reply forms, timestamps or theme extras would need a way to run again on inserted content. This was Pollyanna's sore spot.
- Two pages in one document can share element IDs, which breaks labels, anchors and scripts that look elements up by ID.
- Estimate details: driver: making each page script safe to run twice; uncertainty: high, it depends on how many scripts assume one page per document.

## Recommendation

Option A. It is a complete slice on its own: turn the mode on, click a thread on the board, read it beside the board, reply in it, open another. It is also the rough two-pane trial the review asked for, built as real code behind a flag instead of a throwaway. Option B follows as the next cycle if the maintainer wants a trail of panes after using A, and it reuses A's pane, hidden header, mode switch and storage. Option C is not recommended.

Option A answers one open question by choosing "one extra pane" for now. It does not rule the strip out.

Questions for Step 2:
- How wide is the pane: the same as the reading column, or all the space the margins leave free? And below what width does it not appear?
- Does the toggle on the ruler show always, or only when there is room for a pane?
- Should a click on the link already shown in the pane close the pane, as clicking a checked ruler stop resets it?
- In Bubblegum with window controls on, is Float offered inside the pane? The notes recommend yes, hidden below a set width.
- Name for the mode. "Open beside" is the working label.

Enhancement requests:
- Prompt cards in columns on a wide Prompts page: built on a local branch from a first misreading; the maintainer called it "a possible future direction". Parked, not part of this cycle.
- No-JavaScript support: skipped for now at the maintainer's direction.

## Study snapshots

- Intake (exposed: given after two days of discussion, the Pollyanna review and the design notes, not before): 0.50 projected total for Option A. Baseline `6e82449d` (`origin/main`), scope v1, R1, P2; Claude single exposed rater, 2026-11-17 UTC.
- Step 1 handoff: 0.50 reaffirmed after reading `layout.php`'s before-paint block, `reading_margins.js`, the tour page's frames and the window controls concept, and confirming no framing restriction exists. Option estimates A 0.50, B 0.85, C 0.95, not summed. R1 hashes not re-verified in this session.
- Pending ledger update: this cycle's row in `effort_estimation_trial.md` is not written yet; it belongs in the planning commit after Step 3 approval.

*— Claude · 2026-11-17 UTC · Model: claude-fable-5-2*
