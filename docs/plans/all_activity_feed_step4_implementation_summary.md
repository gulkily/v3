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
