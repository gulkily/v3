# reading_margins_two_clicks debug log

## 2026-11-14T19:30:00Z — one-shot closeout

### Necessary/useful instructions

- `AGENTS.md`, Railspike: the rule that every committed change runs as a cycle had changed on `main` since this session last read it. It was caught only when the file was re-shown mid-task, after drafts existed. Retain; re-reading `AGENTS.md` after each fresh fetch of `main` would have caught it before any drafting.
- `docs/railspike/docs/workflow/one_shot.md`, resumption rules: gave a clean recovery (set the drafts aside, commit the plan, restore) without backdating. Retain.
- `AGENTS.md`, "Follow-up changes after a PR": checking the PR state before pushing found #221 already merged, so this went on a new branch. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Missing or unclear instructions

- Nothing tells an agent continuing a long session to re-read `AGENTS.md` after fetching a newer `main`; the instruction-set change was found by chance. Candidate addition to the follow-up guidance.

### Process notes

- The intake checkpoint is missing because implementation was drafted first; disclosed in the record rather than reconstructed.
