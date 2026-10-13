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
