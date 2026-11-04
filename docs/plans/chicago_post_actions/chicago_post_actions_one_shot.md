# Chicago post actions — one-shot

Cycle: `v3/chicago_post_actions`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](chicago_post_actions_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/chicago_post_actions`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; no real alternatives or separate work units)

**Cycle estimate: 0.15**

Status: implemented and the requested UI condition is locally verified on `fix/chicago-post-actions`; Linux signed-save acceptance, review and deployment remain pending. Next action: completion commit, push, draft PR and thread reply.

## Request and authorization

Feedback item: [PF-20261104-F66](../../feedback/items/PF-20261104-F66.txt), "Apply Word97 repairs to Chicago."

Original request: the user pasted `https://zenmemes.com/threads/thread-20261103164303-b35451ea` on 2026-11-04. The linked guest post says, "the same fixes we did for word97 theme we should also do for chicago."

Authorization: the pasted URL is the user's complete instruction. Under [thread to pull request](../../agents/thread_to_pr.md), this authorizes a one-shot cycle for the bounded thread outcome, pushing the feature branch, opening a pull request, and replying to the request post. It does not authorize merge or deployment.

The referenced Word97 request thread defines "the same fixes": Reply, Like, Flag and other post actions use the theme UI font without underlines; Like and Flag alone gain left/right padding and a soft raised bevel without a dark outline, with a pressed state. The QDB vote row is excluded from the padding/bevel change; compose submit buttons and unrelated controls are fully excluded.

## Outcome and work type

Application UI change. On an ordinary Chicago-themed thread, the Reply, Like and Flag actions read as Chicago UI controls rather than underlined content links. Like and Flag have comfortable horizontal padding and the existing Chicago white/grey three-dimensional edge in both raised and pressed states. Reaction behavior and every other theme remain unchanged.

## Approach, risks and reuse decisions

- Extend `public/assets/theme-chicago.css`, the canonical Chicago theme stylesheet. Reuse the existing `.post-card-actions` and `.thread-reaction-button` contracts rendered by `templates/partials/thread_root_card.php` and `templates/partials/post_card.php`; no markup, component, behavior, or API contract is added.
- Follow the shipped Word97 rules in `public/assets/theme-word97.css`, but scope padding and bevel selectors explicitly to the existing Like/Flag `data-tag` hooks so Agent, retraction, Handoff, QDB and compose controls do not acquire reaction-button spacing.
- Reuse Chicago's existing control palette: white top/left and `#7b7b7b` bottom/right, reversed while pressed. Do not introduce new colors or dependencies.
- Add a focused PHP source-contract test and extend the actual thread-page browser matrix in `tests/browser/post_signals.mjs` to check Chicago and Word97 computed typography, decoration, padding, border and pressed state. The browser flow already verifies that a reaction can be saved and survives reload.
- Risk: theme CSS loads before shared page CSS, so an insufficiently specific selector would be overwritten by `content-interactions.css`. Resolve by asserting computed styles through the ordinary thread route, not source text alone.
- Risk: a broad copied selector would restyle non-reaction controls. Resolve through Like/Flag data hooks and negative computed-style assertions for an unrelated post action where available.

## Completion conditions

- Ordinary entry: open a thread, select Chicago, and inspect root and reply action rows at phone and desktop widths.
- End-to-end result: Reply/Like/Flag use the Chicago UI font with no underline; Like/Flag show horizontal padding and raised/pressed white-grey bevels; clicking Like still saves and remains applied after reload.
- Failure/retry recovery: unchanged because the change is CSS-only; the existing reaction feedback and retry behavior must remain visible and usable.
- Release handoff: smoke plus focused tests and the browser flow pass; the feedback item and records are updated; the branch is pushed, a ready PR is opened, and a thread reply says review/deployment are still required.
- Exclusions: merge, deployment, production verification, physical-device verification, other Chicago repairs, Word97 changes, navigation/icon changes, schema/data changes, and dependencies.

## Concepts touched

[Post presentation](../../concepts/post_presentation.md) is used unchanged: the existing thread/root action markup and reaction behavior remain canonical. No concept inventory addition or correction is needed for theme-only presentation.

## Verification and recovery plan

- Run `./v3 test --smoke PostActionThemeTest` and the actual thread-page browser check in `tests/browser/post_signals.mjs`; do not run the full suite.
- Check Chicago and Word97 at 375px and 1280px through the existing browser matrix, including root and reply actions, no horizontal overflow, raised/pressed styling, a saved Like and reload persistence.
- Visually inspect representative Chicago phone and desktop screenshots. Other themes are protected by scoped selectors and the existing full-theme browser loop.
- Run `./v3 feedback check`, `./v3 feedback index`, syntax/diff checks, and compare the final dependency delta (expected zero).
- Recovery: revert the Chicago stylesheet and focused test changes. No data migration, record rewrite, publication rebuild, or user action is required.

Starting revision and branch: `8bc40af67d14c3b76f8ec057edac1bc084a151be` on `main`; branch `fix/chicago-post-actions`.

Planning commit: `4d702bc9`, confirmed before implementation edits.

## Study history

- **Intake, 2026-11-04T20:15:47Z, 0.15.** Scope v1 as above. Codex is the single exposed rater; the linked request threads, the two Word97 commits, both theme stylesheets and relevant markup were inspected before rating, so pristine intake is missing rather than reconstructed. R1's three frozen files were verified at their recorded hashes. The projection is slightly above duplicate-preview suppression (0.10) because it includes theme-specific interaction styling, computed-style/browser checks, feedback bookkeeping, PR publication and a thread reply.
- **Execution entry, 2026-11-04T20:15:47Z, 0.15 reaffirmed.** Same scope v1 and starting revision; implementation may begin only after the planning commit succeeds.
- **Closeout, 2026-11-04T20:26:52Z.** Projection 0.15 reaffirmed. Retrospective cycle scope **0.15**, same single exposed rater and not an independent rating. Scope v1 is implemented; requested UI acceptance passed. Ordinary correction: the existing browser harness now resolves macOS's real temporary path and can stop after its style matrix because signed verification requires Linux `setsid`. No product-scope additions. Linux signed-save acceptance, review, merge and deployment remain pending; maintainer comfortable-fit/acceptance and accepted-count change remain pending/none.

## Dated execution history

- 2026-11-04: Read the complete Chicago request and the earlier Word97 request/follow-up posts. Chose the two shipped Word97 outcomes as the bounded meaning of "same fixes" rather than extrapolating the larger historical Word97 redesign.
- 2026-11-04: Found existing feedback item PF-20261104-F66 in `Workflow: untriaged`; no competing implementation was recorded.
- 2026-11-04: Added Chicago UI typography/no-underline rules for Reply and reaction controls. Added the Like/Flag-only side padding and raised/pressed bevel using Chicago's existing white and grey palette. No markup or behavior changed.
- 2026-11-04: Added `PostActionThemeTest` and registered it with the runner. Extended the actual thread-page browser matrix with computed typography, decoration, padding, border, inner-bevel and pressed-state checks for Chicago and Word97 at phone and desktop widths.
- 2026-11-04, verification correction: the browser fixture initially failed because macOS exposes the temporary directory through a `/var` symlink, which the application's image-root safety check correctly rejects. The fixture now resolves the real temporary path. Its style-only mode lets CSS acceptance finish where the later signed-write phase cannot run without Linux `setsid`; server diagnostics are printed on failure.

## Outcome

Delivered scope v1: Chicago thread actions now carry the same UI typography repair as Word97, and Chicago Like/Flag buttons alone carry the roomy soft raised/pressed bevel. The ordinary thread route verifies root and reply presentation. Limitation: the signed save/reload phase remains pending on a Linux host; this branch changes no reaction markup, script, request or server behavior.

## Verification

- Passed: `./v3 test PostActionThemeTest`, 2/2.
- Passed: `POST_SIGNALS_STYLE_ONLY=1 ... node tests/browser/post_signals.mjs`: anonymous no-JavaScript render; 16 themes × 375/1280 widths on the actual thread and Forte routes; Chicago/Word97 root Reply/Like/Flag computed UI font and no underline; Like/Flag padding, 1px raised bevel, inner bevel and reversed pressed colors; no recorded matrix failures. Representative Chicago phone and desktop screenshots were visually inspected and show readable, unobscured controls with no horizontal overflow.
- Environment-limited: the full browser command reaches and passes the style matrix, then the unchanged signed Like phase is refused because this macOS host lacks Linux `/usr/bin/setsid`. Docker is installed but no daemon is running. Signed save/reload is therefore not claimed.
- Smoke set plus focused tests: branch 425 run, 325 passed, 100 failed; unchanged `main` baseline 423 run, 323 passed, 100 failed. The failure-name sets are identical. Causes include Linux `setsid`, the macOS `/var` symlink, sandboxed local sockets/GPG and PHP 8.5 deprecation output. The two added tests are the two additional passes.
- Passed: PHP and Node syntax checks and `git diff --check` before closeout.
- Passed: `./v3 feedback check` (0 problems; 21 existing review notes) and `./v3 feedback index` (211 items).
- Not run: full suite (maintainer runs it), physical-device and production/deployment checks.
- Dependency delta: zero repository dependencies, manifests, locks, copied assets or runtime components. Existing `playwright-core` 1.58.1 was installed in `/private/tmp` only to run the browser test.

## Final review and commit/publication status

Final diff review: the product change is confined to the canonical Chicago stylesheet; existing post-action contracts are reused; Like/Flag padding selectors exclude Agent, retraction, Handoff, QDB and compose controls; no component, navigation icon, data format, schema, API, dependency or deployment change exists. Test-harness corrections are limited to making the existing acceptance runnable and diagnosable on macOS. Planning commit `4d702bc9`; completion commit pending. Merge and deployment are not authorized.

*— Codex · 2026-11-04 UTC*
