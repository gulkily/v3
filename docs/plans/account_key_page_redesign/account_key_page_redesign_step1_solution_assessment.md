> **Feature plan:** [Step 1](./account_key_page_redesign_step1_solution_assessment.md) · [Step 2](./account_key_page_redesign_step2_feature_description.md) · [Step 3](./account_key_page_redesign_step3_development_plan.md) · [Step 4](./account_key_page_redesign_step4_implementation_summary.md)

Debug mode: enabled

## Original Query
Please help me redesign the /account/key page while maintaining its unique features that are unique to this application. Imagine you're a UI/UX designer at Apple, Airbnb, or Stripe, and you're tasked with redesigning this page according to the principles of good design. Also keep in mind that this application is meant to be transparent and accessible to technical users. Write Step 1 of RAILSPIKE.md.

## Understood Intent
Make `/account/key/` read as a calm, well-ordered page with one clear primary task per state, while keeping what is distinctive: browser-local key generation, encrypted text/Starfield PNG backup and restore, clear-with-undo, the optional guest-key publication setting, and raw public/private key visibility. Key handling, signing, storage, and server behavior are unchanged.

## Problem Statement
The page buries a few important actions (set up, back up, restore) among a dozen equal-weight technical controls, so newcomers cannot tell what state they are in or what to do next, while technical users cannot easily see what is stored where.

## Option A: Styles-only polish
Tighten the existing `.account-key-*` styles: type scale, spacing, button hierarchy, and focus rings. Markup and section order stay as they are.

- **Pros:** Smallest change; no risk to the script hooks and tests that depend on the markup.
- **Cons:** Leaves the flat "Advanced / technical details" drawer that mixes safe actions (copy public key) with destructive ones (clear keypair, copy private key); hierarchy and next-step guidance stay weak.

## Option B: Status-first restructure with a transparent details layer
Reorganize the page around the user's state: a title with one-line purpose, a status card (ready / not set up, username, fingerprint, profile link) with a single primary action, then a clearly ordered "Back up and restore" section. Technical content moves into a labeled "Under the hood" section that groups it by intent: public key (copy, view), private key (view behind an explicit reveal, with a plain warning), storage and identity hint (what lives in this browser versus the server), and destructive actions (clear, with the existing undo) set apart visually. A deliberate type scale, whitespace, and theme tokens are scoped to this page.

- **Pros:** Resolves hierarchy and grouping together; keeps every current capability and its data hooks; makes "what is stored where" explicit for technical users; destructive actions are separated and easier to trust.
- **Cons:** Touches template and CSS; needs visual and keyboard checks across themes at desktop and 375px widths; moving controls requires verifying existing tests and script selectors.

## Option C: Guided multi-step wizard
Replace the page with a step-by-step flow (choose a name, generate, back up, confirm) and tuck raw key tools into a separate view.

- **Pros:** Strongest onboarding; least initial noise.
- **Cons:** New states and navigation to build and test; hides the transparency that technical users expect; exceeds one slice and adds behavior to a security-sensitive flow.

## Recommendation
Choose **Option B**, folding in Option A's polish. It is one releasable slice: a visitor lands on the page, sees their key state at a glance, takes the primary action, backs up or restores, and technical users can still inspect, copy, link, and clear keys exactly as before. Behavior stays in the existing script; only structure, hierarchy, and presentation change. Defer Option C's guided onboarding as a possible follow-up once the clearer layout is in place.
