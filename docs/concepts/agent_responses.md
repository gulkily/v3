# Agent-response attribution

Purpose: Record response kind and source/version binding within the signed post.
Scenario: Parsing a new named-header response or a historical JSON-header response
produces the same validated metadata without rewriting the signed record.
Sources: `src/ForumRewrite/Canonical/AgentResponseMetadata.php`,
`src/ForumRewrite/Canonical/PostRecordParser.php`,
`docs/specs/agent_response_metadata_v1.md`.

# Response expansion

Purpose: Bind a longer reply to the correct initial response and source versions.
Scenario: An expansion retains its deterministic identity and is displayed only
when its author, parent, thread and version relationships are valid.
Sources: `src/ForumRewrite/Agent/ScriptureExpansionService.php`,
`src/ForumRewrite/Canonical/AgentResponseMetadata.php`.

# Offline response projection

Purpose: Keep response grouping available in portable snapshots.
Scenario: Rebuilt canonical metadata is projected into machine JSON used by the
offline reader; the reader does not parse historical canonical headers itself.
Sources: `src/ForumRewrite/Offline/PublicOfflineSnapshotBuilder.php`,
`public/assets/offline_reader.js`.
