> **Feature plan:** [Step 1](./linkedin_contacts_crm_step1_solution_assessment.md) · [Step 2](./linkedin_contacts_crm_step2_feature_description.md) · [Step 3](./linkedin_contacts_crm_step3_development_plan.md) · [Step 4](./linkedin_contacts_crm_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/linkedin_contacts_crm ([P2 ledger](../effort_ledger/linkedin_contacts_crm.md))

Extended estimates: enabled

**Cycle estimate: 0.70**

# LinkedIn Contacts CRM

## Problem and user stories

LinkedIn's archive is hard to use as an address book. Its connections file can sit beside private messages and other records, and connections can be missing contact details. [LinkedIn Help](https://www.linkedin.com/help/linkedin/answer/a1339364)

- As a v3 owner, I want to turn my LinkedIn download into a searchable contacts list on my computer so I can find people and keep a note and last-contacted date.
- As a returning owner, I want to review a fresh import and back up my contacts so I can keep earlier details and recover my work.

## Core requirements

- Open Contacts on the same computer from a dedicated v3 launch command. Keep its data in an owner-only location outside the checkout and web roots. Contacts works offline and has no public routes, forum identity dependency, or automatic uploads.
- Choose or drop a ZIP or its `Connections.csv`. Read just that file from an archive. Preview additions, updates, unchanged rows, and skipped rows with reasons. Write contacts only on confirmation and leave the saved list intact on cancellation or failure.
- Match reimports by LinkedIn profile URL when available. Skip exact repeats of rows without URLs and flag uncertain matches for review. Same-name rows never merge automatically. Preserve saved notes, last-contacted dates, and source values missing from the new file.
- Search by name, company, position, or email where present. Open a contact to see available imported details and edit its note and last-contacted date. Provide private backup and restore instructions that recover both contacts and edits.

## Delivery scope and completion

Application change for a single owner on a local v3 checkout, including backup and restore documentation. Multiuser hosting, sync, enrichment, outreach, and forum integration are outside this slice.

1. Launch Contacts and open the printed local URL.
2. Select the file, review changes, and confirm.
3. Find a contact, save a note or date, and reopen Contacts to see the edit.

Release requires a fresh-start browser run using synthetic data, a repeat import that preserves saved edits and omitted fields, recovery after a refused import, and a backup restored to a clean private store. A synthetic contact marker must be absent from forum routes, Git, the read-model download, public exports, backups, and snapshots.

## Risks and early checks

| Risk | Check and mitigation |
| --- | --- |
| Archive format varies and can produce incomplete or duplicate contacts | Verify legacy and reduced fixtures, including exports without a URL column, quoted names with Unicode characters, and blank contact fields. Preview uncertainty and preserve saved data. The reported post-change schema is still unverified. [Microsoft compliance annex, Figure 42](https://www.microsoft.com/content/dam/microsoft/mscle/documents/presentations/Microsoft_DMA_Compliance_Report_LinkedIn_Section_2_Annex.pdf) |
| ZIP support varies and large or ambiguous archives can prevent an import | Verify direct ZIP on a compatible runtime and the CSV path on this workspace's PHP, which lacks ZIP support. Refuse oversized, unreadable, or ambiguous archives and tell the owner to extract only `Connections.csv` locally and retry. [PHP ZIP documentation](https://www.php.net/manual/en/book.zip.php) |
| A forum route, forwarded request, or backup could expose contacts | Verify loopback access and same-origin protections before connecting storage. Check that contact data stays out of caches and request logs. |

## Shared components and concepts

- Reuse launch conventions from [the local server](../../../scripts/local_server_start.sh) and request checks from [LocalDevelopmentImport](../../../src/ForumRewrite/Support/LocalDevelopmentImport.php) and [SameOriginRequest](../../../src/ForumRewrite/Http/SameOriginRequest.php). The standalone listener keeps Contacts apart from forum routes and credentials.
- Follow the native file input in [image compose](../../../templates/partials/image_compose_fields.php) and the explicit review pattern in [Port](../../../templates/pages/port.php). Contacts needs its own confirmation and storage because those existing flows write forum content.
- Follow [v3's plain-text format policy](../../../CONTRIBUTING.md#human-readable-formats) for human-facing records and use the existing [private-data exclusion test pattern](../../../tests/UsageReportsExportExclusionTest.php).
- Concepts touched: use [local development launch](../../concepts/local_development_server.md) conventions, verify the [public snapshot boundary](../../concepts/content_snapshot_export.md), and add a private contacts concept entry when the feature ships. No matching feedback item was found.

## Study snapshot and approval

User approved Step 1 and Option A on 2026-11-23 UTC. Step 2 handoff for `v3/linkedin_contacts_crm`, scope v1, baseline `9ae843cf6c433c95bb44c914b651b72e79e8b377`, verified R1 / P2. Jiro, single exposed rater. Projection revised from 0.65 to 0.70 for newly understood handling of exports without profile URLs and verification on PHP runtimes with and without ZIP.

*Jiro · 2026-11-23 UTC*
