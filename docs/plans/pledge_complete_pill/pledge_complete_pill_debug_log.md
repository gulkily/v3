# Pledge Complete pill debug log

## 2026-11-17T23:15Z — one-shot closeout

**Necessary/useful instructions**
- `AGENTS.md` "Follow-up changes after a PR": PR #320 had merged, so the fix went on a new branch from `origin/main` with a new PR. Retain.
- `implementation_standards.md` "Replay each reported failure": the maintainer reported a look, not a number. Measuring every theme turned "looks off in some themes" into six named failures and a cause, and the new browser assertion fails on the old CSS. Retain.
- `one_shot.md` "Planning commit gate": the plan, with the measured defect, was committed before the CSS change. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Uncertain assessments**
- The pills now differ only by word and border colour. Whether that is enough is a design call, so it is named in the outcome rather than decided here.
- The original `pledge_frontend` and `pledge_board_rows` checks measured bar colours and layout, not text contrast, which is how this slipped through. Railspike's UI verification guidance could name contrast explicitly.
