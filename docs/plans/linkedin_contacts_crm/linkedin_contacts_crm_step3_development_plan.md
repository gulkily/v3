> **Feature plan:** [Step 1](./linkedin_contacts_crm_step1_solution_assessment.md) · [Step 2](./linkedin_contacts_crm_step2_feature_description.md) · [Step 3](./linkedin_contacts_crm_step3_development_plan.md) · [Step 4](./linkedin_contacts_crm_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/linkedin_contacts_crm ([P2 ledger](../effort_ledger/linkedin_contacts_crm.md))

Extended estimates: enabled

**Cycle estimate: 0.70**

# LinkedIn Contacts CRM Implementation Plan

**Goal** — Turn a LinkedIn download into a searchable private address book with notes and last-contacted dates.

**Architecture** — `./v3 contacts` prints the URL for a standalone loopback app. The browser previews imports before saving them to an owner-only text snapshot outside the checkout and forum storage.

**Tech stack** — PHP 8.1+, native browser forms and JavaScript, native CSV parsing, PHP ZIP support when available.

## Completion Contract

From a local checkout, launch Contacts, import a ZIP or `Connections.csv`, find and edit a contact, then reopen the app and verify the edit. Reimport a reduced export and confirm saved details and edits survive. Recover from a refused upload by importing the CSV directly. Restore a stopped-app backup into a clean private directory and verify the same contacts and edits. Release requires a normal-entry browser replay, CSV recovery on this workspace's PHP, ZIP success on a ZIP-enabled PHP, and synthetic-marker exclusion from the forum and its release artifacts.

## Key Risks

- **High risk: private data exposure.** Prove the listener, owner access, and safe data path before wiring uploads. Verify the released forum routes, read model, Git, backups, and offline snapshot with a synthetic marker.
- **High risk: reimport or write failure loses edits.** Test collision, stale preview, and failed write against an existing book. Publish the whole import atomically and require a fresh preview when saved data changed.
- **High risk: ZIP compatibility masks a blocked import.** Test CSV recovery on the current PHP first. Check ZIP streaming on PHP 8.1-compatible APIs and record separate end-to-end ZIP evidence. The reported post-change export format remains unverified.

## Testing Plan

Write failing boundary tests for each stage before adding its behavior. Exercise the launcher and browser with synthetic files, read back results after restart, and inspect actual forum artifacts for a synthetic marker. Run `./v3 test --smoke ContactsLaunchTest ContactsImportTest ContactsReleaseIsolationTest ScriptsIndexTest` plus `tests/browser/contacts.py`. Add one fast main-path smoke test. Verify CSV fallback and direct ZIP on separate PHP configurations.

Baseline `./v3 test --smoke` ran 487 tests. Four image tests require PHP GD, and the forum repository ZIP test requires the `zip` executable. Both are absent here. The ZIP failure reproduced in isolation. Full suite was outside this run.

## Stage 1

**Estimate: 0.11**

- **Goal and dependencies** — Open the private empty book from the terminal. First stage.
- **Expected changes** — Route `v3` to `scripts/contacts.php` and `contacts/router.php` with document root `contacts/public/`. Bind to `127.0.0.1`, print the bound URL, and stop cleanly. Remove the launch secret from the URL fragment after reading it and send it in an authorization header on private requests. Establish an owner-only data directory, with `--data-dir` for a safe alternate and restore replay. Keep contact data out of URLs, cookies, HTTP caches, and logs.
- **Verification approach** — `ContactsLaunchTest` covers help, invalid options, port retry, shutdown, unsafe data paths, and refusal of missing or old credentials, wrong Host, forwarded requests, and wrong write Origin.
- **Estimate details** — Launcher and browser access wiring. Port handoff and refusal handling are the uncertainty.
- **Risks or open questions** — Establish the access boundary above before accepting input or creating contact data.
- **Canonical components/API contracts touched** — [CLI reference](../../reference/v3_cli.md), [local server launcher](../../../scripts/local_server_start.sh), [local request guard](../../../src/ForumRewrite/Support/LocalDevelopmentImport.php), [same-origin checks](../../../src/ForumRewrite/Http/SameOriginRequest.php), and [ArchiveCache](../../../src/ForumRewrite/Images/ArchiveCache.php) path checks. The new router owns its credential.

## Stage 2

**Estimate: 0.10**

- **Goal and dependencies** — Preview a direct CSV. Requires Stage 1.
- **Expected changes** — Add `src/ForumRewrite/Contacts/LinkedInConnectionsReader.php`, a native file picker, and drop support. Detect the header after an optional BOM or notes preamble. Accept legacy and reduced columns, standard quoting, and Unicode. Show prospective counts, affected rows, and skip reasons.
- **Verification approach** — `ContactsImportTest` previews historical, current-style, and reduced fixtures. A malformed, oversized, or canceled input leaves saved state untouched.
- **Estimate details** — Header and record compatibility. Malformed input recovery is the uncertainty.
- **Risks or open questions** — Validate upload and parse limits before preview. Avoid logging row contents and keep temporary upload data in owner-only storage.
- **Canonical components/API contracts touched** — [Image compose](../../../templates/partials/image_compose_fields.php) provides file input conventions. Use [Port's review pattern](../../../templates/pages/port.php) with a Contacts-specific flow.

## Stage 3

**Estimate: 0.12**

- **Goal and dependencies** — Save a confirmed import and reopen it. Requires Stage 2.
- **Expected changes** — Add `src/ForumRewrite/Contacts/ContactBook.php` with a versioned `Key: value` snapshot and multiline notes. Use a `0700` directory, `0600` files, a stable lock, and same-directory atomic replacement. Bind previews to the launch and book revision. Verify the revision under the write lock and discard previews after use, cancellation, or expiry.
- **Verification approach** — Confirm, restart, and read back contacts. Retry a confirmation and interrupt a write to verify the saved book remains recoverable.
- **Estimate details** — Atomic writes and persisted preview. Filesystem edge cases are the uncertainty.
- **Risks or open questions** — Verify failed-write recovery before enabling reimport.
- **Canonical components/API contracts touched** — [Private config writer](../../../scripts/write_private_config.php) for lock and replacement and [PortFile](../../../src/ForumRewrite/Port/PortFile.php) for escaped multiline framing.

## Stage 4

**Estimate: 0.10**

- **Goal and dependencies** — Reimport without losing history. Requires Stage 3.
- **Expected changes** — Match by normalized LinkedIn profile URL. Skip exact repeats of URL-less rows. Preview uncertain matches and skip them with a reason. Keep distinct profiles separate, surface conflicting duplicate rows, and preserve blank or omitted source fields and user edits. Reject stale previews with a retry action.
- **Verification approach** — Test the same file twice, a reduced export, same names with different URLs, exact URL-less repeats, conflicting rows, and a change to the book between preview and confirm.
- **Estimate details** — Match rules and stale-preview recovery. Historical rows without profile URLs are the uncertainty.
- **Risks or open questions** — Show counts and row reasons before writing so a wrong merge or silent deletion is detectable.
- **Canonical components/API contracts touched** — The Contacts preview and atomic store from Stages 2–3. Forum identity and canonical storage serve different data.

## Stage 5

**Estimate: 0.09**

- **Goal and dependencies** — Import from a ZIP or recover with the CSV. Requires Stages 2–4.
- **Expected changes** — Open only a single unambiguous `Connections.csv` entry. Cap archive size, entry count, and bytes actually read. Feed that stream to the same parser. For unsupported, unreadable, or ambiguous ZIPs, show how to extract the CSV locally and retry.
- **Verification approach** — Use a ZIP-enabled PHP for the ordinary ZIP path, unrelated-entry exclusion, and early refusals. Replay CSV recovery on the current PHP 8.2.36 without ZIP. Test oversize multipart refusal as well as inflation limits.
- **Estimate details** — One archive entry feeds the existing CSV parser. Runtime availability is the uncertainty.
- **Risks or open questions** — Confirm a ZIP-enabled verification runtime before claiming direct ZIP works. Use an existing runtime or seek separate dependency authorization if needed.
- **Canonical components/API contracts touched** — [ImageZipReader](../../../src/ForumRewrite/Images/ImageZipReader.php) provides bounded-read precedent. Use the [PHP 8.1-compatible entry stream](https://www.php.net/manual/en/ziparchive.getstream.php) with Contacts entry selection.

## Stage 6

**Estimate: 0.10**

- **Goal and dependencies** — Find and annotate a saved contact. Requires Stages 3–4.
- **Expected changes** — In the private UI under `contacts/public/`, search name, company, position, or email. Open a result and save a multiline note or last-contacted date. Persist edits through the same snapshot write path.
- **Verification approach** — Browser replay searches each populated field, edits and reloads, reimports reduced data, and checks the edit survives. Exercise keyboard use, narrow layout, and valid or invalid dates.
- **Estimate details** — Search, detail, and edit interactions. Multiline round tripping is the uncertainty.
- **Risks or open questions** — Treat imported fields and notes as text. Verify delimiter-like note content round trips and imported text cannot become markup.
- **Canonical components/API contracts touched** — Follow [board search](../../../templates/pages/board_search.php) for native controls and results. Query the private book directly.

## Stage 7

**Estimate: 0.12**

- **Goal and dependencies** — Recover a backup and verify release isolation. Requires Stages 1–6.
- **Expected changes** — Document the stopped-app snapshot copy and restore in `docs/runbooks/local_contacts.md`, the text record format in `docs/specs/`, and the command in CLI and scripts references. Add the private contacts concept.
- **Verification approach** — Restore into a clean owner-only store and reopen. `ContactsReleaseIsolationTest` checks Git, public routes, read-model download, repository and image backups, static artifacts, and offline snapshot using synthetic data. Review the final diff and record focused test and browser evidence.
- **Estimate details** — Restore replay, public surfaces, and docs. Coverage of ZIP-backed artifact paths is the uncertainty.
- **Risks or open questions** — Verify restore while stopped. Run artifact checks with the forum's existing archive dependencies available and report any release check still pending.
- **Canonical components/API contracts touched** — [Usage reports exclusion](../../../tests/UsageReportsExportExclusionTest.php), [private message release isolation](../../../tests/PrivateMessageReleaseIsolationTest.php), and [forum downloads](../../../src/ForumRewrite/Http/InstancePageController.php).

**Concepts touched** — Reuse [local launch](../../concepts/local_development_server.md), verify the [public snapshot boundary](../../concepts/content_snapshot_export.md), and add a concept entry for private Contacts. No matching feedback item.

## Study snapshot and approval

User approved Step 2 on 2026-11-23 UTC. Step 3 handoff for `v3/linkedin_contacts_crm`, scope v1, baseline `9ae843cf6c433c95bb44c914b651b72e79e8b377`, verified R1 / P2. Jiro, single exposed rater. The projected cycle estimate of 0.70 is reaffirmed. Stage estimates cover stage work and verification specifically.

*— Jiro · 2026-11-23 UTC*
