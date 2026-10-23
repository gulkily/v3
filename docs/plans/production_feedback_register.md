# Production feedback register

Current tracking authority for the migrated October 21 batch. [How to maintain it](../reference/production_feedback_management.md) · [Source inventory](zenmemes_meta_feedback_2026-10-21_summary.md) · [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).

Coverage: 16 actionable entries (11 features, 5 bugs), from a partial import of one thread; not all production feedback. [Parent post](https://zenmemes.com/posts/thread-20261021175710-9fe2b724) · [Full thread](https://zenmemes.com/threads/thread-20261021175710-9fe2b724). Sources and estimates below are inherited; migration does not certify current code or production behavior. Each heading is a permanent ID and each dated F/B alias maps back to its original inventory row.

Older inventories **not yet reconciled**: [October 18](zenmemes_meta_feedback_2026-10-18_summary.md) and [imported requests](imported_feature_requests_summary_v1.md). Their IDs/status remain historical. Non-actionable/external context remains in the October 21 source inventory. Do not infer those inventories are duplicate-free or fully represented here.

Workflow and delivery evidence are independent. No entry is closed by this migration; local implementations await production checks. “Blocked” here means awaiting deployment/verification, not proof of a technical obstruction. Untriaged means current scope/approval/evidence still needs review; inherited workflow recommendations remain visible.

## PF-20261021-F01 — Reply focus

- **Alias:** `2026-10-21/F01` (Feature 01); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Keep the reply entry field focused after posting, ready for another reply. **Reports:** [reply-20261021175726-73beca3d](https://zenmemes.com/posts/reply-20261021175726-73beca3d). **Inherited estimate:** 0.15 (October 21 feedback-only); **recommended workflow:** Railspike (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Post once, retain usable reply focus; failure/retry preserves text and sends no duplicate. **Next action:** Reconcile with paused reply-focus/layout work and confirm the phone entry/failure path before scoping.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** Reply submission navigates to the canonical reply; focus and phone-keyboard behavior overlap paused reply work. [Source](../../public/assets/browser_signing.js).

## PF-20261021-F02 — Identity-ready notice

- **Alias:** `2026-10-21/F02` (Feature 02); **workflow state:** blocked; **delivery evidence:** locally verified; deployment/production acceptance pending. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Remove the “browser identity ready” message. **Reports:** [reply-20261021175826-902c5145](https://zenmemes.com/posts/reply-20261021175826-902c5145). **Inherited estimate:** 0.03 (October 21 feedback-only); **recommended workflow:** One-shot (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** No ready notice or empty gap; pending/error and Account results remain useful. **Next action:** Verify deployment and the reported notice in production; source-comment reply remains pending and requires authorization.
- **Recorded evidence/context:** Implemented and locally verified — [checks and limitations](browser_identity_ready_triage_2026-10-21.md#implementation-closeout--october-21-2026); deployment and source-comment reply pending. **Triage context:** Completed shared status cleanup; no new interface needed. [Source](../../public/assets/browser_signing.js).

## PF-20261021-F03 — Text-entry macros

- **Alias:** `2026-10-21/F03` (Feature 03); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Brainstorm text-entry macros for prompts, the current date, the current commit, and similar insertions. **Reports:** [reply-20261021180225-ffc44b89](https://zenmemes.com/posts/reply-20261021180225-ffc44b89). **Inherited estimate:** 0.5 (October 21 feedback-only); **recommended workflow:** Railspike (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Agree a small macro set; insertion preserves surrounding text, selection, and undo. **Next action:** Agree the smallest macro set and entry points before planning insertion/undo behavior.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** Insertion rules, selection handling, and intended entry points are undefined. [Source](../../templates/partials/thread_compose_form.php).
