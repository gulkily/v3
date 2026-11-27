# music_share_compact Railspike debug log

## 2026-11-26T13:45Z — One-shot

### Necessary/useful instructions

- AGENTS.md, follow-ups after a merged PR: new branch from a freshly fetched `origin/main` and a new PR, not a push to `feat/mitrapclub_music`.
- AGENTS.md, UI verification with the real stylesheets: the comparison screenshot exposed the actual cause (no stylesheets mapped for `music.php`), which unit tests and the earlier 380px overflow check had not caught.

### Uncertain assessments

- The earlier cycle's screenshots looked styled because the theme stylesheet styles cards and buttons; the missing `compose.css` only showed as a fully expanded form. That check measured overflow, not parity with the Board.
