> **Feature plan:** [Step 1](./nav_links_load_stability_step1_solution_assessment.md) · [Step 2](./nav_links_load_stability_step2_feature_description.md) · [Step 3](./nav_links_load_stability_step3_development_plan.md) · [Step 4](./nav_links_load_stability_step4_implementation_summary.md)

Debug mode: enabled

# Step 4: Implementation Summary — Stable Messages/Invite Nav Buttons

## Stage 1 - Diagnose late nav buttons
- Changes:
  - None to application code (read-only diagnosis).
- Verification:
  - Code reading only; no browser run yet.
  - `FrontController::resolveStaticArtifactPath()` serves prebuilt static HTML only for GET requests with no query string and **no cookies at all**; any cookie (session, `identity_hint`, theme hint) falls through to the dynamic `Application`.
  - Static HTML is built with no viewer, so it never contains Messages/Invite. Dynamic pages include them only when `resolveViewerProfileFromIdentityHint()` finds an approved viewer with `_authenticated_identity === true` (needs a live PHP session; an `identity_hint` cookie alone gives `false` and no items).
  - No script found that inserts Messages/Invite on a normal page load. `feature_flags.js` patches Messages only after a flag toggle; `private_site_auth.js` reloads the page after authenticating, and is loaded only in members-only/public-resume modes.
- Notes:
  - **Step 2 assumption not confirmed:** there is no client-side post-load insertion in the code. Plausible explanations, each unverified: (a) navigating between cookie-less static pages and dynamic pages makes the items appear/disappear between page loads; (b) a viewer with an `identity_hint` but no live session gets no items until a session is established; (c) site.css loads non-blocking (`media="print"` swap) so nav styling/icons for these links arrive after first paint.
  - Needs the user's observation (which pages, signed-in how, which theme) or a browser repro before Stage 2 so the fix targets the real cause.
