# Source repository link — one-shot

Cycle: `v3/source_repository_link`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](source_repository_link_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/source_repository_link`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.12**

Status: technically complete on `feat/source-repository-link`; pushed, not merged or deployed. Next action: maintainer review, merge and deployment.

## Request and authorization

Feedback item: [PF-20261104-F27](../../feedback/items/PF-20261104-F27.txt), "Discover code repository URLs" (found after implementation; see history).

Original request, 2026-11-04: "it should be very easy for an agent to figure out the repo address. recently, an agent crawled the website's content to find it. it should be easier to find than that."

Authorization, 2026-11-04: the agent proposed a private-config setting shown in `/llms.txt` and the `/api/` index, built as a one-shot cycle on `feat/source-repository-link`. The user replied "go ahead please". The agent treats this as explicit bounded one-shot authority for that proposal, including pushing the branch (the session asks for unpushed work to be pushed).

Exclusions: human-facing UI copy, merge, deployment, and changes to other instances' private configuration.

## Outcome and work type

Application change. An agent that knows only the instance URL finds the source repository from the instance's documented agent entry point: `/llms.txt` names the repository and where contributor instructions live, and the `/api/` index names it under its existing "Agents start at /llms.txt" line. Operators set the address with `FORUM_SOURCE_REPOSITORY_URL`; the default is `https://github.com/gulkily/v3`, which every current instance runs, and an empty value removes the section.

## Approach and reuse decisions

- **Setting:** register `FORUM_SOURCE_REPOSITORY_URL` in `PrivateConfigSchema` with `template: true`, following `FORUM_STATUS_URL`, so the private-config writer emits it with no separate key list (CONTRIBUTING, Private runtime configuration).
- **Agent entry point:** extend `LlmsTxtRenderer` rather than adding a route; `/llms.txt` is already where `/api/` sends agents. The section's prose lives in `prompts/llms_txt_source_code.txt` with a `{{source_repository_url}}` placeholder, not in PHP (maintainer review, see history).
- **API index:** extend the text built from `ApiRouteCatalog`, which already renders the `/api/` header.
- **Contributor pointers:** link `AGENTS.md` and `docs/agents/` on the repository's default branch. Both exist on `main`; the thread-to-PR procedure on `docs/thread-to-pr` is listed from `docs/agents/` once merged, so no link depends on an unmerged file.
- **Validation:** accept only an absolute `https://` URL without credentials; anything else renders nothing, so a mistyped value cannot inject text into agent instructions.
- **No new dependency, route, table or UI copy.**

## Concepts touched

None in the concept inventory. The agent entry point and private configuration are used unchanged in shape.

## Risks

- **Rendering stays static:** both pages are rendered per request from configuration only, identical for every viewer.
- **Default owner name in product code:** acceptable because the default names this project's canonical repository; forks override it.

## Verification and recovery plan

- Tests: `/llms.txt` shows the section by default, uses a configured value, omits it when empty or invalid; `/api/` index shows the line; private config template includes the key and refresh preserves custom values.
- Run the smoke set plus those tests; the full suite is not run.
- Render `/llms.txt` and `/api/` through the local server.
- Recovery: setting the value empty removes the output; reverting the commit removes the feature.

Starting revision and branch: `e83cb201ae45b900a8d260708081a02f920ccf32` on `main`; branch `feat/source-repository-link`.

## Study history

- **Intake, 2026-11-04T19:41:02Z, 0.12.** Single exposed rater: Claude (claude-opus-5-5). Pristine intake missing: `LlmsTxtRenderer`, `ApiRouteCatalog`, `PrivateConfigSchema` and the live `/llms.txt` and `/api/` output were inspected before rating. Scope v1 as above. R1 three frozen hashes verified 2026-11-04. Compared with R1's duplicate preview suppression (0.10) and about-copy integration (0.20).
- **Execution entry, 2026-11-04T19:45Z, 0.12 reaffirmed.** Same scope v1; the private-config writer's dedicated comment block was already within the CONTRIBUTING requirement the intake assumed.
- **Closeout, 2026-11-04.** Projection 0.12 reaffirmed. Retrospective scope assessment **0.12**, from the same single exposed rater, not an independent rating. Corrections: none. Additions: none; the feedback item update is required bookkeeping. Delivered scope v1 in full. Maintainer comfortable-fit judgment and acceptance pending. No active-work measurement was collected.

## Dated execution history

- 2026-11-04: Record created before implementation.
- 2026-11-04: Implemented `SourceRepository` (setting resolution and validation), the `/llms.txt` section and More link, the `/api/` index line, the private-config template comment, and reference updates in `agent_api.md` and `v3_cli.md`. Contributor pointers name `AGENTS.md` and `docs/agents/` as repository paths rather than host-specific URLs, so they work for any Git host.
- 2026-11-04, discovery: `/llms.txt` and `/api/` are rendered per request and allowed on members-only instances; neither is in static, offline or high-traffic artifacts, so deployment needs no rebuild.
- 2026-11-04, correction: the record started without a feedback item. A post-implementation search found [PF-20261104-F27](../../feedback/items/PF-20261104-F27.txt), now `Workflow: blocked`, `Evidence: locally-verified`. Its mention of `gulkily/pollyanna`, About-page and meta-tag links remains open as follow-up; [PF-20261030-B18](../../feedback/items/PF-20261030-B18.txt) (revision labels) is separate.

- 2026-11-04, correction from maintainer review: "we mixed prompt text into code, and it should be in a separate file." Moved the `/llms.txt` source code prose to `prompts/llms_txt_source_code.txt`, following the existing `prompts/` `{{name}}` placeholder convention; `LlmsTxtRenderer::SOURCE_CODE_PROMPT_PATH` names it and an unreadable file omits the section. The `/api/` line and More link stay in code as a label plus URL. Rendered output unchanged byte for byte; tests also assert the file keeps its placeholder and no placeholder reaches the output. Smoke set plus changed tests rerun: 442 passed.
- 2026-11-04: completion commit confirmed as `c6a454d`; this correction is a separate follow-up commit on the same branch.

## Outcome

Delivered: an agent given only an instance URL finds the code repository in the opening section of `/llms.txt` and on the second line of `/api/`, with where to start (`AGENTS.md`, `docs/agents/`). Operators change or remove it with `FORUM_SOURCE_REPOSITORY_URL`. Limitations: production output changes only after deployment; whether an agent can clone the repository depends on its visibility, which this change does not alter.

## Verification

- Passed: `./v3 test --smoke LlmsTxtRendererTest ApiRouteCatalogTest PrivateConfigCommandTest`, 442 run, 442 passed. New tests cover the rendered section and its position, absence without a repository, 13 validation cases including the default, and end-to-end `/llms.txt` and `/api/` output with an environment value and with an empty value.
- Passed: local server (`./v3 start`) served the section, More link and `/api/` second line with the default.
- Passed: `./v3 feedback check` (0 problems; 21 pre-existing review notes) and `./v3 feedback index`.
- Not run: the full suite (maintainer runs it). Inapplicable: UI and theme checks (no human-facing UI changed), read-model and dependency checks (none changed; dependency delta zero).

## Commit, merge and publication status

Completion commit `c6a454d` on `feat/source-repository-link`, then the prompt-file correction commit (reference pending in this record). Pushed to origin; not merged, not deployed.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
