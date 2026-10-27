# Why profile registration is expensive, and how to fix it

Assessment dated 2026-10-24, against application commit `2d91c8029` and the QDB production evidence supplied during the incident. This document explains the current behavior and proposes repairs; it does not record an implemented performance fix or an approved implementation cycle.

Registering one identity currently refreshes derived state for the entire community. The code calls this an incremental update because it avoids dropping and rebuilding the database, but it still loads all profiles and posts, recalculates approval and counts, updates every profile, and refreshes activity attribution separately for every identity. Those activity updates lack an index on the field they search. All of this runs inside a database transaction while holding the shared execution lock.

That combination makes an ordinary registration capable of blocking the home page and user directory. Repeated registrations can keep the site almost continuously busy even when the task queue is empty and SQLite can still serve reads.

The expensive all-profile loop predates retractions. `git blame` attributes `refreshProfileState()` to commit `4d38fad17e8f7832bd26f031685a5fd1e0f3aae5`, “Add incremental identity link updater,” dated April 23, 2026. Retraction publication locking is part of the current write path, but the repeated profile/activity work was already present. The earlier Zenmemes retraction failure and this QDB registration bottleneck need separate causal explanations.

The evidence has these limits:

| Evidence | What it establishes |
| --- | --- |
| Manual rebuild completed in 158.431 seconds, producing 18,750 posts, 3,835 profiles, and 18,853 activity rows | The candidate rebuild succeeded at this production scale. It did not prevent subsequent writes from creating contention. |
| Subsequent status reported stale metadata, an execution lock, and a SQLite write lock; the queue had no running tasks | A writer was active outside the reported queue lifecycle. This alone does not identify the process or prove a full rebuild. |
| Five successive commits were signed identity registrations at 07:05:48, 07:07:48, 07:10:09, 07:12:12, and 07:15:01 UTC | Registrations were repeatedly changing the canonical repository. This does not establish who initiated them or whether the traffic was abusive. |
| Commit `c9fa9fa` was created at 07:10:09; visible metadata recorded that commit with `write_incremental` at 07:11:42 | There was a 93-second commit-to-metadata interval. It is consistent with expensive derived-state work, but is not a measurement of any individual function. |
| Local SQLite query-plan check against the application schema returned `SCAN activity` | The current author-specific activity update has no usable author index. Adding the proposed index in memory changed the plan to `SEARCH activity USING INDEX activity_author_identity_idx (author_identity_id=?)`. |

Production stage timings and the identity of the lock holder at each status sample are still missing. The code establishes a real scaling defect and a mechanism consistent with the outage; it does not establish that this defect accounts for every second of host latency.

Registration has a legitimate burden beyond inserting a username. A profile binds a verified key to an identity, a bootstrap post and thread, a username route, authorship, approval state, and public activity. Linking an identity to existing content can change historical attribution. Making an approval seed or previously missing identity available can affect other identities through approval chains and invitations. Those relationships explain why a general recomputation was convenient. They do not require every registration to rewrite every profile and activity author.

The signed browser flow uses `/api/prepare_identity` followed by `/api/create_identity`. In [LocalWriteService](../../src/ForumRewrite/Write/LocalWriteService.php), `createIdentityBootstrap()` enters `withTimedWriteLock()`, which acquires the execution lock and the retraction publication lock. The operation verifies the submitted material, writes the identity/bootstrap/key files, commits them to Git, synchronizes derived state, and invalidates affected artifacts before returning. Offline snapshot publication is enqueued after the locked callback finishes.

Within the incremental path, [IncrementalReadModelUpdater::applyIdentityLink()](../../src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php) performs this sequence:

1. Start a SQLite transaction and insert the profile.
2. Ensure the bootstrap post and username route exist.
3. Link this identity's posts and activity.
4. Call `refreshProfileState()` for the whole site.
5. Write repository-head metadata and commit the transaction.

The fourth step expands a local change into global work:

| Operation | Scope and cost |
| --- | --- |
| Load profiles | All profiles, including identities unrelated to the new registration. |
| Load posts for derivation | All posts, including bodies and board tags, materialized in PHP arrays. |
| Derive approval state | Repeated passes over posts until approvals stop changing; invitation candidate replay is also invoked on each pass. |
| Derive profile counts | Another pass over posts and profiles, preserving hidden-bootstrap and ownership rules. |
| Update profiles | One update for every profile, even when its derived fields are unchanged. |
| Relink activity | For every profile, read its approval fields and update activity matching its identity. The unindexed activity predicate causes a table scan for each profile. |

