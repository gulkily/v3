# Read-model rebuild cycles: checklist

Each section is one Railspike cycle, in order.

Problem: some writes (retraction, profile name, key retirement, and others) do a full read-model rebuild inside the request. While it runs, pages hang for up to 60 s instead of showing a "come back soon" page.

## 1. `rebuild_availability`: pages stay up during a rebuild

- [ ] Readers never wait on a full rebuild: WAL mode or build-and-swap, chosen in this cycle's Step 1
- [ ] Read connections get a short timeout (1-2 s); "database is locked" shows the busy page (503, `Retry-After`, auto-refresh)
- [ ] Page requests never rebuild or wait on the write lock; if a rebuild is queued or running, show the busy page immediately
- [ ] Deploy stays fast: after fast-forward, compare schema identity only; on a mismatch, queue `rebuild_read_model`, start a detached worker, and return
- [ ] Deploy page shows rebuild progress after the deploy result
- [ ] Applies to Deploy now, queued Deploy, and `./v3 deploy` (shared `AppUpdate::runDeploy`)

## 2. `retraction_speed`: no full rebuild for retractions or profile names

- [ ] Retraction updates the read model incrementally (`applyPostRetraction` or similar; tombstone, not removal), falling back to a full rebuild
- [ ] Retraction invalidates only affected pages (thread, listing boards, author profile, recent/activity feeds), not every static page
- [ ] Profile-name change gets the same treatment
- [ ] Measure on a qdb-sized repo: retraction under 1 s

## 3. `background_rebuild`: full rebuilds leave the request path

- [ ] A write that needs a full rebuild commits, marks the site stale, queues the rebuild, and returns
- [ ] Every full rebuild builds a new file and swaps it in (unless cycle 1 already does)
- [ ] Stale-marker recovery runs in the background, not in the next visitor's write
- [ ] Key retirement rebuilds only when stale (it currently rebuilds 2-3 times per operation)
- [ ] Attribution-cutoff and feature-flag writes use the background path
- [ ] The stale state is visible on the System status page
