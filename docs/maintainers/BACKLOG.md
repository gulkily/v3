# Railspike backlog

Track the current copy changes and deferred case studies in the [positioning checklist](./positioning-checklist.md).

Maintainer follow-up work. Completed plans and superseded drafts remain available in Git history.

- [x] Confirm GitHub Pages publishes `master` from `/` (root) with HTTPS at `https://gulkily.github.io/railspike/`; verify the live navigation, images, and external links.
- [x] Set the repository homepage to the website URL and review topics and desired integrations.
- [x] Walk through installation and the first planning artifact with supported agents. Verify whether `@railspike` offers `RAILSPIKE.md`, retain the explicit-path fallback, and assess whether starting a task can be simpler.
- [x] Update any maintainer-owned host installations, prompts, and documentation to the Railspike repository and `docs/railspike` path.
- [x] Review both README Mermaid diagrams on GitHub in light and dark themes. Keep them compact and readable at a glance, with clear approval/commit boundaries, legible labels, and no confusing crossing edges; adjust layout as needed.
- [x] Create better replacement examples and end-to-end prompt fixtures, including normal work and returning to planning after a scope change. Follow the evidence requirements in the [HN readiness checklist](./launch/HN_WRITEUP_READINESS_CHECKLIST.md).
- [ ] After the rename and website are ready, choose the first release version, create a signed annotated tag, and publish the GitHub release following [Releases and Versioning](./RELEASES.md).
- [ ] Finish the [HN draft](./launch/HN_POST_DRAFT.md) and [readiness checklist](./launch/HN_WRITEUP_READINESS_CHECKLIST.md), using verified canonical links before submission or other promotion.
- [-] Add support for agents that allow multiple choice responses, this can be used to make it easier to send "Approved Step N" or (what other common prompts?)
- [x] Integrate FDP debug mode in its own planning and implementation cycle after plan organization: durable mode state, evidence-backed diagnostics, append-only logs, and log-specific commit rules. Completed in the [debug-mode cycle](../plans/instruction_debug_mode/instruction_debug_mode_step4_implementation_summary.md), with a separate feature history as selected in the [integration assessment](../plans/fdp_feature_integration/fdp_feature_integration_step1_solution_assessment.md).
- [x] Clean up the repo: remove unused files, consolidate or archive old drafts, and ensure the remaining files are clearly organized and documented. Completed in the [repo cleanup cycle](../plans/repo_cleanup/repo_cleanup_step4_implementation_summary.md).
- [ ] Logo: make the spike in `railspike-logo.png` golden with a slight glow (currently flat bronze `#c08000`), keeping it legible at favicon size and on light and dark pages. Own Railspike cycle (`logo_golden_spike`, asset change); should ship before the homepage, which references the file by its current name. Source art is `temp/Rail Spike Cross-Section Icon.png`.
- [ ] Paper: a short design note (motivation, method, real excerpts from `~/v3`, failure modes) published in this repository, documentation-only. Own Railspike cycle (`railspike_paper`); confirm what `~/v3` material may be published and quote it unmodified with attribution. The homepage links to it once published.
