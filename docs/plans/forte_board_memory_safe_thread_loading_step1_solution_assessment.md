> **Feature plan:** [Step 1](./forte_board_memory_safe_thread_loading_step1_solution_assessment.md) · [Step 2](./forte_board_memory_safe_thread_loading_step2_feature_description.md) · [Step 3](./forte_board_memory_safe_thread_loading_step3_development_plan.md) · [Step 4](./forte_board_memory_safe_thread_loading_step4_implementation_summary.md)

# Forte Board Memory-Safe Thread Loading Step 1 Solution Assessment

## Original Query

Please help me debug this issue with the Forte interface:

`[Tue Oct 06 14:45:24 2026] [qdb.us] [warn] [client 73.149.153.67:5088] [pid 856328] fcgid_bucket.c(153): mod_fcgid: stderr: PHP Fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 8388616 bytes) in /home/qdb/v3/src/ForumRewrite/View/TemplateRenderer.php on line 384`

Once you're done investigating, please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md for a fix.

## Understood Intent

Prevent `/forte` from exhausting PHP memory as the board grows, while retaining its three-pane thread-selection experience.

## Problem Statement

Forte renders every thread and reply into hidden page content, so its request memory and response size grow with the entire board rather than the selected thread.

## Option A — Increase the PHP memory limit

Pros:
- Fast operational mitigation.
- No application behavior changes.

Cons:
- Does not bound memory or response growth.
- Will fail again as content grows.

## Option B — Render only the selected thread and use full-page navigation

Pros:
- Bounds initial render work to one thread.
- Smallest application change.

Cons:
- Replaces in-place selection with page loads.
- Weakens the Forte reader experience.

## Option C — Load the selected thread's rendered pane on demand

Pros:
- Bounds initial-page rendering to the thread list and selected thread.
- Preserves in-place selection, URL/history behavior, replies, reactions, and compose targeting.
- Creates a reusable thread-detail boundary without a schema change.

Cons:
- Adds a client/server loading and error state to test.

## Option D — On-demand loading with bounded progressive preloading

Pros:
- Retains Option C's bounded PHP request memory and small initial document.
- Preloads nearby and visible-list threads in small background batches, making common navigation immediate.
- A small board can become fully cached in the browser; a large board retains only a size- and count-bounded working set.

Cons:
- Requires cache eviction, request prioritization, and offline/error behavior to be defined.
- Background loading adds controlled server work after initial page load.

## Recommendation

Recommend Option D, with Option A used only as a temporary deployment safeguard if needed. It is a viable vertical slice: opening or selecting a thread loads its complete pane without rendering other threads' bodies or replies in the initial document, including direct permalinks and recoverable load failures. Preloading must be incremental and budgeted rather than server-rendering the full board to decide whether it is small enough.
