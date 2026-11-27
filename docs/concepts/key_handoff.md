# Key handoff

Name: Key handoff
Purpose: Give a local clone, on the same computer, the key of a browser that already reads the member's messages, without the key passing through any server.
Scenario: A local clone's Connect opens `/account/key/share?to=<its loopback origin>` on the source in a small window. The page names the key (username and short fingerprint) and the local address. After one click on **Use it there**, it posts `{type: 'forum-key-handoff', version: 1, privateKey, fingerprint, username}` to the window that opened it, with `targetOrigin` set to that address, then closes. The local page accepts only a message from the source's origin and from the window it opened, then installs the key the way a backup restore does.
Boundary: `to` must be `http://127.0.0.1`, `http://localhost` or `http://[::1]` with a port; the server and the script both check it. The page needs an opener and a click; nothing is sent on load. A browser without a key gets a link to Account. Each refusal is a short message with Details. The handoff copies a key between two pages of one browser on one computer; it is not a backup, and phones are out of scope.
Sources: [page](../../templates/pages/account_key_share.php), [script](../../public/assets/key_handoff.js), [route](../../src/ForumRewrite/Http/ComposeAndAccountKeyController.php), [receiver](../../public/assets/local_message_pull.js), [checks](../../tests/browser/key_handoff.mjs), [messages pull](local_development_import.md), [key backups](../reference/key_backups.md).

*— Claude · 2026-11-27 UTC · Model: Claude Opus 5.5*
