# Static publication

Purpose: Publish a generated public site from the derived read model.
Scenario: build-static prepares and validates a candidate, renders public pages/assets and the offline snapshot, promotes the read model, then activates the release. Successful rendering alone is not successful activation; an earlier candidate failure is not a new published release.
Sources: [command](../../scripts/build_static_artifacts.php), [artifact builder](../../src/ForumRewrite/Host/StaticArtifactBuilder.php), [release publisher](../../src/ForumRewrite/Host/StaticArtifactReleasePublisher.php), [offline snapshot](../../src/ForumRewrite/Offline/PublicOfflineSnapshotBuilder.php).
Known limitation: QDB rendering and offline selection can load broad input collections before choosing output; see the [QDB audit](../plans/qdb_memory_audit/qdb_memory_audit_findings.txt). High-traffic cache refresh is a separate publication path.

Tag artifacts contain only the first 25 matching threads. Later native page links include a position query and use runtime rendering; the frontend's existing query bypass prevents a first-page artifact from replacing a later page. Shared refresh preserves existing tag artifacts; a full build regenerates their bounded first pages.

Profile prebuilding is independently controlled by `FORUM_STATIC_PROFILE_PAGES_ENABLED` (default false). Disabled builds omit profile artifacts and the PHP frontend bypasses stale ones; shared refresh excludes copied profile artifacts and single-route generation skips them. Re-enabling requires a full build to restore artifacts. Identity/history data remains in the read model. See [profile delivery](../reference/profile_static_delivery.md).
See [profile delivery](profile_delivery.md) for the runtime/static transition contract.
