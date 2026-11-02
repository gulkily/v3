# Initial naming and renaming

Purpose: Let a guest choose a name without replacing the key that owns posts and membership.
Scenario: An automatic guest key opens an untargeted invitation, enters a Username beside Redeem, signs its initial name, and gains membership under the same key. An already accepted guest uses Account → Name → Change.
Authority: The canonical original name is guest and no verified name update exists. Prepare and finalization check this under the write lock; a prior rename back to guest does not reopen initial naming. Later renaming follows the Username changes flag. Initial naming itself grants no approval.
Recovery: The browser retains the exact signed name request per identity before finalization. A retry verifies committed bytes/signature/history, repairs stale derived state and returns the latest name. If redemption fails afterward, the saved name remains and the invitation is retried without replacing the key.
Compatibility: Existing canonical identity/name formats and duplicate-name rules remain; legacy missing Username defaults to guest. Current local UI hints never grant server naming authority. Keyless participation and targeted automatic invitation entry remain.
Sources: src/ForumRewrite/Write/LocalWriteService.php; src/ForumRewrite/ReadModel/ProfileNames.php; public/assets/browser_signing.js; public/assets/invite_redemption.js; templates/pages/account_key.php; templates/pages/lobby.php; docs/reference/invitation_entry.md.
