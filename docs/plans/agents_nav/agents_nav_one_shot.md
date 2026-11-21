# Point the Agents navigation item to the guide

Cycle: v3/agents_nav
Debug mode: enabled by the checkout marker
Effort study: railspike-effort, R1, P2. [Ledger](../effort_ledger/agents_nav.md)
**Cycle estimate: Unrated.**

On November 21, the maintainer requested renaming Prompts to Agents in the main navigation and linking it to the new guide. Follow-up to [PR 351](https://github.com/gulkily/v3/pull/351), from the merged main revision. No matching feedback item surfaced.

Reuse the existing navigation item and its feature gate, position and icon. Map the new URL in all existing icon sets, preserving the Tools launcher mapping. Use the existing section key to highlight Agents on the guide. Link to the community library from the guide. The concepts touched are navigation and agent onboarding. No data or deployment changes.

Run smoke plus the presentation, app-navigation and icon tests. Browser verification remains subject to the workspace's Chromium socket limitation. Open a follow-up PR for review.

Planning commit: b33ac15d (local). One implementation changes the destination, highlights it on the guide and adds a library link. The existing Tools launcher and theme icon stay available.

Verification: The 21 focused checks passed, including the guide's selected navigation state. The smoke-plus-focused run passed 482 of 490 checks, with the same eight failures reproduced on the unchanged baseline during PR 351. Diff whitespace and PHP syntax checks passed. Chromium theme rendering remains unverified because of the workspace socket restriction. The full suite was not run. PR review and maintainer acceptance are pending.
