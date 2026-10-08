# Forte Unauthenticated Vote Identity Setup — Step 3: Development Plan

## Stage 1 - Preserve first-vote identity-loader failures

- Goal: Make an unauthenticated Forte vote continue to identity preparation when the lazy runtime succeeds, and report the actual loader/preparation failure when it does not.
- Dependencies: Approved Step 2; existing lazy browser-signing and reaction-feedback contracts.
- Expected changes: Extend the shared reaction identity handoff to distinguish an absent lazy-loader contract from a rejected loader result, retain actionable error context, and leave the vote unapplied on failure. No API or database changes.
- Verification approach: Syntax-check the changed client asset; manually test a fresh browser's first Like and Flag without touching the composer, plus a prepared-identity reaction regression.
- Risks or open questions:
  - Confirm the customer-facing text is useful without exposing internal asset URLs; retain technical detail only through the existing diagnostic treatment.
  - Confirm an identity/OpenPGP failure already classified by the canonical identity flow remains unchanged.
- Canonical components/API contracts touched: `thread_reactions.js` reaction identity handoff and shared in-place reaction feedback; `ForumLazyComposeSigning.load()` consumed unchanged.

## Stage 2 - Cover lazy-load failure and first-vote recovery

- Goal: Prove the shared reaction path handles loader success, loader rejection, and a missing loader deterministically.
- Dependencies: Stage 1.
- Expected changes: Extend the existing browser-signing/reaction and lazy-loader test harnesses with focused first-vote and failed-load cases; assert no reaction write follows an identity-loader failure. No production API or database changes.
- Verification approach: Run the focused reaction and lazy-loader tests; verify both Like and Flag test fixtures preserve normal optimistic/applied behavior after successful identity preparation.
- Risks or open questions:
  - Keep the DOM stubs aligned with the production event ordering so the test covers the first-click path rather than only a preloaded helper.
- Canonical components/API contracts touched: `BrowserSigningNormalizationTest`, `LazyComposeSigningTest`, reaction fetch contract (read-only verification).

## Stage 3 - Verify Forte release asset consistency

- Goal: Detect a rendered Forte page that references unavailable or incompatible signing/reaction assets before release.
- Dependencies: Stage 2; existing fingerprinted-asset rendering and asset-serving behavior.
- Expected changes: Extend the existing Forte/render or asset smoke coverage to verify its lazy-loader, reaction script, runtime asset configuration, and fingerprinted asset responses form one compatible release. No new deployment pipeline or database changes.
- Verification approach: Run focused render/asset smoke tests and inspect a cache-busted Forte response; confirm every referenced signing/reaction asset resolves and a fresh browser can exercise the Stage 2 first-vote path.
- Risks or open questions:
  - The current symptom may originate from an external cache or an older deployed release; local checks cannot prove the live deployment without a staging/production capture.
- Canonical components/API contracts touched: `ForteBoardController` script list, `TemplateRenderer` browser runtime asset configuration, fingerprinted-asset/FrontController serving contract, existing smoke tests.
