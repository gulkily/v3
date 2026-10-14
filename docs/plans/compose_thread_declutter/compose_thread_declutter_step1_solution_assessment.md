> **Feature plan:** [Step 1](./compose_thread_declutter_step1_solution_assessment.md) · [Step 2](./compose_thread_declutter_step2_feature_description.md) · [Step 3](./compose_thread_declutter_step3_development_plan.md) · [Step 4](./compose_thread_declutter_step4_implementation_summary.md)

Debug mode: enabled

## Original Query
As a user, I feel intimidated by the /compose/thread page. Let's collapse the event section into an expando, collapse the image section into one small widget that expands to allow description when an image is already added, and collapse the tags and subject fields into another expando under the body (before the event section). Please write Step 1 of Railspike.

## Understood Intent
Make the full-page thread composer look like "write a post" first: the body is the dominant field, and tags/subject, event details, and the image description stay available but tucked away. Field names, signing, and submission behavior are unchanged.

## Problem Statement
The compose-thread page shows every field at once (tags, subject, body, four event inputs, image dropzone, description, help text), which makes a simple post look like a long form.

## Option A: Native disclosure sections plus a compact image widget
Wrap tags/subject (directly under the body) and the event inputs in `<details>` expandos that start closed, and shrink the image block to a single-line "Add image" control whose description field appears only after an image is attached. Sections open automatically when they hold a prefilled value, a validation error, or a normalization warning.

- **Pros:** No new state machinery for the expandos; collapsed fields stay in the form so submission and signing are untouched; keyboard and screen-reader behavior is built in; matches existing `<details>` usage on other pages.
- **Cons:** The image widget needs a small change to the shared uploader render logic and affects every page that reuses the image partial; auto-open rules need care so warnings are never hidden.

## Option B: Scripted progressive disclosure
Replace the fields with "Add subject/tags", "Add event", and "Add image" text links that reveal inline fields via JavaScript, remembering what the user opened.

- **Pros:** Cleanest first impression; can animate and tailor wording per link.
- **Cons:** Fields do not exist for no-JS or failed-script cases unless duplicated; more behavior to test with the signing/outbox flow; larger than one cycle's worth of value over Option A.

## Option C: Visual simplification only
Keep every field visible but restyle: smaller event/image blocks, shorter help text, lighter labels.

- **Pros:** Lowest risk; no behavior or shared-partial change.
- **Cons:** Does not meaningfully reduce the form's length, so it likely leaves the intimidation problem unsolved.

## Recommendation
Choose **Option A**. It delivers the requested layout (body first, then a tags/subject expando, then an event expando, with a one-line image widget) as a single releasable slice: a user can write and publish a thread using only the body, and open any section to use it as before. Scope the image-widget change to the full-page composer by keeping the compact/paned variants' appearance unchanged, and verify auto-open for prefilled values, errors, and normalization warnings.
