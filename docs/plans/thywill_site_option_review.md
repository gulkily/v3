# Thywill feature support and gaps in v3

Reviewed 2026-10-15. Source revisions: v3 `ab3b1659d48bb996961ea44cc093aca600e0222a`; Thywill `19ca19d6b1e2cc9e450afd5fd3adcb7b5f9666cc`.

**v3 can host Thywill as another site profile, but reproducing Thywill requires a prayer application on top of the shared platform.** Branding, posting, profiles, invitations, approval, AI providers, and durable storage already have foundations. Repeat prayer events, prayer lifecycle, guided prayer, and Thywill's account recovery are substantial gaps. Adding a theme alone would produce a prayer-themed forum.

“New site option” here means an additional v3 deployment profile, alongside `zenmemes`, `chouse`, `qdb`, and `mitrapclub`. This is a source-level capability review, not a production configuration audit. Conditional Thywill features below are present in code but may be disabled in a running deployment. The unrelated untracked Thywill installation guide was excluded.

## Existing site support

[`SiteProfileRegistry`](../../src/ForumRewrite/SiteProfileRegistry.php) already selects a site using `FORUM_SITE_ID`, including its name, theme choices, browser storage namespace, composer prompt, editorial copy, and experience keys. Thywill is not registered; an unknown site ID currently falls back to Zenmemes.

[`PresentationSlotRegistry`](../../src/ForumRewrite/PresentationSlotRegistry.php) supports registered variants of navigation, cards, composition, about content, styles, and favicons. New Thywill variants must be added to those allowed choices. [`QdbExperience`](../../src/ForumRewrite/Qdb/QdbExperience.php) demonstrates that a site can have specialized routes and behavior while sharing v3 services. Experience keys alone do not implement those routes.

Site profiles separate presentation and browser namespaces; they do **not** automatically provision an independent community. A separate Thywill instance needs its own content repository, database, private state, and deployment configuration. [`PresentationPathResolver`](../../src/ForumRewrite/PresentationPathResolver.php) separates default static output by profile, while the [import runbook](../runbooks/instance_content_import.md) documents the independently configured repository/database paths.

## Feature comparison

**Supported** means the general capability exists now. **Partial** means useful infrastructure exists but Thywill behavior needs changes. **Add** means no equivalent application workflow was found. Evidence links point to the checked source or current behavior reference.

