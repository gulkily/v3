# Activity repository coverage checklist

Activity should report every change committed to the **content repository**, including files and record types it does not recognize. An unrecognized type must appear as **Unrecognized type**, with its path and available change metadata, rather than being silently omitted. This concerns the content repository, not the application source repository.

This is a backlog for separate work. It does not expand or approve implementation of the [thread-like Activity plan](../thread_like_activity/thread_like_activity_step1_solution_assessment.md).

## Current reporting gaps

The ordinary All Activity feed derives selected semantic events. Its separate Commits view already provides broader commit/file history; the gaps below concern ordinary Activity visibility, not whether the files exist in Git or backups.

- [x] **Later thread likes:** each distinct accepted eligible contribution is reported, even when `like` is already present, with per-record evidence and rebuild recovery. Completed and merged in `e507cb71`; [implementation and verification](../thread_like_activity/thread_like_activity_step4_implementation_summary.md).
- [ ] **Other repeated thread labels:** report accepted label additions that do not introduce a previously absent label. Currently these can disappear for the same reason as later likes.
- [ ] **Post retractions:** report the retraction action at its own time, with its actual actor and target. Updating the original post to a retraction notice does not report the new retraction record as an event.
- [ ] **Physical record deletions:** report the deletion even though its source file no longer exists in the current tree.
- [ ] **Source-removal evidence and legacy label repairs:** report newly accepted removal/ownership-resolution records and their subsequent changes, including `records/post-removals/`.
- [ ] **Generated thread-subject changes:** report records under `records/thread-subjects/`; currently these change the displayed subject without a dedicated Activity event.
- [ ] **Approval-seed changes:** report additions, modifications and removals under `records/approval-seeds/`; changes to effective approval state are not a complete record of these actions.
- [ ] **Instance metadata:** report changes to `records/instance/public.txt` and other instance records without requiring a specially recognized feature-flag event.
- [ ] **Supporting identity/key files:** account for identity records and public keys individually or as clearly listed files within their associated action. Identity/bootstrap events are not separate entries for every supporting file.
- [ ] **Signatures and acceptance receipts:** account for detached signatures and `records/approval-acceptances/` records, including later changes. They are currently supporting details rather than consistently independent events.
- [ ] **Legacy timestamp records:** account for `records/post-timestamps/`; recovering a post's original date does not itself report the metadata addition.
- [ ] **Conditionally hidden username-change events:** retain repository-change visibility when the username-change feature is off; currently that setting can hide indexed name-change events from All Activity.
- [ ] **General modifications and renames:** report changes to existing records, not merely their current derived value or original creation event. Recognized feature-flag/name-change histories cover particular cases, not arbitrary repository edits.
- [ ] **Unrecognized record families and other committed files:** add a fallback for paths without a recognized semantic event, including files outside `records/` in the content repository.
- [ ] **Malformed or unsupported recognized records:** report their addition/change with an unreadable or unsupported status instead of losing the repository event when parsing fails.

## Future coverage checks

- [ ] Account for added, modified, deleted and renamed files; use a fallback for other Git change statuses.
- [ ] Keep paths, commit identity, change time and actor available when known. Distinguish a record's original creation time from its later import/change time; do not invent missing authorship.
- [ ] Account for every file in bulk imports and mixed known/unknown commits. Supporting files may be grouped, but remain discoverable and must not disappear because another file supplied the visible event.
- [ ] Show **Unrecognized type** without requiring a new parser or deployment merely to make the change visible. A recognized but malformed record should retain that distinction.
- [ ] Preserve history when a contribution is withdrawn. Its current effect can stop applying without erasing the fact that an addition and withdrawal occurred.
- [ ] Keep ordinary writes, imports and rebuilds consistent, with stable event identities and no duplicate entries from retries.
- [ ] Keep All Activity, classic/Forte views, counts, pagination and supported feeds consistent. Filtered views may intentionally show a subset; All Activity must retain the complete repository-change inventory.
- [ ] Preserve existing access controls and retraction/content-suppression rules. Reporting that a file changed does not require exposing private or retracted payloads.
- [ ] Verify the inventory against Git history, including unknown files, initial commits, edits, renames, deletions, bulk imports and replay/rebuild scenarios.

Displaying an action does not automatically make it retractable. Withdrawal of button-added likes remains a separate capability from complete reporting.

## Audit basis

Initial review: 2026-10-23 UTC, application revision `39be8293`. Unchecked items describe reporting gaps and follow-up acceptance checks from that review; checked items record subsequently completed work. Existing reply-reaction records already generate per-record events; they are not included as a confirmed omission.

- [Activity event generation](../../../src/ForumRewrite/ReadModel/ReadModelBuilder.php): selected post, label, reaction, username and feature-flag events.
- [Incremental label activity](../../../src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php): non-like labels retain first-effective-appearance reporting; eligible like contributions now produce per-record events.
- [Activity filtering and commit manifests](../../../src/ForumRewrite/Activity/ActivityService.php): conditional visibility and separate commit/file history.
