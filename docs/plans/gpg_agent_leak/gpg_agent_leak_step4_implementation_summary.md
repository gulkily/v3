> **Feature plan:** [Step 2](./gpg_agent_leak_step2_feature_description.md) · [Step 3](./gpg_agent_leak_step3_development_plan.md) · [Step 4](./gpg_agent_leak_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

# Step 4: Implementation Summary – GPG agent leak

## Stage 1 - Confirm the kill command
- Changes:
  - None (manual repro in `/tmp` scratch homes; GnuPG 2.2.27, WSL2).
- Verification:
  - Passed: using a temp home starts one `gpg-agent` (0 → 1); `gpgconf --homedir <dir> --kill all` returns the count to 0.
  - Passed: the agent socket directory is derived from the home path (`/run/user/1000/gnupg/d.<hash>/`), so the kill must name the same `--homedir`.
- Notes:
  - Risk 1 confirmed: kill is path-keyed; helper must run kill before delete and pass the explicit home.
  - In this plain shell, 2.2.27 agents exited on their own when the home was deleted, so the reported leak is likely environment-dependent (e.g. missing `XDG_RUNTIME_DIR`, or PHP-spawned processes). Explicit kill is correct either way; Stage 5 measures the real suite.
