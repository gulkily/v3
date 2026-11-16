> **Feature plan:** [Step 1](./pledge_campaigns_step1_solution_assessment.md) · [Step 2](./pledge_campaigns_step2_feature_description.md) · [Step 3](./pledge_campaigns_step3_development_plan.md) · [Step 4](./pledge_campaigns_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_campaigns_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_campaigns`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Steps 1 and 2 approved 2026-11-16. Step 3 approved 2026-11-16 by Claude under the maintainer's one-time delegation ([record](./pledge_campaigns_step2_feature_description.md#delegated-approval)).

**Cycle estimate: 0.50**

# Step 3: Development Plan — Pledge campaigns

## Completion Contract

- Entry: the New post page for the `goal:` line; the reply form on the campaign thread for a pledge.
- End-to-end outcome: an author posts a campaign, a second person pledges from the reply form, and the thread shows the amount and the new sum.
- Recovery: a refused pledge keeps its number and text in the form. An uncounted pledge shows why. Turning `FORUM_PLEDGES_ENABLED` off removes every block and field.
- Deployment: no schema change, no rebuild, no new dependency. The flag is off by default.
- Release condition: with the flag on at zenmemes.com, Ilya posts a campaign and pledges from his iPhone SE. This cannot be done from this session and stays pending in Step 4.

## Refinements to Step 2, within its scope

- **One amount field, no Pledge/Reply switch.** The reply form on a campaign gains one optional amount field above the body. Empty means an ordinary reply. This works without JavaScript and removes a control. The body is the note.
- **The "not counted" reason is shown to every reader.** Thread pages are the same for every reader on first paint. Step 2 named this fallback.
- **"Unverified" follows the Verified attribution flag as it is now**, through the `attribution_counted` value each thread post already carries.
- **Paned layouts and the separate Reply page** show the lines as text and get no amount field in this cycle.

## Key Risks

- **Static thread pages show an old sum.** Impact: a pledge posts and the total does not move. Early validation: Stage 2 bakes a thread, replies, and bakes again. Mitigation: a reply already drops the baked page.
- **The amount field breaks a composer route.** Impact: signed, Outbox or no-JavaScript replies fail on campaigns. Early validation: Stage 3 posts by the form path and checks the script only edits the body field. Mitigation: the field writes one line into the body the composer already sends.
- **The block is unreadable in a theme.** Impact: a hidden bar or sum. Early validation: Stage 4 renders every theme at phone and desktop width. Mitigation: only existing theme variables are used.

## Stage 1

**Estimate: 0.10**

- Goal: read `goal:` and `pledge:` lines and numbers; add the flag.
- Dependencies: none.
- Expected changes: `Support/GoalDirective::extract(string $body): ?array` (amount in hundredths, amount as written, unit). `Support/PledgeDirective::extract(string $body): ?string` (text after `pledge:`). `Support/PledgeAmount::parse(string $text): ?int` and `format(int $hundredths): string`. `FeatureFlagRegistry::PLEDGES_ENABLED`, off by default, in the Forum experience group.
- Verification approach: a new `PledgeCampaignTest` covers whole numbers, decimals, grouping commas, zero, signs, words, three decimal places, quoted lines and first-match. `FeatureFlagEvaluatorTest` lists the flag.
- Estimate details: driver: number rules; uncertainty: the flag lists kept in tests.
- Risks or open questions:
  - Impact: a number rule surprises a pledger. Early warning: the test table reads as a specification. Mitigation: uncounted lines show a reason in Stage 2.
- Canonical components/API contracts touched: directive readers (`ThemeDirective.php` pattern, reused); `FeatureFlagRegistry.php` (extended).

## Stage 2

**Estimate: 0.18**

- Goal: a campaign thread shows its totals block, and each pledge shows its amount or why it is not counted.
- Dependencies: Stage 1.
- Expected changes: `View/PledgeCampaign::fromPosts(array $posts, string $rootPostId): ?array` derives goal, unit, sum, count and each reply's state from the rows the thread page already loads. `ThreadAndPostPageController` passes the result when the flag is on. New partial `pledge_totals.php` under the opening post, beside the event block. `post_card.php` gains one amount line. Styles in `site.css` from existing theme variables. No query and no stored value is added.
- Verification approach: `PledgeCampaignTest` renders a thread with counted, unverified, unnamed, unreadable, wrong-unit and retracted pledges; checks the sum, the bar, flag-off output, and a baked page before and after a reply.
- Estimate details: driver: states on the reply card; uncertainty: the bar past the goal.
- Risks or open questions:
  - Impact: static page shows an old sum. Early warning: the baked-page test. Mitigation: existing invalidation on reply.
- Canonical components/API contracts touched: `ThreadRepository` thread rows (used unchanged); `thread_root_card.php` and `post_card.php` (extended); `VerifiedAttribution` result (used unchanged); `event_block.php` (placement followed).

## Stage 3

**Estimate: 0.14**

- Goal: a reader pledges from the reply form without typing the line.
- Dependencies: Stage 2.
- Expected changes: `reply_form.php` shows an amount field when the thread page passes the campaign unit. `ComposeAndAccountKeyController::submitComposeReply` writes the `pledge:` line into the body when the form sends an amount and the body has none. A small script, `pledge_field.js`, keeps the line in the body field in step with the amount, so signed and Outbox sending carry it unchanged.
- Verification approach: `PledgeCampaignTest` posts the form path with an amount and a note, with an amount alone, and with a bad amount, and checks the stored body and the redrawn form. A browser check posts a signed pledge if a browser is available in the session; otherwise it stays pending.
- Estimate details: driver: two routes into one body; uncertainty: order of submit handlers in `browser_signing.js`.
- Risks or open questions:
  - Impact: a double line when both script and server add it. Early warning: the form test sends both. Mitigation: the server adds a line only when the body has none.
- Canonical components/API contracts touched: [shared composer](../../concepts/shared_composer.md) (`reply_form.php` extended, `composer.php` gains one optional slot); `/compose/reply` form contract (one optional field added).

## Stage 4

**Estimate: 0.08**

- Goal: the feature is checked in every theme and documented.
- Dependencies: Stage 3.
- Expected changes: a `pledge_campaign` entry in `docs/concepts/`; lines in the shared composer and post presentation entries; the checklist, ledger and debug log; the Step 4 closeout.
- Verification approach: render the campaign thread in each theme at 375 and 1280 pixels wide and look at each; run the smoke set with `PledgeCampaignTest` and the tests for touched classes; `git diff --check`; review the whole diff.
- Estimate details: driver: 13 themes; uncertainty: whether a browser is available here.
- Risks or open questions:
  - Impact: a theme check cannot run. Early warning: no browser binary. Mitigation: report it unverified and list it for the maintainer.
- Canonical components/API contracts touched: concept inventory (extended).

Concepts touched: shared composer and post presentation, changed; thread title and page theme, used unchanged; pledge campaign, created in Stage 4.

## Study checkpoint

- Step 3 handoff: **0.50** projected total reaffirmed, scope v1 with the four refinements above (none widens scope). Stage work 0.10, 0.18, 0.14, 0.08, not summed. Baseline `9e5aa010ddff888248ca670c932e3b5f7ec69274`, R1, P2; Claude single exposed rater, 2026-11-16 UTC. R1 hashes not re-verified. Approval is delegated self-approval, so no independent review of this plan exists.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