Let `U` be profiles, `P` posts, `A` activity rows, and `R` approval passes. The repeated activity lookup contributes approximately `O(U × A)` scan work. Approval derivation adds repeated post processing, with further invitation replay/sorting costs. Counts and the unconditional profile updates add more work. At the reported QDB scale, `3,835 × 18,853 = 72,301,255` row examinations is a useful estimate of the activity-scan component alone. It is not a measured number of physical disk reads: caching, SQLite's execution, and host scheduling affect the actual cost.

The missing index is explicit in [ReadModelSchema](../../src/ForumRewrite/ReadModel/ReadModelSchema.php). Activity has indexes for recency, post ID, and action key, but none for `author_identity_id`. The profile identity is a primary key, so the per-profile approval lookup is indexed; the expensive scan is the subsequent activity update. `linkIdentityPosts()` also searches by bootstrap post or author identity, and the current posts schema has no author-identity index. That is another query to measure, although it is not the repeated activity scan.

The lock makes the cost visible to readers. Git is committed before SQLite metadata is updated. During that interval, [Application::ensureReadModel()](../../src/ForumRewrite/Application.php) sees a repository-head mismatch on ordinary dynamic requests and attempts to acquire the execution lock. The registration already owns it. The default lock wait is five seconds, after which the front controller can return the site's busy page. A successful registration lasting tens of seconds is sufficient to cause this behavior; a deadlock or failed rebuild is not required.

```mermaid
sequenceDiagram
    participant W as Registration request
    participant G as Canonical Git repository
    participant D as SQLite read model
    participant R as Ordinary page request
    W->>W: Acquire execution and publication locks
    W->>G: Commit new identity (head H1)
    W->>D: Start derived-state transaction
    R->>G: Read head H1
    R->>D: Read committed metadata H0
    R->>R: Wait for execution lock
    W->>D: Refresh all profiles and activity
    R-->>R: Lock wait expires; busy response
    W->>D: Write metadata H1 and commit
    W->>W: Release locks
```

The [identity artifact invalidator](../../src/ForumRewrite/Write/StaticArtifactInvalidator.php) also retires generated user-directory, activity, instance, and identity-related pages. That can send subsequent requests through PHP. It does not directly invalidate the home page in `invalidateIdentityLink()`; the home-page symptom can arise through its existing dynamic path and freshness check. A full static build is therefore not a durable repair for the registration cost.

An incremental failure can additionally fall back to a full rebuild inside the write operation. `canIncrementallyUpdateReadModel()` checks database existence, stale marker, schema identity, and repository path; it does not verify that the model represents the canonical parent of the new commit. These are separate recovery/consistency issues to address. The observed `write_incremental` metadata does not prove that a full rebuild occurred during this incident.

The recommended repair sequence is:

1. **Contain the incident while preserving the data.** Temporarily reject new identity-registration POST requests in Apache before they enter PHP. The proposed rule below belongs immediately after `RewriteEngine On` in the deployment's existing `public/.htaccess`; preserve its QDB configuration. This pauses new browser-key registration, including first-use actions that need an identity. Existing operations still need to finish. Observe the locks and repository/model heads afterward; do not repeatedly rebuild or unlink lock files. This containment has been proposed, not confirmed applied.

   ```apache
   # Temporary incident containment: pause new identity registration.
   RewriteCond %{REQUEST_METHOD} =POST
   RewriteRule ^(?:api/(?:prepare_identity|create_identity|link_identity)|account/key/?)$ - [R=503,L]
   ```

2. **Remove the repeated full-table lookup first.** Add an index on `activity(author_identity_id)` through the maintained schema. A proposed definition is `CREATE INDEX activity_author_identity_idx ON activity (author_identity_id)`. Confirm the actual update's query plan and measure the resulting registration stages. This is a small, broadly useful mitigation, but still leaves all-profile processing and many redundant updates. It cannot justify a promised production latency without measurements. Assess a posts author index separately using its actual query plan and workload.

3. **Stop writing unchanged derived state.** As an intermediate repair, compute a change set and update profile fields only when their values differ. Refresh activity for the newly linked identity and identities whose public attribution or approval changed. Pass the already-computed approval state rather than querying it again for each profile. This can preserve the current derivation semantics while removing the unconditional all-profile activity loop. It still leaves full post loading and approval replay, so it is not the final scaling fix.

