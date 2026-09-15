# Forte — FDP Cycle Backlog

Exhaustive, ordered list of every Feature Development Process cycle needed to close the gaps identified in `forte_roadmap.md` Section A. Each row below is one full `{feature_name}` that will get its own Step 1-4 planning docs and (where warranted) feature branch, run one at a time. Order reflects dependency and value judgment beyond item 1; only item 1 is confirmed — feel free to reshuffle the rest.

| # | Feature name | What it covers | Step 1? |
|---|---|---|---|
| 1 | `forte_identity_signing` | Wire browser-key signed authorship into Forte's reply composer instead of always posting as anonymous "guest" — the flagged correctness issue. **Confirmed first; scoped to the board view only** now that the single-thread view is being deprecated (item 2) — no per-page-type split needed. | Yes — approach choices for the board view specifically (reuse `lazy_compose_signing.js` as-is vs. eager-load vs. a Forte-native indicator) |
| 2 | `forte_deprecate_single_thread_view` | Deprecate, then remove, the single-thread Forte reader (`/threads/{id}/forte`, `forte.php`) in favor of board-view-only. Confirmed low-risk: nothing outside Forte links to this route, and the board view is already fully self-contained (no `href` back to it anywhere). Queued right after identity-signing so no later cycle builds more into a view about to be removed. | Optional — no real solution ambiguity in "delete these files/route," but worth a Step 2 to record the rationale given it reverses several previously-shipped features (`forte_step1-4`, `forte_reply`, `forte_keyboard_navigation`'s single-thread half) |
| 3 | `forte_compose_thread` | Wire the permanently-disabled "New" toolbar button to actually compose a new thread, reusing `thread_compose_form.php` (a different canonical form than the one `forte_reply` already reused). Board-view-only by the time this runs. | Recommended — same "native vs. bolted-on" congruence question that came up for replies |
| 4 | `forte_reactions` | Like/Flag buttons on posts/threads, reusing the existing `/api/apply_thread_tag` / `/api/apply_post_tag` endpoints. | Optional — straightforward reuse, low ambiguity |
| 5 | `forte_post_permalink` | A shareable direct link to one specific post (classic has `/posts/{id}`; Forte has none). **Needs rework given item 2**: the original idea assumed the single-thread view's own URL scheme — this now needs to resolve to `/forte?selected=...`-style restoration instead, similar to the existing new-reply highlight mechanism. | Recommended — URL scheme and redirect-vs-direct-render are real choices |
| 6 | `forte_classic_nav_bridge` | A way to reach classic-only pages from Forte (or back). Reverses a stated non-goal (`forte_step2`: "no link from Forte back to the standard page"), so this needs a deliberate decision, not just an implementation pass. **Dependency note:** may need to land alongside or before later identity/account-management polish, since `/account/key` itself is a classic-only page. | Yes — this is a scope-reversal decision, not just a build |
| 7 | `forte_profiles` | Profile pages, user directory, and linking author names to profiles instead of plain text. | Recommended — several viable approaches (full Forte-styled page vs. reusing classic's page via the nav bridge above) |
| 8 | `forte_tag_index` | A browsable, shareable tag index (`/tags/`, `/tags/{tag}`) distinct from the board's filter-only folder tree. | Optional — additive, low ambiguity |
| 9 | `forte_activity_feed` | A Forte-native view of classic's `/activity/` recent-activity feed. | Optional, but worth a quick Step 1 to scope what "activity" means in a paned layout |
| 10 | `forte_mobile_responsive` | An engineered (not pragmatic-fallback) mobile/narrow-viewport layout, including fixing the known Subject-column squeeze at 420px. | Yes — genuine breakpoint/layout strategy trade-offs |
| 11 | `forte_user_approval` | The pending-user approval workflow (`/users/pending/`, approve endpoints) — administrative/moderator-facing. | Recommended — permissions and surface-area questions |
| 12 | `forte_agent_codex_workflow` | Agent-reply requests and the Codex handoff/approval workflow. | Yes — the largest, least-defined item on this list |

**Total: 12 FDP cycles.**

## Not on this list (handled outside FDP)

Per `forte_roadmap.md` Section C, these rough edges are small enough to fix directly (as we did for the composer focus/scroll and new-reply-highlight polish) rather than running a full cycle each: the dead New/Refresh buttons (New resolves itself once cycle #3, `forte_compose_thread`, lands; Refresh has no clear job yet), missing empty-state messaging, no error-surfacing in the composer JS, and the missing `aria-live` announcements. Flagging them here only so nothing from the roadmap silently disappears — revisit as direct fixes whenever convenient, or promote any of them to a full cycle later if they turn out bigger than expected.

## Next step

Start Step 1 for `forte_identity_signing` whenever you're ready.
