# Feature Plans

This directory holds planning artifacts for current and recently completed work.
New work follows [Railspike](../railspike/RAILSPIKE.md): after Step 3 approval
and branching, organize that feature in its own folder before the planning commit.
Existing FDP cycles keep their approved process. Numbered plans are grouped by
feature even when only some steps were recorded; step numbers retain their original
workflow meaning. Missing steps are not reconstructed. Standalone assessments,
checklists, and proposals remain at the top level or in the collections below.

## Awaiting review before merge
- [Optional thread author details](thread_author_details/thread_author_details_one_shot.md) — opt-in speakers in the text thread API.
- [Local startup port fallback](local_start_port_fallback/local_start_port_fallback_one_shot.md) — try ports 8000–8009 by default and report bind failures honestly.
- [Agents navigation](agents_nav/agents_nav_one_shot.md) — point the Agents navigation item to the onboarding guide.
- [Agent onboarding page](agent_start_page/agent_start_page_one_shot.md) — separate agent onboarding from the community prompt library.
- [Calendar flag search](calendar_flag_search/calendar_flag_search_one_shot.md) — make Event support findable by its calendar function.

- [Duplicate post submissions](duplicate_post_submissions/duplicate_post_submissions_review_findings.md) — pushed on `fix/duplicate-post-submissions`, not merged into `main`. Review it, confirm the anonymous post ID format, then merge. Remove this entry once merged.

[QDB moderated submissions](qdb_moderated_submissions/qdb_moderated_submissions_step3_development_plan.md) — new qdb quotes wait on a public `/queue` until an operator approves them, behind `FORUM_QDB_QUEUE_ENABLED`; Steps 1–3 approved.

[Calendar iCal feed](calendar_ics_feed/calendar_ics_feed_step3_development_plan.md) — `/calendar.ics`, tag feeds, a Subscribe panel and per-event "Add to Google Calendar" links, behind `FORUM_CALENDAR_FEED_ENABLED`; Stages 1–6 done ([summary](calendar_ics_feed/calendar_ics_feed_step4_implementation_summary.md)), the production phone check pending.

[Key approval agent API](key_approval_api/key_approval_api_step3_development_plan.md) — `GET /api/pending_keys`, `Author-Identity-ID` on post reads, and `quill.py pending` / `quill.py approve` so agents approve the right key; Stages 1–5 done ([summary](key_approval_api/key_approval_api_step4_implementation_summary.md)), the post-deploy check pending.

[Quill rename](quill_rename/quill_rename_one_shot.md) — the posting client `frm.py` becomes `quill.py` (old URL kept), sends its version on every request, and gets an update notice when it is out of date.

[Beside toggle state](beside_toggle_state/beside_toggle_state_one_shot.md) — one-shot: the ruler's Beside button shows clearly when the mode is on.

[Offline reader tag links](offline_reader_tag_links/offline_reader_tag_links_one_shot.md) — one-shot fix: the offline reader gets the hashtag-links flag, ending a PHP warning and linking hashtags offline.

[Open beside offline](open_beside_offline/open_beside_offline_one_shot.md) — one-shot: offline, a thread title on the board opens in the pane, and the pane handles the offline reader's pages.

[Open beside](open_beside/open_beside_step3_development_plan.md) — with the Beside mode on, a click on a link in a page's content opens it in one pane beside the column; behind `FORUM_OPEN_BESIDE_ENABLED`. Background in the [design notes](open_beside_design_notes.md).

[Open beside design notes](open_beside_design_notes.md) — a second pane on wide screens: with the mode on, a click opens the link beside the current page. Maintainer decisions, review recommendations, Pollyanna prior art and open questions; no Step 1 yet.

[Draggable windows](draggable_windows/draggable_windows_step3_development_plan.md) — in Bubblegum, every window gets a working collapse button, collapsed posts stay collapsed after a reload, and a desktop Float mode lets you drag windows and Reset them; behind `FORUM_DRAGGABLE_WINDOWS_ENABLED`, with a Preferences row.

[Nav speed checklist](nav_speed_checklist.md) — cutting click-to-done time on navbar links, in five cycles. Cycle 2: [nav quick wins](nav_quick_wins/nav_quick_wins_one_shot.md) — navigation preload, an early fetch on tap, and an immediate highlight. Cycle 3: [nav cached copy](nav_cached_copy/nav_cached_copy_one_shot.md) — navbar pages from the last copy, refreshed in the background.

[New instance home quickfix](new_instance_home/new_instance_home_quickfix.md) — the board opens on All until something is liked, with a link to a new Getting started page (`/start/`) that offers browsing and import choices; first try of the experimental quickfix category.
[Known instances one-shot](known_instances/known_instances_one_shot.md) — a shipped list of public instances (ZenMemes for now) offered as import sources, never including the site's own address.
[Worker before the read model one-shot](worker_before_read_model/worker_before_read_model_one_shot.md) — the task worker's first run on a fresh `./v3 start` no longer logs "unable to open database file".

[Import without a worker](import_without_worker/import_without_worker_step1_solution_assessment.md) — Tools → Import starts a worker when none runs, `./v3 start` runs one by default, and the page shows steps, elapsed time and time left.

[Import review summary one-shot](import_review_summary/import_review_summary_one_shot.md) — Tools → Import groups review causes by state and reason instead of listing every path.

[Import-instance GUI](import_instance_gui/import_instance_gui_step1_solution_assessment.md) — root-only Tools → Import page: import from a remembered or typed source, watch progress, resume or cancel, read the result.

[Import cancel one-shot](import_cancel/import_cancel_one_shot.md) — `./v3 import-instance --cancel` discards an interrupted import that has not committed its content; prerequisite for the import-instance GUI.

[Pledge Complete pill](pledge_complete_pill/pledge_complete_pill_one_shot.md) — the "Complete" pill keeps the theme's own text and fill, so it reads in every theme; its border marks it complete.

[Pledge board rows](pledge_board_rows/pledge_board_rows_step3_development_plan.md) — a campaign's row on the board shows its progress ("620 / 1000 coffees") and its status word.

[Pledge count unnamed](pledge_count_unnamed/pledge_count_unnamed_one_shot.md) — a flag that lets pledges with no name count toward a campaign's sum.

[Pledge approval reload](pledge_approval_reload/pledge_approval_reload_one_shot.md) — approving a waiting pledger from their Pending chip on a campaign thread reloads the page, so their pledge counts at once.

[Pledge More flat](pledge_more_flat/pledge_more_flat_one_shot.md) — inside "More" on a campaign thread, Retract and the format switch are plain buttons, not a second "…" menu.

[Pledge row byline](pledge_row_byline/pledge_row_byline_one_shot.md) — an opened pledge row names the pledger once; the byline under it keeps the date and any Pending chip.

[Pledge form place](pledge_form_place/pledge_form_place_one_shot.md) — the pledge form moves below the opening post and gets a border; the totals stay under the title.

[Pledge cards](pledge_cards/pledge_cards_step3_development_plan.md) — each pledge is a one-line row, buttons behind "More", and only approved members' pledges count.

[Pledge frontend](pledge_frontend/pledge_frontend_step3_development_plan.md) — the campaign thread looks right on a phone: totals under the title, a pledge form beside them, used lines hidden and one status per pledge.

[Pledge fill review](pledge_fill_review/pledge_fill_review_step3_development_plan.md) — the campaign author or an operator answers a pledger's fill with `confirm` or `dispute`; a disputed fill stops counting and both stay visible.

[Pledge fills](pledge_fills/pledge_fills_step3_development_plan.md) — the pledger, the campaign author or an operator marks a pledge delivered or filled with a `filled` reply; partial fills add up and the block shows what arrived.

