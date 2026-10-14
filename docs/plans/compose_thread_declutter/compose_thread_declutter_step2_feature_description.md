> **Feature plan:** [Step 1](./compose_thread_declutter_step1_solution_assessment.md) · [Step 2](./compose_thread_declutter_step2_feature_description.md) · [Step 3](./compose_thread_declutter_step3_development_plan.md) · [Step 4](./compose_thread_declutter_step4_implementation_summary.md)

Debug mode: enabled

## Problem
The full-page thread composer shows a heading, an implementation-detail intro, tags, subject, body, four event inputs, the image uploader, and three action buttons at once, so a simple post looks like a long form. Approved Option A, revised to apply the compact image widget everywhere, makes the body the focus and tucks the rest away.

## User stories
- As a member starting a thread, I want to see mainly the body field and one Post button so that posting feels quick and unintimidating.
- As a member who wants a subject or tags, I want to open a section below the body so that I can add them without leaving the page.
- As a member organizing an event, I want an event section I can expand so that date, time, location, and link remain available.
- As a member attaching an image on any form, I want a small control that reveals a description field once an image is added so that forms stay short until it matters.
- As a member who needs a secondary action, I want it behind a "..." menu so that the main action is unmistakable.

## Core requirements
- Order on `/compose/thread`: body, a collapsed tags/subject expando, a collapsed event expando (when events are enabled), the compact image widget, then actions.
- Collapsed fields remain in the form and submit, sign, and clear exactly as today; field names and values are unchanged. Subject remains optional.
- An expando opens automatically for values that are not the default (`general` tags are the default, so they do not open it), values supplied by query string, quote, or reply context, a server error or notice, or an unsupported-character normalization warning. The collapsed header summarizes current values (e.g. "Subject & tags · general").
- The compact image widget replaces the current uploader layout on every form that uses it (reply, board, QDB add, paned dialogs, dedicated compose). The description field appears only once an image is attached and hides again on removal; upload, eligibility, recovery, and retry behavior are unchanged.
- Trim the "Compose Thread" heading and the "Posts are stored as canonical text files…" intro to at most one short friendly line; keep an accessible page title for screen readers.
- Only the primary submit ("Create thread"/"Post") stays visible; every other action button ("Create thread anonymously", "Clear fields", and the quote-only "Remove timestamps") moves into a "..." menu that is keyboard- and touch-accessible. Each action behaves as before.
- Sections and menu have visible focus and 44 px targets.

## Delivery scope and completion boundary
**Work type: application change.** One day / at most eight stages. Release requires the normal flow working: open `/compose/thread`, write a body, optionally open sections, attach an image, or use the menu, publish, and recover from errors with affected sections visible. The compact thread variant keeps its look except for the shared image widget; no data, API, or schema changes.

## Risks
- **Shared image partial everywhere:** the new widget changes five surfaces (reply, board, QDB add, paned dialog, dedicated compose). Before Step 3, list each surface and its selector/CSS dependencies; mitigate by testing every surface and existing image browser tests.
- **Hidden warnings or errors:** collapsed fields could hide normalization warnings or server messages. Before Step 3, trace where they render; mitigate with auto-open rules tested for each.
- **Script coupling:** compose, draft-clearing, signing, and uploader scripts find fields, status paragraphs, and `data-action` buttons by selector. Before Step 3, confirm the selectors; mitigate by keeping hooks intact inside the expandos and menu.
- **Secondary-action discoverability:** moving anonymous posting, clear, and quote cleanup into "..." makes them harder to find. Mitigate with a clear menu label; revisit if users miss it.

## Shared component inventory
- `templates/partials/thread_compose_form.php`: extend; used by dedicated compose, board, QDB add, and paned new-thread dialog.
- `templates/pages/compose_thread.php`: trim heading and intro.
- `templates/partials/image_compose_fields.php`: extend with the compact widget; also used via `reply_form.php`.
- `public/assets/compose.css` and `image_upload.js`: extend for expando, menu, and widget behavior; reuse existing `<details>` patterns and compose-clear/anonymous-submit scripts.
- No new API required.

## Simple user flow
1. User opens `/compose/thread` and sees a short title, the body, collapsed tags/subject and event headers, a small image control, and a Post button with a "..." menu.
2. User writes the body and optionally expands sections or attaches an image, revealing its description field.
3. User submits; the thread publishes as before. Anonymous posting, clear, and other secondary actions are in the menu.
4. On an error or warning, the relevant section is already open.

## Success criteria
- Initial page shows one text field (the body), collapsed section headers, one image row, and one primary button plus "...".
- Threads posted with only a body, and with subject/tags/event/image values, are stored identically to before.
- Default tags do not open the expando; non-default, supplied, errored, or warned sections render open in tests.
- Every surface using the image widget works for select, replace, remove, and description; existing compose, signing, and image tests pass.
