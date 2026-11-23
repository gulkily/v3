> **Feature plan:** [Step 1](./linkedin_contacts_crm_step1_solution_assessment.md) · [Step 2](./linkedin_contacts_crm_step2_feature_description.md) · [Step 3](./linkedin_contacts_crm_step3_development_plan.md) · [Step 4](./linkedin_contacts_crm_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/linkedin_contacts_crm ([P2 ledger](../effort_ledger/linkedin_contacts_crm.md))

Extended estimates: enabled

Cycle estimate: 0.65

## Original Query

Have you heard about how LinkedIn is trimming their data exports to no longer include contact data? Everyone is mass-exporting their last download right now, and I wonder if we can use this somehow by offering people a place to drop in their LinkedIn dump for an instant CRM? It would be something for a local/private instance. This would be for the github.com/gulkily/v3 project. Can we work on this, please?

## Understood Intent

Make a person's own LinkedIn archive useful as a private address book they can search and annotate. A [published screenshot of LinkedIn's notice](https://newsletter.fullstackrecruiter.net/p/linkedin-hoped-you-wouldnt-notice) shows email, current company, and job title dropping from connections downloads starting in December 2026. The exact cutoff and resulting CSV schema are unverified. [LinkedIn Help](https://www.linkedin.com/help/linkedin/answer/a1339364) describes opt-in email and lists previously uploaded address-book contacts separately.

**Problem.** A LinkedIn archive contains valuable connection details alongside unrelated private records, and the v3 forum's normal content pipeline publishes and exports what it stores.

**Concepts touched.** Proposed private connection import and contact book. In A, reuse [local-launch conventions](../../concepts/local_development_server.md) on a dedicated listener. In B, use the shared local server. The [public snapshot exporter](../../concepts/content_snapshot_export.md) remains outside both data flows. No matching feedback item was found.

### Option A — Isolated local companion shipped with v3

**Estimate: 0.65**

Start a separate loopback-only Contacts page. Drop in the ZIP or `Connections.csv`, review the import, then search contacts and keep private notes and a last-contacted date.

- Separate browser origin and private records outside the public document root, Git, and forum backups.
- Requires its own launch command and backup instructions. ZIP support must be verified with a CSV fallback.

### Option B — Contacts on a private v3 instance

**Estimate: 0.95**

Add Contacts to a private instance with operator configuration and authenticated per-user data.

- One entry point and access from authorized devices.
- Shared browser origin, account boundaries, and deployment configuration require more verification.

### Option C — One-session browser viewer

**Estimate: 0.35**

Read a local CSV in the browser and show immediately searchable rows for the current session.

- No durable contact storage and a shorter release path.
- The user loses notes and follow-up state when they close the page.

## Recommendation

Choose A. Ship one complete flow from ZIP or CSV to a searchable personal contact book with notes, safe reimport, and a private backup. Read only `Connections.csv`. Missing email, company, and title must work without erasing existing values. Verify the flow with synthetic older and reduced exports and confirm the public routes and export artifacts exclude the data. Treat the reported December change as a reason to save the archive now.

## Study snapshots

Intake was first considered after the initial repo and source inspection, so no independent pre-inspection score is recorded. Step 1 handoff, 2026-11-23 UTC, Jiro, single exposed rater. Scope v1 / verified R1 / P2, baseline `9ae843cf6c433c95bb44c914b651b72e79e8b377`. Projection 0.65 for A. Option ratings reflect the release and recovery described above.

*Jiro · 2026-11-23 UTC*