4. **Make ordinary registration proportional to its effects.** Distinguish a genuinely new identity/bootstrap from linking an identity with existing content or approval dependencies. For a new independent identity, insert the profile, hidden bootstrap records and route, establish the applicable approval state, and update only its records. Do not assume every new identity is unapproved or has no prior references: check canonical seeds, existing authorship, invitations, and approval dependencies. For linking/import cases, query affected content by identity and propagate approval changes through a bounded worklist of dependent identities. Materializing/indexing those dependencies is a separate schema/design change. Preserve deterministic approval ordering and provenance, and deduplicate the worklist so cycles cannot cause endless processing.

5. **Shorten and make recovery explicit.** Separate expensive parsing/signature preparation from the critical section where safe, then revalidate the facts needed for acceptance under the lock. Keep canonical ordering and publication/retraction protections intact. Validate that an incremental update starts from its expected canonical parent; handle mismatches through explicit reconciliation rather than stamping a newer head onto unprocessed changes. Replace silent, unbounded full-rebuild fallback in a request with an observable, deduplicated maintenance operation and a clear response when canonical data was saved but derived state is pending. Candidate rebuilding and promotion already exist as mechanisms, but concurrency and head changes must be handled deliberately.

6. **Review read availability separately.** Serving a last-committed snapshot while a routine write completes could prevent readers from joining the write lock queue. It needs an explicit policy for safe presentation staleness versus current authorization, retractions, and privacy state. Simply disabling freshness checks or increasing timeouts would hide the problem or risk serving invalid state. WAL alone would not remove this application-level lock wait either. Make this an additional design effort rather than a prerequisite for the index and targeted-update repairs.

The schema repair must follow [CONTRIBUTING.md](../../CONTRIBUTING.md): increment `ReadModelMetadata::SCHEMA_VERSION` from the current 25 to the next available version, update [the read-model schema specification](../specs/read_model_schema_v1.md), add a prior-schema startup regression, and verify `./v3 rebuild` plus affected UI/API flows. An index inserted manually into production can disappear on the next rebuild and is not a maintained fix. Plan deployment so the migration does not unexpectedly launch a large web-request rebuild: prepare and validate the compatible candidate during controlled maintenance, coordinate writes and code activation, and promote through the supported path. Never relabel old metadata to bypass validation.

Verification should establish both correctness and a bounded cost:

- Compare fresh registration, linking existing authorship, seeded identities, transitive approvals, invitation redemption, and duplicate/retried registration against canonical expectations and a full rebuild. Preserve usernames, hidden bootstrap counting, public attribution, approval receipts, retraction behavior, and profile/activity visibility. Investigate any existing incremental/full-build discrepancy rather than blindly treating either implementation as the oracle.
- Exercise failures before canonical commit, after commit but before SQLite commit, and during recovery. A retry must not create another identity or silently claim that unapplied commits are indexed. Verify serialized competing writers and repository advancement during candidate preparation/promotion.
- Use a representative disposable fixture of at least roughly 19,000 posts/activity rows and 4,000 identities. Grow the unrelated identity count independently: a single independent registration must not produce one activity update per unrelated profile. Inspect query plans for the targeted lookups. Keep timing benchmarks separate from deterministic correctness tests.
- Record lock wait and hold duration, signature verification, Git commit, approval derivation, profile counts, changed-profile count, affected activity rows, transaction commit, artifact invalidation, peak memory, and fallback reason. Existing identity timing fields provide a starting point; make them accessible in operator diagnostics without exposing keys or submitted private material.
- During controlled registration traffic on a representative host, measure registration latency and concurrent `/` and `/users/` success/latency. Acceptance should require that registrations no longer produce busy responses on those reads. Establish numerical budgets from the host baseline and leave substantial margin below the five-second lock timeout; no production speedup is measured yet.
- Verify migration from schema 25, candidate promotion, CLI and web configuration agreement, and recovery after interrupted work. Remove the temporary Apache restriction only after the repaired flow passes verification, then monitor real lock duration and errors.

No new third-party dependency is needed for these proposals. This assessment used source/history inspection and a disposable in-memory SQLite query-plan comparison; it did not alter the application's schema, production data, or registration behavior. The implementation should enter the repository's required planning/review process with the index mitigation and targeted-update work scoped separately. Related incident notes are in [QDB lock diagnostics](qdb_lock_diagnostics.txt).
