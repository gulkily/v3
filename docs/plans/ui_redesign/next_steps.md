# UI redesign: next steps

Status as of 2026-10-26: the plan and every mockup are committed on
`ui-redesign-mockups` (commit `9486ab97b`). No application code has changed.
This document records how we agreed to move from mockups to the real app. The
work itself is broken into cycles in
[implementation_checklist.md](implementation_checklist.md).

Related documents:

- [plan_prompt.md](plan_prompt.md): round 1 plan, the 11 screens and the
  template each one touches.
- [visual_direction_notes.md](visual_direction_notes.md): feedback rounds,
  graded ideas, the Living Sky and Ma themes, and performance rules.
- [mockups/index.html](mockups/index.html): the gallery.

## Decisions

Decided by the user on 2026-10-26:

1. **Theme: Ma first.** Ma is the theme we build. Living Sky stays in the
   mockups as an optional later theme (cycle T3), not scheduled.
2. **Opt-in until ready.** Ma is added as a choice in the theme menu and stays
   opt-in until we decide it is ready to become the default (cycle T4).
3. **Colors from the key fingerprint: later.** Person colors and avatar cuts
   stay derived from the name for now; fingerprint colors remain cycle D4,
   deferred. Usernames are not unique, so revisit this before D3 (seals).
4. **No member preview.** Skipped: there are no regular members yet.

5. **Round 1 product proposals** (from
   [plan_prompt.md](plan_prompt.md#proposals-that-need-a-product-decision-marked-proposal-in-the-notes)):
   - **Pending chip: shown to everyone.** This keeps today's behavior, where
     everyone sees "(unapproved)", restyled as a "Pending" chip. Limiting it to
     the author and approvers would need the page to know who is viewing and
     whether they can approve, which is extra work.
   - **Unlike: not implemented.** Dropped entirely.
   - **Undo after Approve: skipped.**
   - **Last backup date stored in the browser: yes.** It drives the "Not
     backed up" warning on the Account page; the date stays on the device.
   - **Offline message queue: deferred.**
   - **Hiding Tools groups the viewer can't use: deferred.**

## Suggested order

1. **Base structure (round 1).** The most valuable part and independent of
   any theme: wording, arrangement and flow, one screen at a time. Shell and
   navigation first, because every page depends on it; then Board, Thread,
   account setup and first post, composers, Profile and Users, Messages, and
   Tools.
2. **Theme hooks.** Small attributes and empty slots the themes need (sky
   layer, `data-tab`, `data-tone`, `--person`, avatar cuts, `data-pulse` and
   others). Invisible in existing themes, so each ships with the base cycle
   for its screen rather than as a separate pass.
3. **The theme.** Ma (and Living Sky if chosen) as new entries in
   `src/ForumRewrite/View/ThemeRegistry.php` with their own
   `public/assets/theme-*.css`, adapted from the mockup CSS to the real
   templates.
4. **Features that need data.** Community pulse, growing constellations,
   seals and fingerprint colors. Each is its own decision and cycle.

## Risks to plan for

- **Existing themes.** Sixteen registered themes (plus Auto) style today's markup.
  Structure changes can break them, so each base cycle checks every theme,
  not only Light and Dark. See `docs/runbooks/theme_development_guide.md`.
- **Navigation icons.** `AGENTS.md` requires updating
  `public/assets/theme-nav-icons.css` for Word97, Chicago and Midnight when a
  navigation destination is added or renamed, including Tools buttons and
  subnavigation, and checking each icon theme visually.
- **UI copy.** Follow the UI verification steps in `CONTRIBUTING.md` and track
  copy cleanup in `docs/plans/ui_copy_audit_checklist.md`. Update tests that
  assert removed strings.
- **Dependencies.** The plan needs none: fonts are system fonts and the
  generated art is first-party code. Any new font or library needs approval
  under `CONTRIBUTING.md` first.
- **Read model.** Growing constellations may need a read-model schema change,
  which has its own required steps in `CONTRIBUTING.md`.
- **Performance.** Living Sky's frosted glass should be tested on a low-end
  phone. Ma uses no blur. Follow the performance rules in
  [visual_direction_notes.md](visual_direction_notes.md#performance-rules-for-any-new-effect).
- **Security UX.** Seals and the night-mode "Signatures verified" caption may
  only appear after verification has actually succeeded.

## Process

Railspike is used where there are real unknowns or high stakes, and skipped
where the mockups already settle the work (decided 2026-10-26). Each cycle in
[implementation_checklist.md](implementation_checklist.md#process-per-cycle)
is marked **Railspike** (with Step 1), **Railspike, no Step 1**, or
**Direct**. Direct is the user's explicit Railspike opt-out for that cycle
under `AGENTS.md`; it still uses a branch and every shared check. Every cycle,
Direct or not, must be a releasable vertical slice within a day or eight
stages. Railspike cycles are enrolled in the `railspike-effort` study under
protocol P2 automatically; there is no need to ask.

These documents are planning inputs, not Railspike artifacts. Each Railspike
cycle creates its own step files and, after `Approved Step 3`, its own feature
folder under `docs/plans/`.

## Starting the next conversation

Suggested opening prompt:

> Start implementing the UI redesign from `docs/plans/ui_redesign/`
> (see `next_steps.md` and `implementation_checklist.md`). Follow the process
> marked for each cycle. Begin with cycle 1, the app shell and navigation
> (Railspike, starting with Step 1).
