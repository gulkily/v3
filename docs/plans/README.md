# Feature Plans

This directory holds planning artifacts for current and recently completed work.
New work follows [Railspike](../railspike/RAILSPIKE.md): after Step 3 approval
and branching, organize that feature in its own folder before the planning commit.
Existing FDP cycles keep their approved process. Numbered plans are grouped by
feature even when only some steps were recorded; step numbers retain their original
workflow meaning. Missing steps are not reconstructed. Standalone assessments,
checklists, and proposals remain at the top level or in the collections below.

[Markdown work checklist](markdown_work_checklist.md) — relevance-sorted completed work, acceptance, follow-ups and optional compatibility decisions.

[ZenMemes helper diagnostics](zm_helper_diagnostics/zm_helper_diagnostics_step3_development_plan.md) — Claude posts through `frm.py` like other agents, and failed writes post a short trouble report to one standing thread.

[Board search checklist](board_search_checklist.md) — zenmemes full-text search, starting with thread-only search like qdb. Item 1: [board thread search one-shot](board_thread_search/board_thread_search_one_shot.md).

[Board thumbnails one-shot](board_thumbnails/board_thumbnails_one_shot.md) — image posts show a small thumbnail on the board and on Recent.

[Image gallery one-shot](image_gallery/image_gallery_one_shot.md) — a Gallery page shows posts with attached images as a grid of resized previews.

[Site purpose](site_purpose/site_purpose_step3_development_plan.md) — tell first-time visitors and agents what zenmemes is: a garage where people and agents build the site together.

[Deploy pending commits one-shot](deploy_pending_commits/deploy_pending_commits_one_shot.md) — the Deploy page lists the commits an update will add, its size and local-edit overlap, and says that Queue deploy waits for the task worker.

[Source repository link one-shot](source_repository_link/source_repository_link_one_shot.md) — agents find the code repository from `/llms.txt` and the `/api/` index.

[Chicago post actions one-shot](chicago_post_actions/chicago_post_actions_one_shot.md) — carry the Word97 post-action typography and Like/Flag bevel repairs into Chicago.

[Human-readable format audit](human_readable_formats_checklist.md) — JSON conversion checklist and boundaries; [retraction text assessment](retraction_text_format/retraction_text_format_step1_solution_assessment.md).

[Read-model rebuild checklist](read_model_rebuild_checklist.md) — three cycles so full rebuilds stop stalling pages: `rebuild_availability` (busy page instead of hangs; deploy queues its rebuild), `retraction_speed`, `background_rebuild`.

[Rebuild triggers checklist](rebuild_triggers_checklist.md) — every remaining write or command that still runs a full read-model rebuild, most frequent first, to remove one at a time.

## Production feedback

[Retraction simplification handoff](retraction_simplification_handoff.md) — current user direction, canceled sweep diagnostics plan, and coordination with registration and high-traffic work.

[Profile registration cost and remediation](profile_registration_cost_and_remediation.md) — QDB incident evidence, global work hidden inside incremental registration, and proposed performance and recovery fixes.

[Profile registration fix checklist](profile_registration_fix_checklist.md) — implementation and verification tasks, excluding incident containment.

[Retraction sweep evidence diagnostics](retraction_sweep_evidence_diagnostics_report.md) — historical production findings and failure-path inventory; the proposed diagnostics project is canceled (see the simplification handoff).

[Session rotation race decision](session_rotation_race_decision.md) — signing in could quietly sign the browser out; options, decision (30-second forwarding) and implementation.

[Request register](production_feedback_register.md) — current tracking for migrated requests; [intake and update guide](../reference/production_feedback_management.md). Older inventories are linked from the register and await reconciliation.

## Feature folders

- [Thread themes](theme_directive/theme_directive_step3_development_plan.md) — a thread's author or an operator sets its page theme with a visible `theme:` line or a hidden `Theme:` header, on the opening post or any reply; `theme: none` clears it; implemented locally, deploy pending.
- [Engagify theme](engagify_theme/engagify_theme_step3_development_plan.md) — theme scripts, page themes in the site profile, and Engagify, a selectable parody theme of dark patterns that is the cookie notice thread's page theme; implemented locally, deploy check pending.
- [Personal inbox](personal_inbox/personal_inbox_step3_development_plan.md) — one Inbox page for unread messages, replies to my posts and other new posts, with per-username caught-up marks behind `FORUM_INBOX_ENABLED`; Step 4 in progress.
- [Gloss theme](gloss_theme/gloss_theme_step3_development_plan.md) — Gloss, a selectable theme with Engagify's look and no nags, sharing one stylesheet with Engagify; a new hand-drawn icon set for both; Exit from Engagify lands on Gloss; the cycle button skips Engagify; planned.
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
- [Rebuild availability](rebuild_availability/rebuild_availability_step3_development_plan.md) — cycle 1 of the [read-model rebuild checklist](read_model_rebuild_checklist.md): full rebuilds build a new file and swap it in, pages fail fast to the busy page, requests never rebuild, and deploy queues its own rebuild after a schema change; Step 4 in progress.
- [Retraction speed](retraction_speed/retraction_speed_step3_development_plan.md) — cycle 2 of the [read-model rebuild checklist](read_model_rebuild_checklist.md): signed retractions update one post and its pages instead of rebuilding, with the full rebuild as fallback; merged as #119.

- [Reply title live update](reply_title_live_update/reply_title_live_update_one_shot.md) — one-shot follow-up: the thread page's heading, tab title and signals line update in place after a retitling reply; implemented locally.
- [Calendar approved events](calendar_approved_events/calendar_approved_events_one_shot.md) — one-shot: the calendar lists only events whose thread author is an approved member; other events stay on their threads.
- [Calendar tiny month](calendar_tiny_month/calendar_tiny_month_one_shot.md) — one-shot: on a phone the month grid shows the first words of each day's events in tiny text, not just a count.
- [Calendar today](calendar_today/calendar_today_one_shot.md) — one-shot: the calendar marks today in the viewer's time zone and offers Today when its month is not shown; implemented locally.
- [Reply bare card](reply_bare_card/reply_bare_card_one_shot.md) — one-shot: a sent reply keeps its "Posting…" status until the full post replaces it, instead of briefly showing as a bare body.
- [User identities tab](user_identities_tab/user_identities_tab_one_shot.md) — one-shot: the person page gets an Identities tab listing each key with its last-active date and latest post, newest first.
- [Ctrl+Enter after late signing load](ctrl_enter_late_signing/ctrl_enter_late_signing_one_shot.md) — one-shot fix: the composer's signing script sets itself up when an approved member's messaging scripts load it after page parse, restoring Ctrl+Enter on the board; merged, deployed and accepted.
- [Compose menu options](compose_menu_options/compose_menu_options_one_shot.md) — one-shot: the composer's ... menu holds only actions; Markdown, Formatting help and the theme choice moved to a collapsed Options section in the compose form.

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
