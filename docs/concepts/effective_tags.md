# Effective tags

Purpose: Show public tags implied by current compatible scores alongside authored tags.
Scenario: A transferred score for accepted root-post content meets the destination's current meta threshold. Existing live, Forte, text, offline and static readers expose the meta tag. Destination tagging policy and legacy suppressions still apply; transferred scores create no signed label or classification row.
Sources: [derived mapping](../../src/ForumRewrite/Scoring/FastmodDerivedTags.php), [read overlay](../../src/ForumRewrite/Scoring/FastmodMetaRead.php), [policy](../../src/ForumRewrite/Scoring/FastmodTaggingPolicy.php).
