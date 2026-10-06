# Dev server output improvements plan

## Problem

`./v3 start` runs the PHP built-in server directly:

```bash
php -S "${ADDRESS}" -t "${ROOT}/public" "${ROOT}/public/router.php"
```

(`v3`, `start` case.) The output is hard to use for day-to-day work:

- **Noisy.** Every connection prints an `Accepted` and a `Closing` line, even for
  requests that were never interesting.
- **Wrong information.** The remote port and open/close status are shown, but the
  thing the developer wants, the requested URL and what happened to it, is often
  missing.
- **Missing request lines for pages.** Verified with `curl` against the running
  server: a request to `/` (200) and a request to `/does-not-exist-page` (404)
  each produced only `Accepted` / `Closing` lines, with no URL or status. Only
  requests the router hands back to PHP as static files (`/assets/*`,
  `/favicon.ico`, `/manifest.webmanifest`, `/service_worker.js`, which
  `public/router.php` returns with `return false`) get a `[200]: GET ...` line.
  The app's own pages, the 404s and the redirects are all silent.
- **No identity.** Once the startup banner scrolls off, nothing on screen says
  which port or which checkout a line belongs to. This matters when two
  checkouts or two ports are running.
- **Timestamps on everything.** The timestamp is useful sometimes, but it adds
  width to every line and does nothing to show which lines belong together.
- **Hard to see bursts.** A page load produces a cluster of asset requests. Nothing
  separates one page load from the next, so the cluster is hard to pick out.

## Goals

1. Show one line per meaningful request: method, URL (with query string), status,
   duration, and the reason for anything unusual (redirect target, route or
   handler name, error message).
2. Show every request for the app's pages, including 404s and redirects, not just
   static files.
3. Hide connection-level noise (`Accepted`, `Closing`, remote ports) by default.
4. Make the server identity (port, and optionally checkout name) visible on every
   line, not only in the banner.
5. Make chronology easy to scan: group bursts visually, and show full timestamps
   only where they help.
6. Keep the defaults quiet. Assets and everything else noisy sit behind a flag.

## Proposed output

Default output (assets hidden, one line per page request):

```
v3 :8001  listening on 127.0.0.1:8001  (ctrl+c to stop)

01:20:07 :8001  200  GET   /                              18ms  home
01:20:09 :8001  200  GET   /qdb/123                       42ms  qdb.permalink
01:20:09 :8001  404  GET   /missing-page                  2ms  -
                                                          ^ blank line when gap > 2s

01:20:31 :8001  302  POST  /login                        31ms  -> /home
01:20:31 :8001  500  GET   /thread/9                     88ms  thread.show  ERROR: ...
```

With `--verbose` (or `V3_DEV_LOG=verbose`), `/assets/*` and other static files are
included, shown dimmer than page requests:

```
01:20:07 :8001  200  GET   /assets/inline_reply_form.90d2e7257cd6.js   static
```

Notes on the format:

- Port is on every line, so lines from two servers can be told apart after they
  scroll.
- Status codes are colored when output is a TTY: 2xx default or green, 3xx cyan,
  4xx yellow, 5xx red. Color is off when piped, and `NO_COLOR` is respected.
- Full `HH:MM:SS` is printed on every line. A blank line marks a gap of more than
  about two seconds, so bursts stand apart without relying on timestamps. A
  relative `+0.4s` column can be added later if the absolute time proves noisy.
- Duration lets slow requests be spotted without a separate tool.
- The trailing "why" column carries the redirect target, the matched route or
  handler, or the error message. This is the "sometimes why" the developer asked
  for.

## Implementation plan

### Step 1: Filter connection noise and tag each line with the port (small, no PHP changes)

Change the `start` case in `v3` so `php -S` output goes through a filter:

- Pipe `2>&1` into a small filter (awk, or a short PHP script under `scripts/`)
  that drops lines matching `Accepted$` and `Closing$`.
- Prefix each remaining line with the port, and keep the startup banner line as
  the first line.
- Keep `[200]: GET` lines for static files, but reformat them to the proposed
  shape.

This alone removes most of the noise and fixes the identity problem. It does not
fix the missing page lines. Check first that stderr buffering does not delay
output. A `stdbuf -oL` or unbuffered pipe may be needed.

Optional, cheap: set the terminal title with an OSC escape
(`printf '\033]0;v3 :%s\007' "$PORT"`), so the tab or window shows the port even
after scrolling.

### Step 2: Log page requests from the router (the main fix)

`public/router.php` is the one place every request passes through. Add logging
there before `require __DIR__ . '/index.php'`:

- Record `microtime(true)` at the start.
- Register a shutdown function that reads `http_response_code()` (or the default
  200), computes the duration, and writes one line with `error_log()` or
  `fwrite(STDERR, ...)`. This catches 404s, redirects and fatal errors too.
- Log the method and the full `REQUEST_URI`, including the query string.
- For redirects, read the `Location` header with `headers_list()` and include it.
- For the "why" column, have the dispatcher in `index.php` (or the route table)
  set a small global or static such as `$GLOBALS['v3_dev_route'] = 'thread.show'`
  when a route matches. Investigate the dispatcher first. This may need a few
  lines in `src/` and is the part most worth checking before committing to it.
- Mark static-file requests as `static` in the router branch that returns `false`,
  so the same logger can handle both kinds of request.
