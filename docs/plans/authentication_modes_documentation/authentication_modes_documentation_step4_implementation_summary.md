> **Feature plan:** [Step 1](./authentication_modes_documentation_step1_solution_assessment.md) · [Step 2](./authentication_modes_documentation_step2_feature_description.md) · [Step 3](./authentication_modes_documentation_step3_development_plan.md) · [Step 4](./authentication_modes_documentation_step4_implementation_summary.md)

Debug mode: enabled

## Setup

- Branch: `docs/authentication-modes`.
- Planning commit: `255f3f84`; approved plans organized with repaired links and index entry before implementation.
- Scope: the four approved product documents plus this feature's workflow artifacts. Existing `todo.txt` edits are excluded.
- Evidence baseline: `7948badf6555aa52c2515981caba77bd5860134d` (also contained in `origin/main` at review time).
- Production deployment, schema/migration changes, broad runtime regression, and generated release artifacts are not applicable to this documentation-only change. Targeted behavior and document-rendering checks are applicable to its claims.

## Stage 1 - Authentication Reference and README Entry

- Changes: added the [authentication reference](../../reference/authentication_modes.md) with state/setting matrices, route exceptions, action limits, persistence, recovery, and pinned source/test evidence. Updated README discovery, public-key publication wording, and members-only exceptions.
- Verification: `php tests/run.php PrivateSiteAuthTest AuthNavigationTest InvitationIssuanceTest` — 19 passed. A second filtered run — 15 passed — covered the five automatic-guest/anonymous browser methods and ten server-session/access methods listed below. A third run of `FeatureFlagEvaluatorTest::testDefaultsMatchExistingSiteFlags`, `::testEnvironmentOverridesDefaults`, and `::testRepositoryValuesOverrideDefaultsButNotEnvironment` — 3 passed. Total: 37 passed, zero failures.
- Verification: an ad hoc Python/PHP check validated all 23 reference links against Git objects/local files, resolved documentation URLs in repository and `/docs/` contexts, rendered the new page with `PublicMarkdownRenderer`, and checked all plan-navigation links. HTTP HEAD checks for all 16 distinct pinned GitHub evidence URLs returned 200. `git diff --check` passed; only README, the new reference, and this summary are included in the stage commit.
- Notes: source inspection additionally covered invitation redemption eligibility, identity hints/clearing, publication before authentication and vote prewarming, route ordering, and host session policy. These are not claims of end-to-end production or browser coverage. No authentication logic changed. Existing `todo.txt` edits remain excluded.

### Stage 1 Filtered Test Inventory

The 15-method run used `php tests/run.php` with these `Class::method` filters:

- `BrowserSigningNormalizationTest`: `testAnonymousComposeSubmitSkipsBrowserIdentity`, `testAutomaticGuestIdentityDefersPublicationUntilFirstSignedAction`, `testAutomaticGuestIdentityPreservesExistingBrowserKeypair`, `testAutomaticGuestIdentityPublishesImmediatelyWhenBrowserPreferenceIsSet`, `testAccountGuestPublicationPreferencePersistsAndPublishesCurrentKey`.
- `LocalAppSmokeTest`: `testApprovedPrivateSessionCanViewOwnProfileAndBoard`, `testApprovedPublicSessionIsResumedForBoardAndInvite`, `testAnonymousPublicBoardDoesNotStartViewerSession`, `testPrivateViewerSessionCookiePersistsAcrossBrowserRestart`, `testClearingIdentityRevokesApprovedPrivateSession`, `testPrivateAuthenticationEndpointReachesSignatureVerifier`, `testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces`, `testPrivateForteRoutesRecoverExpiredSessionsInsteadOfReturningFalseNotFound`, `testPendingPrivateSessionNavigationOnlyShowsLobbyOwnProfileAndAccount`, `testAutomaticGuestKeypairFlagRendersBrowserRuntimeOption`.

## Stage 2 - Reconcile Guidance and Verify Reader Journeys

- Changes: architecture guidance now distinguishes identity from access, explains automatic preparation, qualifies backup/source access, and links to the reference. The session document corrects the navigation-guard claim, summarizes implemented public/private recovery, and labels original problems, alternatives, sequencing, and acceptance targets as history rather than current behavior or test results.
- Verification: `php tests/run.php PlatformDocsCatalogTest PlatformDocsPageTest` — 12 passed; `php tests/run.php ResumeTargetTest` — 3 passed. Total across both stages: 52 tests passed, zero failures.
- Verification: an ad hoc PHP check issued six fixture-backed `Application::handle` GETs: architecture, new reference, and session history, each with members-only off/on. All returned HTTP 200 with the expected heading/source label; architecture discovery and reference access/recovery sections were present. No production host was contacted for application behavior.
- Verification: an ad hoc Python/PHP check resolved all 11 relative documentation links across these three documents in both filesystem and rendered URL contexts, checked both README entry links, and rendered links with the existing Markdown renderer. Historical/current labels and the corrected guard claim were checked. Plan links and `git diff --check` passed; the stage commit includes only architecture, session reference, and this summary.
- Notes: source/test evidence and reader scenarios were reviewed together, not treated as a complete security or cross-browser audit. Host cleanup, browser retention, and untested multi-tab behavior remain explicit verification limits. No code, tests, deployment configuration, or generated release assets changed.

### Reader Scenario Review

- Public, no key: README → reference → public/keyless matrix row → browsing remains available; anonymous compose remains an attribution option.
- Automatic guest on/off: setting rows → local preparation versus explicit setup → publication/readiness explained independently of approval. Five browser methods and flag-precedence checks support this distinction.
- Published/hinted or authenticated-unapproved: identity definitions → no inferred approval → members-only entry exceptions and Lobby/invitation guidance. Source inspection confirms the status endpoint's approved-only semantics.
- Approved, active session: access row → protected site access → additional signature/operator rules still apply; server smoke and active-navigation checks passed.
- Expired session with saved key: recovery → Reconnecting/guard → validated destination; authentication/navigation and return-target checks passed.
- Missing key or failure: recovery → Account Key/Lobby or stay on public page → restore an existing private key, set up a different identity, obtain approval, or retry after the displayed error. Relevant authentication tests passed; real clipboard/storage/network environments were not exercised.

## Completion

- All four approved product documents are complete and consistent with the reviewed evidence. Documentation readers can discover the reference, choose the applicable state, and find participation/recovery guidance.
- The two implementation stages each include verification and this summary in their stage commit, following the separate planning commit. The feature remains on `docs/authentication-modes`; no merge, push, or deployment was performed.
- Follow-up boundary: this review makes no claim of complete endpoint authorization, host-retention, or multi-tab coverage. Any runtime investigation or change requires separate scope. Existing `todo.txt` edits remain untouched and uncommitted by this work.
