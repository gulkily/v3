# UI Localization — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./ui_localization_step1_solution_assessment.md) · [Step 2](./ui_localization_step2_feature_description.md) · [Step 3](./ui_localization_step3_development_plan.md) · [Step 4](./ui_localization_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

> as a user, i want to see the interface in another language.

(Clarified in conversation: use Railspike per `AGENTS.md`; Spanish proposed as the first added language, pending user confirmation.)

## Understood Intent

Let a visitor view the forum's interface chrome (navigation, buttons, status/notice copy) in a language other than English, choosing it themselves and having that choice persist. This is the foundation for localizing the wider UI later; it is **not** translating user-authored post content.

## Problem statement

All user-facing UI copy is hardcoded English across ~130 PHP templates with no mechanism to select or render an alternate language.

## Options

### Option A — Dependency-free translation foundation + a scoped proof-of-concept slice (recommended)

Build a small, self-contained translation layer (locale registry, catalog loader, request-locale resolution) using only PHP arrays and built-in facilities, wire a translate helper into the existing `TemplateRenderer`, add a header language selector (cookie-persisted, mirroring the theme menu), set `<html lang>` dynamically, and convert **one high-visibility slice** — the nav + layout chrome — as a working end-to-end vertical slice. Remaining templates are localized in later cycles.

- **Pros:** No new third-party dependencies (honors `CONTRIBUTING.md`/`AGENTS.md`). Delivers a releasable vertical slice (choose language → see it applied → persists). Low blast radius; existing call sites unchanged. Selector is a header widget, so it needs no nav-icon mapping. Reviewable on GitHub.
- **Cons:** Only a slice is translated initially; most pages stay English until follow-up cycles. Introduces a new resources/i18n catalog area to maintain.

### Option B — Full externalization of all template copy in one cycle

Extract every hardcoded string across all ~130 templates into catalogs and translate them together in a single effort.

- **Pros:** Whole interface localized at once; no "half-translated" interim state.
- **Cons:** Very large; exceeds the one-day / eight-stage Railspike slice guidance. High risk of breaking existing copy-assertion tests and nav-icon/layout checks across many themes and states. Hard to review; long-lived branch.

### Option C — Third-party i18n library (e.g. gettext or a framework translation package)

Adopt an established i18n library to manage catalogs, pluralization, and locale negotiation.

- **Pros:** Mature pluralization/formatting; familiar tooling.
- **Cons:** Requires explicit dependency approval under `CONTRIBUTING.md`/`AGENTS.md` before any adoption — not a default-safe path. Added runtime/build weight for a server-rendered PHP app that can meet current needs with arrays. Possible extension requirement (e.g. gettext) not guaranteed on target hosts.

## Recommendation

**Option A.** It respects the repository's dependency-approval policy, fits Railspike's vertical-slice sizing, and produces a genuinely usable result — a visitor can switch the interface language, see the nav/chrome change, and have the choice persist — while establishing the pattern the rest of the UI can adopt incrementally. First added language proposed: **Spanish (`es`)**, with English (`en`) as default/fallback.

**Vertical-slice viability:** Normal entry (open any page), end-to-end outcome (pick a language in the header → nav + chrome render in it → `<html lang>` updates → cookie persists across pages), and recovery (unsupported/absent choice falls back to `Accept-Language`, then English). Follow-up cycles extend coverage to additional templates and languages.

## Enhancement Requests

_None recorded yet._
