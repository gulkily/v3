# Word 97 action fonts — debug log

## 2026-11-23T07:10Z — one-shot closeout

- Mode and state: one-shot, debug enabled by the project marker, enrolled in `railspike-effort` (P2). Started from the backlog prompt (`docs/agents/backlog_to_pr.md`) naming two items.
- Chronology: plan committed (`8d6a1ba1a`) before any implementation edit; CSS, test, items, ledger and record in one completion commit.
- Necessary/useful: backlog_to_pr step 2 (check the item is still open) mattered here: B12's own note pointed at PR #8, which was already merged, so most of the item was done. Checking the live computed styles separated the done part from the new controls added since.
- Friction: every source post for both items returned 404, so B18's "confirm before implementation" note could not be honoured with the reporter. backlog_to_pr has no rule for a vanished source; I chose the reversible reading and recorded it in the item and PR.
- Friction: `gh pr list` (GraphQL) is blocked here; REST `gh api` worked after attaching the repo with push access.
- Friction: computed-style checks needed the instance's namespaced theme key (`forum-zenmemes-theme`, not `forum-v3-theme`); setting `data-theme` alone doesn't load the theme stylesheet.
- Unverified: the reporter's phone; a real signed-in "…" menu (markup injected); full suite (policy).