| Thywill capability | v3 today | Work needed for Thywill |
| --- | --- | --- |
| Site identity, responsive presentation, light/dark themes | **Supported foundation.** Site profiles, themes, registered presentation variants, editorial content, and favicons. [V1] | Register Thywill; add its branding, prayer vocabulary, navigation, cards, and composer. |
| Publish and read requests | **Partial.** Threads/posts provide authorship, timestamps, detail pages, feeds, and replies. Thywill stores both the original request and a generated prayer. [T1] [V2] | Preserve and present those two distinct pieces of content; provide the prayer submission flow and its access rules. Ordinary replies are available if wanted. |
| AI prayer preview and generation | **Partial.** Anthropic and OpenAI-compatible provider infrastructure and agent generation exist. Thywill previews a community-oriented prayer before saving and has a degraded fallback. [T1] [T2] [V3] | Add prayer prompts, preview/accept behavior, association with the original request, failure handling, and generation-specific persistence. Existing agent replies are a different workflow. |
| “I prayed” with repeated prayer history | **Add domain behavior.** v3 reactions deduplicate a tag from the same identity on the same target. Thywill deliberately permits repeated marks and tracks timestamps. [T3] [V4] | Record distinct prayer events; distinguish a retry from another intentional prayer; show total prayers, distinct people, personal totals, and who prayed. Renaming Like would lose behavior. |
| Prayer feeds and personal views | **Partial.** Boards, sorting, tags, profiles, and activity exist. Thywill has unprayed, most-prayed, personally prayed/unprayed, own requests, needs-attention, archived, and daily-prayer views. [T4] [V5] | Add prayer-based queries, filters, counts, and navigation. Most depend on prayer-event and lifecycle data. |
| Archive and restore a request | **Partial.** v3 has signed author/operator retraction and an operator archive command. Neither is Thywill's reversible author/admin prayer status. [T3] [V6] | Add author/admin archive and restore with consistent feed/detail visibility and activity history. `archive-thread` removes a component from the live repository; it is not an Archived Requests tab. |
| Answered prayers, testimony, celebration feed | **Add.** Labels/replies could carry text, but do not supply authorized transitions, answer dates, testimony semantics, or celebration views. [T3] [T4] [V4] | Add answered state, testimony, authorized actions, and celebration presentation. |
| Guided prayer mode | **Add.** Thywill ranks a short queue by age, prayer history, and skip history, and remembers progress. [T5] | Add a focused prayer session, queue selection, skip events, progress/resume, and completion behavior. |
| Daily reflection and daily priority | **Partial.** Shared editorial surfaces can display content; there is no equivalent prayer-priority workflow. Thywill rotates seven reflection texts; priority is optional. [T2] [T3] | Add rotating reflection copy and, if retained, administrator priority selection/removal and expiry behavior. |
| Prayer categories, specificity, safety indicators | **Partial.** Tags, LLM analysis, and Fastmod scoring exist, but their meanings differ from Thywill's optional categorization fields and filters. [T6] [V3] | Add the prayer taxonomy, classifications, badges/filters, and applicable fallback rules. A generic moderation score does not establish prayer safety or specificity parity. |
| Public prayer browsing with authenticated participation | **Partial.** v3 supports public browsing or a site-wide members-only gate; public mode also permits anonymous posting paths. Thywill exposes eligible prayer pages publicly and requires full authentication for submitting/marking. [T1] [T7] [V7] | Enforce Thywill participation rules at write endpoints and implement its public prayer selection. A private-site flag alone cannot reproduce the mixed public/member experience. |
| Invitations and QR links | **Supported core; partial parity.** v3 has signed issuance, expiry, revocation, redemption, approval propagation, and QR presentation. Thywill also has multi-use and device-login token semantics. [T8] [V8] | Reuse membership invitations; add multi-use behavior if required. Device-login tokens belong to the separate account-recovery gap. |
| Invitation ancestry and community growth | **Partial.** v3 retains issuer/recipient relationships and approval provenance, with user/activity views. [T8] [V8] | Add Thywill's invitation-tree visualization, generation/descendant statistics, and migration of historical ancestry. Approval provenance and invitation ancestry are related but not interchangeable. |
| Membership applications | **Add.** Thywill accepts an essay/contact, supports application status, and has approval/rejection handling that can issue an invitation. v3's pending-identity approval is a different flow. [T7] [V7] | Add application intake, private contact storage, review decisions, applicant status/recovery, and invitation handoff. |
| Access to an existing account on a new device | **Partial foundation; different identity model.** v3 authenticates browser-held OpenPGP keys and supports key backups/restoration. Thywill supports device links and self/admin/peer approval of login requests, with verification and audit state. [T9] [V7] | Either adopt v3 key restoration as an explicit product change, or design account/device linking and recovery that preserves identity without pretending peer approval supplies a missing private key. |
| Optional email verification and recovery | **Add.** Thywill has encrypted email storage in a separate database, verification, recovery links, and SMTP delivery behind `EMAIL_AUTH_ENABLED`. [T10] | Add private email storage and delivery/recovery workflows if this option must be preserved. Do not place email addresses or recovery secrets in public canonical records. |
| Community flagging and administrator unflagging | **Partial.** v3 has flags/scoring and signed operator retraction. Its post-reaction reducer automatically hides flagged `reply-agent` posts, not all human posts. Thywill hides flagged prayers and allows admin unflagging. [T11] [V4] [V6] | Add prayer quarantine/review/unflag behavior and consistent visibility across surfaces. Existing permanent/additive signals do not provide reversible moderation parity. |
| User administration and roles | **Partial.** v3 has approved identities and operator authority. Thywill has role assignments, admin grants, deactivation/reactivation, and login assistance. [T12] [V7] | Map retained roles and permissions; add account disable/reactivate and appropriate recovery controls where required. |
| Profiles and statistics | **Partial.** User profiles, authored content, activity, and visitor statistics exist. Thywill shows requests, total/unique prayers, prayer history, supporter status, and prayer statistics. [T13] [V5] | Add prayer aggregates and profile sections; keep visitor traffic and prayer participation metrics distinct. |
| Authentication notifications and audits | **Partial.** v3 has public activity/approval evidence and some feature-specific unread state. Thywill has pending-login notifications, read state, security logs, and bulk approval. [T9] | Add notifications and administrative audit views for any retained login/application workflow. Private-message unread state is not a general notification system. |
| Offline reading and queued actions | **Partial.** v3 has public snapshots and a signed outbox; offline reactions currently support Like. Thywill queues prayer marks, including the prayer timestamp. [T14] [V9] | Extend outbox behavior to repeatable prayer events, retry identity, reconciliation, and any guided-mode offline experience. Members-only v3 does not expose the public snapshot path. |
| Donations and supporter badges | **Partial presentation foundation.** Thywill has configured PayPal/Venmo links and manually assigned supporter types/badges. [T15] [V1] | Add donation copy/links, supporter fields, badges, and assignment controls. The reviewed feature does not require an integrated checkout or payment-webhook system. |
| Explanatory pages and change announcements | **Partial.** v3 has About, documentation, and version UI; Thywill adds How It Works, Nicene Creed, welcome dismissal, and a friendly changelog. [T15] [V1] | Add the desired content and small presentation/state features. A public version indicator is not Thywill's changelog. |
| Durable records, backups, and reconstruction | **Supported architectural approach; incompatible formats.** Both favor readable canonical/archive data and derived SQLite state. v3 adds Git history, signatures, rebuilds, and repository exports. [T16] [V10] | Extend v3 reconstruction/export coverage for prayer data. Thywill text archives are not accepted v3 records merely because both are text. |
| Migration of the existing community | **Add adapter.** v3 imports supported v3 repository records, not Thywill's Python models/text-archive format. [T16] [V10] | Convert requests, generated text, marks, statuses/testimonies, authors, ancestry, and timestamps; preserve legacy URLs and provenance. Re-enroll accounts deliberately; legacy sessions and private signing keys cannot be manufactured from usernames. |

