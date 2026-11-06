# Forte Activity: per-kind left-pane filters — implementation summary

Workflow: Direct (small, single-surface change). Started from an external
investigation note ("Forte Activity: Missing Activity-Type Filters", 2026-11-06).
Related feedback: PF-20261030-F05 (asks for thread/reply filters among other
things; this change covers only that part on Forte).

## What changed

- `ActivityService::KIND_VIEWS` maps URL view keys to exact `activity.kind`
  values: `thread`, `reply`, `reaction` (`post_reaction_add`), `label`
  (`thread_label_add`), `profile_name` (`profile_name_change`), `identity_key`
  (`identity`), `invitation`, `feature_flag` (`site_feature_flag`),
  `codex_handoff`. `activityViews()` is the single list of activity-table views;
  normalization, the row partial's `data-paned-activity-view-*` flags, the
  Forte pagination endpoint and the counter schema all read it.
- Each kind view filters with `AND activity.kind = :view_kind`, so the existing
  keyset pagination (`activity_kind_key_idx` and the date index) and the
  profile-name visibility clause apply unchanged.
- Counts: `ActivityCounterSchema` adds one `activity_counts` row and trigger
  predicate per kind view. Every writer (full rebuild, incremental updater,
  local writes, Codex handoffs) goes through the same triggers, so rebuild and
  incremental totals cannot diverge. Schema 49 → 50; normal startup rebuilds.
- Forte left pane lists, in order: All Activity, Visible Content, Threads,
  Replies, Reactions, Label Changes, Profile Changes (only while
  `USERNAME_CHANGES_ENABLED` is on), Identity, Identity Keys, Bootstraps,
  Approvals, Invitations, Feature Flag Changes, Codex Handoffs, Commits.
  No markup, style or layout change: the existing filter-list partial renders
  the extra entries.

## Decisions

- No separate entries for `identity_bootstrap` and `approval` kinds: the
  existing tag-based Bootstraps and Approvals views already cover them, and a
  second "Bootstraps"/"Approvals" would be confusing duplicates.
- Plain `identity` kind is labelled "Identity Keys" so it does not collide with
  the broad "Identity" category.
- `?view=profile_name` while username changes are off falls back to All Activity
  on the Forte page; the pagination API returns no rows (the existing
  `record_family != 'profile_name'` clause still applies).
- Classic `/activity/` keeps its tab list. It accepts the new `?view=` keys
  because normalization is shared, which also makes the Forte RSS link work for
  every new entry.

## Verification

- `./v3 test --smoke ActivityProjectionTest StandardActivityPaginationTest
  ForteActivityReadModelRecoveryTest ActivityItemLinkTest
  LocalAppSmokeTest::testForteActivityOffersDefaultCategoriesAndEligibleRss`:
  450 passed. Full suite not run.
  - Counter consistency test now checks every view, including kind inserts and a
    kind-changing update; prior-schema startup test checks every kind total.
  - New `testForteKindViewsListFilterCountAndPageEachKind`: labels and counts,
    selected entry, only matching rows, profile-name hidden/no leak, cursor
    paging through all 45 reactions, unknown-view fallback.
- Browser (Chrome via playwright-core, fixture repository + seeded kinds) at
  1280 and 390 wide: clicking Threads and Reactions shows only those kinds;
  direct `?view=feature_flag` selects the entry and lists only flag changes;
  counts match the table. Layout unchanged; on phone the left pane now scrolls
  within its existing box (14–15 entries instead of 6).

## Limitations / follow-ups

- Not browser-checked with `USERNAME_CHANGES_ENABLED` on (covered only by the
  controller condition) and not checked in icon themes (no nav destination
  added).
- Opening the page now prefetches the first page of ~14 views instead of ~5
  (sequential, background). Watch Activity load time (PF-20261030-B20).
