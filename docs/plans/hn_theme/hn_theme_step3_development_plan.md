> **Feature plan:** [Step 1](./hn_theme_step1_solution_assessment.md) · [Step 2](./hn_theme_step2_feature_description.md) · [Step 3](./hn_theme_step3_development_plan.md) · [Step 4](./hn_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/hn_theme`; host ledger [hn_theme.md](../effort_ledger/hn_theme.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.65**

Concepts touched: [theme script](../../concepts/theme_script.md) (used by Stage 4); [page theme](../../concepts/page_theme.md) (used unchanged by `theme: ochre`); [ported thread](../../concepts/ported_thread.md) (read only). Stage 6 adds `docs/concepts/ochre_theme.md`. Feedback: none.

## Completion Contract

- Normal entry: a reader picks Ochre in the theme menu, or an operator replies `theme: ochre` on a thread.
- End-to-end outcome: the board, Recent, a thread, a ported thread and Tools read correctly in Ochre at 380px and on desktop. Collapse and the parent and next links work on a ported thread nested 8 levels deep.
- Recovery: picking another theme or `theme: none` leaves no Ochre toggles or links. With JavaScript off, Ochre is the look alone and every comment shows.
- Deployment: production needs `php scripts/build_static_artifacts.php`, or baked thread pages fall back to the default theme.
- Release: merged to main, deployed with the rebuild, and tried on production by the maintainer.

## Key Risks

- **High risk:** baked pages carry an old allow-list of theme names. Impact: Ochre silently fails on thread routes, the very pages it is for. Early validation: Stage 1 rebuilds artifacts and opens a baked thread in Ochre. Mitigation: the PR description and the guide name the rebuild as a deploy step.
- Collapse misreads the tree. Impact: a fold hides the wrong comments or miscounts them. Early validation: Stage 4 runs on a synthetic port 8 levels deep, with a same-author continuation. Mitigation: a branch is the run of following cards whose `data-reply-depth` is greater.
- The script and a page-theme exit disagree. Impact: leftover toggles after leaving Ochre. Early validation: Stage 4's VM test checks that stop removes everything. Mitigation: record every added element in one context, as `theme-engagify.js` does.
- The compact layout hides shared controls. Impact: composers or the Activity drawer become unreachable. Early validation: the guide's "what must survive" list at 380px in Stages 2 and 3.

## Stage 1

**Estimate: 0.10**

- Goal: Ochre exists in the menu with its palette, top bar, page column and fonts.
- Dependencies: none.
- Expected changes: an `ochre` entry in `ThemeRegistry.php` (light, `'script' => true`); `ochre` → `dark` in `COUNTERPARTS`; a new `public/assets/theme-ochre.css` with the variable block, swatch and the top bar; the allow-list in `LocalAppSmokeTest.php`.
- Verification approach: `./v3 test --smoke ThemeRegistryTest LocalAppSmokeTest ThemeCatalogPageTest`; `tests/theme_contrast_audit.py` passes for Ochre; rebuild artifacts and open a baked thread in Ochre.
- Estimate details (extended mode only): driver: one stylesheet's base and its tests; uncertainty: low.
- Risks or open questions:
  - Impact: the baked allow-list risk above.
  - Early warning / validation: a baked thread shows Ochre after the rebuild.
  - Mitigation: rebuild in this stage, before any styling depends on it.
- Canonical components/API contracts touched: `ThemeRegistry` entry and `COUNTERPARTS` (reused), `theme-word97.css` as the file template, `ThemeDirective` permitted names (unchanged, derived from the registry).

## Stage 2

**Estimate: 0.10**

- Goal: threads read like a compact text page: one grey metadata line, thin indents, plain composers.
- Dependencies: Stage 1.
- Expected changes: scoped rules in `theme-ochre.css` for `post_card.php`'s cards, its depth indents, the reply composer and the Activity drawer. No markup changes.
- Verification approach: screenshots at 380px and desktop on a thread and a ported thread; keyboard focus, `:target` jumps and text selection; contrast audit again.
- Estimate details (extended mode only): driver: post cards carry many states (pledge, agent, continuation); uncertainty: medium.
- Risks or open questions:
  - Impact: a card state other themes style is lost in Ochre.
  - Early warning / validation: walk the classes on `post_card.php`'s `article`.
  - Mitigation: style through variables first and add scoped rules only where needed.
- Canonical components/API contracts touched: `templates/partials/post_card.php` (read only), `ReplyTree::MAX_INDENT_DEPTH` (unchanged).

## Stage 3

**Estimate: 0.10**

- Goal: the board, Recent and Tools are compact and numbered.
- Dependencies: Stage 1.
- Expected changes: scoped rules for `thread_row.php` rows, with numbers from a CSS counter; the board controls and Tools cards made compact.
- Verification approach: screenshots of the board, Recent, Tools, Tags and Users at 380px and desktop; the board composer still opens.
- Estimate details (extended mode only): driver: several list layouts; uncertainty: low.
- Risks or open questions:
  - Open question: hide each row's excerpt, as in the mockup? The plan hides it unless the maintainer says otherwise.
- Canonical components/API contracts touched: `templates/partials/thread_row.php`, `board.php`, `recent.php` (all read only).

## Stage 4

**Estimate: 0.15**

- Goal: Ochre's script adds `[–]` toggles and parent and next links, and removes them on stop.
- Dependencies: Stage 2.
- Expected changes: a new `public/assets/theme-ochre.js` that registers start and stop with `forumThemeScripts`. Start reads `data-reply-depth` and `data-post-id` from the cards. A toggle hides the comment's body and its branch and shows `[+N]`. The links point at the cards' existing anchors. Nothing is stored. `tests/browser/theme_scripts.cjs` gains Ochre.
- Verification approach: the VM test shows start adds controls and stop leaves none; switch to Gloss and back without a reload.
- Estimate details (extended mode only): driver: tree walking over a flat list; uncertainty: medium.
- Risks or open questions:
  - Impact: the collapse and script-exit risks above.
  - Early warning / validation: the VM test and Stage 5's deep fixture.
  - Mitigation: one context object owns every added element and listener.
- Canonical components/API contracts touched: `theme_toggle.js` (`forumThemeScripts`, reused unchanged), `theme-engagify.js` (pattern only). Bubblegum's window controls are not reused: they fold one card and keep their own storage.

## Stage 5

**Estimate: 0.10**

- Goal: prove the reading habits end to end on a deep ported thread.
- Dependencies: Stage 4.
- Expected changes: a new browser test, modelled on `draggable_windows.mjs`, over a synthetic port 8 levels deep. It covers folding and unfolding, `[+N]` counts, parent and next, `theme: ochre` with its page exit, and JavaScript off.
- Verification approach: the new test passes; `./v3 test --smoke` passes.
- Estimate details (extended mode only): driver: fixture setup; uncertainty: low.
- Risks or open questions:
  - Impact: the fixture misses a real port's shape.
  - Early warning / validation: build it with `createPortedThread` from `tests/fixtures/hn/`.
  - Mitigation: reuse the thread_port fixtures rather than hand-written posts.
- Canonical components/API contracts touched: `LocalWriteService::createPortedThread`, `ThreadTheme::forThread` (both unchanged).

## Stage 6

**Estimate: 0.08**

- Goal: document Ochre and close out.
- Dependencies: Stages 1–5.
- Expected changes: `docs/concepts/ochre_theme.md`; Ochre in the theme guide, including the deploy rebuild; the deferred ported default as a follow-up; the Step 4 summary and ledger closeout.
- Verification approach: review the whole diff for scope; spot-check Gloss, Word 97 and Bubblegum for unchanged pages; `git diff --check`. The full suite is left to the maintainer.
- Estimate details (extended mode only): driver: documentation; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `docs/runbooks/theme_development_guide.md`, the concept inventory.

## Branch note

`hn-theme` was rebased locally onto `origin/main` `a1cefffa`. The remote branch still has the old base, so the first push in Step 4 needs `--force-with-lease`.

## Study snapshots and approval

- 2026-11-19 UTC, Step 3 handoff: **0.65** reaffirmed. The existing contrast audit, VM test and browser fixtures offset the rebuild step and a sixth stage. Claude, single exposed rater; scope v2; R1, hashes not re-verified. Awaiting `Approved Step 3`.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*
