# Reaction record

Purpose: Record one author adding tags to a thread (labels, likes, QDB votes) or a post (likes, flags) in one self-describing format.
Scenario: A signed Like is saved as `Target-Kind: thread` in `records/thread-labels/`, beside its `.asc` signature. A legacy `Thread-ID`/`Labels` record from the same author for the same target and tag counts as the same vote. Thread scope drives labels and thread scores; post scope drives post scores and moderation. Legacy header sets are read only through a deprecated adapter so their support can be removed later.
Sources: [format](../specs/reaction_record_v1.md), [parser](../../src/ForumRewrite/Canonical/ReactionRecordParser.php), [legacy adapter](../../src/ForumRewrite/Canonical/LegacyReactionHeaders.php), [signed submission](../specs/signed_reaction_submission_v1.md).
