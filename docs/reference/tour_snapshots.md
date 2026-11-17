# Tour snapshots

The tour at `/assets/tour/` is one row per feature: an essay beside a working copy of the page. The copies are **tour snapshots**: logged-out HTML of each page, saved ahead of time, so a visit costs static files only, however many people open it at once. The old address `/assets/tour.html` redirects there. The tour is not linked from anywhere and is `noindex`.

## Editing

Features and essays live in [`resources/tour.txt`](../../resources/tour.txt) (`Schema: tour-v1`): sections separated by `---`, each with `Name`, `Route`, `Title`, a blank line, then the essay. `Name` is the snapshot file name; `Route` is a plain public path (no query, not under `/api/`, `/internal/`, `/tools/` or `/assets/`). The page is built from [`templates/tour/page.php`](../../templates/tour/page.php). After editing, refresh.

## Refreshing

- `./v3 tour refresh` renders every route through the isolated tour worker (`scripts/tour_render.php`, the same logged-out render as the high-traffic cache, without its refresh hint) and writes `public/assets/tour/` atomically. A page that fails keeps its previous snapshot; a page that is not found (a missing thread, a feature turned off) is left out of the page. Only one refresh runs at a time.
- `./v3 tour status` shows the last refresh, each page's result, and whether a refresh is due.
- **Tools → Operations → Tour snapshots → Refresh now** starts one in the background (signed-in root operator, same checks as Deploy now).
- A refresh also starts on its own when one is due: after a deploy (the new code's read-model check; skipped while a read-model rebuild is pending), when a queued rebuild finishes, after a retraction sweep, and from the high-traffic cron where installed. It is due when the page or a snapshot is missing, `resources/tour.txt` or the page template changed, or the deployed code is newer than the page. A failed page is retried after an hour.

Status lives in `state/tour/<repository hash>/status.txt` (`Key: value`).

## Serving

The front controller serves `/assets/tour/` and `/assets/tour/<name>.html` as plain files (not on approved-members-only instances, where the renderer also refuses). Snapshots open links in the same tab (`<base target="_top">`).

## Retraction

`public/assets/tour/` is an artifact root of the [retraction sweep](retraction_sweep.md): snapshots older than an accepted retraction are retired, and the refresh the sweep starts renders them again from the current read model.

## Limits

- Snapshots reference the site's fingerprinted assets; right after a deploy that changes them, snapshots can look unstyled until the deploy-triggered refresh finishes.
- Pages a logged-out visitor cannot see (Unread, Messages) cannot be snapshots.
- The shipped `resources/tour.txt` is written for ZenMemes; on another instance its thread route is left out until edited.
