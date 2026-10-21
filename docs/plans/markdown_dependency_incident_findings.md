# Markdown dependency introduction: findings and proposed instruction patches

Investigation date: 2026-10-20. Scope: investigation snapshot at `02512b8e`, before dependency changes or history rewriting. The working tree was clean on entry. Proposed instruction text records that snapshot; the remediation status at the end records subsequent implementation.

## What entered the repository

Commit `0e13f9dbce2e950c84880f4f7bc55ee26b15104d` (2026-10-19, “feat(docs): bundle safe CommonMark renderer”) introduced `third_party/markdown/`. At the investigated HEAD it contains **425 tracked files, 1,141,428 bytes** (file contents, not Git object storage). That commit changed 429 files overall, with 37,357 insertions and 142 deletions. This was an intentional production dependency bundle, not merely a temporary Markdown experiment or a development tool installation.

The checked-in `composer.lock` identifies one direct dependency and seven transitive dependencies:

| Package | Locked version | Relationship |
| --- | --- | --- |
| league/commonmark | 2.10.4 | Direct |
| dflydev/dot-access-data | v3.0.3 | Transitive |
| league/config | v1.2.0 | Transitive |
| nette/schema | v1.3.6 | Transitive |
| nette/utils | v4.0.10 | Transitive |
| psr/event-dispatcher | 1.0.0 | Transitive |
| symfony/deprecation-contracts | v3.7.1 | Transitive |
| symfony/polyfill-php80 | v1.43.0 | Transitive |

Historical evidence inspected: `0e13f9db:third_party/markdown/composer.json`, `composer.lock`, `NOTICES.md` and the production vendor tree. Those files are removed from the current branch; the package inventory above preserves the findings without copying vendor source. Composer autoload/metadata files also shipped inside the bundle.

`PublicMarkdownRenderer` loads the bundled autoloader and imports CommonMark classes. `DocsCodeRenderer`, `DocsTableRenderer` and `DocsHtmlRenderer` implement its interfaces. Removing only the vendor directory would break Docs rendering. [The release test](../../tests/platform_docs_release_test.py) deliberately manipulates this bundle to test recovery, and [the runbook](../runbooks/markdown_renderer.md) tells maintainers to ship and restore it.

## Instruction and decision chain

1. **No repository dependency gate.** At investigation revision `02512b8e`, both [AGENTS.md](../../AGENTS.md) and [CONTRIBUTING.md](../../CONTRIBUTING.md), and their versions immediately before `0e13f9db`, contained no requirement for explicit consent to new third-party code, transitive dependencies, vendoring or new package-manager infrastructure. Their concrete gates concern UI verification, navigation icons, read-model changes and Railspike. The small-change exception in AGENTS also has no dependency exclusion; it was not the observed route here, but leaves another path open.
2. **Feature approval covered a broad architectural recommendation.** [Renderer Step 1](./markdown_renderer/markdown_renderer_step1_solution_assessment.md), Option B, recommends an established PHP parser and says it “adds dependency/licensing/update obligations.” No library/version was selected then. [Step 2](./markdown_renderer/markdown_renderer_step2_feature_description.md) explicitly allows an established parser, subject to compatibility, licensing and reproducible packaging. These documents disclose dependency adoption, but do not present a concrete dependency inventory for a separate user decision.
3. **The concrete expansion arrived in auxiliary evaluation.** [Pre-Step 3 evaluation](./markdown_renderer_parser_evaluation.md) selects CommonMark, lists all eight packages and recommends a tracked production vendor tree. It explains roughly 2.5 MB for its evaluation installation; that is not the tracked-byte measurement above. This auxiliary evaluation was not among the files committed in planning commit `fbdafc52` (verified with `git show --name-only`). Step 3 links it and requests a “tracked isolated production bundle with manifest/lock/notices,” but does not repeat the eight-package inventory beside the approval request.
4. **Workflow approval was treated as sufficient.** [Step 4 summary](./markdown_renderer/markdown_renderer_step4_implementation_summary.md) records “Approved Step 3 received 2026-10-19” and then the bundled parser implementation. [Workflow diagnostics](./markdown_renderer/markdown_renderer_debug_log.md) reinforce compatibility, packaging and licensing checks, without identifying a missing dependency-consent decision. Railspike's [key rules](../railspike/RAILSPIKE.md) prefer reuse of shared components/API contracts; that is not an explicit instruction to download third-party packages. Its [Step 3 approval gate](../railspike/docs/workflow/step4_implementation_before.md) authorizes implementation of approved plans, but has no separate dependency gate.

**Conclusion:** the observable failure is a consent/disclosure gap: generic feature-plan approval could authorize a broad third-party dependency tree. Packaging and technical checks answered whether the bundle would work, not whether the user knowingly agreed to introduce it. There is no evidence of an instruction explicitly requiring surprise dependencies, nor enough evidence to claim approval gates were skipped. Git records the plans and the agent's approval claim; it cannot establish the original conversation, what the user saw, or informed consent. No complete prior agent transcript was supplied. Do not treat commit authorship as proof of a human decision.

