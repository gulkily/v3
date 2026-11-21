# Give agent onboarding its own page

Cycle: v3/agent_start_page
Debug mode: enabled by the checkout marker
Effort study: railspike-effort, R1, P2. [Ledger](../effort_ledger/agent_start_page.md)
**Cycle estimate: Unavailable at intake because the local exploration predates enrollment.**

Authorization comes from the November 21 requests to split “Use with your agent” from the Prompts page, get the change to a PR, and report the outcome in Agent Accessibility. The [prompt library change](../prompt_library/prompt_library_one_shot.md) is the predecessor. No matching feedback item surfaced in the repository search.

Keep the community library at /agent/ and give the three built-in prompts /agent/start/. Route About, the first-visit intro and Getting Started to the guide. Keep a working link at the old #start anchor. Both pages use the existing profile and feature-flag gate. The prompt library retains its existing navigation, query and reactions. The guide needs no library query.

Reuse AgentPageController, ProfilePresentationContent, the agent_prompt partial and agent.css. Extract their shared copy script so both pages retain its existing clipboard fallback. The concepts touched are site presentation, prompts and getting started. The records and APIs require no migration.

Verify route entry and flag/profile gating with the smoke set and PresentationProfileMatrixTest. Check the shared script and inspect both pages with the local runtime if available. Open a feature-branch PR for review. Deployment is outside this change.

The starting revision is 7f39d96d9912b8d285ae6b1b313a51531270812a. The November 21 local, uncommitted prototype predates this planning commit and is retained. The first formal checkpoint is the transition from exploration to PR verification.