- Gate everything on an environment variable, for example `V3_DEV_LOG`, so
  production and tests are unaffected. The `v3 start` command sets it. Tests
  should not see the output.

Because `php -S` writes `error_log` output to the same stderr stream, these lines
land in the same place as the filtered built-in lines and go through the same
Step 1 formatting.

### Step 3: Colors, grouping and gap separators

- Add the status coloring and the 2-second gap blank line in the filter from
  Step 1, or in the router logger, whichever owns the formatting. Keep one place
  that owns the format so the two sources stay consistent.
- Respect `NO_COLOR` and TTY detection (`posix_isatty` or `stream_isatty`).

### Step 4: Flags and a log file

- `./v3 start --verbose` includes static assets. Default is off.
- `./v3 start --quiet` prints errors and 5xx only.
- `./v3 start --log-file=/path` writes the same lines to a file as well, for
  reviewing a session after it ends. Keep the file out of the repo; add it to
  `.gitignore` if the default path is inside the tree.
- Update the usage text in `v3` (the no-argument block) for the new flags.

### Step 5: Clean Ctrl+C

Pressing Ctrl+C today leaves `^C` echoed at the end of the last log line, with
the shell prompt starting on that same line. `php -S` prints nothing when it
exits, so nothing ends the line cleanly. The terminal echoes `^C` because
`echoctl` is on, and the script has no trap.

Planned fix in the `start` case of `v3`:

- While the server runs, turn off the `^C` echo with `stty -echoctl` (only when
  stdin is a TTY), and restore the previous setting on exit with an `EXIT` trap.
- Trap `INT` and `TERM`. The trap prints a newline and one line, for example
  `v3 :8001 stopped`, then exits 0. Ctrl+C is the normal way to stop the server,
  so it should not look like a failure.
- With the Step 1 pipeline, wait for the pipeline to finish inside the trap, so
  the filter's last lines flush before the stop message prints.

Verify this in a real terminal, not only through piped output. The `^C` echo and
`stty` only apply to a TTY.

### Step 6: Always-visible clickable URL and status header

Not covered by Steps 1 to 5 until now. Two parts:

- **Clickable URL.** The URL is printed only once, in the banner. Options:
  - Print it as an OSC 8 hyperlink (`ESC ]8;;URL BEL text ESC ]8;; BEL`). Most
    current terminals, including Windows Terminal, GNOME Terminal and VS Code,
    make it clickable. Terminals without support show the plain text.
  - Print a plain `http://...` URL in the header too. Most terminals detect it.
  - Keep the host as the server is bound. `--listen-all` prints
    `http://0.0.0.0:8000/`, which the developer confirms works as a link, so no
    change is needed there. The banner should keep showing the exact URL that
    was started.
- **Always visible.** Keep the URL in the header from Step 6b below, so it does
  not scroll away.

**6b. Status header (the question is whether this is in scope).** A header bar
that stays at the top while requests scroll below it is achievable and fits the
goals of this plan. There are two ways to build it, with different costs:

1. **Pinned header with a scroll region (recommended).** Use ANSI scroll regions
   (`ESC[1;Nr`, which sets the scrolling area to lines 1..N) to reserve the top
   1 or 2 lines for the header. The rest of the terminal keeps its normal
   scrollback, so you can still scroll back through past requests. On `SIGWINCH`,
   reread the terminal size (`tput lines`/`cols`), reset the scroll region and
   redraw the header. This is a modest amount of code and works in both bash and
   PHP. It is the smaller and safer option.
2. **Full-screen alternate-screen UI.** Switch to the alternate screen
   (`ESC[?1049h`), draw a header, and manage the request list in memory with
   your own scrolling, keys and resizing. This gives a true full-screen view. It
   costs more: you lose the terminal's native scrollback, you handle keys and
   input yourself, and you need careful cleanup on exit and on crashes. It
   is a real project, not a small step, and I would not start there.

I recommend option 1 as Step 6, after Steps 1 to 5. Start with a header showing
the URL, port, checkout name, and a count of requests and errors since start.
Option 2 can be reconsidered later if option 1 is not enough.

## Things to check before starting

- Confirm whether the built-in server prints request lines for router-handled
  requests in any PHP version this project runs on. The local PHP is 8.1.2
  (Ubuntu). The verification above was done on that version only.
- Confirm that `error_log()` and `STDERR` writes from inside the router reach the
  terminal under `php -S`, and check interleaving with the built-in lines when
  requests run concurrently.
- Check how `index.php` dispatches routes, so the "why" column can name the route
  without a large refactor.
- Check that the `__offline_bootstrap` query strings seen in the log (the
  service-worker bootstrap requests) are filtered or shortened in the output, since
  they are noise on every reload.

## Test plan

- Unit-style test for the line formatter (status color, gap detection, port tag,
  `NO_COLOR` handling), under `tests/` and run by `./v3 test`.
- Manual check: `./v3 start 8001`, load a page, load a 404, follow a redirect, hit
  a static asset with and without `--verbose`, and confirm each line matches the
  proposed format.
- Check that `V3_DEV_LOG` unset leaves `./v3 test` output and production
  behavior unchanged.

## Out of scope for now

- A web UI or dashboard for request logs.
- Request and response body logging.
- Replacing `php -S` with another server.
