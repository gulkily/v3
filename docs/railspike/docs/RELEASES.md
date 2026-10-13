# Releases and Versioning

Railspike uses semantic versioning for published releases.

- **Patch:** clarifications, examples, and fixes that do not change the workflow contract.
- **Minor:** backwards-compatible new guidance, templates, or optional workflow capabilities.
- **Major:** a changed approval, artifact, branch, or commit requirement that requires consumers to alter their process.

Publish a signed annotated Git tag as `vMAJOR.MINOR.PATCH` after the README links, examples, and Markdown checks pass. Create a GitHub release from that tag with a concise list of workflow-contract changes and any migration note. Consumers pinned through a submodule choose when to update; they should review release notes before updating.

The repository has not yet declared its first public release tag. The first tag should be created only after the public repository and its linked evidence are available to readers.

## Railspike rename (unreleased)

Feature Development Process (FDP) is now Railspike. This is a breaking rename with no backwards compatibility: the maintainer is the only known user. The canonical repository is `gulkily/railspike`, host installations use `docs/railspike`, the main instructions are `RAILSPIKE.md`, and per-step instructions live in `docs/workflow/`. The agent shorthand is `@railspike`; select `@RAILSPIKE.md` when available. Legacy entrypoints and aliases are not provided.

The four-step workflow and approval rules are unchanged. Installation remains one `git submodule add` command. An agent handles Git updates, outlines the changes, and asks whether to commit the pointer or leave it for review. The proposed `rsp` utility was abandoned.

Publish the first release only after the rename and GitHub Pages website are ready. Version selection, the signed annotated tag, and GitHub release are follow-up work; no release is declared by this note.
