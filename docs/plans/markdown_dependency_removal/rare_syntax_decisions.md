# Rare-syntax decision table

Threshold approved with Step 3: at most five distinct documents per source syntax/variant, including zero-use rows. Counts below are **source candidates**, not all proven semantic uses; the independent semantic reference totals and counting caveats are in [render metrics](render_metrics.md). Keep full counts and source examples so the threshold can change. No implement/defer/omit choice is made for the user. All extension decisions remain pending in the [separate extension cycle](../markdown_renderer_extension/markdown_renderer_extension_step1_solution_assessment.md).

| Syntax/variant | Candidate uses | Documents | Examples (line) | Restored behavior / safe fallback | Importance; effort/risk | Decision |
| --- | ---: | ---: | --- | --- | --- | --- |
| autolink_email_candidate | 0 | 0 | No detected uses; not proof of impossibility | Literal escaped source, no automatic link | Navigation; medium, URL policy | Pending user choice |
| autolink_url | 1 | 1 | `docs/runbooks/phone_site_development_live_walkthrough.md:49` | Literal escaped angle URL | Navigation; medium, URL policy | Pending user choice |
| blockquote_nested_line | 0 | 0 | No detected uses; not proof of impossibility | Escaped inner marker in flat quote | Structure; medium | Pending user choice |
| fence_mermaid | 6 | 2 | `docs/architecture/private_messaging.md:40`; `docs/railspike/README.md:106` | Existing labeled fenced source; no diagram execution | Architecture; high for diagram engine, retain safe source | Pending user choice |
| fence_tilde | 0 | 0 | No detected uses; not proof of impossibility | Escaped source; not a code block | Commands/examples; medium, fence boundaries | Pending user choice |
| footnote_candidate | 0 | 0 | No detected uses; not proof of impossibility | Literal source | References; medium/high, cross-links | Pending user choice |
| front_matter | 1 | 1 | `docs/research/sigtbd_fault_tolerant_governance_slides_5min_marp.md:1` | Retained collapsed escaped metadata | Presentation safety; do not apply CSS, already retained | Pending user choice |
| gfm_alert_candidate | 0 | 0 | No detected uses; not proof of impossibility | Readable quote markers | Warnings can be critical; medium | Pending user choice |
| hardbreak_backslash | 0 | 0 | No detected uses; not proof of impossibility | Literal backslash | Line layout; low/medium | Pending user choice |
| html_comment_start | 10 | 2 | `docs/railspike/README.md:305`; `docs/research/sigtbd_fault_tolerant_governance_slides_5min_marp.md:41` | Prose comments suppressed; fenced source preserved | Privacy/readability; retained safety behavior | Pending user choice |
| image_markdown_candidate | 0 | 0 | No detected uses; not proof of impossibility | Alt text plus image unavailable; no fetch | External access/privacy; medium/high | Pending user choice |
| link_reference_explicit_candidate | 0 | 0 | No detected uses; not proof of impossibility | Inspect recorded source locations; plain escaped text if unsupported | Manual review; effort pending | Pending user choice |
| reference_definition | 26 | 1 | `docs/plans/thywill_site_option_review.md:123` | Literal definitions; no reference expansion | Citations; medium/high, document link policy | Pending user choice |
| strikethrough_candidate | 1 | 1 | `docs/plans/chouse_club_hosting_plan_v1.md:141` | Literal tildes | Resolved-state meaning; low/medium | Pending user choice |
| strong_underscore_candidate | 1 | 1 | `docs/plans/backlog/fast_post_scoring_mvp_product_plan_v1.md:77` | Literal underscores | Emphasis; low/medium | Pending user choice |
| thematic_break_candidate | 18 | 5 | `docs/plans/backlog/v3_terminal_operator_ui_step1_solution_assessment.md:8`; `docs/plans/ephemeral_testing_tasks.md:24` | Inspect recorded source locations; plain escaped text if unsupported | Manual review; effort pending | Pending user choice |
| unclosed_fence | 0 | 0 | No detected uses; not proof of impossibility | Remaining text escaped as code | Malformed source; retain safe output | Pending user choice |

Critical uses override rarity: safe links/anchors, procedure numbering, escaping and source-file boundaries remain required. Existing preserved behavior is documented here without implying new syntax approval. Frequent unsupported forms (nested/loose lists, continuations, tasks and pipe tables) also need prioritization in the extension cycle; rarity filtering does not imply they are already implemented.
