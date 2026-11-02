# Application update

Purpose: Let an operator move the running application to the latest `origin/main` without SSH, and never leave the checkout half-updated.
Scenario: The maintainer, on a phone, opens Tools → Deploy. The page shows the current commit and, from the last fetch, that it is two commits behind. They tap Deploy now; the server fetches, sees a clean `main` that `origin/main` fast-forwards, moves `HEAD`, logs the attempt and shows the new commit. Had someone edited a tracked file on the server, the same tap would have been refused with "local changes" and nothing touched. The queued Deploy button does the same through the task queue, and `./v3 deploy` does it over SSH; all three share one lock and one attempt log.
Sources: [routine](../../src/ForumRewrite/Deploy/AppUpdate.php), [page and actions](../../src/ForumRewrite/Http/DeployController.php), [template](../../templates/pages/deploy.php), [page script](../../public/assets/deploy_control.js), [CLI](../../scripts/deploy.php), [operator guide](../runbooks/production_deploy.md#deploy-from-the-site), [tests](../../tests/AppUpdateTest.php), [page tests](../../tests/DeployPageTest.php).

Boundaries: pull only (no docs publish or static build); only session-authenticated root-approved operators and same-origin requests; opening the page reads local Git state and never fetches.
