# MIT Rap Club: About page copy

Cycle: `v3/rapclub_about_copy`; Mode: one-shot; Record/index: this file, [plans index](../README.md). Follows [socials-only About](../rapclub_about_dark/rapclub_about_dark_one_shot.md) (PR #389, merged).

Debug mode: enabled (project marker `.railspike_debug_on`); [debug log](./rapclub_about_copy_debug_log.md)

Effort study: `railspike-effort`, protocol P2, reference R1; [cycle ledger](../effort_ledger/rapclub_about_copy.md)

Extended estimates: disabled

**Cycle estimate: 0.15 (intake)**

Status: planned; next permitted action: implement.

## Original request

2026-11-23/25, in chat. After the socials-only About page: "it looks really bare with just that, it needs some text, but the text before was slop... help?" Claude drafted copy and asked for facts. Reply: "we meet fridays at 6pm, join the facebook messenger chat for the location. the show is now listed in the calendar on zenmemes, pull it from there (also in our calendar on mitrapclub.com) rap_club_exec@mit.edu please pull from the repo before doing anything else", then "also non-mit people are welcome too".

Feedback items: none match (searched `docs/feedback/items/` for rap club, mitrapclub).

## Authorization

The user supplied the facts for the drafted copy and asked for it to be built; carried to an open PR. Excluded: other profiles, an automatic upcoming-events list (not requested; offered as a follow-up), merge and deployment.

## Outcome and work type

Application change. On `mitrapclub`, `/about/` shows, above Find us: one line on what the club does and that everyone is welcome; weekly cyphers Fridays 6 pm with the Messenger chat for location; the next show, The Underground Showcase, Fri Dec 4, 6–9 pm, City Arena (W41-1306), linked to its calendar thread `thread-20261117053927-61637885` (the thread says the venue may move); contact `rap_club_exec@mit.edu`.

Show facts taken from the club calendar on mitrapclub.com, 2026-11-25.

Allowed paths: `ProfilePresentationContent`, `PresentationSlotRegistry`, `SiteProfileRegistry`, `templates/pages/about.php`, affected tests, this folder, plans index and ledger.

## Approach and risks

The `socials` About variant becomes `short`: profile copy paragraphs plus Find us. The paragraphs are trusted inline HTML in the private editorial const, the same contract the template already documents for graph/participation/portable. Risk: the show line goes stale after Dec 4. Mitigation: it links to the thread; reported to the user with the automatic option.

## Reuse decisions

`about` slot, `about-section` markup and `about.css`; editorial const for copy. No new component.

## Completion conditions

`/about/` on `mitrapclub` shows the copy and Find us; other profiles unchanged. Release condition: an open PR.

Concepts touched: none.

## Verification and recovery plan

`./v3 test --smoke ProfilePresentationContentTest PresentationSlotRegistryTest SiteProfileRegistryTest`; render `/about/` with `FORUM_SITE_ID=mitrapclub` and check the HTML. Full suite not run. Recovery: revert the commit.

Starting revisions and branch: `origin/main` `b926c9a9f`; branch `feat/rapclub-about-copy`.

Planning commit: pending.

## Dated execution history

- 2026-11-25: pulled `main` (`b926c9a9f`) first, as asked; record written.

## Study history

- 2026-11-25 UTC, intake: **0.15**. Claude, single exposed rater.

Attribution: Claude · 2026-11-25 UTC · Model: claude-opus-5-5
