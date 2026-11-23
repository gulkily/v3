# Favicon in light theme — debug log

## 2026-11-23T06:35Z — one-shot closeout

- Mode and state: one-shot, debug enabled by the project marker, enrolled in `railspike-effort` (P2). Started from the backlog prompt (`docs/agents/backlog_to_pr.md`).
- Chronology: plan committed (`c1391b64`) before any implementation edit; asset, test, item and record in one completion commit.
- Necessary/useful: backlog_to_pr step 3 ("read the posts under Sources only when the item says too little") sent me to the thread; it held nothing beyond the title, so the bug was reconstructed from the asset. Step 3's rule to write findings back into the item kept that diagnosis for the next agent.
- Necessary/useful: the app_icons one-shot record was the fastest guide to the favicon plumbing and the house record format.
- Friction: `gh pr list` fails (GraphQL is blocked in this environment); the PR check and creation went through `gh api` REST, which thread_to_pr already mentions as a fallback.
- Uncertain: "odd" has no detail; the chosen reading (muddy 16 px glow plus hard dark corners) is stated in the PR so the maintainer can redirect.
- Unverified: a real browser tab; the reporter's device and browser; full suite (policy).
