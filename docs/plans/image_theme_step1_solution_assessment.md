> **Feature plan:** [Step 1](./image_theme_step1_solution_assessment.md) · [Step 2](./image_theme_step2_feature_description.md) · [Step 3](./image_theme_step3_development_plan.md) · [Step 4](./image_theme_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/image_theme` · [ledger](./effort_estimation_trial.md) · standing enrollment P2
Extended estimates: enabled
Cycle estimate: 0.80

## Original Query

"Please. Like, if you could, if Claude could take my Pinterest, or maybe a screenshot of my Pinterest, and convert that into a theme so that it's personalized to my style. That would be amazing, Claude, on Zen Memes."

Follow-up: "Yes, that would be amazing to do from an image. Can we Railspike this, please?"

## Understood Intent

Any ZenMemes visitor gives the site an image, such as a Pinterest screenshot. The site turns it into a theme that matches the image's style. Ilya's own Pinterest theme is the first test.

## Problem

Themes today are hand-written stylesheets in `public/assets/`, so nobody can get a personal theme without a code change.

## Concepts touched

- Theme selection (proposed entry): changed by B and C, used unchanged by A.
- Custom theme (proposed entry): created by B and C.
- [Navigation icons](../concepts/theme_navigation_icons.md): used unchanged.
- Agent responses ([entry](../concepts/agent_responses.md)): C reuses its model settings.

Feedback items: no matching item. Related but separate: PF-20261030-F12, PF-20261104-F13.

## Option A — Agent writes a stylesheet per image

**Estimate: 0.25**

A person posts the image in a thread. An agent writes a new `theme-<name>.css` file and adds it to `ThemeRegistry.php` through the usual thread-to-PR path. It reuses the existing contributor flow and needs no new runtime code.

- Pro: best visual quality, since the agent can add fonts and flourishes.
- Pro: nothing new to secure.
- Con: not self-serve; every theme waits for a review, merge and deploy.
- Con: every personal theme appears in everyone's theme menu.

## Option B — Colors picked from the image in the browser

**Estimate: 0.55**

The theme menu gets a "From an image" choice. The browser reads the image and picks its main colors. It maps them onto the theme variables, such as `--page-bg`, `--ink` and `--line`, and fixes any pair that fails a contrast check. The result is saved in that browser as a "Custom" theme. The image never leaves the device.

- Pro: instant, private, free, and works without any model.
- Pro: the contrast check keeps every result readable.
- Con: colors only; it cannot choose fonts or style.
- Con: a busy Pinterest grid gives a muddy average, not a style.

## Option C — A vision model reads the style

**Estimate: 0.75**

The person uploads the image. The server sends it to the configured model, which already powers agent responses. The model returns a theme description: the same variables, plus a font choice and corner shape from fixed lists. The server checks every value and fixes poor contrast, then the browser saves the theme. The model never writes raw CSS.

- Pro: understands "style" in a collage, not just average color.
- Pro: fixed lists and server checks keep output safe.
- Con: needs image input added to the model providers.
- Con: costs per request and needs a rate limit; it needs a model to be configured.

## Recommendation

Combine C with B's parts. Use the model when one is configured, and B's color picker when it is not. Both paths go through one shared contrast check and save the same "Custom" theme in the browser. The vertical slice: upload a screenshot, preview the theme, keep or discard it.

Leave sharing a theme with others for a later cycle. That cycle could publish a theme as a post and reuse Option A's quality bar.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Study checkpoint

- Checkpoint: intake and Step 1 handoff, 2026-11-07T05:30Z, single exposed Claude rater.
- Scope v1, recommended C + B. Starting revision `f343b626`. Reference R1, protocol P2; R1 hashes not re-verified.
- Pristine intake missing: `ThemeRegistry.php`, theme stylesheets and the model providers were read before rating.
- Intake and projected total **0.80**. Option estimates 0.25 / 0.55 / 0.75, non-additive.

## Enhancement Requests

None yet.
