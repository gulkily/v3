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

## Proposed next work

Recommendations only; confirm current behavior and authorization before starting.

- **F07:** bounded default-port fallback could remove local startup friction at low inherited effort (0.2); verify it is still missing.
- **F09:** compact query controls are a small consistency improvement (0.15), after confirming surfaces and overlap with F08.
- **B05:** move availability investigation ahead of polish if QDB is currently unavailable; cause and scope remain uncertain (0.75). No fresh live check was performed.

Coordinate F01/B01/B02/B04 with existing reply work before scoping. F08/F09/F10 share a report but have different completion checks.

## PF-20261021-F04 — Simple recent activity

- **Alias:** `2026-10-21/F04` (Feature 04); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Provide a simple, nontechnical “most recent activity” page. **Reports:** [reply-20261021180354-1a31022c](https://zenmemes.com/posts/reply-20261021180354-1a31022c). **Inherited estimate:** 0.5 (October 21 feedback-only); **recommended workflow:** Railspike (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Ordinary readers can find recent content and open it without technical detail. **Next action:** Choose the intended audience and useful events before scoping a reader-facing Activity view.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** Existing Activity exposes record kinds and source metadata; choose audience and useful events before redesign. [Source](../../templates/pages/activity.php).

## PF-20261021-F05 — QR appearance

- **Alias:** `2026-10-21/F05` (Feature 05); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Make QR codes stand out slightly while matching the site theme; explore a background roughly 20% lighter than the page background (prefer lighter over darker). **Reports:** [reply-20261021180542-57b8fc3f](https://zenmemes.com/posts/reply-20261021180542-57b8fc3f). **Inherited estimate:** 0.25 (October 21 feedback-only); **recommended workflow:** One-shot (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** QR stands out across themes and phone/desktop; quiet zone and scanning still work. **Next action:** Confirm the reported QR surface, then scope visual and scan checks.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** Invitation QR currently draws black on white; first confirm the reported QR surface, then bound the visual change. [Source](../../public/assets/invite_issuance.js).

## PF-20261021-F06 — LCD color choices

- **Alias:** `2026-10-21/F06` (Feature 06); **workflow state:** blocked; **delivery evidence:** locally verified; deployment/production acceptance pending. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Explore adding a few color choices to the LCD theme. **Reports:** [reply-20261021181124-47b0956c](https://zenmemes.com/posts/reply-20261021181124-47b0956c). **Inherited estimate:** 0.4 (October 21 feedback-only); **recommended workflow:** Railspike (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Chosen palettes remain readable; selection persists and existing themes work. **Next action:** Verify deployment and palette behavior in production; retain local browser/contrast evidence.
- **Recorded evidence/context:** Implemented and locally verified — [Railspike summary](lcd_color_choices/lcd_color_choices_step4_implementation_summary.md); deployment pending. **Triage context:** LCD has a fixed palette; choose palette scope and preference behavior before adding controls. [Source](../../public/assets/theme-lcd.css).

## PF-20261021-F07 — Default port fallback

- **Alias:** `2026-10-21/F07` (Feature 07); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Make local `./v3 start` try a few successive ports after 8000 before giving up by default. **Reports:** [reply-20261021180905-9bf88f1f](https://zenmemes.com/posts/reply-20261021180905-9bf88f1f). **Inherited estimate:** 0.2 (October 21 feedback-only); **recommended workflow:** One-shot (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Occupied default ports advance within a fixed range; actual URL, exhaustion, and shutdown behave correctly. **Next action:** Confirm current default/explicit port behavior, then scope bounded fallback and exhaustion checks.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** Startup selects one address; bounded retries can preserve explicit-address behavior. [Source](../../v3).

## PF-20261021-F08 — View query coverage

- **Alias:** `2026-10-21/F08` (Feature 08); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Expand “View query” availability to more pages; assess coverage for every page that includes database data. **Reports:** [reply-20261021181111-3d37d8d5](https://zenmemes.com/posts/reply-20261021181111-3d37d8d5). **Inherited estimate:** 0.6 (October 21 feedback-only); **recommended workflow:** Railspike (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Coverage and exclusions are documented; each added control opens the correct read-only query. **Next action:** Inventory database-backed pages and explicit exclusions before choosing additions.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** Broad audit across database-backed pages; stage the inventory and bounded additions. [Source](../../templates/partials/query_action.php).

## PF-20261021-F09 — Compact query buttons

- **Alias:** `2026-10-21/F09` (Feature 09); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Use the same small square “View query” button as `/tools/codebase`. **Reports:** [reply-20261021181111-3d37d8d5](https://zenmemes.com/posts/reply-20261021181111-3d37d8d5). **Inherited estimate:** 0.15 (October 21 feedback-only); **recommended workflow:** One-shot (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Consistent square buttons retain accessible names and open the correct query across affected themes. **Next action:** Agree surfaces using compact controls; coordinate with F08 without conflating outcomes.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** Shared query partial already supports compact buttons; reuse it on agreed surfaces. [Source](../../templates/partials/query_action.php).

## PF-20261021-F10 — Query-dialog improvements

- **Alias:** `2026-10-21/F10` (Feature 10); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Improve the “View query” dialog; review its current problems before defining the changes. **Reports:** [reply-20261021181111-3d37d8d5](https://zenmemes.com/posts/reply-20261021181111-3d37d8d5). **Inherited estimate:** 0.75 (October 21 feedback-only); **recommended workflow:** Railspike (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Record reproduced problems and verify their fixes, including keyboard, phone layout, and recovery. **Next action:** Collect specific dialog failures before selecting changes; coordinate with F08/F09.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** The report does not identify specific dialog failures; reproduce before choosing changes. [Source](../../public/assets/sqlite_dialog.js).

## PF-20261021-F11 — Easy-op discovery

- **Alias:** `2026-10-21/F11` (Feature 11); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Make it easy to discover how to enable “easy-op” via its flag file, including the button that elevates the operator to approved. **Reports:** [reply-20261021181246-675f949e](https://zenmemes.com/posts/reply-20261021181246-675f949e). **Inherited estimate:** 0.25 (October 21 feedback-only); **recommended workflow:** Railspike (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Identify the intended flag; expose one useful discovery path while preserving approval restrictions. **Next action:** Identify the intended easy-op flag and authority model before proposing discovery changes.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** An Account approval button and `.test-self-approval` exist, but their equivalence to “easy-op” is unconfirmed. [Source](../../src/ForumRewrite/Support/TestingSelfApproval.php).

### F06 evidence reconciliation

Reviewed 2026-10-23: the historical mini-triage describes LCD palette selection as future work. The later source summary links the [local implementation record](lcd_color_choices/lcd_color_choices_step4_implementation_summary.md), which reports browser/persistence/contrast checks. Local implementation is recorded here; deployment and production acceptance remain pending. This is document reconciliation, not a new test.

## PF-20261021-B01 — Composer jumping

- **Alias:** `2026-10-21/B01` (Bug 01); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Investigate the multiple causes of the home-page post form intermittently jumping up and down; use a rubber-duck root-cause review. **Reports:** [reply-20261021175826-902c5145](https://zenmemes.com/posts/reply-20261021175826-902c5145). **Inherited estimate:** 0.75 (October 21 feedback-only); **recommended workflow:** Railspike (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Reproduce each jump and verify corrected geometry through loading, focus, drafts, and recovery. **Next action:** Reproduce each jump and distinguish focus, loading and expansion causes before scoping.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** Several causes are reported; existing focus and expansion paths need a reproducible root-cause review. [Source](../../public/assets/inline_reply_form.js).

## PF-20261021-B02 — Textarea edge alignment

- **Alias:** `2026-10-21/B02` (Bug 02); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Keep the textarea’s top, left, and right edges fixed when the composer expands. **Reports:** [reply-20261021175826-902c5145](https://zenmemes.com/posts/reply-20261021175826-902c5145). **Inherited estimate:** 0.25 (October 21 feedback-only); **recommended workflow:** One-shot (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Top/left/right edges stay fixed during expansion across phone/desktop themes; other content does not overflow. **Next action:** Measure composer edges in compact/expanded states before choosing a bounded fix.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** Compact and expanded states use different CSS geometry; start with measured board-composer bounds. [Source](../../public/assets/compose.css).

## PF-20261021-B03 — Saved draft starts collapsed

- **Alias:** `2026-10-21/B03` (Bug 03); **workflow state:** blocked; **delivery evidence:** locally verified; deployment/production acceptance pending. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Keep the composer collapsed on page load, even when the textarea contains saved text. **Reports:** [reply-20261021180138-eddd96a4](https://zenmemes.com/posts/reply-20261021180138-eddd96a4). **Inherited estimate:** 0.15 (October 21 feedback-only); **recommended workflow:** One-shot (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Restored draft stays compact until focus, preserving text, selection, warnings, and retry. **Next action:** Verify deployment and saved-draft behavior in production; preserve home-page-only evidence.
- **Recorded evidence/context:** Home-page fix implemented and locally verified — [checks and limits](ui_copy_audit_checklist.md#saved-home-page-draft-stays-collapsed--october-21-2026); deployment pending. **Triage context:** Completed bounded state change; draft text no longer implies initial engagement. [Source](../../public/assets/inline_reply_form.js).

## PF-20261021-B04 — Slow reply feedback

- **Alias:** `2026-10-21/B04` (Bug 04); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Investigate and reduce the many-second delay before a posted reply produces a visible response on the live site. **Reports:** [reply-20261021175854-431cbb06](https://zenmemes.com/posts/reply-20261021175854-431cbb06). **Inherited estimate:** 1.5 (October 21 feedback-only); **recommended workflow:** Railspike (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Measure the delay, improve the identified stage, and verify prompt feedback plus one accepted reply and retry. **Next action:** Measure the live delay by stage and reconcile historical latency reports before choosing a slice.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** Reply preparation, signing, finalization, and navigation are separate stages; the live bottleneck is unknown. [Source](../../public/assets/browser_signing.js).

## PF-20261021-B05 — QDB temporarily busy

- **Alias:** `2026-10-21/B05` (Bug 05); **workflow state:** untriaged; **delivery evidence:** unknown; production report not reproduced by this migration. Reviewed 2026-10-23 from existing documents, no runtime audit.
- **Request:** Investigate and fix QDB returning “temporarily busy.” **Reports:** [reply-20261021180434-90201f38](https://zenmemes.com/posts/reply-20261021180434-90201f38). **Inherited estimate:** 0.75 (October 21 feedback-only); **recommended workflow:** Railspike (not approval). [Historical triage](zenmemes_meta_feedback_2026-10-21_mini_triage.md).
- **Done when:** Identify the failing route and cause; verify ordinary QDB use and controlled recovery without hiding errors. **Next action:** Confirm the failing QDB route and current availability; collect evidence for the 503 cause.
- **Recorded evidence/context:** Open — resolution not verified. **Triage context:** FrontController has several 503 paths; the reported production cause has not been reproduced. [Source](../../src/ForumRewrite/Host/FrontController.php).

## Related historical reports awaiting reconciliation

B04 and F01 overlap earlier slow-reply/focus reports, including October 18 Feature 09/Bug 04. These are relationships, not confirmed duplicates or closures. October 18 Feature 33 concerns different browser-identity wording from F02. Same-day composer reports outside the October 21 snapshot are not silently added; review them as new intake. Older inventories and excluded external Codex feedback remain outside this register's 16-entry coverage.
