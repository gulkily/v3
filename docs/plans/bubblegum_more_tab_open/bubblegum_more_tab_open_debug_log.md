# Debug log: v3/bubblegum_more_tab_open

## 2026-11-15T19:30Z, closeout (technically complete)

- Mode: one-shot under `docs/agents/thread_to_pr.md`; debug enabled by `.railspike_debug_on`.
- Reproduction: live `/` HTML saved, hashed asset names rewritten to the repo's `public/assets/` files, Bubblegum stylesheets injected and `data-theme` set directly (theme stylesheets are not linked statically in the snapshot), More `<details>` opened by script.
- Cause: equal-specificity (0,5,0) conflict; Bubblegum's later phone tab rule resets the open trigger's background to transparent while the shared rule's white `--active-ink` colour survives.
- Friction: the planning commit's new feedback item used invalid Workflow/Evidence values; `./v3 feedback check` caught it before completion.
- Full suite not run, per `AGENTS.md`.
