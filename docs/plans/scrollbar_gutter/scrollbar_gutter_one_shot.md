Cycle: `v3/scrollbar_gutter`; Mode: one-shot; Record/index: [plans index](../README.md).

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/scrollbar_gutter`; host ledger [scrollbar_gutter.md](../effort_ledger/scrollbar_gutter.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.10**

Status: implemented and committed; next permitted action: maintainer review (PR).

Original request: "some pages on mitrapclub.com exhibit a jumping effect shortly after first paint." Then: "the affected pages are board, recent" and "i think it's partially due to scrollbar, so we could maybe mitigate it by always showing scrollbar, if no better option exists". After the diagnosis, the maintainer said "please go ahead and make pr".

Authorization: the maintainer asked on 2026-11-25 for a PR with the diagnosed fix: reserve the scrollbar's space with `scrollbar-gutter: stable`. The boundary is clear, so it runs as a one-shot. Exclusions: no other layout or loading changes. The maintainer authorized a PR, not a merge or deploy.

Outcome and work type: application change (CSS). On Board and Recent, the page column keeps its horizontal position from first paint onwards. Today it moves left by half a scrollbar width (about 7px) when the rest of the page arrives.

Diagnosis (before this record, on the live site):
- Board (90 KB) and Recent (117 KB) are long. The browser paints before the whole document has arrived. At that first paint the page is shorter than the window, so there is no vertical scrollbar.
- When the rest arrives, the page outgrows the window and the scrollbar appears. The viewport narrows by about 15px, so the centred `.shell` moves left by about 7px.
- About, Calendar and Users are shorter than a desktop window, so they never get a scrollbar and do not move.
- Replay: the live HTML was served from a local server in two chunks (head plus first card, then the rest 1.2s later), with headless Chrome tracking `.shell` per frame at 1280x800. Board and Recent: `.shell` x 260 then 253. About: 260 throughout. With `html { scrollbar-gutter: stable }` injected: 253 from the first frame on all three pages.
- Ruled out: the theme stylesheet is chosen before paint, and deferred scripts do not change the layout above the fold. `layout-shift` entries were empty in every run. Chrome does not report this kind of shift, so that metric cannot confirm a fix.

Approach and risks:
- Add `scrollbar-gutter: stable` to the `html` rule at the top of `public/assets/site.css`. That rule is in the critical block (before `/* critical-css-end */`), which every page inlines, so it applies at first paint.
- This reserves the gutter on every page, including short ones, which now sit a few pixels left of their old position. The maintainer's suggested fallback, `overflow-y: scroll`, would also draw an empty scroll track there. The gutter does neither.
- Overlay scrollbars (phones, macOS default) take no space, so nothing changes there.
- **Risk:** a theme or script relies on the full viewport width. Impact: a layout off by one gutter. Early validation: no theme stylesheet sets `overflow` or `scrollbar-gutter` on `html`, `body` or `:root`. The `100vw` rules in `site.css` are for the `[data-shell="app"]` header and fixed overlays, which already include the scrollbar in `vw`.
- **Side effect, positive:** `sqlite_dialog.js` sets `body` overflow to hidden while it is open. The gutter stays reserved, so the page behind no longer widens.

Reuse decisions: change the existing `html` rule in `public/assets/site.css` in place. Extend the existing critical CSS test in `tests/ThemeRegistryTest.php` rather than adding a test file. `forte.css` already uses the same property on a list header, so this introduces no new technique.

Completion conditions: on Board and Recent at desktop width with classic scrollbars, `.shell` does not move when the page grows past the window. About keeps a stable position. No theme loses its layout. Deploy with Deploy now; no schema change.

Concepts touched: none. No inventory entry covers page-level layout.

Related feedback: [PF-20261030-B02](../../feedback/items/PF-20261030-B02.txt) "Pages jump vertically" is about a vertical jump. This fix is horizontal, so it is not claimed and gets no trailer.

Verification and recovery plan:
- `./v3 test --smoke ThemeRegistryTest`, with a new assertion that the critical block's `html` rule reserves the gutter.
- Repeat the two-chunk replay against the local app with the changed `site.css`. Board and Recent: `.shell` x unchanged between the first and settled frames. About: also unchanged.
- Recovery: revert the one CSS line.
- Not replayed: Firefox and Safari, which are not installed here. The property is supported in Chrome, Edge and Firefox; Safari uses overlay scrollbars by default.

Starting revisions and branch: `fix/scrollbar-gutter` from `origin/main` `1e9e80b99`.

Planning commit: `1aee2f0` (plan and index entry only, before any implementation).

Study history:
- 2026-11-25 UTC, intake and execution entry: **0.10**. Recorded after the diagnosis (live-site inspection and the replay above), before any repository change. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

Dated execution history:
- 2026-11-25: `site.css` adds `scrollbar-gutter: stable` to the `html` rule in the critical block, with a comment saying why.
- 2026-11-25: new `ThemeRegistryTest::testPageReservesTheScrollbarGutterOnFirstPaint`. It checks that the critical block's `html` rule reserves the gutter and that no theme sets overflow or a gutter on `html`, `body` or `:root`. It failed with the CSS line removed and passed with it.
- Discovery: Calendar shifts too when it is taller than the window (800px high at 1280px wide). The same line fixes it. Correction, not added scope.

Study history, when enrolled:
- 2026-11-25 UTC, closeout: retrospective scope assessment **0.10**. Delivered as planned. Claude, single exposed rater. Maintainer judgment pending; measured time unknown.

Outcome: delivered. Long pages keep their column in place while they load. Short pages sit half a gutter (about 7px) left of their old position, steadily.

Verification:
- `./v3 test --smoke ThemeRegistryTest`: 490 run, 490 passed. The full suite was not run.
- Two-chunk replay of live mitrapclub.com HTML in headless Chrome with classic 15px scrollbars. "x" is `.shell`'s left edge, first frame then settled.
  - `main` CSS at 1280x800: Recent 260 then 253; Calendar 260 then 253; About 260 throughout. At 900x800: Board, Recent and Calendar 70 then 63; About 70.
  - Branch CSS (inlined critical block and `site.css` both from this branch) at 1280x800 and 900x800: Board, Recent, About and Calendar at 253 (or 63) from the first frame.
- Not replayed: Firefox, Safari and the maintainer's own browser. Not checked: each theme's screenshot. No theme sets page-level overflow, and long pages already rendered with the scrollbar's width taken, so the settled layout of long pages is unchanged.

Final review: the diff touches `public/assets/site.css` (3 lines), `tests/ThemeRegistryTest.php`, this record, the plans index and the cycle's ledger and debug log. No new component, dependency or schema change. Deploy now is enough.

Commit/merge/publication status: implementation commit pending at the time of writing; PR to follow. Not merged or deployed.

*— Claude · 2026-11-25 UTC · Model: Claude Opus 5.5 (claude-opus-5-5)*
