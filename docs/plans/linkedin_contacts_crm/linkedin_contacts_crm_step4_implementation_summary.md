> **Feature plan:** [Step 1](./linkedin_contacts_crm_step1_solution_assessment.md) · [Step 2](./linkedin_contacts_crm_step2_feature_description.md) · [Step 3](./linkedin_contacts_crm_step3_development_plan.md) · [Step 4](./linkedin_contacts_crm_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/linkedin_contacts_crm ([P2 ledger](../effort_ledger/linkedin_contacts_crm.md))

Extended estimates: enabled

**Cycle estimate: 0.70**

# LinkedIn Contacts CRM implementation

Step 3 approved on 2026-11-23 UTC. Planning commit `57e531f`.
Step 4 entry reaffirms **0.70** for scope v1. The [approved stages and estimates](linkedin_contacts_crm_step3_development_plan.md#stage-1) retain the original scopes and values.

Study v3/linkedin_contacts_crm, R1 / P2. Jiro, single exposed rater.

*— Jiro · 2026-11-23 UTC*

## Stage 1 - Launch the private app

**Original estimate: 0.11**

**Retrospective stage-work assessment: 0.11**

- Wired the standalone loopback listener through the existing Bash supervisor. A fresh credential gates private routes. The launcher rejects unsafe data paths and reports the actual bound port.
- Verification passed for `./v3 test ContactsLaunchTest ScriptsIndexTest DevServerStartTest` (11 tests), including port collision and cleanup. The launch tests first failed on the missing command.
- Scope and original estimate retained. Current baseline PHP and archive prerequisites are described in the plan.

*— Jiro · 2026-11-23 UTC*

## Stage 2 - Preview a connections CSV

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.10**

- Added a native picker and drop target, row outcomes, and a bounded CSV reader for legacy and reduced exports.
- `./v3 test ContactsImportTest ContactsLaunchTest` passed (6 tests). The new HTTP cases first failed on the missing preview route, then passed for valid exports, oversize and invalid inputs, and a denied origin followed by successful retry. PHP and JS syntax checks passed.
- Scope and original estimate retained. Fixtures approximate the reported reduced export.

*— Jiro · 2026-11-23 UTC*

## Stage 3 - Save a confirmed import

**Original estimate: 0.12**

**Retrospective stage-work assessment: 0.12**

- Added a versioned plain-text book, per-launch expiring preview, and an owner-only atomic replacement under a stable lock. The launcher validates the book before opening it.
- `./v3 test ContactsImportTest ContactsLaunchTest` passed (8 tests). New confirmation tests failed with the routes absent. They now prove cancellation, duplicate confirmation denial, restart persistence, and failed-write recovery after restoring directory permissions. JS syntax and diff checks passed.
- The failure test blocks a real filesystem write. Verification does not include cutting power mid-write.

*— Jiro · 2026-11-23 UTC*

## Stage 4 - Reimport safely

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.10**

- Matched canonical LinkedIn profile URLs, preserved omitted fields and edits, and flagged uncertain and conflicting rows in the preview. Exact unlinked repeats produce no change.
- `./v3 test ContactsImportTest` passed (7 tests). The new repeat and conflict tests failed before matching was added. Two launchers verified that a preview cannot write through a different session or over a changed book.
- Scope and original estimate retained. Uncertain rows remain visible for review and are skipped.

*— Jiro · 2026-11-23 UTC*

## Stage 5 - Open a ZIP or recover with CSV

**Original estimate: 0.09**

- Streams one unambiguous regular `Connections.csv` into an owner-only temporary stream. The reader bounds archive size, entry count, and inflated CSV bytes.
- `./v3 test ContactsImportTest` passed (8 tests) on PHP 8.2.36 without ZIP. The new test first failed on the missing extraction guidance and now replays the message, direct CSV retry, and saved outcome.
- Direct ZIP acceptance and retrospective estimate are pending an extension-enabled verification lane. Stage 6 depends on the confirmed CSV path.

*— Jiro · 2026-11-23 UTC*