## Important boundaries

**Visibility needs an explicit product decision.** Thywill's public service excludes flagged prayers and archived requests without an answered/praise-report attribute; archived answered requests can remain public. Its archive-route comment is therefore less precise than the actual public selection code. Do not assume all prayer content is private today. [T7]

In v3, content suppression is also distinct from erasure: retracted originals remain in Git history and whole-repository downloads. A Thywill archive or moderation label would not automatically protect content in source views, exports, static pages, offline snapshots, or LLM diagnostics. Any expectation that archived requests become author-only needs a policy applied across those surfaces, including a decision about repository-download access. [V6] [V10]

**README claims are not all current features.** Thywill's README advertises religious preferences, but the current `User` model lacks those fields, the active profile router has no religious-preference update endpoint, and prayer generation takes request text without a user preference argument. References remain in backup/import code and welcome copy. Treat personalized faith preferences as an optional new requirement, not established parity. [T2] [T12] [T13]

**Labels alone do not close the data gaps.** v3's label/reaction contracts are additive and writes deduplicate per identity/tag/target. Answered testimony, reversible archive state, and repeated prayer events need explicit durable semantics and rebuild/query support. Existing text/Git machinery should be reused, but a promise of zero schema or contract changes would be premature. [V2] [V4]

## What would have to be added

For a useful first Thywill experience, the essential additions are:

1. **A selectable Thywill profile and prayer presentation:** branding, entry page, request/prayer card, composer, member controls, and a clear public-versus-member access policy.
2. **The core prayer journey:** submit a request, generate/preview or recover from AI failure, publish, pray repeatedly, and see accurate totals and personal history.
3. **Prayer lifecycle and moderation:** answered testimony, archive/restore, author/admin authorization, and consistent filtered visibility.
4. **An account transition decision:** retain v3 key-based identity or add Thywill-compatible device/recovery behavior before moving existing members.

Guided prayer mode, specialized feeds, membership applications, email recovery, daily priority, categorization, invitation-tree visualization, prayer analytics, supporter badges, and notification/admin parity enlarge that scope. They are real features to retain or explicitly defer, not things a Thywill profile automatically inherits. Offline prayer-event synchronization is also separate work despite v3's existing outbox.

Moving the live community additionally requires migration and reconciliation. Preserve original request/generated text separately; reconcile total marks and distinct participants; retain testimony and status chronology; map usernames to new identities without inventing signatures; translate old links; and keep authentication/email/contact data outside public imports. A fresh empty Thywill site can defer migration; replacing the existing site cannot.

## Adoption options

| Option | Result | Tradeoff |
| --- | --- | --- |
| A — Branded forum | Thywill appears as a v3 option with prayer-oriented copy and ordinary posts/reactions. | Smallest change, but materially different from the existing prayer application. |
| B — Native prayer experience | Reuse v3 infrastructure and add the core prayer journey, then retain additional features in separate slices. | Best balance of shared infrastructure and recognizable Thywill behavior; requires explicit decisions about identity and public access. |
| C — Full replacement parity | Preserve the active Thywill feature set, including recovery, administration, offline behavior, and migrated history. | Largest scope; cannot reasonably be treated as a theme addition or one small feature cycle. |

