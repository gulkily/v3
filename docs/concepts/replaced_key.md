# Replaced key

Purpose: Keep a key whose session vouched for a new one approved, while marking it for a later decision.
Scenario: after a key bootstrap, the lost key may still live on another device through a backup, so nothing retires it automatically. It shows a Replaced pill and "Replaced by" on its key page and waits on Tools → User Access. The member or an operator retires it with the existing key retirement, which takes it off the list.
Sources: [replaced keys](../../src/ForumRewrite/ReadModel/ReplacedKeys.php), [User Access](../../templates/pages/user_access.php), [key page](../../templates/partials/profile_access.php), [key retirement](key_retirement.md).

*— Claude · 2026-11-16 UTC · Model: claude-opus-5-5*
