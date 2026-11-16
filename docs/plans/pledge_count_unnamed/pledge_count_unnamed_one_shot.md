Cycle: `v3/pledge_count_unnamed`; Mode: one-shot; Record/index: [this record](./pledge_count_unnamed_one_shot.md) · [plans index](../README.md) · [pledges checklist](../pledges_checklist.md)

Debug mode: enabled ([debug log](./pledge_count_unnamed_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_count_unnamed`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_campaigns`.

Extended estimates: disabled

**Cycle estimate: 0.08**

Status: technically complete on `feat/pledge-count-unnamed`; release check pending maintainer; next permitted action: maintainer review.

# Pledges with no name: a flag to count them

## Original request

Quoting the cycle 1 handoff ("A pledge posted without JavaScript is shown but not counted. Those posts have no key, so they have no name, and I had decided unnamed pledges stay out of the sum. I only saw this consequence during the browser run. Counting them is a one-line change."): "can this be a feature flag please"

## Authorization

Maintainer, 2026-11-16 UTC, the message above. Scope: one flag that decides whether pledges with no name count. Excluded: changing how unverified pledges are treated, giving no-JavaScript posts a name, and anything in cycles 2 to 7.

## Outcome and work type

Application change. A new flag, "Count pledges with no name" (`FORUM_PLEDGES_COUNT_UNNAMED_ENABLED`), in the Forum experience group, changeable from Tools → Feature flags, and available only while Pledges is on. Off, the default, keeps the merged behaviour: the pledge shows "Not counted: no name". On, a pledge with no name counts like any other and shows no note.

Default off because it is the behaviour already merged in PR #268; the maintainer chooses per instance.

## Approach and risks

`PledgeCampaign::fromPosts()` takes one more argument saying whether unnamed pledges count. The thread page controller passes the flag.

- Risk: the flag is on while Pledges is off. Impact: none, because no campaign is read. Mitigation: the flag is registered as requiring Pledges.
- Risk: a nameless post floods a campaign's sum. Impact: a wrong total on instances that turn the flag on. Mitigation: the flag's description says so; an operator can retract the post.

## Reuse decisions

- `src/ForumRewrite/View/PledgeCampaign.php`: extended; the one place that decides what counts.
- `FeatureFlagRegistry.php`: extended with one definition, using `requiresEnabledFlag` as List thumbnails does.
- `ThreadAndPostPageController.php`: the existing pledge wiring, extended.
- No template, style or script changes: `pledge_amount.php` already shows a counted pledge without a note.

## Completion conditions

- Entry: Tools → Feature flags.
- End to end: with the flag on, a pledge posted without JavaScript raises the sum on the thread page.
- Recovery: turning the flag off returns such pledges to "Not counted: no name"; nothing is stored, so no rebuild.
- Release: the maintainer turns the flag on at zenmemes.com and sees a no-name pledge counted. Not reachable from this session.

## Concepts touched

[Pledge campaign](../../concepts/pledge_campaign.md): changed (the Counting line).

## Verification and recovery plan

- `PledgeCampaignTest`: unnamed pledges with the flag off and on, in the totals reader and on the rendered thread page.
- `FeatureFlagEvaluatorTest`: the flag lists.
- `tests/browser/pledge_campaign.mjs` with the flag on: a no-JavaScript pledge counts.
- Smoke set. The full suite is not run.
- Toggling the flag in the Feature flags page in a browser.

## Starting revisions and branch

Branch `feat/pledge-count-unnamed` from `origin/main` at `9bdd4b39` (the merge of PR #268).

Planning commit: `a5a80600`, before any implementation edit.

## Dated execution history

- 2026-11-16: added `FeatureFlagRegistry::PLEDGES_COUNT_UNNAMED_ENABLED` ("Count pledges with no name", Forum experience, off by default, requires Pledges). `PledgeCampaign::fromPosts()` gained a third argument, `$countUnnamed`; `ThreadAndPostPageController` passes the flag. No template, style or script changed.
- 2026-11-16: `tests/browser/pledge_campaign.mjs` gained `--count-unnamed`, which runs with the flag on and skips the theme loop.
- 2026-11-16: the checklist's cycle 1 entry now records the merge of PR #268 and this flag. Its log section was left alone, because `plan/pledge-withdraw` edits the same lines.
- Maintainer question during the work ("is there a UI for verifying etc yet? does that involve tagsets?"): answered in the handoff; no scope change.

## Outcome

Delivered as planned. With the flag off nothing changes. With it on, a pledge whose post has no name counts and shows no note; unverified pledges are unaffected.

## Verification

- `./v3 test --smoke PledgeCampaignTest FeatureFlagEvaluatorTest`: passed, 475 of 475. The full suite was not run.
- `./v3 test FeatureFlagChangePageTest`: passed.
- `node tests/browser/pledge_campaign.mjs --count-unnamed`: passed. A pledge posted without JavaScript raised the sum from 1300 to 1312.5 and showed as counted.
- Not done: toggling the flag on the Feature flags page in a browser. The flag is registered the same way as the others on that page, and the page's own test passed, but the click itself was not exercised.
- Not done: the release check on zenmemes.com.

## Final review

Diff read: one flag definition, one argument, one controller call, tests, the browser check option, the concept line, the checklist entry and the ledger. No duplication; nothing outside the authorized scope.

## Commit, merge and publication status

Planning commit `a5a80600`. Completion commit pending in this record; see the PR. Not merged.

## Study history

- Intake: 0.08 projected total, scope v1, baseline `9bdd4b39`, R1, P2; Claude single exposed rater, 2026-11-16 UTC. The rater wrote cycle 1, so this is not a pristine intake. R1 hashes not re-verified.
- Execution entry: 0.08 reaffirmed.
- Closeout: retrospective scope assessment 0.07; no revisions. Maintainer comfortable-fit judgment pending.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