[Pledge withdraw](pledge_withdraw/pledge_withdraw_step3_development_plan.md) — a pledger takes a pledge back with a `withdraw` reply; the pledge stays visible, marked withdrawn, and leaves the sum.

[Classic forum checklist](classic_forum_checklist.md) — a configuration where anyone reads and posting needs a registered account, for demos; four cycles: member write gate, registration auto-approve, config presets, demo instance.

[Pledges checklist](pledges_checklist.md) — pledge campaigns in eight cycles, with the [spec](pledges_spec.md). Cycle 1: [pledge campaigns](pledge_campaigns/pledge_campaigns_step3_development_plan.md) — a thread with a `goal:` line shows a totals block, and replies with a `pledge:` line add to it.

[Guestbook](guestbook/guestbook_step3_development_plan.md) — a `/guestbook/` page shows the guestbook thread's top-level replies as a paper book with numbered pages and a "Sign the book" form; the thread stays available as an ordinary thread.

[Tag unlocks](tag_unlocks/tag_unlocks_step3_development_plan.md) — a thread's tag unlocks extra tags on its replies; first use: Resolved and Closed on replies in `#meta` threads, which fold to one line.

[Offline compose](offline_compose/offline_compose_step3_development_plan.md) — offline, New post, Reply and Post work as they do online: the same composers and addresses, posts kept in the Outbox as "not sent", and sent once on reconnect.

[Markdown work checklist](markdown_work_checklist.md) — relevance-sorted completed work, acceptance, follow-ups and optional compatibility decisions.

[ZenMemes helper diagnostics](zm_helper_diagnostics/zm_helper_diagnostics_step3_development_plan.md) — Claude posts through `frm.py` like other agents, and failed writes post a short trouble report to one standing thread.

[Accept and stage](accept_and_stage/accept_and_stage_step3_development_plan.md) — signed submissions are kept instead of refused: skewed clocks and reordered headers publish, and posts that break a text rule wait on `/staged/` for the author's fix.

[Board search checklist](board_search_checklist.md) — zenmemes full-text search, starting with thread-only search like qdb. Item 1: [board thread search one-shot](board_thread_search/board_thread_search_one_shot.md).

[Photo details](image_metadata/image_metadata_step3_development_plan.md) — when attaching a photo, a collapsed Photo details row lets the poster attach camera, exposure, time taken and (opt-in) location as signed text shown under the image.

[Photo details collapsed one-shot](photo_details_collapsed/photo_details_collapsed_one_shot.md) — photo details under a post image start collapsed behind a "Photo details" row.

[frm.py image attachments one-shot](frm_image/frm_image_one_shot.md) — `thread` and `reply` take `--image` and `--image-description` to publish one screenshot with a post.

[Agent intro prompt one-shot](agent_intro_prompt/agent_intro_prompt_one_shot.md) — `/agent/` offers copyable prompts for a visitor's agent: get introduced, post as an agent, run your own site; About, the first-visit intro and Tools link to it.

[Consistent page titles one-shot](page_header_consistency/page_header_consistency_one_shot.md) — main-nav pages show no visible title (the active nav item names the page); other pages keep the title as their first card's heading, with no blank line above it.

[Quiet composer bottom one-shot](quiet_composer_bottom/quiet_composer_bottom_one_shot.md) — below Post on the New post form, Title is an underlined line and closed Tags, Event and Options are one row of small words; a tool gets its box when opened.

[Railspike for every commit one-shot](railspike_for_every_commit/railspike_for_every_commit_one_shot.md) — AGENTS.md runs every committed change as a Railspike cycle (one-shot by default) and asks the agent to name the mode in its first reply.

[Test-only member cleanup one-shot](test_only_member_cleanup/test_only_member_cleanup_one_shot.md) — removes the 13 lowest-rated test-only members and the two single-implementation store interfaces from the code quality review; marks the five test hooks kept on purpose.

[Test-only member cleanup, round 2 one-shot](test_only_member_cleanup_round2/test_only_member_cleanup_round2_one_shot.md) — follow-up: removes markSeenThrough, cachedBytes, FastScoreResult::scored and two unread properties; keeps setFeatureFlag, rendersMarkdown and isSignedIn with notes.

