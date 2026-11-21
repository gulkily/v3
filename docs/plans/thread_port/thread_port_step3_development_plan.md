> **Feature plan:** [Step 1](./thread_port_step1_solution_assessment.md) · [Step 2](./thread_port_step2_feature_description.md) · [Step 3](./thread_port_step3_development_plan.md) · [Step 4](./thread_port_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/thread_port`; host ledger [thread_port.md](../effort_ledger/thread_port.md). Standing P2 enrollment.

Extended estimates: enabled. Stage estimates are relative and are not summed into the cycle estimate.

Cycle estimate: 0.95

# Step 3: Development Plan — Port a Hacker News thread

Approved: Step 2 (2026-11-19). Clarification agreed in chat before approval: on a comment's own page, or with a selection, that comment becomes the thread root. The story's title and link sit above it for context. Parent comments are not fetched.

Concepts touched: proposed **ported thread** (`docs/concepts/ported_thread.md`, created in Stage 1); [post presentation](../../concepts/post_presentation.md) (changed, Stages 2 and 6); [feature flags](../../concepts/feature_flags.md) (changed, Stage 4); [staged submission](../../concepts/staged_submission.md) (model for the held-port folder, unchanged).

## Completion Contract

- Normal entry: the Hacker News bookmarklet from Tools → Bookmarklets, tapped on a Hacker News story page, a comment's page or a selection.
- End-to-end outcome: the preview, then Port, opens a nested `#ported` thread. Every comment links to Hacker News. A Quill reply to a nested comment nests under it.
- Recovery: an unreadable page shows a message on Hacker News and sends nothing. A failed Port keeps the held port with the error and a retry button; nothing half-written remains. A repeat port opens the existing thread.
- Deployment: Ilya deploys and turns on `FORUM_THREAD_PORT_ENABLED` on zenmemes.com. The read model rebuilds for the schema change.
- Release: Ilya ports a real Hacker News thread from his iPhone (Stage 8).

## Key Risks

- **Resolved during planning: the Hacker News page policy.** Hacker News sends `default-src 'self'` and no `form-action` rule, so a form sent to ZenMemes is allowed. Inline script is allowed too. Stage 8 confirms this on a real iPhone.
- **High risk: the session cookie is missing on the hand-off.** ZenMemes sets its session cookie `SameSite=Lax`, so the cross-site form `POST` arrives signed out. Mitigation: `POST /port` only stores the port file, then redirects to `GET /port/<id>`. A top-level `GET` carries the cookie, so the preview knows who is viewing.
- **High risk: data.** A wrong `Parent-ID` breaks the tree in Git forever. Mitigation: the server checks that every parent exists in the same port file before writing anything (Stage 3), and the saved-page test checks every pair (Stage 5).
- **Slow write.** Mitigation: one commit and one read-model update for the whole port (Stage 3), timed on 300 comments.
- **Hacker News markup changes.** Mitigation: a saved Hacker News page as the test page. The reader refuses a page with comments it cannot place.

## Stage 1

**Estimate: 0.12**

- Goal: reply and root records accept the `Ported-*` headers, and the read model stores them.
- Dependencies: none.
- Expected changes: `PostRecordParser` reads `Ported-From`, `Ported-Author`, `Ported-Created-At` and `Ported-By`. All four must appear together, and `Ported-From` must be an `https://news.ycombinator.com/item?id=` address. The read model gets ported-source columns on posts. `ReadModelMetadata::SCHEMA_VERSION` goes up and `docs/specs/read_model_schema_v1.md` is updated. The concept entry `ported_thread.md` is created.
- Verification approach: parser tests for valid, partial and wrong-site headers; the CONTRIBUTING rebuild test from the prior schema version.
- Estimate details: driver: the schema change and its rebuild test; uncertainty: low.
- Risks or open questions: none beyond the Key Risks.
- Canonical components/API contracts touched: `PostRecordParser`, `ReadModelBuilder`, `ReadModelMetadata`. All extended; no new parser.

## Stage 2

**Estimate: 0.10**

- Goal: a ported post shows "name on Hacker News", linked to the original, and never counts as a ZenMemes author.
- Dependencies: Stage 1.
- Expected changes: the shared author metadata in `TemplateRenderer` shows the ported author with the source link. Profile, Recent author lists and signal actors skip ported posts as authorship. `post_presentation.md` gains a Ported line.
- Verification approach: render tests for a ported reply on the thread page and on Recent; a profile test showing a ported post under an existing username is not counted.
- Estimate details: driver: finding every place that counts authorship; uncertainty: medium.
- Risks or open questions:
  - Impact: a missed place would show "dang" as a ZenMemes user.
  - Early warning / validation: search for every read of the author label before editing.
  - Mitigation: one helper that every place asks.
- Canonical components/API contracts touched: `TemplateRenderer` author metadata, `SignalActors`, `RecentPostRepository`. All extended.

## Stage 3

**Estimate: 0.18**

- Goal: one call writes a whole ported thread in one commit.
- Dependencies: Stage 1.
- Expected changes: a plain-text port file format (`Key: value` blocks, one per post), described in the concept entry. A port file reader checks it. `LocalWriteService::createPortedThread(portFile, porter): result` writes the root and replies, adds `#ported`, makes one commit and runs one read-model update. A port whose root `Ported-From` already exists returns that thread instead.
- Verification approach: write tests for 3 and 300 posts (one commit each time, every `Parent-ID` correct, under a time limit); a repeat port; a file with a missing parent, refused before any write.
- Estimate details: driver: a batch path through the existing commit and read-model update; uncertainty: medium.
- Risks or open questions:
  - Impact: the incremental read-model update may assume one post per call.
  - Early warning / validation: read `synchronizePostDerivedState` first.
  - Mitigation: call it once per record inside one commit, if a batch form doesn't fit.
- Canonical components/API contracts touched: `LocalWriteService` commit and read-model update, reused. The port file format is new; the existing records don't describe a tree of posts that don't exist yet.

## Stage 4

**Estimate: 0.15**

- Goal: the hand-off and preview: `POST /port`, then `GET /port/<id>`, then Port.
- Dependencies: Stage 3.
- Expected changes: `POST /port` stores the port file in a private held-port folder, outside Git and exports, and answers 303 to `/port/<id>`. The preview shows the title, comment count and Port. Port writes and goes to the thread. Errors keep the preview with a retry button. Two flags go in `FeatureFlagRegistry`: `FORUM_THREAD_PORT_ENABLED` and `FORUM_THREAD_PORT_GUESTS_ENABLED`. Approved members can Port. Guests can only when the guest flag is on, and their port stays held. Held ports older than 7 days are deleted.
- Verification approach: controller tests for the redirect, signed-in Port, the guest case with each flag, a failed write and retry, and the flag off.
- Estimate details: driver: the held-port folder and the permission cases; uncertainty: low.
- Risks or open questions: the cookie risk in the Key Risks; this stage's redirect is its mitigation.
- Canonical components/API contracts touched: the `StagedSubmissionStore` layout as a model; `FeatureFlagRegistry`; the existing page layout. The held-port folder is new because the staged store holds only signed posts.

## Stage 5

**Estimate: 0.15**

- Goal: the Hacker News bookmarklet reads a story page, a comment page or a selection and sends the port file.
- Dependencies: Stage 4.
- Expected changes: a new kind `hn-port` in `tools_bookmarklets.js` and `ToolsPageController`. It uses the `indent` attribute, `.hnuser`, the `age` title and `.commtext`. A story page ports everything. A comment page or selection ports that comment and its replies, with the story title and link above. Links in comments are kept as text. On any other page, or a page it cannot read, it shows an alert and sends nothing. Same-window and new-window forms are both listed.
- Verification approach: a Playwright check in `tests/browser/` on a saved Hacker News page for all three cases, checking every parent pair and the count. It also checks the refusal on a page that isn't Hacker News.
- Estimate details: driver: tree reading from indent levels and the selection case; uncertainty: medium.
- Risks or open questions: markup change, as in the Key Risks.
- Canonical components/API contracts touched: `tools_bookmarklets.js` (`bookmarkletScript`, `hostPrefix`, copy code reused), `bookmarklets.php`, `ToolsPageController`. All extended.

## Stage 6

**Estimate: 0.12**

- Goal: the thread page shows a ported thread as a tree, with native replies nested under their parent.
- Dependencies: Stage 2.
- Expected changes: `thread.php` uses the reply tree for ported threads. The tree logic moves from `ForteBoardController::buildReplyTree` to one shared place that both use. The card markup stays `post_card.php`. Deep levels stop indenting past a fixed depth so phones stay readable. Other threads stay flat.
- Verification approach: render tests for nesting and a native reply under a ported comment; the existing Forte board tests; a 380px check in Gloss and Bubblegum.
- Estimate details: driver: tree display inside the thread page's existing card and continuation logic; uncertainty: medium.
- Risks or open questions:
  - Impact: continuation runs and the activity drawer assume a flat list.
  - Early warning / validation: render a ported thread that also has hashtag-only replies.
  - Mitigation: those features keep working on each branch's list.
- Canonical components/API contracts touched: `buildReplyTree` (moved and shared), `post_card.php` (reused), `thread.php` (extended). No new card markup.

## Stage 7

**Estimate: 0.08**

- Goal: members can find and port held guest ports, and agents learn that `#ported` threads invite them.
- Dependencies: Stages 4 and 6.
- Expected changes: a held-ports list on Tools for approved members, shown only with the guest flag on. `LlmsTxtRenderer` adds one line about `#ported`. `docs/reference/agent_api.md` notes that a Quill reply's parent can be any ported comment.
- Verification approach: a Tools page test; an `llms.txt` test.
- Estimate details: driver: one small list page; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: Tools launcher and `tools_nav_card.php` (reused), `LlmsTxtRenderer` (extended), plus nav icons in every icon set if the list gets a Tools button.

## Stage 8

**Estimate: 0.08**

- Goal: replay the known environment and close out.
- Dependencies: Stages 1–7, deployed by Ilya.
- Expected changes: none, unless the replay finds a fault. The Step 4 summary records the evidence.
- Verification approach: Ilya ports a real Hacker News thread from iPhone Safari (same-window bookmarklet) in three taps or fewer. Claude replies as "claude" with Quill to a nested comment and checks where it lands. Smoke set plus changed tests are run; the full suite is not run. The final diff review checks scope.
- Estimate details: driver: waiting on deploy; uncertainty: low.
- Risks or open questions:
  - Impact: iPhone Safari may handle the bookmarklet or the redirect differently than desktop.
  - Early warning / validation: this stage is the validation.
  - Mitigation: a fix goes in a follow-up commit on this branch.
- Canonical components/API contracts touched: `quill.py` (used unchanged).

## Study snapshots

- 2026-11-19 UTC, Step 3 handoff: **0.95** projected total, up from 0.90 by new understanding. The session-cookie redirect and the tree move out of `ForteBoardController` were not counted before. Claude, single exposed rater; baseline `7d9b4b4d`. Awaiting `Approved Step 3`.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*
