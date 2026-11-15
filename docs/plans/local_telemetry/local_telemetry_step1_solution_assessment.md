> **Feature plan:** [Step 1](./local_telemetry_step1_solution_assessment.md) · [Step 2](./local_telemetry_step2_feature_description.md) · [Step 3](./local_telemetry_step3_development_plan.md) · [Step 4](./local_telemetry_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_telemetry`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.75 (revised; original 0.65)

## Original Query

As a user, I want to have the option to collect telemetry and store it locally only, and have the ability to either copy and paste it or upload it to the admin so that we can see stuff like which buttons get clicked the most and which are the most inefficient paths that we can optimize.

## Understood Intent

Recording is off until the user turns it on, and the log stays in their own browser. Nothing leaves the device until the user copies it or sends it. The operator's goal is design feedback: which controls get used, and where people take long or looping routes to reach something. This is separate from [visitor statistics](../visitor_statistics/visitor_statistics_step2_feature_description.md), which counts visits on the server for every visitor and records no clicks or routes.

## Revised Direction

Revision 1 (maintainer, 2026-11-10): "Let's go with option B so that we can store the data separately from the main message data, and also so that it doesn't accidentally make it into the public export."

Repository check (main `40c49e76`):

- **Export:** the Backup archive stages the content repository's committed tree and adds one allowlisted Fastmod score file; the offline snapshot reads the public read model. Neither reads `state/private/`, which is ignored by Git.
- **Separate store:** Nine stores already sit in their own SQLite or JSON file under `state/private/` (sessions, visitor statistics, inbox marks, task queue, LLM exchanges and others), most with a private-config path override. A report store would be one more of these.
- **Correction to Option A:** private messages also live in `state/private/messages.sqlite3`, so they were never in the public export either. The real gain from B is the first reason: reports stay out of message data, and the operator does not see them mixed with conversations.
- **Upload limits:** history sync is the only rate-limited endpoint (an admission check, a 100 KB body cap, and a 429 with Retry-After). The report endpoint can copy that pattern.
- **Control names:** no telemetry exists yet. Navbar links already carry a section key (`data-tab`), Tools links carry `data-tone`, and about 66 buttons carry `data-action`. The first slice can count those without touching templates. Most `data-role` attributes mark status text, not controls.

Revision 2 (maintainer, 2026-11-10): "Anyone can upload. Username should be included. The reports can be kept indefinitely for now. The summary page can only be seen by root operators. The reports should be kept in a SQLite file unless you have a better idea?"

- **Username:** taken from the uploader's signed-in [viewer session](../../concepts/viewer_session.md), never from the report body, so nobody can file a report under someone else's name. A visitor who is not signed in uploads as anonymous.
- **Storage:** one SQLite file, `state/private/usage_reports.sqlite3`, with a private-config path override, like the other private stores. SQLite fits better than append-only text files because parallel uploads are safe and the summary can query without loading every report.
- **Kept indefinitely:** with open uploads, total size still needs a ceiling. Step 2 sets a per-report size cap, a per-client rate limit and a total store cap; uploads are refused once the store is full, and existing reports are not deleted.
- **Summary page:** root operators only, on the server, using the existing root-approval check.

## Problem Statement

The operator has no evidence of how people move through the interface, so navigation changes (such as the planned navbar rethink) rest on guesses.

## Concepts touched

- All options: proposed *usage log* (the on-device record) and *usage report* (a copy the user hands over); [tools launcher](../../concepts/tools_launcher.md) (used, gains a "Your device" entry for the log's controls).
- Option A: [private message history](../../concepts/private_message_history.md) (used unchanged, as the upload channel).
- Option B: proposed *report inbox* (server storage for received reports).
- Option C: [viewer session](../../concepts/viewer_session.md) (changed, carries the recording choice).

## Solution Options

### Option A: Browser log, sent as a private message to the operator

**Estimate: 0.65**

A small script, active only after the user opts in on a "Your device" tool page, appends one line per page view and per control used: the page kind with IDs removed, the control's stable name, and seconds since the previous step. The log is capped in size and age. The same page shows the log in plain text with Copy, Clear and Pause, and a Send button that puts it in a signed private message to the operator. The operator pastes received reports into a reader page that runs in their browser and shows the most-used controls per page and repeated or looping routes (A → B → A, many steps to one destination).

- Pros: nothing is stored on the server except a message the user chose to send; reuses signing and private messages; the reader needs no storage; the user sees exactly what is sent.
- Cons: Send works only for members with a key (anonymous visitors can copy and paste but not upload); the operator moves reports into the reader by hand; every control needs a stable name, which touches many templates.
- Driver: naming controls across templates. Uncertainty: how many controls already carry usable `data-` hooks.

### Option B: Browser log, uploaded to a report inbox on the server

**Estimate: 0.75**

Same on-device log and controls as Option A. Upload goes to a small server inbox that accepts a report from anyone, rate-limited and size-capped, stored outside forum content. An operator-only Tools page summarizes all received reports automatically.

- Pros: anonymous visitors can contribute; summaries are always up to date with no copying.
- Cons: a new write endpoint, abuse limits and storage to maintain; received reports become server-side data the operator must keep or purge; more surface than the question needs today.
- Driver: inbox endpoint and abuse limits.

### Option C: Server records the route for opted-in sessions

**Estimate: 0.50**

The opt-in sets a flag on the viewer's session; the server logs that session's page requests and lets the user download them.

- Pros: no new script; works without JavaScript.
- Cons: breaks "local only" because the log lives on the server; cannot see menus, toggles or other controls that make no request, which is half the question.
- Driver: per-session request logging.

## Recommendation

**Option B (Revision 1).** Recording and the log stay on the device until the user presses Upload or copies the text, as in every option. Received reports live only in a private report store kept apart from forum content, and the export cannot reach it. The original recommendation was Option A because it added no server storage. The maintainer chose B to keep reports out of message data; both options already stayed out of the public export, and Step 2 should add a check that keeps it that way.

Settled by Revision 2: open uploads, session username or anonymous, indefinite retention, one SQLite file, root-only summary. Step 2 must still settle the size, rate and total caps; whether a sender can withdraw a report; and which controls count in the first slice (navbar, Tools and `data-action` buttons are enough).

**Vertical-slice viability:** the operator turns recording on, opens the Tools page, finds Unread through the navbar, goes back, and opens a thread. The log shows those steps with page kinds and control names and no thread IDs or text. They press Upload. The operator-only summary page then shows the navbar control counts and flags the back-and-forth route. A content export taken afterwards contains no trace of the report.

## Enhancement Requests

None.

## Study Checkpoint

2026-11-09T06:27:32Z; scope v1; starting revision `3ec5a236818c11b84a840a3fcddb1c50e1a172e8`; reference R1 (hashes not re-verified this session); P2 standing enrollment, no parent. Claude, single exposed rater; pristine intake missing because the Railspike guide, related plans, concept list and front-end assets were read before rating.

- Intake after inspection and Step 1 handoff: **0.65 projected total**, Option A. Alternatives B 0.75 and C 0.50, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

2026-11-10T07:10:00Z revision: maintainer chose Option B (separate private store, kept out of the public export). Scope v2/B; projected total 0.65 → **0.75** (changed scope). A 0.65 and C 0.50 reaffirmed, non-additive. Repository re-checked at main `40c49e76`; existing `data-tab`, `data-tone` and `data-action` hooks lower the cost of naming controls, offset by the new endpoint and limits. Same R1/P2, single exposed Claude rater. Direction change only; not Approved Step 1.

2026-11-10T07:55:00Z Revision 2: upload, attribution, retention, storage and access settled as above. Scope v3/B; **0.75** reaffirmed (decisions narrow choices without adding surface; the total store cap offsets the dropped withdrawal question). Same R1/P2, single exposed Claude rater. Not Approved Step 1.

Status: **Approved Step 1** (maintainer, 2026-11-10T17:25Z) — Option B with Revisions 1–2. Planning records uncommitted.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-10 UTC · Model: claude-opus-5-5*