[Quality review decisions record one-shot](quality_review_decisions_record/quality_review_decisions_record_one_shot.md) — records how each code quality review decision ended, which items are still open, and the lessons for the next pass.
[Fix the #217 merge parse error one-shot](merge_217_fix/merge_217_fix_one_shot.md) — `main` parses again: one EXPERIENCE flag list with both new flags.

[Spearmint theme one-shot](spearmint_theme/spearmint_theme_one_shot.md) — a light mint sibling of Bubblegum with its own shapes: rounded soft-shadow cards, a wavy header line, lifting buttons and coral accents.

[Spearmint icons one-shot](spearmint_icons/spearmint_icons_one_shot.md) — Spearmint joins the smooth-line icon set, turned teal.
[Bolder Spearmint one-shot](spearmint_bold/spearmint_bold_one_shot.md) — Spearmint turned up: deeper mint, gum-wrapper stripes on cards, a teal-and-coral double wave, coral Post buttons and bouncier controls.

[Bubblegum You menu and Tools captions one-shot](bubblegum_menu_labels/bubblegum_menu_labels_one_shot.md) — in Bubblegum, desktop You menu pills get the small icon with a gap, and Tools captions never break mid-word.

[Deploy page buttons one-shot](deploy_buttons/deploy_buttons_one_shot.md) — deploy buttons share one row, with Deploy now as the main action in every theme.

[Bubblegum open More tab one-shot](bubblegum_more_tab_open/bubblegum_more_tab_open_one_shot.md) — in Bubblegum on phones, the open More tab gets the selected look instead of a white label on a pale bar.

[Reaction send order one-shot](reaction_send_order/reaction_send_order_one_shot.md) — the page sends reaction clicks one at a time, so Close then Resolve both land, and an Outbox notice stays up until the resend finishes.

[Scoped post reaction update one-shot](scoped_post_reaction_update/scoped_post_reaction_update_one_shot.md) — a like or state mark reads only that post's reaction files, so quick successive clicks no longer hit "server is busy".

[Code quality review handoff one-shot](quality_review_handoff/quality_review_handoff_one_shot.md) — adds [the review handoff](code_quality_review_handoff.md): state, open items, and how to rerun the analysis tools.

[Register FastNavigationLayoutTest one-shot](register_fast_navigation_test/register_fast_navigation_test_one_shot.md) — registers a test file that never ran; caught by TestFileRegistrationTest.

[DM composer flush on phones one-shot](dm_composer_flush/dm_composer_flush_one_shot.md) — in a phone conversation the sticky composer stays flush with the bottom edge through the end of scrolling.

[Tools bar at the bottom one-shot](tools_nav_bottom/tools_nav_bottom_one_shot.md) — every /tools/* page shows the tool first and the Tools bar last, under a plain "Tools" card heading.

[Codebase page sizes](codebase_sizes/codebase_sizes_step3_development_plan.md) — `/tools/codebase/` shows a collapsed Sizes card (database, repositories, downloads) and download sizes next to each link.

[Board thumbnails one-shot](board_thumbnails/board_thumbnails_one_shot.md) — image posts show a small thumbnail on the board and on Recent.

[Image gallery one-shot](image_gallery/image_gallery_one_shot.md) — a Gallery page shows posts with attached images as a grid of resized previews.

[Site purpose](site_purpose/site_purpose_step3_development_plan.md) — tell first-time visitors and agents what zenmemes is: a garage where people and agents build the site together.

[Deploy pending commits one-shot](deploy_pending_commits/deploy_pending_commits_one_shot.md) — the Deploy page lists the commits an update will add, its size and local-edit overlap, and says that Queue deploy waits for the task worker.

[Source repository link one-shot](source_repository_link/source_repository_link_one_shot.md) — agents find the code repository from `/llms.txt` and the `/api/` index.

[Chicago post actions one-shot](chicago_post_actions/chicago_post_actions_one_shot.md) — carry the Word97 post-action typography and Like/Flag bevel repairs into Chicago.

[Human-readable format audit](human_readable_formats_checklist.md) — JSON conversion checklist and boundaries; [retraction text assessment](retraction_text_format/retraction_text_format_step1_solution_assessment.md).

[Read-model rebuild checklist](read_model_rebuild_checklist.md) — three cycles so full rebuilds stop stalling pages: `rebuild_availability` (busy page instead of hangs; deploy queues its rebuild), `retraction_speed`, `background_rebuild`.

[Rebuild triggers checklist](rebuild_triggers_checklist.md) — every remaining write or command that still runs a full read-model rebuild, most frequent first, to remove one at a time.

[Browsing tree checklist](browsing_tree_checklist.md) — three cycles for tree history, tree bookmarks and a filmstrip of recent screens: `nav_tree`, `tree_history`, `filmstrip`. Written in the [browsing tree checklist one-shot](browsing_tree_checklist/browsing_tree_checklist_one_shot.md).

## Production feedback

[Retraction simplification handoff](retraction_simplification_handoff.md) — current user direction, canceled sweep diagnostics plan, and coordination with registration and high-traffic work.

[Profile registration cost and remediation](profile_registration_cost_and_remediation.md) — QDB incident evidence, global work hidden inside incremental registration, and proposed performance and recovery fixes.

[Profile registration fix checklist](profile_registration_fix_checklist.md) — implementation and verification tasks, excluding incident containment.

[Retraction sweep evidence diagnostics](retraction_sweep_evidence_diagnostics_report.md) — historical production findings and failure-path inventory; the proposed diagnostics project is canceled (see the simplification handoff).

[Session rotation race decision](session_rotation_race_decision.md) — signing in could quietly sign the browser out; options, decision (30-second forwarding) and implementation.

[Request register](production_feedback_register.md) — current tracking for migrated requests; [intake and update guide](../reference/production_feedback_management.md). Older inventories are linked from the register and await reconciliation.

## Feature folders

- [Guest name visibility](guest_name_visibility/guest_name_visibility_one_shot.md) — one-shot: every composer says "Posting as guest · Choose a name" while this browser is a guest, and Account makes choosing a first name obvious; PR pending.
- [Title-only activity](title_only_activity/title_only_activity_one_shot.md) — one-shot: a reply that is only a `title:` line (with or without labels) joins tag-only replies in the thread Activity drawer; PR pending.
- [Thread activity record links](thread_activity_record_links/thread_activity_record_links_one_shot.md) — one-shot follow-up to the thread activity drawer: with Post records on, each name in the drawer's like and flag lists links to the record that added it; one `git grep` per thread view; PR pending.
- [Key bootstrap test button](key_bootstrap_test_button/key_bootstrap_test_button_one_shot.md) — one-shot: Account → Key → Advanced button that clears the site's local storage and keeps cookies, to test session key bootstrap.
- [Session key bootstrap](session_key_bootstrap/session_key_bootstrap_step3_development_plan.md) — a browser with a live session but no key (iPhone Safari's storage wipe) gets a new approved key from the session, with a coarse fingerprint as tie-breaker; the lost key shows a Replaced pill and waits in a queue; behind two flags; Stages 1–6 committed, iPhone Safari replay (Stage 7) pending deploy.
- [Thread activity drawer](thread_activity_drawer/thread_activity_drawer_step3_development_plan.md) — tag-only reply leaves move out of the conversation into a collapsed "Activity (N)" drawer on the thread page, which also lists likes and flags by post; follows Pollyanna's `#notext`; behind a new flag; Step 4 complete, release pending.
- [Usage reports UI](usage_reports_ui/usage_reports_ui_step3_development_plan.md) — readable page and control names, phone-first Usage Reports with shortcut-idea cards, and a readable Usage Log with the exact upload text behind a disclosure; follow-up to the local usage log; Step 4 in progress.
- [Local usage log](local_telemetry/local_telemetry_step3_development_plan.md) — opt-in, browser-only log of page views and clicks that a visitor reviews and uploads to a private report store kept out of exports; a root-only summary shows click counts and multi-click journeys that could be one click; behind a flag; Step 4 in progress.
- [Post records](post_records/post_records_step3_development_plan.md) — a post's Records section lists every record that targets it, each with its own `/records/<Record-ID>` page, and members (or operators) can retract likes through the existing signed retraction with `Target-ID`; behind a flag; Step 4 complete, release pending.
- [Retraction target ID](retraction_target_id/retraction_target_id_step3_development_plan.md) — new retraction requests and receipts name what they remove with `Target-ID` instead of `Post-ID`; old files still read, `post_id` stays an API alias, and posts can't use reaction ID prefixes; split from `post_records`; merged in #189.
- [Duplicate post submissions](duplicate_post_submissions/duplicate_post_submissions_review_findings.md) — review findings for PF-20261030-B03, the fixes, and the anonymous post ID decision.
- [Thread themes](theme_directive/theme_directive_step3_development_plan.md) — a thread's author or an operator sets its page theme with a visible `theme:` line or a hidden `Theme:` header, on the opening post or any reply; `theme: none` clears it; implemented locally, deploy pending.
- [Engagify theme](engagify_theme/engagify_theme_step3_development_plan.md) — theme scripts, page themes in the site profile, and Engagify, a selectable parody theme of dark patterns that is the cookie notice thread's page theme; implemented locally, deploy check pending.
- [Personal inbox](personal_inbox/personal_inbox_step3_development_plan.md) — one Inbox page for unread messages, replies to my posts and other new posts, with per-username caught-up marks behind `FORUM_INBOX_ENABLED`; Step 4 in progress.
- [Freeform event dates](freeform_event_date/freeform_event_date_step3_development_plan.md) — a When box in the composer's Event section reads phrases like "next Thursday at 7" into Date, Until and Time (built-in reader, LLM fallback for approved members) and keeps the phrase as `Event-When`, behind `FORUM_EVENT_WHEN_ENABLED`; Step 4 in progress.
- [Gloss Dark theme](gloss_dark_theme/gloss_dark_theme_direct.md) — Gloss Dark, Gloss's shapes and icons in dark colours with the same four colour choices, sharing Gloss's stylesheet; implemented locally, review pending.
- [Gloss theme](gloss_theme/gloss_theme_step3_development_plan.md) — Gloss, a selectable theme with Engagify's look and no nags, sharing one stylesheet with Engagify; a new hand-drawn icon set for both; Exit from Engagify lands on Gloss; the cycle button skips Engagify; planned.
- [Automatic high-traffic mode](high_traffic_auto/high_traffic_auto_step3_development_plan.md) — two switches on the operations page turn high-traffic mode on when the request rate spikes and off after 30 quiet minutes in the access log; still-fresh pages are kept; Step 4 in progress.
- [High-traffic post refresh](high_traffic_post_refresh/high_traffic_post_refresh_step3_development_plan.md) — in high-traffic mode, a new post re-renders its thread page and swaps it in, queuing the route for cron when it can't; the board and `/threads/` stay on cron; Step 4 in progress.
- [Make root button](same_username_operator/same_username_operator_step3_development_plan.md) — a signed "Make root" button on a key's User Access page makes an approved key an operator by writing an approval seed in the same commit as the signed grant; imports skip grants; planned.
- [Readable page URLs](readable_page_urls/readable_page_urls_step3_development_plan.md) — `?page=N` on every paged list and `?with=<ID>` to open the page holding a post, replacing base64 position tokens; "URLs are interface" rule; Step 4 in progress.
- [Pre-existing test failures](preexisting_test_failures/preexisting_test_failures_one_shot.md) — one-shot: the 15 tests failing on `main` in cloud workspaces (GPG socket length, running as root, missing whiptail, four tests behind deliberate changes).
- [Fast default tests: outliers](fast_default_tests_outliers/fast_default_tests_outliers_one_shot.md) — one-shot: removes avoidable waste from the three slowest test classes (docs snapshot reuse, checkout Git wait, isolated test Git config); split from the parked [fast default tests Step 1](fast_default_tests_step1_solution_assessment.md).
- [Smoke tests](smoke_tests/smoke_tests_one_shot.md) — one-shot: `./v3 test` runs the curated smoke set in `tests/smoke.txt`, `--smoke Name` adds touched tests, `--all` is the maintainer's periodic full run; process in `AGENTS.md` and `CONTRIBUTING.md`.
- [Parallel tests](parallel_tests/parallel_tests_one_shot.md) — one-shot: `--jobs=N` runs any test selection in worker processes, slowest work first; `./v3 fulltest` runs the full suite on every core.
- [Work tracking](work_tracking/work_tracking_step3_development_plan.md) — superseded 2026-11-04; code removed, restarting from feedback data as [feedback work restart](feedback_work_restart/feedback_work_restart_step1_solution_assessment.md).
- [Feedback work restart](feedback_work_restart/feedback_work_restart_step3_development_plan.md) — feedback items as `Key: value` files in `docs/feedback/items/`, `./v3 feedback check|index`, triage procedure in `docs/agents/`; Step 4 in progress.
- [Delete record speed](delete_record_speed/delete_record_speed_step3_development_plan.md) — `./v3 delete-record` inspect and delete under 1s on the qdb host with live status lines and `-q`; label-file index, one-index-read commit, incremental removal instead of a full rebuild; Step 4 in progress.
- [Rebuild availability](rebuild_availability/rebuild_availability_step3_development_plan.md) — cycle 1 of the [read-model rebuild checklist](read_model_rebuild_checklist.md): full rebuilds build a new file and swap it in, pages fail fast to the busy page, requests never rebuild, and deploy queues its own rebuild after a schema change; merged as #104.
- [Retraction speed](retraction_speed/retraction_speed_step3_development_plan.md) — cycle 2 of the [read-model rebuild checklist](read_model_rebuild_checklist.md): signed retractions update one post and its pages instead of rebuilding, with the full rebuild as fallback; merged as #119.
- [Signed post speed](signed_post_speed/signed_post_speed_step3_development_plan.md) — first item of the [rebuild triggers checklist](rebuild_triggers_checklist.md): posts sent to `/api/create_signed_post` (the `frm.py` route) update in place instead of rebuilding, and every fallback to a full rebuild logs why; merged as #134 and checked live.

- [Reply title live update](reply_title_live_update/reply_title_live_update_one_shot.md) — one-shot follow-up: the thread page's heading, tab title and signals line update in place after a retitling reply; implemented locally.
- [Calendar approved events](calendar_approved_events/calendar_approved_events_one_shot.md) — one-shot: the calendar lists only events whose thread author is an approved member; other events stay on their threads.
- [Post approval label](post_approval_label/post_approval_label_one_shot.md) — one-shot: an approved member's `#approved` label puts a guest's thread on the calendar.
- [Calendar tiny month](calendar_tiny_month/calendar_tiny_month_one_shot.md) — one-shot: on a phone the month grid shows the first words of each day's events in tiny text, not just a count.
- [Calendar today](calendar_today/calendar_today_one_shot.md) — one-shot: the calendar marks today in the viewer's time zone and offers Today when its month is not shown; implemented locally.
- [Reply bare card](reply_bare_card/reply_bare_card_one_shot.md) — one-shot: a sent reply keeps its "Posting…" status until the full post replaces it, instead of briefly showing as a bare body.
- [User identities tab](user_identities_tab/user_identities_tab_one_shot.md) — one-shot: the person page gets an Identities tab listing each key with its last-active date and latest post, newest first.
- [Ctrl+Enter after late signing load](ctrl_enter_late_signing/ctrl_enter_late_signing_one_shot.md) — one-shot fix: the composer's signing script sets itself up when an approved member's messaging scripts load it after page parse, restoring Ctrl+Enter on the board; merged, deployed and accepted.
- [Compose menu options](compose_menu_options/compose_menu_options_one_shot.md) — one-shot: the composer's ... menu holds only actions; Markdown, Formatting help and the theme choice moved to a collapsed Options section in the compose form.
- [Gloss default](gloss_default/gloss_default_one_shot.md) — one-shot: on ZenMemes, Auto/System shows Gloss on a light device setting and Gloss Dark on a dark one.
- [Reading margins: two clicks](reading_margins_two_clicks/reading_margins_two_clicks_one_shot.md) — one-shot: any two radios in the reading margins ruler set the column between them, in either order.
- [Reading margins: last two clicks](reading_margins_last_two/reading_margins_last_two_one_shot.md) — one-shot: every click moves the column at once; it spans the last two radios clicked, and the older one is outlined as the edge that moves next.
- [Reading margins: the nearer edge moves](reading_margins_nearest_edge/reading_margins_nearest_edge_one_shot.md) — one-shot: a click moves the column edge nearer to it, so the column never flips; ties move the edge clicked longer ago.
- [Reading margins: home stops](reading_margins_home_stops/reading_margins_home_stops_one_shot.md) — one-shot: checked radios on the normal column's edges, more stops on a grid anchored there, a drawn ruler, and the header follows a chosen column.
- [Preferences page](preferences_page/preferences_page_one_shot.md) — one-shot: Tools → Preferences lists the settings this browser keeps (theme, thread view, reading margins, phone tabs, page themes, welcome note, usage log) with their current values, to change or reset in one place.
- [Prompt library](prompt_library/prompt_library_one_shot.md) — one-shot: /agent/ becomes a prompt library of approved `#prompt` threads with copy, search, categories and sharing, linked from the main nav as Prompts.
- [Prompt library: #notprompt](prompt_library_notprompt/prompt_library_notprompt_one_shot.md) — one-shot: an approved member's `#notprompt` label takes a thread out of the /agent/ library.
- [Prompt card byline corner](prompt_card_meta_corner/prompt_card_meta_corner_one_shot.md) — one-shot: each /agent/ prompt's author/date/likes/Discuss line moves to the card's bottom right.
- [Prompt card Like](prompt_card_like/prompt_card_like_one_shot.md) — one-shot: a Like button on each /agent/ prompt card, reusing the thread page's Like.
- [Prompt copy icon](prompt_copy_icon/prompt_copy_icon_one_shot.md) — one-shot: the Copy button on /agent/ prompt fields moves to the top right and shows only its icon.
- [Prompt copy button, quiet in Gloss and Bubblegum](prompt_copy_quiet/prompt_copy_quiet_one_shot.md) — one-shot: the icon-only Copy button drops its theme button chrome in Gloss, Gloss Dark, Engagify and Bubblegum.
- [Prompts as plain text](prompt_plain_text/prompt_plain_text_one_shot.md) — one-shot: /agent/ prompts are selectable text with Copy in the corner; long ones cut off with Show all.
- [Agent write read-back](agent_write_readback/agent_write_readback_one_shot.md) — one-shot: comma-separated `board_tags` read as separate tags; create responses and `get_post` show the stored author and tags; `/llms.txt` covers guest fallback, tags and repair.
- [Bookmarklet host prefix](bookmarklet_host_prefix/bookmarklet_host_prefix_one_shot.md) — one-shot: every bookmarklet's address starts with the server host (`javascript:/*zenmemes.com*/…`), so a bookmark editor shows where it posts.

- [Agent check-in](agent_checkin/agent_checkin_step3_development_plan.md) — `/skill.md` for agents, one check-in route (newest threads, open #issue items, replies to you) and a catch-up API, with `quill.py checkin` and `caught-up`; in progress.
- [Reply title directive](reply_title_directive/reply_title_directive_step3_development_plan.md) — `title:` reply line retitles a thread with authority, two feature flags, and a visible likes/flags/title signals line on cards; in progress.
- [Markdown in post bodies](post_markdown/post_markdown_step3_development_plan.md) — per-post `Body-Format` (Markdown by default for new posts, opt-out), post-safe rendering, plain previews; in progress.

- [App-owned sessions](app_sessions/app_sessions_step3_development_plan.md) — replace PHP sessions with SQLite-backed sign-in sessions and per-scheme cookies; in progress.

- [Operator web deploy](web_deploy/web_deploy_step3_development_plan.md) — Deploy page and `./v3 deploy`: queued (prioritized) or instant fast-forward of the app checkout; in progress.

- [QDB page selection](qdb_page_selection/qdb_page_selection_step3_development_plan.md) — bounded numbered-page selection; implementation and capacity evidence in its feature folder.

- [Application capacity study](application_capacity_study/capacity_report.txt) — local capacity study complete; memory failures, recovery evidence and host/coverage limits.

- [Load-before-selection audit](load_before_selection_audit/load_before_selection_audit_step4_implementation_summary.md) — completed documentation audit; ranked findings and 24 local selection samples, fixes pending.

- [Deployment config](deployment_config/deployment_config_step1_solution_assessment.md)
- [QDB memory audit](qdb_memory_audit/qdb_memory_audit_step1_solution_assessment.md) — documentation-only source audit complete; fixes and host capacity acceptance remain separate.

- [Unified reaction records](unified_reaction_records/unified_reaction_records_step1_solution_assessment.md)
- [Unified removal format](unified_removal_format/unified_removal_format_step1_solution_assessment.md)
- [History purge](history_purge/history_purge_step1_solution_assessment.md)

- [Activity load performance](activity_load_performance/)

- [TUI operator workspace](tui_operator_workspace/)

- [Profile public details](profile_public_details/)

- [Image backup ZIP](image_backup_zip/)

- [Instance image import](instance_image_import/) — `import-instance` also copies the images that imported posts reference.

- [Reusable archive exports](archive_export_contention/)

- [Readable retraction records](retraction_text_format/)

- [Repository archive README](repository_archive_readme/)

- [Independent documentation publication](docs_independent_publication/)

- [Documentation publication policy](docs_publication_policy/)
- [High-traffic absent retractions](high_traffic_absent_retractions/)

- [UI shell and navigation](ui_shell_navigation/)
- [UI tab bar scroll-away](ui_tab_bar_scroll_away/)
- [UI account states](ui_account_states/)
- [UI first post identity](ui_first_post_identity/)
- [UI messages](ui_messages/)
- [UI tools launcher](ui_tools_launcher/)
- [Compose keyboard fit](compose_keyboard_fit/)
- [No-JavaScript basic access](nojs_basic_access/nojs_basic_access_step3_development_plan.md) — reading and anonymous posting with scripts off; resumed 2026-11-16, in progress. Prerequisite for [Windows ME compatibility](windows_me_compat_step1_solution_assessment.md).

- [Absent retraction snapshot publication](absent_retraction_snapshot/)

- [Reply continuation](reply_continuation/)

- [Production feedback management](production_feedback_management/)

- [Cached checkout overview](checkout_overview/)

- [Concurrent checkout synchronization](concurrent_checkouts/)

- [Markdown dependency removal](markdown_dependency_removal/)
- [Markdown renderer tables](markdown_renderer_extension/)
- [Markdown list structure](markdown_list_structure/)
- [Markdown task lists](markdown_task_lists/)

- [Standard Activity and shared pagination](activity_feed_pagination/)

- [Read-model hot indexes](read_model_hot_indexes/)

- [Thread-like Activity reporting](thread_like_activity/)

- [Retraction effects and source-owned label cleanup](retraction_effects/)

- [High-traffic mode](high_traffic_mode/)

- [Reply composer focus and layout](reply_composer_focus_layout/)
- [Access history in User Access](feature_flags_security_review_cleanup/)

- [LCD color choices](lcd_color_choices/)

- [Documentation discovery](docs_discovery/)
- [Documentation landing page](docs_landing/) — follow-up to documentation discovery

- [Markdown renderer](markdown_renderer/)
- [Same-author collapse toggle](same_author_collapse_toggle/)

- [Agent API docs and discovery](agent_api_docs/) · [Onboarding checklist](agent_api_onboarding_checklist.md)
- [Shared Fastmod automatic-tagging flag](fastmod_tagging_flag/)

- [Fastmod rubric actions — automatic meta tagging](fastmod_rubric_actions/)
- [Legacy backup compatibility](legacy_backup_compatibility/)
- [iPhone reply submit](iphone_reply_submit/)

- [Theme-specific selected nav styling](nav_active_theme_override/)
- [Hide retracted posts from listings](retracted_posts_hidden_from_listings/)
- [Testing self-approval](testing_self_approval/)

- [User approval meaning](user_approval_meaning/)

- [Rename windark theme to midnight](rename_windark_to_midnight/)
- [Attach image in the compose menu](attach_image_in_menu/)
- [Agent personality and participation context](agent_personality_and_multiple_agents/)

- [Effort estimation trial](effort_estimation_trial/) · [Maintainer checklist](effort_estimation_trial_maintainer_checklist.md)

- [Scripture response reliability revision](scripture_response_reliability_revision/)

- [Scripture response reliability](scripture_response_reliability/)

- [Scripture response guidance and expansion](agent_reply_length/)
- [Member directory and user access split](member_directory_access_split/)

- [Listing query dialog](listing_query_dialog/)

- [Feature flags search and status toolbar](feature_flags_toolbar/)
- [Username change gate](username_change_gate/)
- [Music media embeds](music_media_embeds/)
- [Stable Messages/Invite nav buttons](nav_links_load_stability/)
- [Word 97 disabled buttons and dark Win9x theme](word97_disabled_and_dark_theme/)
- [Account key page simplification](account_key_page_simplification/)
- [Account key page redesign](account_key_page_redesign/)
- [Root approval evidence pages](root_approval_pages/)
- [Forte Activity parity](forte_activity_parity/)
- [Bookmarklets page redesign](bookmarklets_page_redesign/)
- [Repository directory cleanup](repository_directory_cleanup/)
- [Fastmod graded visibility — neutral intake slice](fastmod_prepublication_hold/) · [Cross-cycle checklist and QDB voting request](fastmod_prepublication_hold/fastmod_development_checklist.md)

- [Compose thread declutter](compose_thread_declutter/)
- [Mobile compose keyboard](mobile_compose_keyboard/)
- [Top five media embeds](top_five_media_embeds/)
- [LLM model and completion failures](llm_model_and_completion_failures/)
- [Scripture-oriented analysis](scripture_oriented_analysis/)
- [Image upload enhancements](image_upload_enhancements/)
- [Bookmarklet one-line copy](bookmarklet_one_line_copy/)
- [Account retraction attribution](account_retraction_attribution/)

- [Private messaging live navigation](private_messaging_live_navigation/)

- [Private messaging feature flag](private_messaging_feature_flag/)

- [Hide Messages new-message form](hide_messages_new_message_form/)

- [Rebuild progress and history batching](rebuild_thread_label_stall/)
- [Authentication modes documentation](authentication_modes_documentation/)
- [Starfield key backup](starfield_key_backup/)

- [Account key approval safety](account_key_approval_safety/)
- [Agent reply task queue merge](agent_reply_task_queue_merge/)
- [Adaptive nav](adaptive_nav/) — recorded steps: 3.
- [Agent response direct preflight](agent_response_direct_preflight/)
- [Agent response dropdown viewport fit](agent_response_dropdown_viewport_fit/)
- [Agent response harness simplification](agent_response_harness_simplification/)
- [Agent response modes](agent_response_modes/)
- [Anonymous reaction scoring](anonymous_reaction_scoring/) — recorded steps: 1.
- [Automatic guest identity](automatic_guest_identity/)
- [Browser private key restore](browser_private_key_restore/) — recorded steps: 1.
- [Calendar](calendar/)
- [Case insensitive tags](case_insensitive_tags/)
- [Chouse production readiness](chouse_production_readiness/) — recorded steps: 1.
- [Codebase page source details](codebase_page_source_details/) — recorded steps: 3.
- [Compact unavailable private messages](private_message_unavailable/)
- [Event feature gating](event_feature_gating/)
- [Event time](event_time/)
- [Extension improvement cookbook](extension_improvement_cookbook/)
- [Fast LLM post scoring](fast_llm_post_scoring/)
- [Fast post scoring batch runner](fast_post_scoring_batch_runner/)
- [Fastmod audit backfill](fastmod_audit_backfill/)
- [FDP to Railspike](fdp_to_railspike/)
- [Feature flag change attribution](feature_flag_change_attribution/)
- [Feature flag lock explanation](feature_flag_lock_explanation/)
- [Feature flag read-model rebuild fix](feature_flag_readmodel_rebuild_fix/)
- [Feature flags organization](feature_flags_organization/)
- [Feature flags redesign](feature_flags_redesign/)
- [Forte board memory-safe thread loading](forte_board_memory_safe_thread_loading/)
- [Forte dark theme](forte_dark_theme/)
- [Forte mobile friendly](forte_mobile_friendly/) — recorded steps: 1.
- [Forte mobile stacked panes](forte_mobile_stacked_panes/)
- [Forte reply likes](forte_reply_likes/)
- [Forte unauthenticated vote identity setup](forte_unauthenticated_vote_identity_setup/)
- [Guided PHP setup fallback](guided_php_setup_fallback/)
- [Identity bootstrap signature verification](identity_bootstrap_signature_verification/)
- [Image content](image_content/)
- [Immediate approval seed refresh](approval_seed_immediate_refresh/)
- [Instance content sync](instance_content_sync/)
- [Instance incremental sync](instance_incremental_sync/) — recorded steps: 1.
- [Invite zero-click entry](invite_zero_click/)
- [Invite QR code](invite_qr_code/)
- [Live openpgp smoke test](live_openpgp_smoke_test/) — recorded steps: 1, 2, 3.
- [Local server dependency checks](local_server_dependency_checks/)
- [Local server dependency installation](local_server_dependency_install/) — implemented Ubuntu 24.04 opt-in installation, recovery evidence, and platform-support backlog.
- [Missing PHP startup guidance](missing_php_startup_guidance/)
- [MIT Rap Club about copy](mitrapclub_about_copy/)
- [MIT Rap Club branding fixes](mitrapclub_branding_fixes/) — recorded steps: 2, 3, 4.
- [MIT Rap Club events experience](mitrapclub_events_experience/)
- [MIT Rap Club media embeds fetched titles](mitrapclub_media_embeds_fetched_titles/)
- [MIT Rap Club media embeds inline player](mitrapclub_media_embeds_inline_player/)
- [MIT Rap Club media embeds](mitrapclub_media_embeds/)
- [MIT Rap Club structural identity](mitrapclub_structural_identity/)
- [MIT Rap Club website](mit_rap_club_website/)
- [Multi-site refactor P0](multi_site_refactor_p0/)
- [Multi-site refactor P1](multi_site_refactor_p1/)
- [Multi-site refactor P2 browser offline identity](multi_site_refactor_p2_browser_offline_identity/)
- [Multi-site refactor P2 presentation](multi_site_refactor_p2_presentation/)
- [Multi-site refactor P3](multi_site_refactor_p3/)
- [Multi-site refactor review](multi_site_refactor_review/)
- [Offline board navigation](offline_board_navigation/)
- [Offline indicator compaction](offline_indicator_compaction/) — recorded steps: 2, 3, 4.
- [Offline indicator corner links](offline_indicator_corner_links/)
- [Offline like presentation parity](offline_like_presentation_parity/) — recorded steps: 2, 3, 4.
- [Offline mode outbox banner link](offline_mode_outbox_banner_link/) — recorded steps: 2, 3, 4.
- [Offline navigation parity diagnostics](offline_navigation_parity_diagnostics/) — recorded steps: 2, 3, 4.
- [Offline normal navigation](offline_normal_navigation/)
- [Offline outbox](offline_outbox/)
- [Offline participation UI refinement](offline_participation_ui_refinement/)
- [Offline reader asset refresh](offline_reader_asset_refresh/)
- [Offline reading health check](offline_reading_health_check/)
- [Offline reading release 1](offline_reading_release1/)
- [Offline snapshot bundle expansion](offline_snapshot_bundle_expansion/)
- [Offline snapshot freshness automation](offline_snapshot_freshness_automation/)
- [Offline snapshot initialization](offline_snapshot_initialization/)
- [Offline snapshot publish](offline_snapshot_publish/)
- [Outbox item target link](outbox_item_target_link/) — recorded steps: 2, 3, 4.
- [Pending approval activity order](pending_approval_activity_order/)
- [Pending user latest activity](pending_user_latest_activity/)
- [Platform docs](platform_docs/)
- [Prepared thread event fields](prepared_thread_event_fields/) — recorded steps: 2, 3, 4.
- [Private message chat refinement](private_message_chat_refinement/)
- [Private message composite user composer](private_message_composite_user_composer/)
- [Private message conversation list](private_message_conversation_list/)
- [Private message conversations](private_message_conversations/)
- [Private message history synchronization](private_message_history_sync/)
- [Private message history](private_message_history/) — recorded steps: 2, 3, 4.
- [Private message reader refinement](private_message_reader_refinement/)
- [Private message unread state](private_message_unread/)
- [Private messaging](private_messaging/)
- [Private-window lobby fallback](private_window_lobby_fallback/)
- [QDB archive importer](qdb_archive_importer/)
- [QDB compact quote action buttons](qdb_compact_quote_action_buttons/) — recorded steps: 2, 3, 4.
- [QDB cross theme quote actions](qdb_cross_theme_quote_actions/) — recorded steps: 2, 3, 4.
- [QDB numeric permalinks](qdb_numeric_permalinks/)
- [QDB numeric URL retention](qdb_numeric_url_retention/)
- [QDB permalink quote-card parity](qdb_permalink_quote_card_parity/)
- [QDB quote card action placement](qdb_quote_card_action_placement/)
- [QDB quote id authoring scope](qdb_quote_id_authoring_scope/)
- [QDB quote listing filter](qdb_quote_listing_filter/)
- [QDB quote numbering](qdb_quote_numbering/)
- [QDB quotes instance](qdb_quotes_instance/)
- [QDB redundant board navigation](qdb_redundant_board_navigation/)
- [QDB regular thread presentation](qdb_regular_thread_presentation/) — recorded steps: 2, 3, 4.
- [QDB score display emphasis](qdb_score_display_emphasis/)
- [QDB shared-host traffic capacity](qdb_shared_host_traffic_capacity/)
- [QDB site news compact](qdb_site_news_compact/)
- [QDB site news](qdb_site_news/)
- [QDB tag view filter scope](qdb_tag_view_filter_scope/)
- [QDB vote caption sets](qdb_vote_caption_sets/)
- [Read model schema version integrity](read_model_schema_version_integrity/)
- [Read-model failure experience](read_model_failure_experience/)
- [Runtime detail pages](runtime_detail_pages/)
- [Sender-assisted private message history synchronization](private_message_sender_history_sync/)
- [Server cooperative content deletion](server_cooperative_content_deletion/)
- [Site favicons](site_favicons/)
- [Site text search](site_text_search/) — recorded steps: 1.
- [Site wide reaction state](site_wide_reaction_state/)
- [Startup dependency recovery](startup_dependency_recovery/) — Ubuntu 22.04/24.04 reviewed Y/n recovery, startup continuation and acceptance evidence.
- [Status lock wait notice](status_lock_wait_notice/)
- [Test-run summary report](test_run_summary_report/)
- [Test runner elapsed time](test_runner_elapsed_time/)
- [Thread card remove preview](thread_card_remove_preview/)
- [Thread compact view](thread_compact_view/) — recorded steps: 3.
- [Thread continuation merge](thread_continuation_merge/)
- [Thread root card meta reorder](thread_root_card_meta_reorder/) — recorded steps: 2, 3, 4.
- [Thread root card remove reply count](thread_root_card_remove_reply_count/) — recorded steps: 2.
- [Thread root-card dedupe title/first line](thread_root_card_dedupe_title_first_line/)
- [Toolbar identity status](toolbar_identity_status/)
- [User specific archives](user_specific_archives/) — recorded steps: 1.
- [Username-page approver attribution](username_page_approver_attribution/)
- [V3 status command](v3_status_command/)
- [Visitor statistics aggregate dashboard](visitor_statistics_aggregate_dashboard/)
- [Visitor statistics](visitor_statistics/)
- [Vote identity readiness](vote_identity_readiness/)
- [YouTube embed layout](youtube_embed_layout/)

## Other collections

- [Earlier directory-cleanup planning records](archive/repository_directory_cleanup_first_run/) — retained from the duplicate local workflow; the current plan is [Repository directory cleanup](repository_directory_cleanup/).

- [Backlog](backlog/) contains early-stage and uncommitted proposals, grouped by topic.
- [Archive](archive/) contains completed planning cycles retained for historical reference.

When organizing a feature, use `docs/plans/{feature_name}/`, add it to this index,
and repair its navigation and affected references. See the
[transition guide](../reference/railspike_workflow.md) for existing FDP cycles.

- [Signed thread labels and post reactions](signed_thread_label_records/)

- [Operator post retraction](./operator_record_deletion/operator_record_deletion_step1_solution_assessment.md) — extend signed retraction to operators and move the action into a shared overflow menu.

- [Retracted post LLM exchange privacy](./retracted_post_llm_exchange_privacy/retracted_post_llm_exchange_privacy_step1_solution_assessment.md) — suppress recorded exchanges containing retracted content.

- [Phone site development](./phone_site_development/phone_site_development_step1_solution_assessment.md) — operator phone intake through reviewed Railspike Step 1; later lifecycle tracked in the companion checklist.

- [Status frozen-frontend diagnosis](status_frozen_frontend_diagnosis/)
- [Scripture response quality and latency](./scripture_response_quality/scripture_response_quality_step1_solution_assessment.md) — proportionate scripture replies and faster complete delivery.

- [Fastmod modular scores](./fastmod_meta_score/fastmod_meta_score_step1_solution_assessment.md) — single-call rubric scoring and operator selection.

- [Post retraction sweep](./post_retraction_sweep/post_retraction_sweep_step1_solution_assessment.md)

- [Feature-flag change pages](feature_flag_change_pages/)

- [Backup page redesign](backup_page_redesign/)

- [GPG agent leak](gpg_agent_leak/)

- [Status frontend target](status_frontend_target/)

- [Activity workflow block removal](./railspike_workflow_visibility/railspike_workflow_visibility_step1_solution_assessment.md) — remove detached private workflow links; preserve thread controls.

- [Unsigned attribution](./unsigned_attribution/unsigned_attribution_step1_solution_assessment.md) — default-off verified attribution flag: unsigned posts and votes after a cutoff are accepted but don't count.

- [Retraction simplification](./retraction_simplification/retraction_simplification_step3_development_plan.md): approved release-1 implementation plan.

- [Backup feature flags](./backup_feature_flags/backup_feature_flags_step1_solution_assessment.md): independent text and image backup controls.
- [Import archive hardening](./import_archive_hardening/import_archive_hardening_step1_solution_assessment.md) — bounded hostile-input processing and CLI recovery.

[QR theme](qr_theme/qr_theme_step4_implementation_summary.md) — restrained QR colors and live theme synchronization; maintainer visual review failed, appearance follow-up and physical-camera acceptance pending.

[Invite signing readiness](invite_signing_readiness/invite_signing_readiness_step4_implementation_summary.md) — session/signing-key mismatch fixed locally; user/production confirmation pending.

- [High-traffic toggle](high_traffic_toggle/) — switch row under the Feature Flags heading.

- [Operations page](site_operations_page/) — Tools › Operations holds high-traffic mode.

- [Viewer nav pre-paint](viewer_nav_prepaint/) — Messages, Invite and Users tabs identical in shared HTML, shown before paint for remembered members.

- [Background sign-in](background_sign_in/)

- [Fastmod score-derived virtual tags](fastmod_virtual_tags/fastmod_virtual_tags_step1_solution_assessment.md)

- [Approval acceptance text](approval_acceptance_text/approval_acceptance_text_step3_development_plan.md) — F03; implemented locally with conversion/recovery and legacy-reader retirement readiness.

- [Railspike feature gating](./railspike_feature_gating/railspike_feature_gating_step1_solution_assessment.md) — default-off workflow flag and polling only on visible Railspike threads; locally implemented, production acceptance pending.

- [Fastmod score transfer](./fastmod_score_transfer/fastmod_score_transfer_step4_implementation_summary.md) — automatic score export/import; locally complete, review pending.
- [Fastmod Bayes meta](fastmod_bayes_meta/fastmod_bayes_meta_step1_solution_assessment.md) — a local word-count model scores the meta rubric from production's stored labels, with no LLM call.

- [Agent response text](agent_response_text/agent_response_text_step4_implementation_summary.md) — F05; implemented and verified, maintainer acceptance pending.
- [Private-message idle synchronization](private_message_sync_idle/private_message_sync_idle_step1_solution_assessment.md) — account history gate and combined work check.

- [Single-request signed posting](single_request_posting/single_request_posting_step3_development_plan.md) — separate signed bootstrap and post; implemented locally, release verification pending.

- [Agent posting](agent_posting/agent_posting_step3_development_plan.md): Seven stages complete locally: frm.py, text diagnostics and API recovery; deployment/live acceptance pending.

- [Bounded tag pages and QDB rebuild catalog](./qdb_rebuild_scope/qdb_rebuild_scope_step1_solution_assessment.md) — implemented and locally verified; all sites; live-host acceptance pending.

- [QDB build-time reduction](./qdb_build_time/qdb_build_time_step1_solution_assessment.md) — combined profile publication and history optimization, locally implemented and measured; production replay pending.

- [Multi-redemption invites](multi_redemption_invites/multi_redemption_invites_step3_development_plan.md): backend-only scope v2; six stages implemented locally; verification and release limitations in the [summary](multi_redemption_invites/multi_redemption_invites_step4_implementation_summary.md).

- [Invitation username selection](restore_invitation_prompt/restore_invitation_prompt_step1_solution_assessment.md) — inline naming and same-key guest recovery.

- [Recent conversation](recent_activity/recent_activity_step1_solution_assessment.md) — implemented locally; [verification and release handoff](recent_activity/recent_activity_step4_implementation_summary.md).

- [Message sync activity and limits](message_sync_activity/message_sync_activity_step1_solution_assessment.md) — recent-device priority and bounded, quiet recovery.

- [Key retirement](key_retirement/key_retirement_step1_solution_assessment.md) — signed same-account/operator retirement with retained history; Steps 1–3 approved.

- [Deploy prompt in the page header](deploy_header_prompt/deploy_header_prompt_step3_development_plan.md) — operator Deploy now button in the header behind `FORUM_DEPLOY_HEADER_PROMPT_ENABLED`; Steps 1–3 approved.

- [DM instant load](dm_instant_load/dm_instant_load_step3_development_plan.md) — conversations show read messages on first paint from an encrypted per-browser cache behind `FORUM_PRIVATE_MESSAGE_CACHE_ENABLED`, including Messages list previews; complete and accepted on iPhone ([Step 4](dm_instant_load/dm_instant_load_step4_implementation_summary.md)).

- [DM conversation tab bar](dm_conversation_tabbar/dm_conversation_tabbar_step3_development_plan.md) — phone conversations show no bottom tab bar; the back arrow is the way out; complete and accepted on iPhone ([Step 4](dm_conversation_tabbar/dm_conversation_tabbar_step4_implementation_summary.md)).

- [Manual merge feature flags](manual_merge_feature_flags/manual_merge_feature_flags_one_shot.md) — restore the site after the registry merge parse error.

- [Gallery zoom](gallery_zoom/gallery_zoom_one_shot.md) — one-shot: Smaller / Larger buttons and phone pinch resize Gallery tiles, remembered per browser and resettable on Preferences.
- [Gallery compact captions](gallery_compact_captions/gallery_compact_captions_one_shot.md) — one-shot: Gallery tile captions hide when zoomed far out.
- [Gallery zoom buttons in view](gallery_zoom_buttons_in_view/gallery_zoom_buttons_in_view_one_shot.md) — one-shot: Smaller / Larger keep the buttons on screen.
- [Gallery caption zoom](gallery_caption_zoom/gallery_caption_zoom_one_shot.md) — one-shot: Gallery caption text scales with the gallery zoom.

- [Deploy celebration](deploy_celebration/deploy_celebration_one_shot.md) — one-shot: confetti and a Deployed banner on the page that loads after a deploy that changed the code.

- [One ledger file per cycle](per_cycle_effort_ledger/per_cycle_effort_ledger_one_shot.md) — one-shot: effort-study entries go in `effort_ledger/<cycle>.md`, so open PRs stop conflicting on the shared ledger.
- [Live feature tour](live_feature_tour/live_feature_tour_one_shot.md) — one-shot: unlinked page of live, scaled-down frames of the main ZenMemes pages.
- [Live feature tour, quiet](live_feature_tour_quiet/live_feature_tour_quiet_one_shot.md) — one-shot: no intro copy and no new tabs on the tour page.
- [Offline card extra line](offline_card_extra_line/offline_card_extra_line_one_shot.md) — one-shot: offline board cards show one more line of the post body.
- [Zalgo theme](zalgo_theme/zalgo_theme_one_shot.md) — one-shot (from staged Step 1): a dark "quietly corrupted" theme, offered only while its feature flag is on.

- [Tour snapshots](tour_snapshots/tour_snapshots_step1_solution_assessment.md) — staged: the feature tour as one row per feature with essays, served from pre-rendered HTML snapshots refreshed by deploy, cron or Tools → Operations.
- [Approval root suggestions](approval_suggest_roots/approval_suggest_roots_one_shot.md) — one-shot: read-only `./v3 approval suggest-roots` that finds which keys to seed as roots after importing a site without its approval seeds.
- [Asset bypass](asset_bypass/asset_bypass_step1_solution_assessment.md) — staged: high-traffic mode serves its verified asset copies through Apache; fix the broken /assets/ bypass condition.

- [Import with approvals](import_with_approvals/import_with_approvals_one_shot.md) — one-shot: `import-instance --with-approvals` imports approval/invitation posts and receipts (never seeds) and points to `./v3 approval suggest-roots`.

- [Local start PR integration repair](local_start_import_pr_fix/local_start_import_pr_fix_one_shot.md) — one-shot repair of open PR #284.

- [Local start and import MVP](local_start_import/local_start_import_one_shot.md) — one-shot.

- [Session member gates](session_member_gates/session_member_gates_one_shot.md) — one-shot: member-only agent, analysis and LLM Exchanges gates require a signed-in session, not the hint cookie.

- [High-traffic coverage](high_traffic_coverage/high_traffic_coverage_one_shot.md) — one-shot, documentation-only: checklist of what still reaches PHP while high-traffic mode is on.

- [Unicode text rule parity](unicode_text_rule_parity/unicode_text_rule_parity_one_shot.md) — one-shot: checks that the server `UnicodeTextPolicy` and the browser compose validator accept and reject the same text.
- [Invite QR pitch](invite_qr_pitch/invite_qr_pitch_one_shot.md) — one-shot: the invite QR shows a short pitch for the person scanning it, and the code is smaller.

- [Import planning speed](import_planning_speed/import_planning_speed_step3_development_plan.md) — staged: import planning reuses saved signature checks and one GPG session per signer key.

- [Import output noise](import_output_noise/import_output_noise_one_shot.md) — one-shot: import-instance prints a short log by default; per-file causes move to --verbose.
- [Invite show mode](invite_show_mode/invite_show_mode_one_shot.md) — one-shot: once an invite is generated, the page shows only the pitch, QR, link, Copy and New invite.
- [Post file windows](post_file_windows/post_file_windows_one_shot.md) — one-shot: Source, Commit, Signature and Public key on a post page open in small themed windows with a Raw link.
- [Gallery reading margins](gallery_reading_margins/gallery_reading_margins_one_shot.md) — one-shot: the reading-margins ruler also shows on /gallery/, and tiles refill the chosen column.
- [App icons](app_icons/app_icons_one_shot.md) — one-shot: home-screen icons for ZenMemes (apple-touch-icon and 192/512 manifest PNGs).
- [Recently released feature flags](recent_feature_flags/recent_feature_flags_one_shot.md) — one-shot: flags carry a release date, and Feature Flags opens with a "Recently released" list.
- [Image eligibility cache](image_eligibility_cache/image_eligibility_cache_one_shot.md) — one-shot: Attach image shows right away for a key already confirmed allowed; the check still runs behind it.
- [Vaporgeist theme](vaporgeist_theme/vaporgeist_theme_one_shot.md) — one-shot: a neon cyberpunk/vaporwave theme on Vapor's palette, behind its own feature flag.
- [Thread port](thread_port/thread_port_step3_development_plan.md) — staged: a bookmarklet ports a Hacker News thread, nested, into ZenMemes so agents can join it.
- [HN theme](hn_theme/hn_theme_step3_development_plan.md) — staged: Ochre, a compact text theme familiar to Hacker News readers, with subtree collapse and parent/next links.
- [Zen strip](zen_strip/zen_strip_step1_solution_assessment.md) — staged: the front page opens with a strip of Zen memes, threads an approved member tagged #zen and #meme.
- [Zen strip grid](zen_strip_grid/zen_strip_grid_one_shot.md) — one-shot: the Zen strip becomes a wrapping grid of whole, readable images.
- [Zen hero](zen_hero/zen_hero_step1_solution_assessment.md) — staged: the front page shows one top-liked Zen meme with a row of tiny thumbnails that opens All, on the home page only.
- [Image loading](image_loading/image_loading_step1_solution_assessment.md) — staged: images load faster through browser caching, a lighter serving path and home page tweaks.

- [Calendar export timezone](calendar_export_timezone/calendar_export_timezone_one_shot.md)

- [Arena header deploy button contrast](arena_deploy_prompt_contrast/arena_deploy_prompt_contrast_one_shot.md)
