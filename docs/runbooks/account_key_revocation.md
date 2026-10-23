# Account key revocation

An authenticated, currently approved root-seeded operator can revoke an ordinary
account key from **Tools → User Access → account → Revoke key…**. Root-seeded
keys require a separate administrative recovery design and cannot use this action.
A remembered username or a display label of “root” is not operator authority.

## Review and outcome

Review the account, full identity ID, and **Revocation cutoff** before choosing
**Sign & revoke key**. The request explicitly contains the UTC cutoff, defaulted
to now when prepared and frozen for signing. It is not inferred from submission,
server acceptance or Git commit time. No custom, backdated or scheduled cutoff
selector is included. Changing the account/key context requires fresh review.

The server accepts under the write lock and separately records acceptance time.
Subsequent protected requests and attributed writes from the revoked key fail,
including existing sessions, fresh login and previously prepared signed actions.
Public/anonymous browsing retains the site's existing audience rules. A revoked
key cannot be approved again, root-seeded, renamed or restored through invitations.

Existing posts, signatures, attribution, scores and previously accepted grants
remain intact. A post accepted while the revocation review was open also remains
intact: the signed cutoff does not retroactively delete or distrust that content.
Previously sponsored members retain access. Unused invitations from the revoked
issuer cannot grant access. Already authorized in-flight reads and downloaded
plaintext/ciphertext cannot be recalled.

## Evidence and recovery

The account retains its revoked key in account-key history, including the original
approval facts and separate revocation cutoff, acceptance, operator and source.
Authenticated operators also see the event in User Access → Access history and
`/activity/?view=security`. There is no new ordinary Activity/RSS feed of revocations.
Evidence source/account routes follow the site's existing membership boundary.

A last-key revocation leaves the username reserved. Use another approved device,
or generate/link a **different** key using the retained username and have an
operator explicitly approve it through User Access → Pending approvals. Members
cannot use ordinary sponsorship or a permissive cross-account flag to bypass
this replacement requirement. A key already assigned to an unrelated account
cannot rename into the reserved username. Root rotation, owner self-service,
non-operator cross-user revocation and social recovery remain separate work.

- **Cancel before signing:** no revocation is accepted.
- **Changed or expired review:** refresh/review again and sign the new request.
- **Unknown outcome/network loss:** Retry submits the exact signed request again;
  Reload shows current account state. Closing the review does not cancel an
  accepted revocation. An exact retry does not create another revocation.
- **Committed but refresh failed:** preserve the signed request and canonical
  bundle. Keep protected access/writers in maintenance if recovery cannot complete;
  repair the configured read model/cache and retry or reload. Canonical revocation
  checks deny the key even if an obsolete approval bit remains cached.
- **Git commit failure:** the handled failure removes incomplete record/signature/
  receipt files and unstages them. After an interrupted process, inspect Git status
  under maintenance; never blindly commit or delete an incomplete security bundle.

## Deploy and restore

1. Record instance code/content revisions and the configured repository, database,
   public artifact and static HTML roots. Back up the complete committed canonical
   repository, including root seeds, public keys, revocations, signatures and
   acceptance receipts. Back up independent private storage under its existing policy.
2. Rehearse against an isolated copy. Deploy compatible code to **all** web/CLI/
   worker writers before reopening them; older writers can ignore revocation.
3. Schema 26 rebuilds automatically before affected normal queries. For explicit
   maintenance, use the instance's paths and artifact-root environment:

   ```bash
   ./v3 rebuild /path/repository /path/read-model.sqlite3
   ./v3 approval audit --repository-root=/path/repository --database-path=/path/read-model.sqlite3
   ./v3 approval audit --repository-root=/path/repository --database-path=/path/read-model.sqlite3 --json
   ```

4. Rehearse an ordinary-key revocation with existing authenticated and fresh
   devices. Verify exact signed cutoff, terminal denial, old content/sponsorship,
   retained username, operator replacement, public/member boundaries, no-store
   operator responses, and classic/Forte evidence. Inspect static/offline retirement;
   already downloaded copies are not recalled.
5. Restore a current archive through trusted repository administration and repeat
   rebuild/normal requests. Compare `revoked_keys` and `revocations` in JSON audit;
   readable output prints a separate Revoked keys table. `--summary` omits individual
   rows; `--review-only` filters approval review candidates, not terminal evidence.
   Remote content imports exclude revocation authority records and their receipts.

A backup predating revocation is not current security state. The supported archive
merge must retain existing revocations; a fresh instance restored from an older
backup needs current revocation evidence restored before writers/private access
open. The application cannot discover revocations missing from all restored records.
Never treat successful restoration of old content alone as security acceptance.

## Rollback limits

Older code may ignore revoked state or lose retained ownership/history during
rebuild. Stop incompatible writers/private serving and publication while rolling
back; preserve current canonical evidence and schema-compatible readers. Prefer
restoring compatible code and rebuilding. Do not remove revocation records,
signatures, acceptance receipts or historical root seeds to make an older rebuild
succeed. There is no same-key undo in this release.

## Verification boundary

The [implementation summary](../plans/account_key_revocation/account_key_revocation_step4_implementation_summary.md)
records isolated signed HTTP/browser, fault, permission, schema-startup, archive,
CLI, cache/offline and 16-theme phone/desktop evidence. Browser phone checks use
Chromium emulation. No production deployment, physical-device acceptance or
full-repository-suite pass is claimed.

Format: [key revocation record v1](../specs/key_revocation_record_v1.md).
Related: [approval safety](account_key_approval_safety.md),
[authentication modes](../reference/authentication_modes.md).
