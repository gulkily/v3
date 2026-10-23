# Shared pagination checklist

Track a shared pagination component, its first use in standard Activity, and later migrations across the app. The default interaction is bounded pages with Previous/Next navigation; numbered jumps are optional where useful. List-specific queries can retain their ordering, security and consistency requirements while sharing navigation and recovery behavior.

The user agreed to this direction. Implementation proceeds through the [Activity pagination Railspike plan](./activity_feed_pagination/activity_feed_pagination_step1_solution_assessment.md); Steps 1–3 were approved for the standard-only scope; implementation and local verification are complete, with maintainer acceptance pending. This checklist tracks the broader work; later migrations require separate approval.

## Agreed direction

- [x] Establish reusable pagination through standard Activity first.
- [x] Use bounded Previous/Next pages as the default interaction, with optional numbered jumps.
- [x] Keep Forte unchanged, including its append behavior and Commits; it is an explicit exception.
- [x] Track other list migrations separately from the first Activity delivery.

## Planning and shared contract

- [x] Revise Activity Step 1 to compare approaches for a reusable component and define the first delivery boundary.
- [x] Complete the required Railspike reviews and approvals before implementation.
- [x] Define a shared page result and navigation contract: items, available directions, position, links and optional totals/page numbers. Avoid requiring expensive totals or arbitrary jumps for every list.
- [x] Choose the shared server-rendered controls and browser behavior; document which logic is shared and which stays in each list's data adapter.
- [x] Define URL and browser-history behavior, preserving filters and sorting and resetting position when they change.
- [x] Define handling for invalid/stale positions, empty results, deleted items, equal sort values and new arrivals between pages.
- [x] Define native failed-navigation recovery through Reload/Back and invalid-position restart. Enhanced navigation, if introduced separately, must retain the current page until success and discard stale responses.
- [ ] Decide which existing append/prepend flows should adopt bounded pages and which need an explicit exception. Record reasons rather than forcing message history into an unsuitable interaction.
- [x] Preserve permissions and privacy boundaries in queries, counts, links and browser caches.

## First delivery through standard Activity

- [x] Provide Previous/Next navigation in standard Activity using the shared component.
- [x] Verify shared-service compatibility leaves Forte append, selection, detail, APIs, preloads and Commits unchanged.
- [x] Add bounded standard Activity pages; preserve Forte growing-list behavior.
- [x] Preserve stable item identity and tied-sort ordering so navigation does not omit or duplicate entries across rebuilds and incremental updates.
- [x] Preserve existing count contracts; any future displayed page count must remain distinct from accessible total. Standard Activity introduces no new count display.
- [x] Preserve source, signature and commit details, including the recently fixed per-record like contributions.
- [x] Keep RSS as a recent-items feed; leave home/board previews and broader repository-coverage work outside this delivery.
- [x] Verify normal entry, first/middle/last/empty pages, direct links, Back/Forward, refresh, filter/sort changes and invalid/stale positions.
- [x] Verify phone/desktop, affected standard themes, keyboard access, loading/failure/retry, expanded details and long-value overflow.
- [x] Verify dynamic and static/offline serving where applicable; record unsupported combinations or recovery limits.
- [x] Publish `docs/reference/pagination.md`, linked from `CONTRIBUTING.md`, explaining the delivered shared API, list adapters and a worked standard Activity example.
- [x] Include guidance on stable ordering/positions, URL/category state, access/cache boundaries, retry/restart, required tests and explicit exceptions such as unchanged Forte append behavior; verify examples and commands against the implementation.
- [x] Record the shared API, tests and remaining migration work in the implementation summary.

## Later migrations

Each migration needs its own scoped review and verification. Mark complete only after shared behavior is in use and superseded code is removed or an exception is recorded.

- [ ] **QDB boards and search:** retain existing numbered navigation and URL compatibility while adopting shared controls/state where appropriate. Current entry points: [QdbBoardPolicy](../../src/ForumRewrite/Qdb/QdbBoardPolicy.php), [BoardViewOptions](../../src/ForumRewrite/Http/BoardViewOptions.php), [navigation template](../../templates/partials/board_pagination_nav.php).
- [ ] **Docs browser:** reconcile duplicated server/browser paging and preserve filters, direct links and static browsing. Current entry points: [controller](../../src/ForumRewrite/Http/PlatformDocsController.php), [browser implementation](../../public/assets/platform_docs.js).
- [ ] **SQLite viewer:** share navigation/state across table previews and query results while retaining local SQL execution and sorting. Current implementation: [sqlite_viewer.js](../../public/assets/sqlite_viewer.js).
- [ ] **Message conversation list:** preserve authenticated snapshot cursors, encrypted previews and restart/retry behavior. Current entry points: [store](../../src/ForumRewrite/Messaging/PrivateMessageStore.php), [list browser code](../../public/assets/private_message_list.js).
- [ ] **Message history:** evaluate older-message prepending, scroll preservation, chronological display and snapshot consistency; record any justified interaction exception. Current entry points: [store](../../src/ForumRewrite/Messaging/PrivateMessageStore.php), [conversation browser code](../../public/assets/private_message_conversation.js).
- [ ] **Account key history:** preserve account scope and approval/access rules while replacing feature-specific offset navigation where appropriate. Current entry points: [ApprovalHistory](../../src/ForumRewrite/ReadModel/ApprovalHistory.php), [history template](../../templates/partials/approval_history.php).
- [ ] **LLM exchange listings:** assess backend offset support when adding or changing a paging UI; do not count it as an existing user-facing paginator. Current implementation: [SqliteLlmExchangeStore](../../src/ForumRewrite/Llm/SqliteLlmExchangeStore.php).

## Consolidation completion

- [ ] Maintain an inventory of migrated lists and explicit exceptions, including any newly discovered paging paths.
- [ ] Remove superseded navigation, URL, loading and recovery helpers after each migration; retain necessary list-specific queries.
- [ ] Put shared behavior checks at the component level, with list-specific checks for ordering, eligibility, consistency and recovery.
- [ ] Update affected user/operator documentation and record verification limits in each migration's implementation document.

## Inventory basis

Repository review at `e507cb71` identified seven user-facing pagination systems: QDB, Docs, SQLite viewer, Forte Activity/Commits, message conversation list, message history and account key history. Several contain separate paging paths internally. Standard Activity currently has a first-batch cutoff rather than navigation to older entries. The initial delivery adds reusable standard Activity navigation. Forte is an explicit unchanged exception; other systems remain separately scoped follow-ups.