## Proposed patches

Keep the canonical policy in host-project `CONTRIBUTING.md`, with a short mandatory pointer in `AGENTS.md`. Avoid editing vendored Railspike to enforce a project-specific policy; upstream updates could overwrite it. Existing approved third-party tools, including Playwright and Railspike, are not silently revoked by this proposal.

Proposed addition to **AGENTS.md**, before the Railspike section:

> Third-party code: Follow the dependency approval policy in CONTRIBUTING.md before adding or updating external components. Formatting requests and generic plan approvals do not authorize new third-party components. This rule also applies to small changes and temporary evaluations; keep unapproved evaluation artifacts outside the repository.

Proposed new **CONTRIBUTING.md** section, “Third-party dependencies and copied code”:

> Prefer existing project code and supported platform facilities. Before adding or updating any external library, framework, plugin, asset, copied source, generated vendor tree, package-manager manifest/lock or externally hosted runtime component, disclose the exact proposed change and obtain explicit user approval for that dependency change. This covers runtime, build and development dependencies, direct and transitive packages, and delivery through downloads, CDNs, subtrees or submodules.
>
> Present the component names, versions, full resolved transitive additions/changes, purpose, origin, licenses, delivery method, affected paths, approximate size/file count, maintenance obligations, and a dependency-free alternative or reduced scope. A request to improve formatting or functionality, a generic Approved Step N, successful tests, a permissive license, or an instruction to reuse components does not substitute for this approval. An explicit dependency approval may accompany a step approval; record its scope in the plan and carry it forward without asking again for the same approved action. A changed dependency tree or delivery method requires renewed approval for that change.
>
> Research may inspect metadata and run disposable evaluations outside the repository using existing approved tools. Do not copy or install the evaluated component into the repository, load it into the application, commit it, or publish it until approved. Temporary evaluations must not alter project manifests, lockfiles or generated artifacts.
>
> Before completion, compare the actual file/manifest/lock changes against the approved inventory and report the dependency delta, including zero. When using Railspike, include that inventory and the explicit approval record in the approved planning artifact; do not leave the decision solely in linked evaluation notes. Existing approved dependencies may be used within their approved scope; their presence does not authorize additional packages or upgrades.

Suggested enforcement follow-up: a required dependency-review check should flag new/changed manifests, locks, vendor/third_party trees, external script/style URLs and imported/copied assets against an approved inventory. It must report unresolved additions for review rather than automatically approving a lockfile. Path/manifest checks cannot detect every renamed or pasted external source file, so source review and the explicit policy remain necessary. This check itself needs scoped implementation; it was not added by this investigation.

## Removal boundary and completion evidence

The requested removal is interpreted as **these eight Markdown-related packages and their bundled generated infrastructure**, including all historical copies or renamed variants. It is not a request to purge every unrelated third-party item, such as Railspike, Playwright, fonts or other pre-existing assets. Confirm the inventory in later planning rather than widening scope silently.

Preserve project-authored incident evidence and historical plan descriptions; mentioning a package name is not retaining its source code. Replace current operational instructions that would reinstall the removed bundle. Inspect fixtures and generated/static releases for embedded copies and stale bundle assumptions.

A file deletion/revert cannot remove historical blobs. The later removal must account for local branches, tags, remote-tracking and other refs, renamed paths, reflogs/unreachable objects, worktrees, hosting refs/caches, forks, clones, CI artifacts and release archives. A local rewrite cannot erase someone else's clone or guarantee deletion from a host's retained storage. Later planning must name controlled repositories/hosts, owners and access, preserve unrelated work, define a freeze and recovery boundary, and distinguish verified deletion from pending external cleanup. Use local Git evidence and the relevant host's current official procedures when that execution is planned; no hosting cleanup claim is made here.

Verification for this investigation: inspected historical and current instruction files, original renderer planning/evaluation/summary, introduction commit, tracked bundle inventory, manifests/lock and application/test references. No runtime or UI behavior changed; application tests were not run. The accompanying [removal Step 1](./markdown_dependency_removal/markdown_dependency_removal_step1_solution_assessment.md) compares full-removal approaches. Instruction patches above remain proposals, not applied policy.

## Remediation status — 2026-10-21

The approved removal cycle has applied the proposed host policy in AGENTS and CONTRIBUTING, restored the earlier project-owned renderer with current safety/integration, removed the eight-package bundle from the current branch, and saved before/after metrics and rare-syntax choices. The original investigation above remains a historical account; “proposed/not applied” describes its initial state. No automated dependency-review gate has been added. Shared history and retained copies remain pending; removal from the current tree is not complete historical erasure. See the [implementation status](./markdown_dependency_removal/markdown_dependency_removal_step4_implementation_summary.md).
