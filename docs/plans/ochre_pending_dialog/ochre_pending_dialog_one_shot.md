Cycle: v3/ochre_pending_dialog, one-shot. [Plan index](../README.md)
Debug mode: enabled
Effort study: railspike-effort P2. [Ledger](../effort_ledger/ochre_pending_dialog.md)
Extended estimates: enabled

**Cycle estimate: 0.15**

Status: Implemented; browser verification and deployment pending.

Original request: Fix the visual bug in [the Ochre report](https://zenmemes.com/threads/thread-20261123021720-e6acc86f), PF-20261122-B04.
Authorization: On 2026-11-22 the requester said “please fix the visual bug” and supplied the report. Follow the repo thread-to-PR procedure.
Outcome: On Recent, opening a Pending author summary in Ochre covers the rows behind it.
Approach and risks: Keep the card frame and backdrop and give the foreground dialog the existing Ochre panel color. Limit the selector to this dialog so inline cards keep their intended transparent treatment.
Reuse: The dialog from public/assets/pending_user_summary.js, the --panel token in public/assets/theme-ochre.css, and the existing pending-chip browser scenario.
Concepts touched: Existing themes and pending author summary. CSS correction only.
Verification: Click Pending at desktop and phone widths and check the dialog color and existing approval interactions. Run repository smoke and feedback checks where available.
Starting revision: fe4a456491a26264c4d6c3b82acfa44630a50d9b, fix/ochre-pending-dialog.
Planning commit: 15f8c982800d2f577b99bf42b7d4d3fce5559daf (GitHub).
Study: 0.15 projected at execution entry, R1/P2, scope v1, single exposed rater. Detailed source was inspected before recording the estimate, so prospective intake is missing.
Attribution: Codex, 2026-11-23 UTC.

2026-11-23 UTC: The reported screenshot shows Recent text through the Pending summary. Ochre applies a transparent background to non-post cards. A more specific rule restores the existing panel background on the summary.
Verification: JavaScript syntax and git diff checks passed. Chromium could not launch because the executor denied its socket call. The new desktop and phone assertions and the live replay remain unverified. Smoke and feedback check/index were blocked by missing PHP. Full suite not run.
Final review: The CSS selector is limited to the Pending summary in Ochre and makes no changes to approval behavior.
Implementation commit: Pending GitHub publication.
