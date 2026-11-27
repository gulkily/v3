# Local start page without the trailing slash

Cycle v3/local_without_slash · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_without_slash`; host ledger [local_without_slash.md](../effort_ledger/local_without_slash.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.05**

Status: implemented, PR open; next permitted action: maintainer merge.

## Original request

"/local/ works but /local returns a 404, please fix" (Ilya, 2026-11-27).

## Authorization

A one-shot on a new branch from `main` with its own PR. `/local` is redirected to `/local/` on a development server, under the same `LocalDevelopmentImport::enabled()` condition as the page itself. Not authorized: any other route, or any change to the page.

## Outcome and work type

Application change. On a development server, `GET` or `HEAD /local` answers 301 to `/local/`, keeping any query string such as `?source=`. Outside development mode `/local` stays a 404, as `/local/` does.

## Risks

None beyond the route. The redirect stays relative to the same host and only adds the slash, so it can't point anywhere else.

## Reuse decisions

Same guard as the page (`LocalDevelopmentImport::enabled()`), in `Application`, next to the page's routing.

## Verification plan

`LocalDevelopmentImportTest` through its real development server: `/local` and `/local?source=…` give 301 to the slashed form, and a server outside development mode keeps the 404. Run it with the smoke set.

## Study checkpoint

Intake 2026-11-27T20:40Z, scope v1, R1, P2 standing enrollment. Claude, single exposed rater. **0.05 projected total.**

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

## Changes and verification

Planning commit `38281dfa`.

- `Application` answers `GET` or `HEAD /local` with 301 to `/local/`, keeping the query, under `LocalDevelopmentImport::enabled()`.
- `./v3 test --smoke LocalDevelopmentImportTest`: 499 run, 499 passed. The full suite was not run.
  - `/local?source=example.com` gives 301 with `Location: /local/?source=example.com`, and following it reaches the page.
  - A server outside development mode gives 404 for both `/local` and `/local/`.

Study closeout 2026-11-27T20:45Z: retrospective **0.05**; acceptance pending.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*
