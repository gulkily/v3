# Post-import feedback automation — implementation checklist

Outline of the manual workflow used on October 21, 2026, for a future automation. This document records implementation work to do; it does not implement or approve the automation. Importing content is a prerequisite and is outside this checklist.

Reference output: [October 21 feedback summary](zenmemes_meta_feedback_2026-10-21_summary.md). Format reference: [existing meta feedback summary](zenmemes_meta_feedback_2026-10-18_summary.md).

## 1. Capture the request and source snapshot

- [ ] Accept the source instance, local content repository, requested time window, and timezone. Support relative windows such as “the past hour or two,” resolved once against a recorded reference time.
- [ ] Accept an optional author identity and output date; do not assume that every poster is the requesting user.
- [ ] Read the latest import report and record its run ID, content commit, and partial-import limitations. Operate on accepted local records without downloading or importing again.
- [ ] Record the exact UTC window, local-time equivalent, and snapshot cutoff in the generated summary. Distinguish the request time from the latest available source data.

## 2. Discover recent feedback

- [ ] Read canonical post headers and bodies, selecting by `Created-At` rather than file modification time, path order, or import commit date.
- [ ] Search all posts in the requested window, including roots and replies, without requiring `meta`, `todo`, or any other tag.
- [ ] Resolve thread and parent relationships to recover context for terse replies such as “also” or “this one.” Label older context separately from in-window requests.
- [ ] Identify the likely feedback author from the request and source context. Flag ambiguous attribution rather than silently presenting another author's suggestions as the user's requests.
- [ ] Separate actionable requests about this repository from social posts, automated replies, bootstrap records, thread markers, and feedback about external products.
- [ ] Retain a source inventory and exclusion reasons so the coverage can be reviewed.

## 3. Compile trackable items

- [ ] Extract each distinct requested change or reported problem. Split combined posts when they describe independently trackable work.
- [ ] Merge repeated requests while retaining every supporting source link; avoid merging superficially similar reports with different symptoms or scope.
- [ ] Preserve qualifiers and uncertainty: “brainstorm,” “maybe,” and questions remain exploratory rather than becoming committed requirements.
- [ ] Preserve concrete details such as exact UI text, commands, routes, failure messages, focus behavior, and suggested visual treatment.
- [ ] Classify items as feature requests, bug reports, or Other. Keep external-product feedback and housekeeping out of the actionable repository inventory.
- [ ] Do not invent implementation decisions or claim a root cause from a symptom report.

## 4. Render the established summary format

- [ ] Produce an introduction describing the source, dates, included thread/post counts, tag-independent selection, and partial-import or snapshot limitations.
- [ ] Render separate Feature requests and Bug reports tables with `Item`, `Request`/`Report`, `Sources`, `Railspike cycles`, and `Status` columns.
- [ ] Number items consistently within each section, matching the reference summary.
- [ ] Use source post IDs as link labels and instance `/posts/{id}` permalinks as targets.
- [ ] Include provisional Railspike cycle estimates on the reference's nonnegative decimal scale, clearly labeled as estimates rather than approved plans or commitments. Mark unavailable estimates explicitly instead of fabricating them.
- [ ] Default to “Open — resolution not verified.” Change a status only when explicit evidence supports it; retain local, production, visual, and physical-device verification limits.
- [ ] Add Other and related historical-feedback notes where useful. Cross-reference an earlier item without treating an adjacent fix as proof of resolution.
- [ ] Include a Source inventory table with thread link, date, post count, and topic. State whether counts include the opening post and older context.
- [ ] Escape Markdown table delimiters and normalize multiline content without losing meaning.

## 5. Name and save the artifact

- [ ] Write directly to `docs/plans/zenmemes_meta_feedback_YYYY-MM-DD_summary.md`, using the agreed report date. Avoid an intermediate “recent todos” filename and subsequent rename.
- [ ] Preserve an existing report when the target filename already exists; support an explicit reviewed update or a distinct output filename.
- [ ] Keep generated summaries outside the vendored Railspike tree and leave `todo.txt` untouched.
- [ ] Write the completed output atomically so interruption cannot leave a truncated report.
- [ ] Retain enough provenance to recognize a repeated run against the same snapshot and inputs. If updating an existing report, preserve reviewed statuses, evidence, and manual notes.

## 6. Verify and hand off

- [ ] Verify that every extracted actionable request appears once, all source links resolve to included local records, and every item can be traced to its source text.
- [ ] Check counts, timestamps, section numbering, table structure, and repository-relative document links.
- [ ] Exercise representative cases: untagged feedback, replies to older threads, ambiguous authors, duplicate requests, combined requests, exploratory ideas, external-product feedback, partial imports, empty windows, date boundaries, and existing output files.
- [ ] Use the October 21 snapshot as an acceptance fixture: recover the 13 actionable replies in the todo thread and the same 16 distinct requests; the source inventory includes 14 posts with the root marker.
- [ ] Compare meaning and source coverage with the reference summary rather than requiring identical prose or invented precision in estimates.
- [ ] Run `git diff --check` and review the generated Markdown. Verify rendered tables in the intended viewer, especially long source links and request text.
- [ ] Return a clickable local file link and a concise account of included items, omissions, and unresolved attribution or coverage questions.

## Optional terminal viewing

The conversation also covered reading the output in Ubuntu with Glow. This is a convenience after generation, not a required dependency of the automation.

- [ ] Document an optional preview command: `glow -p docs/plans/zenmemes_meta_feedback_YYYY-MM-DD_summary.md`.
- [ ] Keep viewer installation separate from report generation; do not install packages or change system repositories as a side effect.

## Planning boundary

Before implementing this automation, use the repository's Railspike workflow to select the entry point and agree on extraction, rerun, and review behavior. This retrospective checklist does not select a CLI command name, add scheduling, publish to the source instance, or authorize implementing the feedback items themselves.
