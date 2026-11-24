# nav_tree Railspike debug log

## 2026-11-15T02:44:42Z — Step 1

Debug mode: enabled (project default: `.railspike_debug_on` present at the v3 root; no explicit request).

### Necessary/useful instructions

- `RAILSPIKE.md`, Step Guide and the Step 1 structure in `step1_solution_assessment.md` (preserve the Original Query, then Understood Intent). The query arrived across four chat messages; keeping all four verbatim kept the device-only and page-change decisions traceable. Retain.
- `RAILSPIKE.md`, Enhancement Requests. The maintainer asked to "make a note" of syncing and in-page changes. The rule gave those notes a home in the artifact rather than only in chat. Retain.
- `AGENTS.md` (host), "decide viewer-specific UI in the client before paint". This framed the History page as client-rendered from device state. Retain.
- `step1_solution_assessment.md`, "vertical-slice viability". It exposed that a capture-only cycle would not be releasable, which moved a plain History page into this cycle. Retain.

### Redundant/unnecessary instructions

- `RAILSPIKE.md`, Experimental Effort Estimation. The rules for reference recovery, worktree deduplication and outcome counting take up much of the file, yet only the snapshot fields applied to a Step 1 handoff. Suggest moving the non-Step-1 parts behind a link, as the debug section partly does already.

### Inapplicable instructions

- Follow-up Cycles Before Merge, Documentation-only scope, and Step 4 commit cadence. They do not apply to a Step 1 draft.

### Uncertain assessments

- Step 1 file location. `RAILSPIKE.md` says to organize plans into `docs/plans/{feature_name}/` only after Approved Step 3, but most recent v3 cycles create the folder at Step 1. This draft follows the written rule (flat file). A one-line clarification of which convention is intended would help.
- "Concepts touched" for a proposed concept with no entry. The concept guidance was not reopened, so it is unclear whether *visit tree* needs an inventory entry now or at Step 4.

## 2026-11-24T17:55Z — Step 1 (revision)

Revision of the 2026-11-15T02:44:42Z entry's step: the draft was rechecked against main `2d7805c`. Debug mode: enabled (inherited from the Step 1 artifact).

### Necessary/useful instructions

- `RAILSPIKE.md`, Instruction Summaries, "On resumption, reread applicable instructions and restore workflow state". Rereading the draft's state fields exposed a stale host-ledger link. Retain.
- `step1_solution_assessment.md`, "Re-estimate combined/revised options with stable identities and append scope/score changes". It kept Options A–C under their letters with both scores visible. Retain.
- `RAILSPIKE.md`, Agent attribution, "Preserve prior attribution when revising". Each revised option keeps its 2026-11-15 line and gains a revised line. Retain.
- Host `AGENTS.md`, Continuing Railspike effort study: "do not append to `effort_estimation_trial.md`". The request named that file; this rule redirected the entry to `effort_ledger/nav_tree.md`. Retain.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- The /new or /clear reminder in `step1_solution_assessment.md`, Next. It is once per cycle and does not repeat on revisions.

### Uncertain assessments

- Scope versioning when only understanding changes. The rules say to "distinguish new understanding from changed scope" but give no label for it. This revision used "scope v2 (new understanding, same outcome)"; a fixed label would help.
- Planning branches. `RAILSPIKE.md` keeps Steps 1–3 uncommitted, yet this cycle keeps its draft on an unmerged planning branch to survive a new session. The workflow says nothing about such branches.
