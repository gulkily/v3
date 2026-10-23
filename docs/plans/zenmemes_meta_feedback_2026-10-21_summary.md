# Zenmemes recent feedback summary — October 21, 2026

> **Historical snapshot:** current status and next actions for all 16 items now live in the [production feedback register](production_feedback_register.md). Item numbers below link to their permanent request records. Preserve this inventory's source/estimate/status evidence; update current tracking in the register. Migration is not a production audit.

Feedback from one imported todo thread, including its opening post and 13 actionable replies (14 posts total). The replies were posted on October 21, 2026, from 17:57 through 18:12 UTC (1:57–2:12 p.m. America/New_York). All imported posts in the approximately two-hour window preceding 18:17 UTC were reviewed without filtering by meta tag; the included posts carry the `general` board tag. Combined requests are split into 16 trackable items below.

Scope is the locally available Zenmemes content accepted or already present in the latest import. That import was partial, so rejected or conflicting source content and posts after the imported snapshot are not covered. Reported behavior is user feedback, not a fresh reproduction. No implementation or production audit was performed for the original compilation. Subsequent local implementation is tracked below; production resolution remains unverified.

Format follows [Zenmemes meta feedback summary](zenmemes_meta_feedback_2026-10-18_summary.md). Railspike cycle estimates use an uncapped scale from 0 upward, including decimals. These are provisional estimates based on the feedback alone, not approved plans or commitments. Brainstorming and tentative suggestions retain their original uncertainty.

Workflow recommendations and completion checks: [mini-triage of all 16 items](zenmemes_meta_feedback_2026-10-21_mini_triage.md). Recommendations do not approve implementation; completed items retain their original workflow.

## Feature requests

