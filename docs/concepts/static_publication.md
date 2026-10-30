# Static publication

Purpose: Publish a generated public site from the derived read model.
Scenario: build-static prepares and validates a candidate, renders public pages/assets and the offline snapshot, promotes the read model, then activates the release. Successful rendering alone is not successful activation; an earlier candidate failure is not a new published release.
Sources: [command](../../scripts/build_static_artifacts.php), [artifact builder](../../src/ForumRewrite/Host/StaticArtifactBuilder.php), [release publisher](../../src/ForumRewrite/Host/StaticArtifactReleasePublisher.php), [offline snapshot](../../src/ForumRewrite/Offline/PublicOfflineSnapshotBuilder.php).
Known limitation: QDB rendering and offline selection can load broad input collections before choosing output; see the [QDB audit](../plans/qdb_memory_audit/qdb_memory_audit_findings.txt). High-traffic cache refresh is a separate publication path.
