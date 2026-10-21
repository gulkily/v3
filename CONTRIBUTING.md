# Contributing

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
