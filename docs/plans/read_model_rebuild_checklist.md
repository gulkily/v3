# Read-model rebuild cycles: checklist

Each section is one Railspike cycle, in order.

Problem: some writes (retraction, profile name, key retirement, and others) do a full read-model rebuild inside the request. While it runs, pages hang for up to 60 s instead of showing a "come back soon" page.

## 1. `rebuild_availability`: pages stay up during a rebuild

[Plans](rebuild_availability/rebuild_availability_step3_development_plan.md); Step 1 chose build-and-swap over WAL.

- [ ] Readers never wait on a full rebuild: WAL mode or build-and-swap, chosen in this cycle's Step 1
- [ ] Read connections get a short timeout (1-2 s); "database is locked" shows the busy page (503, `Retry-After`, auto-refresh)
- [ ] Page requests never rebuild or wait on the write lock; if a rebuild is queued or running, show the busy page immediately
- [ ] Deploy stays fast: after fast-forward, compare schema identity only; on a mismatch, queue `rebuild_read_model`, start a detached worker, and return
- [ ] Deploy page shows rebuild progress after the deploy result
- [ ] Applies to Deploy now, queued Deploy, and `./v3 deploy` (shared `AppUpdate::runDeploy`)

## 2. `retraction_speed`: no full rebuild for retractions

[Plans](retraction_speed/retraction_speed_step3_development_plan.md); Step 1 found retractions already map to `delete-record`'s placeholder, so they reuse its targeted update.

- [x] Retraction updates the read model for one post (`applyPostRemoval`, shared with `delete-record`), falling back to a full rebuild
- [x] Retraction clears only the pages that showed the post, not every static page
- [x] Measure on a qdb-sized repo: the targeted removal took 0.24s locally, against 7.0s for a full rebuild
- [ ] Live check on zenmemes.com after deploy
- Profile-name changes moved to cycle 3 (usernames are copied widely and claimed first-come, so an in-place update is risky for a rare action).

## 3. `background_rebuild`: full rebuilds leave the request path

- [ ] A write that needs a full rebuild commits, marks the site stale, queues the rebuild, and returns
- [ ] Every full rebuild builds a new file and swaps it in (unless cycle 1 already does)
- [ ] Stale-marker recovery runs in the background, not in the next visitor's write
- [ ] Key retirement rebuilds only when stale (it currently rebuilds 2-3 times per operation)
- [ ] Attribution-cutoff and feature-flag writes use the background path
- [ ] Profile-name changes use the background path (moved from cycle 2)
- [ ] The stale state is visible on the System status page