**Recommend B**, with migration required before replacing the existing service. A first vertical slice should take a member from entry through submission, generated-prayer preview/failure recovery, publication, and a repeatable prayer action with accurate counts. Lifecycle/moderation and access policy must be settled before using it for existing community data. Full parity should be divided into separately reviewable features.

The companion [Step 1 assessment](thywill_site_option_step1_solution_assessment.md) records the adoption choice for Railspike. No application implementation is included in this review.

## Verification

Active Thywill router composition, models, services, templates, and client scripts were compared with v3 source and current contracts. Existing v3 tests were run for site selection, presentation slots, and reaction semantics:

`php tests/run.php SiteProfileRegistryTest PresentationSlotRegistryTest ReadModelPostReactionsTest`

**14 passed, 0 failed.** These checks corroborate selected foundations and the moderation mismatch; they do not validate a Thywill port. No Thywill runtime, production database, provider call, browser walkthrough, or migration was executed.

## Evidence index

Thywill links refer to the sibling checkout at `~/thywill`; they require that checkout to remain beside v3.

- [T1]: [Prayer submission and preview](../../../thywill/app_helpers/routes/prayer/prayer_operations.py), [Prayer model](../../../thywill/models.py).
- [T2]: [Prayer generation and daily reflection](../../../thywill/app_helpers/services/prayer_helpers.py), [generation prompt](../../../thywill/prompts/prayer_generation_system.txt).
- [T3]: [Prayer marks and lifecycle actions](../../../thywill/app_helpers/routes/prayer/prayer_status.py), [archive-first event handling](../../../thywill/app_helpers/services/archive_first_service.py).
- [T4]: [Prayer feed filters](../../../thywill/app_helpers/routes/prayer/feed_operations.py), [celebration and activity](../../../thywill/app_helpers/routes/prayer/prayer_operations.py).
- [T5]: [Guided queue and skips](../../../thywill/app_helpers/routes/prayer/prayer_mode.py), [guided presentation and progress](../../../thywill/templates/prayer_mode.html).
- [T6]: [Categorization service](../../../thywill/app_helpers/services/prayer_categorization_service.py), [prompt composition](../../../thywill/app_helpers/services/prompt_composition_service.py).
- [T7]: [Public routes and applications](../../../thywill/app_helpers/routes/public_routes.py), [public eligibility](../../../thywill/app_helpers/services/public_prayer_service.py), [application processing](../../../thywill/app_helpers/services/membership_application_service.py).
- [T8]: [Invitations and tree](../../../thywill/app_helpers/routes/invite_routes.py), [token models](../../../thywill/models.py).
- [T9]: [Multi-device login](../../../thywill/app_helpers/routes/auth/multi_device_routes.py), [notifications](../../../thywill/app_helpers/routes/auth/notification_routes.py), [bulk approval](../../../thywill/app_helpers/routes/admin/auth_management.py), [device links](../../../thywill/app_helpers/routes/general_routes.py).
- [T10]: [Email settings and recovery](../../../thywill/app_helpers/routes/email_settings_routes.py), [email service](../../../thywill/app_helpers/services/email_management_service.py), [private email model](../../../thywill/models_email.py).
- [T11]: [Community moderation](../../../thywill/app_helpers/routes/prayer/prayer_moderation.py), [admin moderation](../../../thywill/app_helpers/routes/admin/moderation.py).
- [T12]: [User and role models](../../../thywill/models.py), [admin user management](../../../thywill/app_helpers/routes/admin/user_management.py).
- [T13]: [Profiles and prayer history](../../../thywill/app_helpers/routes/user_routes.py), [statistics](../../../thywill/app_helpers/routes/admin/analytics.py).
- [T14]: [Offline queue](../../../thywill/static/js/offline-data.js), [prayer synchronization](../../../thywill/static/js/prayer-sync.js).
- [T15]: [Donation, welcome, and explanatory routes](../../../thywill/app_helpers/routes/general_routes.py), [badges](../../../thywill/app_helpers/services/supporter_badge_service.py), [changelog](../../../thywill/app_helpers/routes/changelog_routes.py), [Creed route](../../../thywill/app_helpers/routes/public_routes.py).
- [T16]: [Archive-first service](../../../thywill/app_helpers/services/archive_first_service.py), [export service](../../../thywill/app_helpers/services/export_service.py), [import service](../../../thywill/app_helpers/services/import_service.py).
- [V1]: [Site profiles](../../src/ForumRewrite/SiteProfileRegistry.php), [presentation slots](../../src/ForumRewrite/PresentationSlotRegistry.php), [editorial content](../../src/ForumRewrite/ProfilePresentationContent.php).
- [V2]: [Canonical posts](../../src/ForumRewrite/Canonical/PostRecord.php), [write service](../../src/ForumRewrite/Write/LocalWriteService.php).
- [V3]: [Provider configuration](../../src/ForumRewrite/Llm/LlmProviderConfig.php), [agent generation](../../src/ForumRewrite/Agent/AgentResponseGenerator.php), [Fastmod](../reference/fast_post_scoring.md).
- [V4]: [Reaction/label write deduplication](../../src/ForumRewrite/Write/LocalWriteService.php), [thread-label contract](../specs/thread_label_record_v1.md), [post-reaction contract](../specs/post_reaction_record_v1.md), [moderation reducer tests](../../tests/ReadModelPostReactionsTest.php).
- [V5]: [Thread queries](../../src/ForumRewrite/ReadModel/ThreadRepository.php), [profiles](../../src/ForumRewrite/ReadModel/ProfileRepository.php), [activity](../../src/ForumRewrite/Activity/ActivityService.php), [visitor statistics](../../src/ForumRewrite/Http/VisitorStatisticsController.php).
- [V6]: [Author/operator retraction](../reference/author_post_retraction.md), [archive CLI](../reference/v3_cli.md#archive-and-remove-a-thread), [LLM exchange privacy](../reference/llm_exchange_privacy.md).
- [V7]: [Authentication and access](../reference/authentication_modes.md), [key backups](../reference/key_backups.md), [write endpoints](../../src/ForumRewrite/Http/WritePostAndIdentityApiController.php).
- [V8]: [Invitation ledger](../../src/ForumRewrite/Invitation/InvitationLedger.php), [invitation endpoints](../../src/ForumRewrite/Http/IdentityApprovalAndInvitationApiController.php), [invitation UI](../../templates/pages/invites.php).
- [V9]: [Offline reading](../runbooks/offline_reading.md), [offline outbox tests](../../tests/OfflineOutboxIntentTest.php), [signed offline reaction validation](../../src/ForumRewrite/Write/LocalWriteService.php).
- [V10]: [Public architecture](../architecture/public_architecture_and_trust.md), [instance import](../runbooks/instance_content_import.md), [import planner](../../src/ForumRewrite/Import/ContentImportPlanner.php).

[T1]: ../../../thywill/app_helpers/routes/prayer/prayer_operations.py
[T2]: ../../../thywill/app_helpers/services/prayer_helpers.py
[T3]: ../../../thywill/app_helpers/routes/prayer/prayer_status.py
[T4]: ../../../thywill/app_helpers/routes/prayer/feed_operations.py
[T5]: ../../../thywill/app_helpers/routes/prayer/prayer_mode.py
[T6]: ../../../thywill/app_helpers/services/prayer_categorization_service.py
[T7]: ../../../thywill/app_helpers/services/public_prayer_service.py
[T8]: ../../../thywill/app_helpers/routes/invite_routes.py
[T9]: ../../../thywill/app_helpers/routes/auth/multi_device_routes.py
[T10]: ../../../thywill/app_helpers/services/email_management_service.py
[T11]: ../../../thywill/app_helpers/routes/prayer/prayer_moderation.py
[T12]: ../../../thywill/models.py
[T13]: ../../../thywill/app_helpers/routes/user_routes.py
[T14]: ../../../thywill/static/js/prayer-sync.js
[T15]: ../../../thywill/app_helpers/routes/general_routes.py
[T16]: ../../../thywill/app_helpers/services/archive_first_service.py
[V1]: ../../src/ForumRewrite/SiteProfileRegistry.php
[V2]: ../../src/ForumRewrite/Canonical/PostRecord.php
[V3]: ../../src/ForumRewrite/Agent/AgentResponseGenerator.php
[V4]: ../../src/ForumRewrite/Write/LocalWriteService.php
[V5]: ../../src/ForumRewrite/ReadModel/ThreadRepository.php
[V6]: ../reference/author_post_retraction.md
[V7]: ../reference/authentication_modes.md
[V8]: ../../src/ForumRewrite/Invitation/InvitationLedger.php
[V9]: ../runbooks/offline_reading.md
[V10]: ../architecture/public_architecture_and_trust.md
