> **Feature plan:** [Step 1](./personal_inbox_step1_solution_assessment.md) · [Step 2](./personal_inbox_step2_feature_description.md) · [Step 3](./personal_inbox_step3_development_plan.md) · [Step 4](./personal_inbox_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/personal_inbox`; [host ledger](../effort_estimation_trial.md#cycle-v3personal_inbox). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.85** (revised; Step 1 0.70, Step 2 0.80)

Concepts touched: recent conversation (changed), navigation slots (changed), viewer session (used), private message history (used). New entry: **caught-up mark**. Stage 8 updates the inventory.

## Completion Contract

- Normal entry: with `FORUM_INBOX_ENABLED` on, the Inbox link on the bar or phone tab bar.
- End-to-end outcome: a reply to my post raises the Inbox count on my other browser after a refresh. It appears under Replies with a link to the reply, and disappears from both browsers after Caught up.
- Required recovery: a failed Caught up keeps the items and shows Retry. A signed-out viewer gets the existing sign-in prompt. If the DM store fails, Replies and New posts still render. Flag off gives "not found" and today's navigation.
- Deployment: one read-model schema bump, so the production rebuild runs on deploy. The private mark database is created on first use. No new dependency.
- Release condition: smoke and changed tests pass, including the flag registry checks and the rebuild-from-v50 test. The two-browser journey is checked by hand with JavaScript on and off, and the Inbox icon in Word97, Chicago and Midnight. Full suite not run by the agent.

## Key Risks

- **High risk:** **Backdated replies (data).** Resolved during planning. Signed posts must carry a time within 10 minutes of arrival, and server writes use the server clock. A mark therefore keeps the newest acknowledged time plus the IDs it acknowledged in the 10 minutes before it. A late post inside that window stays unread unless its ID was acknowledged. Instance imports can still add older posts below a mark; they count as history, not news.
- **High risk:** **Messages moves (usability).** Members used to Messages on the bar must find it in the You menu. Validation: the maintainer checks the nav at the end of Stage 5. Mitigation: Inbox lists unread conversations first, with a link to all of Messages.
- **Large sections.** A member returning after a week may have hundreds of new posts. Mitigation: each section shows its oldest 50 unread items. Caught up acknowledges exactly those, and the next 50 appear.
- **Mark store rebuilds.** Marks hold post IDs and times, not read-model row numbers, so a read-model rebuild leaves them valid.

## Stage 1

**Estimate: 0.12**

- Goal: behind the new flag, `/inbox` shows signed-in members their unread conversations.
- Dependencies: none.
- Expected changes: register `FORUM_INBOX_ENABLED` ("Inbox", default off, Forum experience group, appended). Add the `/inbox` route and an Inbox controller and page. Flag off answers "not found". Signed-out or unapproved viewers get the existing access template. Add `PrivateMessageMailboxService::unreadConversations(viewer, limit)`, which lists counterparts with unread messages. With private messages off or failing, the section is omitted or shows Retry.
- Verification approach: registry completeness, order and dependency tests; controller tests for flag off, signed out, approved with and without unread conversations, and DM store failure.
- Estimate details: driver: new route and access gating; uncertainty: low.
- Risks or open questions: the existing unread state takes a counterpart list, so listing unread counterparts is a new bounded query on the DM read state.
- Canonical components/API contracts touched: `FeatureFlagRegistry`, `PrivateMessageMailboxService`, `PrivateMessageReadState` (read only), `templates/pages/private_message_access.php`, `partials/private_message_conversation_row.php`. New controller and page, because no page combines personal sections.

## Stage 2

**Estimate: 0.06**

- Goal: the read model finds replies by parent quickly.
- Dependencies: none.
- Expected changes: add an index on a post's parent; bump `SCHEMA_VERSION` to 51; update `docs/specs/read_model_schema_v1.md`.
- Verification approach: a regression test that opens a v50 read model and upgrades it; `./v3 rebuild` on a local copy.
- Estimate details: driver: CONTRIBUTING's schema-change checklist; uncertainty: low.
- Risks or open questions: none beyond the required rebuild on deploy.
- Canonical components/API contracts touched: `ReadModelSchema`, `ReadModelMetadata`, the read-model schema spec.

## Stage 3

**Estimate: 0.14**

- Goal: Inbox shows a Replies section with a working Caught up button.
- Dependencies: Stages 1 and 2.
- Expected changes: a private mark store in `state/private/`, modeled on `PrivateMessageReadState`. It keeps, per username and section, a baseline, a mark time and the IDs acknowledged in the 10 minutes before it. Planned methods: `unread(viewer, section, keys, limit)`, `catchUp(viewer, section, shownIds)`. Replies are visible posts whose parent was written by any approved key of my username, excluding my own. Caught up is an ordinary form post with the shown IDs and a same-origin check. Failure re-renders the items with Retry.
- Verification approach: store tests for baseline, forward-only marks, the 10-minute window, shown-IDs-only acknowledgment and two keys of one username; controller tests for the form and its failure path.
- Estimate details: driver: the acknowledgment window; uncertainty: medium.
- Risks or open questions:
  - Impact: a forged form could acknowledge another member's replies.
  - Early warning / validation: a test posting without a session or from another origin.
  - Mitigation: reuse the session check and the same-origin rule from `/api/private_messages/read`.
- Canonical components/API contracts touched: `ApprovedUserKeyResolver`, `ViewerSessions`, the Recent row partial (inspected for reuse as Inbox rows). New mark store, because DM marks are per conversation.

## Stage 4

**Estimate: 0.06**

- Goal: Inbox shows a New posts section below Replies, with its own Caught up.
- Dependencies: Stage 3.
- Expected changes: a second section in the same store. New posts are visible posts newer than its mark, excluding mine and anything already listed under Replies.
- Verification approach: controller tests for ordering, exclusion of replies and own posts, and independent marks per section.
- Estimate details: driver: reuse of Stage 3; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: the Stage 3 store and rows.

## Stage 5

**Estimate: 0.10**

- Goal: with the flag on, Inbox takes Messages' place on the bar, and Messages moves into the You menu.
- Dependencies: Stage 1.
- Expected changes: `TemplateRenderer::navItems()` adds Inbox and moves Messages between `$barOrder` and `$menuSections` by flag. The navigation refresh endpoint carries the change. Add Inbox glyphs to `theme-nav-icons.css` and a phone slot rule.
- Verification approach: renderer tests for both flag states; nav refresh test; manual look at the icon in Word97, Chicago and Midnight, desktop and phone. Maintainer reviews the placement here.
- Estimate details: driver: three icon themes and phone slots; uncertainty: medium.
- Risks or open questions: see Messages moves in Key Risks.
- Canonical components/API contracts touched: `TemplateRenderer::navItems()`, `nav.php`, `app_header.php`, `/api/feature_flags/navigation`, `feature_flags.js`, `theme-nav-icons.css`.

## Stage 6

**Estimate: 0.08**

- Goal: the Inbox link shows unread conversations plus unread replies.
- Dependencies: Stages 3 and 5.
- Expected changes: `GET /api/inbox/unread`, no-store, returning the unread reply count. Extend `private_message_unread.js` so the Inbox link and its mirrors show the sum, with the existing refresh triggers. With private messages off, the sum is replies only.
- Verification approach: API tests; extend `tests/browser/nav_unread_outline.mjs` for the Inbox link.
- Estimate details: driver: combining two counts in one script; uncertainty: low.
- Risks or open questions: the script now loads wherever Inbox is shown, not only for messaging; check it stays silent for signed-out viewers.
- Canonical components/API contracts touched: `private_message_unread.js`, `nav.php` and `app_header.php` mirrors, `/api/private_messages/unread` (unchanged).

## Stage 7

**Estimate: 0.05**

- Goal: Inbox works without JavaScript and recovers from failures.
- Dependencies: Stages 3–6.
- Expected changes: fixes found while walking the journey with JavaScript off, a failing store and the flag toggled mid-visit.
- Verification approach: manual two-browser journey; record each case in the Step 4 summary.
- Estimate details: driver: manual checks; uncertainty: medium.
- Risks or open questions: none new.
- Canonical components/API contracts touched: as in Stages 1–6.

## Stage 8

**Estimate: 0.06**

- Goal: documentation matches the shipped feature.
- Dependencies: Stages 1–7.
- Expected changes: add the caught-up mark concept; update recent conversation, navigation slots and `docs/architecture/private_messaging.md` (Inbox reads its unread state); note the feature in v3 feature priorities; update PF-20261030-F05 notes; ledger closeout.
- Verification approach: link check and `git diff --check`.
- Estimate details: driver: several docs; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `docs/concepts/`, `docs/architecture/private_messaging.md`, `docs/feedback/items/`.

## Study checkpoint

2026-11-07T12:05:17Z, `railspike-effort` / `v3/personal_inbox`, Step 3 handoff. Step 2 (scope v2) explicitly approved. Starting revision `f343b6269a3b786e4ebe1ab383096f2109461933`; reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Projected total 0.80 → **0.85**, from new understanding, not changed scope: backdated posts need a 10-minute acknowledgment window, and listing unread conversations needs a new DM query. Stage-work originals 0.12/0.06/0.14/0.06/0.10/0.08/0.05/0.06 are separate annotations and do not add up to the cycle total. No implementation, measured labor or accepted outcome.

## Enhancement Requests

Carried, all not approved and backlog only: follow and unfollow threads, @mentions, email digest or web push, and scrolling to the first new reply inside a thread.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
