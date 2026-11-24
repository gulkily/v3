# Debug log — v3/backlog_claims

## 2026-11-23T07:20Z — Step 1 handoff

### Necessary/useful instructions

- `step1_solution_assessment.md`, structure: the Original Query is quoted from the maintainer's handoff, which already named the three options and the two decisions to settle; no Understood Intent section was needed.
- `AGENTS.md` (host), read `PRINCIPLES.md` before weighing options: "signatures are the authority" is why no option keeps claims in an unsigned server file (backlog_view's Option F), and why a guest claim defaults to shown but not counted.
- `AGENTS.md` (host), inspect existing implementations first: found the Claimed state and the agent check-in's listing of it, and the work tracking decisions (claims on the site, never a commit; claims must go stale), which settle two points the handoff left open.
- Extended estimates: one figure per option, the recommended one repeated as the cycle estimate.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- The `/new` or `/clear` reminder's link: the maintainer has asked for step documents as attached files, not links.

### Uncertain assessments

- How many cited posts sit in `#issue` threads was not measured; Option B's coverage is stated as unknown, not as a number.
- "About 312 of 358 item files cite a site post" comes from a text search for site addresses in item files, closed items included.
- The 3-day stale period is a guess offered as a default.
- Step 1 asks for six decisions, more than a one-page comparison usually carries; the handoff asked for two of them by name.

## 2026-11-23T07:45Z — Step 2 handoff

### Necessary/useful instructions

- `step2_feature_description.md`, shared component inventory: reading the guestbook concept found `guestbookThread` in the site profile and the reply-and-return path, which answer "where is the thread named" and "how does a button post a signed reply" without new parts.
- `step2_feature_description.md`, completion boundary and recovery: Start changes from a link to a post, so the failure cases (no key, scripts off, post fails) became requirements, not notes.
- `step2_feature_description.md`, Known user or environment: Safari dropping the stored key on the maintainer's phone is a named condition for the claim on Start.
- Step 1's approval took every default; choices Step 1 did not put to the maintainer are marked **(default)** so approval of Step 2 is informed.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- Documentation-only scope fields.

### Uncertain assessments

- Where a reply in the claims thread surfaces (Recent, Unread, check-in) was not inspected; it is a risk with its validation due before Step 3.
- Whether `./v3 feedback claim` can post through Quill from a checkout without new arguments was not checked.
- The Step 1 suggestion to show `#issue` Claimed marks was dropped here on the agent's judgment; the maintainer has not ruled on it.
- The document is longer than one page.

## 2026-11-23T08:10Z — Step 3 handoff

### Necessary/useful instructions

- `step3_development_plan_do.md`, resolve each material risk before dependent work: both timing checks sit in Stage 1, with a stop before Stage 3 if a claim reads late.
- `step3_development_plan_do.md`, a stage that replays the known user's condition: Stage 3 (no key, scripts off, failed post at 375 px) and Stage 7's release list.
- `step3_development_plan_before.md`, reconcile requests against approved Step 2: the `Claims:` line in the prompt contradicts Step 2's "the prompt's wording does not change" and is flagged as pending, not folded in silently.
- `AGENTS.md` (host), inspect existing implementations: `pledge_withdraw.js` and the pledge viewer test give the claim and release buttons their posting path and their who-sees-it rule; `InboxFeed` showed where a reply lands in Unread.
- `AGENTS.md` (host), pages look final on first paint: Start keeps opening the start page directly; the claim is a state change after paint.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- Navigation icons: no destination added. Read-model schema rules: nothing stored (unless the board-order alternative is chosen).

### Uncertain assessments

- The two Step 2 validations were done by reading code, not by running the site; the measured parts moved to Stage 1.
- Where a reply shows in Recent was inferred from the board and Unread queries, not traced.
- The source of the two-day cache header is not in the repository; Stage 5's explicit header may be overridden by the host, so the live check is listed for release.
- The plan is seven stages and well over one page.
- The cycle estimate rose to 0.45 after two approvals at 0.40.

## 2026-11-23T23:59Z — Step 4 handoff

### Necessary/useful instructions

- `step4_implementation_before.md`, planning commit gate: `f0c1576c6` holds Steps 1 to 3, the debug log and the index entry only; the summary and ledger came after it.
- `step4_implementation_do.md`, one commit per stage with its summary section: seven stage commits follow the planning commit.
- `AGENTS.md` (host), Follow-up changes after a PR: sixteen hours had passed since Step 3 was written, so `origin/main` was fetched again before branching; PRs #380 and #381 had merged, and the branch was cut from the new head.
- `AGENTS.md` (host), inspect existing implementations: the pledge buttons use the thread page's viewer (session, else identity hint), not the page default; the first version of the Claim form used the default and drew nothing. The pledge tests showed the pattern.
- `AGENTS.md` (host), pages look final on first paint: the Claim and Release controls are hidden by a `(scripting: none)` rule, not revealed by a script after paint.
- Step 2's success criterion "with no claims thread named, the same bytes as before": checked after every stage by rendering six routes before and after and comparing. It caught an inline script line and an attribute that had been added for every site.
- `AGENTS.md` (host), run the smoke set plus the tests for what changed: done at each stage; the full suite was not run.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- Navigation icons, read-model schema rules, dependency approval, feature flags: none touched.

### Uncertain assessments

- The scripts-off case is verified in Chrome started with scripting off. It was not tried in Safari, and a browser without the `scripting` media feature would show the buttons.
- The cache header is the application's; the two-day header seen on zenmemes.com came from the host and may still win there.
- The Recent, board and check-in rows of the Stage 6 table come from reading code, not from a run.
- The environment restarted once during Stage 7; the working tree and commits were intact, and the browser check was rerun afterwards.
- The retrospective 0.50 is above the 0.45 projection and the 0.40 the first two steps were approved at.
- Nothing here was tried on the live site or on an iPhone.