| Item | Request | Sources | Railspike cycles | Workflow | Status |
| --- | --- | --- | --- | --- | --- |
| [01](production_feedback_register.md#pf-20261021-f01) | Keep the reply entry field focused after posting, ready for another reply. | [reply-20261021175726-73beca3d](https://zenmemes.com/posts/reply-20261021175726-73beca3d) | 0.15 | Railspike | Open — resolution not verified |
| [02](production_feedback_register.md#pf-20261021-f02) | Remove the “browser identity ready” message. | [reply-20261021175826-902c5145](https://zenmemes.com/posts/reply-20261021175826-902c5145) | 0.03 | One-shot | Implemented and locally verified — [checks and limitations](browser_identity_ready_triage_2026-10-21.md#implementation-closeout--october-21-2026); deployment and source-comment reply pending |
| [03](production_feedback_register.md#pf-20261021-f03) | Brainstorm text-entry macros for prompts, the current date, the current commit, and similar insertions. | [reply-20261021180225-ffc44b89](https://zenmemes.com/posts/reply-20261021180225-ffc44b89) | 0.5 | Railspike | Open — resolution not verified |
| [04](production_feedback_register.md#pf-20261021-f04) | Provide a simple, nontechnical “most recent activity” page. | [reply-20261021180354-1a31022c](https://zenmemes.com/posts/reply-20261021180354-1a31022c) | 0.5 | Railspike | Open — resolution not verified |
| [05](production_feedback_register.md#pf-20261021-f05) | Make QR codes stand out slightly while matching the site theme; explore a background roughly 20% lighter than the page background (prefer lighter over darker). | [reply-20261021180542-57b8fc3f](https://zenmemes.com/posts/reply-20261021180542-57b8fc3f) | 0.25 | One-shot | Open — resolution not verified |
| [06](production_feedback_register.md#pf-20261021-f06) | Explore adding a few color choices to the LCD theme. | [reply-20261021181124-47b0956c](https://zenmemes.com/posts/reply-20261021181124-47b0956c) | 0.4 | Railspike | Implemented and locally verified — [Railspike summary](lcd_color_choices/lcd_color_choices_step4_implementation_summary.md); deployment pending |
| [07](production_feedback_register.md#pf-20261021-f07) | Make local `./v3 start` try a few successive ports after 8000 before giving up by default. | [reply-20261021180905-9bf88f1f](https://zenmemes.com/posts/reply-20261021180905-9bf88f1f) | 0.2 | One-shot | Open — resolution not verified |
| [08](production_feedback_register.md#pf-20261021-f08) | Expand “View query” availability to more pages; assess coverage for every page that includes database data. | [reply-20261021181111-3d37d8d5](https://zenmemes.com/posts/reply-20261021181111-3d37d8d5) | 0.6 | Railspike | Open — resolution not verified |
| [09](production_feedback_register.md#pf-20261021-f09) | Use the same small square “View query” button as `/tools/codebase`. | [reply-20261021181111-3d37d8d5](https://zenmemes.com/posts/reply-20261021181111-3d37d8d5) | 0.15 | One-shot | Open — resolution not verified |
| [10](production_feedback_register.md#pf-20261021-f10) | Improve the “View query” dialog; review its current problems before defining the changes. | [reply-20261021181111-3d37d8d5](https://zenmemes.com/posts/reply-20261021181111-3d37d8d5) | 0.75 | Railspike | Open — resolution not verified |
| [11](production_feedback_register.md#pf-20261021-f11) | Make it easy to discover how to enable “easy-op” via its flag file, including the button that elevates the operator to approved. | [reply-20261021181246-675f949e](https://zenmemes.com/posts/reply-20261021181246-675f949e) | 0.25 | Railspike | Open — resolution not verified |

## Bug reports

Local fixes and remaining production verification are tracked separately below.

| Item | Report | Sources | Railspike cycles | Workflow | Status |
| --- | --- | --- | --- | --- | --- |
| [01](production_feedback_register.md#pf-20261021-b01) | Investigate the multiple causes of the home-page post form intermittently jumping up and down; use a rubber-duck root-cause review. | [reply-20261021175826-902c5145](https://zenmemes.com/posts/reply-20261021175826-902c5145) | 0.75 | Railspike | Open — resolution not verified |
| [02](production_feedback_register.md#pf-20261021-b02) | Keep the textarea’s top, left, and right edges fixed when the composer expands. | [reply-20261021175826-902c5145](https://zenmemes.com/posts/reply-20261021175826-902c5145) | 0.25 | One-shot | Open — resolution not verified |
| [03](production_feedback_register.md#pf-20261021-b03) | Keep the composer collapsed on page load, even when the textarea contains saved text. | [reply-20261021180138-eddd96a4](https://zenmemes.com/posts/reply-20261021180138-eddd96a4) | 0.15 | One-shot | Home-page fix implemented and locally verified — [checks and limits](ui_copy_audit_checklist.md#saved-home-page-draft-stays-collapsed--october-21-2026); deployment pending |
| [04](production_feedback_register.md#pf-20261021-b04) | Investigate and reduce the many-second delay before a posted reply produces a visible response on the live site. | [reply-20261021175854-431cbb06](https://zenmemes.com/posts/reply-20261021175854-431cbb06) | 1.5 | Railspike | Open — resolution not verified |
| [05](production_feedback_register.md#pf-20261021-b05) | Investigate and fix QDB returning “temporarily busy.” | [reply-20261021180434-90201f38](https://zenmemes.com/posts/reply-20261021180434-90201f38) | 0.75 | Railspike | Open — resolution not verified |

## Other

| Item | Summary | Sources | Railspike cycles |
| --- | --- | --- | --- |
| 01 | **Thread marker:** the opening “todo” post establishes the collection; its requests are in the replies above. | [thread-20261021175710-9fe2b724](https://zenmemes.com/posts/thread-20261021175710-9fe2b724) | 0 |
| 02 | **External product feedback:** repeated posts about Codex terminal selection and clipboard behavior concern Codex itself rather than this repository, so they are excluded from the actionable inventory. | [thread-20261021164428-3e290de5](https://zenmemes.com/posts/thread-20261021164428-3e290de5), [thread-20261021164510-e70e4856](https://zenmemes.com/posts/thread-20261021164510-e70e4856) | 0 |

### Related historical feedback

The earlier summary already records slow reply submission (Feature 09 and Bug 04). This new report supplies recent live-site feedback; it does not establish the cause or close those items. Its resolved browser-identity-copy item (Feature 33) concerns different text, so it does not establish that the newly reported “browser identity ready” notice is gone.

Earlier same-day composer reports about saved text remaining after successful submission, preferring an inline home-page update, and focusing the text field after a redirect fall outside this time window and are not folded into these requests.

## Source inventory

### Triage estimation follow-up

Feature 02 now has a [source-informed estimation snapshot](browser_identity_ready_triage_2026-10-21.md#railspike-estimation-snapshot): **0.10 projected total**, compared with its preserved **0.03 feedback-only estimate**. The snapshot records scope, baseline, R1 calibration, exposure, verification burden, uncertainty, and revision triggers. It does not approve implementation or enroll the item in the effort trial.

Each count includes the opening post. Sources link to the imported instance; corresponding raw records remain under `state/local_repository/records/posts`. The external Codex feedback above is contextual and excluded from this count.

| Thread | Date | Posts | Topic |
| --- | --- | --- | --- |
| [thread-20261021175710-9fe2b724](https://zenmemes.com/threads/thread-20261021175710-9fe2b724) | 2026-10-21 | 14 | Todo: composer behavior, reply latency, text-entry macros, recent activity, QDB availability, themes, local startup, query tools, and easy-op discoverability |
