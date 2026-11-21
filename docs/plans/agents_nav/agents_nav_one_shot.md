# Point the Agents navigation item to the guide

Cycle: v3/agents_nav
Debug mode: enabled by the checkout marker
Effort study: railspike-effort, R1, P2. [Ledger](../effort_ledger/agents_nav.md)
**Cycle estimate: Unrated.**

On November 21, the maintainer requested renaming Prompts to Agents in the main navigation and linking it to the new guide. Follow-up to [PR 351](https://github.com/gulkily/v3/pull/351), from the merged main revision. No matching feedback item surfaced.

Reuse the existing navigation item and its feature gate, position and icon. Map the new URL in all existing icon sets, preserving the Tools launcher mapping. Use the existing section key to highlight Agents on the guide. Link to the community library from the guide. The concepts touched are navigation and agent onboarding. No data or deployment changes.

Run smoke plus the presentation, app-navigation and icon tests. Browser verification remains subject to the workspace's Chromium socket limitation. Open a follow-up PR for review.
