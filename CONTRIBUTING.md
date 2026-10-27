# Contributing

## Human-readable formats

Canonical records and files intended for people to read or edit must use the
project's `Key: value` headers and plain-text body, not JSON. Apply this to source
views, human-facing exports, and editable configuration; keep multiline content
readable rather than embedding escaped strings. Existing JSON is migration debt,
not precedent for new formats.

JSON is appropriate for machine APIs/protocols, explicit `--json` output, and
internal caches or state. A human-facing exception requires explicit maintainer
approval. Document canonical formats in `docs/specs/`; format changes must
preserve signed bytes and trust decisions and explicitly define existing-record handling.
Track outstanding conversions in [the format audit](docs/plans/human_readable_formats_checklist.md).

## UI copy and verification

Remove explanations that repeat controls, visible results, or other feedback.
Review shared templates and browser/server messages together so removed copy
does not return through another source. Hide empty status elements and omit
routine success notices when the result already communicates success. Keep
pending, partial, and failure feedback when users need it to decide what to do.

Keep material access, privacy, and data-loss consequences beside the relevant
decision, with actionable recovery. Use contextual help only for information
the interface cannot convey, and make it accessible without hover. Preserving
a warning does not by itself require adding a confirmation step; preserve the
existing interaction unless changing it is part of the requested work.

Before marking a UI change complete:

- Check the rendered flow on phone and desktop in the relevant initial,
  loaded, empty, success, failure, and expanded states. Include affected shared
  views and themes, and confirm the main action remains easy to find.
- Check page overflow after real content loads, including long values,
  pagination, and expanded details. Wide tables should scroll within their
  containers. Check that initialization does not shift primary actions.
- Update affected copy assertions and run the relevant existing checks.
  Verify recovery and material consequences through the affected flow;
  source-text assertions alone do not establish that the UI works.
- Record what was verified, any limitations, and unresolved follow-ups in
  `docs/plans/ui_copy_audit_checklist.md` for copy cleanup, or the relevant
  implementation document for other UI work. Distinguish completed cleanup
  from unresolved layout or behavior issues.

## Feature flag presentation order

`FeatureFlagRegistry::PRESENTATION_ORDER` is the canonical UI order: Access and
identity, Authored content, Forum experience, Site rendering, Backups, Fastmod,
Agent replies, then LLM exchanges. The page uses `presentationGroups()` rather
than definition insertion order, labels, status or configuration source.

Every registered flag must appear exactly once in its assigned group. Append new
flags to that group's list by default; place dependent controls together immediately
after their parent (after existing siblings). Add new groups at the end unless an
explicit UI change approves another position. Reordering existing controls is an
intentional UI change, not a side effect of registering a flag. Search and status
filters must preserve this order. Keep the registry completeness/dependency and
rendered-order checks current; do not bypass them for new flags.

## Operator CLI

Inspect comparable `v3` commands before designing a command. Use readable output
by default and `--json` for automation. Include actionable setup/recovery guidance,
keep top-level help current, and follow existing subcommand meanings: `cron`
prints installation instructions; `run` executes the scheduled worker. Verify
help, output modes and required next steps in the relevant implementation plan.

## Private runtime configuration

Register new private-config/environment settings in `PrivateConfigSchema`, with
an explicit decision about inclusion in `templateDefaults()`. Template settings
are emitted automatically by the private-config writer; add dedicated comments
and examples when operators need guidance. Do not maintain a separate key list.
Verify creation and `private-config refresh-template` preserve every template
setting and existing custom values with `PrivateConfigCommandTest`; run the
schema and affected configuration-consumer checks as well.

## Read-model schema changes

`state/cache/post_index.sqlite3` is a disposable derived read model, rebuilt
from canonical records by `ReadModelBuilder`. A schema change is any change
to its core tables, columns, indexes, constraints, or a query that depends on
one of them.

When making a read-model schema change, the same change set must:

1. Increment `ReadModelMetadata::SCHEMA_VERSION`.
2. Update `docs/specs/read_model_schema_v1.md`.
3. Add or update a regression test that starts with the prior schema version
   and verifies that normal application startup rebuilds the read model before
   the changed query runs.
4. Verify `./v3 rebuild` and the affected normal UI or API flow.

Do not rely on a repository-head change to rebuild deployed read models: the
canonical-record repository and application code can be deployed separately.

## Third-party dependencies and copied code

Prefer existing project code and supported platform facilities. Before adding or
updating any external library, framework, plugin, asset, copied source, generated
vendor tree, package-manager manifest/lock or externally hosted runtime component,
disclose the exact proposed change and obtain explicit user approval for that
dependency change. This covers runtime, build and development dependencies, direct
and transitive packages, and delivery through downloads, CDNs, subtrees or submodules.

Present component names, versions, full resolved transitive additions/changes,
purpose, origin, licenses, delivery method, affected paths, approximate size/file
count, maintenance obligations, and a dependency-free alternative or reduced scope.
A formatting/functionality request, generic Approved Step N, successful tests,
permissive license or instruction to reuse components does not substitute for this
approval. Explicit dependency approval may accompany a step approval; record its
scope in the plan and carry it forward without asking again for the same approved
action. A changed dependency tree or delivery method requires renewed approval for
that change. Existing approved dependencies may be used within their approved scope;
their presence does not authorize additional packages or upgrades.

Research may inspect metadata and run disposable evaluations outside the repository
using existing approved tools. Do not copy or install an evaluated component into
the repository, load it into the application, commit it or publish it until approved.
Temporary evaluations must not alter project manifests, locks or generated artifacts.

Before completion, compare actual file/manifest/lock changes against the approved
inventory and report the dependency delta, including zero. With Railspike, include
the inventory and explicit approval record in the approved planning artifact; do not
leave the decision solely in linked evaluation notes.

## Pagination

For new bounded lists, use the shared controls and position contract described
in [Implementing pagination](docs/reference/pagination.md). Standard Activity is
the worked example. Keep each list's query/access rules separate; existing list
migrations require their own scoped review. Forte retains its append behavior.
