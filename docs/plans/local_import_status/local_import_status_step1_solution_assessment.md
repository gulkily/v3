> **Feature plan:** [Step 1](./local_import_status_step1_solution_assessment.md) · [Step 2](./local_import_status_step2_feature_description.md) · [Step 3](./local_import_status_step3_development_plan.md) · [Step 4](./local_import_status_step4_implementation_summary.md)

Cycle: `v3/local_import_status`. Follows [local_import_progress](../local_import_progress/local_import_progress_step1_solution_assessment.md) (PR #357, open). This cycle builds on that PR's code. Branch: a new one from `origin/main` once #357 is merged; the open branch is reused only if the maintainer asks.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_import_status`; host ledger [local_import_status.md](../effort_ledger/local_import_status.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.25

## Original Query

"What should we do next to improve this? I think maybe update the local dev page after this is done? At some point we probably want to do a reimport until sync is available, but it doesn't make sense to just have the same form again with no recorded status?"

Then, quoting the reply "The card should change from 'do an import' to 'here is the state of our copy'. Today, after an import, it shows the counts and 'Import again', but nothing says where the content came from or how old it is.": "This is an amazing idea. Yes, please write Step 1 for the status card."

[Maintainer in session, 2026-11-22.]

## Understood Intent

After an import, the "Import public content" card on the local start page should report on our copy: where it came from, how old it is and what the last import brought in. Importing again from the same source should be one action. Sync does not exist yet, so a re-import downloads everything again.

## Problem

After an import the card shows a line of counts and the same form with "Import again", so nothing says where the copy came from, how old it is, or what a re-import would be for.

What exists to build on:

- Each import started from a page leaves a request record with its source and time, and its output with the counts and the elapsed time. Nothing new needs storing.
- A repeat import reports "duplicate 8814", which reads like a failure. Its `import` count is the number of records new since last time.
- A repeat import of zenmemes.com took about 17 seconds in the last cycle's runs.

Concepts touched: [Local development import](../../concepts/local_development_import.md) (changed). [Instance content import](../../concepts/instance_content_import.md) (used unchanged). Options B and C also touch [Local development server](../../concepts/local_development_server.md). Feedback: no item yet; `PF-20261122-F01` notes the idea. A new item is allocated in Step 4.

## Shared by every option

- Before any import, the card is as it is today: the explanation, the source and Import.
- After an import, the card leads with a status line: the source, how long ago, and what came in. For a first import, "8,813 imported". For a repeat, "37 new" or "nothing new".
- One button imports again from the same source. The source field moves under "Change source".
- Records held back for review show as a short second line that opens Import details. It is no longer an alert on every visit.
- A failed or interrupted import keeps today's reason, Retry and Resume, above the last good status.
- While an import runs, the steps show as they do now.
- Server-rendered, the same with and without JavaScript, and still loopback-only.

## Option A — Status card

**Estimate: 0.25**
Main driver: reading the status from the last request and its output, and reshaping the card's finished states. Uncertainty: low; the data and the card exist.

The card becomes a status of our copy, as described above. The start page stays the home page.

- Pro: answers the request exactly, in one small cycle.
- Pro: stores nothing new and adds no script.
- Con: with 8,800 records in, the home page is still a start page with a status card, not the board.

## Option B — Status card, and the board becomes home after an import

**Estimate: 0.40**
Main driver: Option A plus deciding the home page from whether content was imported, and a way back to the start page. Uncertainty: medium; the home page's rule is shared with every `./v3 start` user and its tests.

As Option A. Once an import has finished, `/` shows the board, as on a deployed site. The start page stays at `/local/`, linked from the board.

- Pro: a local copy with content behaves like the site it copies. This fits using a local install as a board.
- Con: the status card moves one click away, just after it became useful.
- Con: the link back needs a place in the shared layout, which every theme must carry.

## Option C — Status card that refreshes itself

**Estimate: 0.40**
Main driver: Option A plus a rule for when to import without being asked, and showing that it happened. Uncertainty: medium; unasked downloads and writes on a developer's machine.

As Option A. When the start page is opened and the copy is older than a set age, a re-import starts by itself.

- Pro: the copy stays fresh with no clicks.
- Con: opening a page starts a full download and a write to the local repository. That surprises, and it can collide with local work.
- Con: it is a stopgap for sync, and would be removed when sync arrives.

## Recommendation

Option A. It is the idea as approved, and everything it shows is already recorded. Option B is worth doing next as its own cycle, once the status card has been used: the two fit together but can be judged separately. Option C is not recommended before sync is designed.

Vertical slice: on a copy that imported zenmemes.com two hours ago, open `http://127.0.0.1:8000/`. The card reads "Imported from zenmemes.com 2 hours ago · 8,813 imported", with a second line for the records held back, and one button, "Check for new content". Press it: the steps run, and the card then reads "Imported from zenmemes.com just now · 37 new". "Change source" opens the source field.

## Open questions for the maintainer

- **The button's words.** Default: "Check for new content". Alternatives: "Import again", "Update".
- **Imports run from the terminal.** `./v3 import-instance` leaves a saved report but no request record. Default: the card also counts those, using the newer of the two. Alternative: page-started imports only, which is simpler.
- **How much history.** Default: the last import only. Alternative: the last few, folded under the details.
- **Saying what a re-import costs.** Default: one quiet line by the button, "Downloads everything again; last time took 17 s." It goes away when sync exists.
- **The count after a first import.** Default: "8,813 imported", with thousands separators. Tools → Import shares the count line and would gain them too.
- **Held-back records.** Default: "1,123 held back for review", opening Import details. The full breakdown (conflict, invalid, unsupported, excluded) stays inside.

## Enhancement requests

- **Request:** should the start page still take over `/` once content is imported? **Disposition:** kept as Option B; proposed as the next cycle if not chosen here. **Approval status:** not approved.
- **Request:** automatic re-import. **Disposition:** kept as Option C; not recommended before sync. **Approval status:** not approved.

## Study snapshots and approval

- 2026-11-22 UTC, intake: 0.25 projected total for the recommended scope. No pristine intake; the rater wrote the code being changed in `v3/local_import_progress` earlier in this session. Claude, single exposed rater; R1 hashes not re-verified this session; baseline `feat/local_import_progress` `f3c8821` (PR #357).
- Step 1 handoff: 0.25 for Option A (alternatives B 0.40, C 0.40; not summed). No implementation.
- 2026-11-22 UTC: **Approved Step 1** by the maintainer ("approved step 1"). No option named; Option A taken as recommended, with the Step 1 defaults for the open questions. Recorded in [Step 2](./local_import_status_step2_feature_description.md#approved-direction).

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
