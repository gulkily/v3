# All Activity Feed Implementation Summary

## Stage 1 - Activity contract and action inventory
- Changes:
  - Defined the activity boundary as successful frontend actions that create or change canonical repository data.
  - Mapped the initial action families: threads/replies, identity/bootstrap, approvals, thread labels, post reactions, and site feature flags.
  - Decided that state-only artifacts such as public keys and instance metadata are represented through the action that creates or changes them, rather than becoming raw file listings.
  - Required All Activity to include internal/bootstrap/approval events; category views remain subsets.
  - Required each event to carry a stable kind, human-readable label, canonical timestamp, resource/source evidence, and deterministic ordering.
- Verification:
  - Reviewed frontend write routes in `Application.php` and public write methods in `LocalWriteService.php`.
  - Confirmed existing activity consumers: HTML activity, RSS, backup preview, static artifacts, and navigation.
  - Confirmed no implementation files were changed in this stage.
- Notes:
  - Frontend-only interactions with no canonical repository write are outside this feature.
  - Codex handoff and agent-reply workflow state are not included unless they acquire canonical repository records in a later scope decision.

## Stage 2 - Read-model activity representation
- Changes:
  - Added nullable `action_key` and required `record_family` fields to the activity read model for deterministic action grouping and non-post event classification.
  - Added an activity action-key index.
  - Bumped the read-model schema version from 12 to 13 so existing databases rebuild into the new representation.
- Verification:
  - `php -l src/ForumRewrite/ReadModel/ReadModelMetadata.php` passed.
  - `php -l src/ForumRewrite/ReadModel/ReadModelBuilder.php` passed.
  - `php tests/run.php ReadModelBuilderTimingTest LocalAppSmokeTest::testActivityRssAndBackupSnapshot` passed.
- Notes:
  - Existing activity insert paths continue to work through the column default; later stages will populate family/action values for each event type.

## Stage 3 - Full-rebuild activity coverage
- Changes:
  - Classified rebuilt post activity as `post`, `identity`, `identity_bootstrap`, or `approval` from canonical board tags.
  - Added rebuilt `post_reaction` activity events with target post/thread links, actor data, and canonical reaction source paths.
  - Added deterministic action keys for post, label, reaction, feature-flag, and classified identity activity rows.
  - Preserved label events for internal records so All Activity can include them in the rendering stage.
  - Updated the read-model smoke expectation for schema version 13.
- Verification:
  - `php -l src/ForumRewrite/ReadModel/ReadModelBuilder.php` passed.
  - `php tests/run.php ReadModelBuilderTimingTest ReadModelPostReactionsTest` passed.
  - Full rebuild of `state/local_repository` into a fresh temporary database produced 1,662 activity rows across approval, identity bootstrap, instance feature flags, post, post reaction, and thread-label families.
  - Full suite was run; existing unrelated failures remain, while the schema-version expectation was corrected in this stage.
- Notes:
  - Public-key, approval-seed, and instance-public files remain state evidence attached to their creating/changing action rather than standalone raw-file events.
