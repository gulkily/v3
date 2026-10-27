# Registration acceptance criteria — REG-03

These criteria apply to REG-01–24. They are engineering targets, not claims that the current implementation or production host meets them. The optional read-availability redesign has its own decision boundary. The user chose sequential implementation without Railspike.

The reference workload is the matched QDB snapshot documented in the [baseline](profile_registration_baseline.md): 18,760 posts, 3,845 profiles, 18,863 activity rows and five canonical approval-seed records. Its initial local median was 18.91 seconds for signed registration and 18.33 seconds for historical linking; profile activity refresh dominates. Local Git clones are packed, unlike the downloaded loose-object repository. Comparisons must preserve and disclose this difference.

Correctness requirements:

- A successful signed registration produces exactly one identity, public key and signed bootstrap, a valid username route, and the corresponding profile/read-model records. Historical linking attaches the intended existing bootstrap and authorship without inventing another discussion post.
- Username collisions, legacy ownership, hidden-bootstrap counts, approval seeds, transitive approvals, invitation ordering/acceptance, and public attribution retain their canonical meaning. Existing unrelated profiles and activity must remain unchanged when the new identity has no dependencies on them.
- Incremental results agree with canonical expectations and the required full-rebuild view. Any pre-existing disagreement is investigated and recorded, not silently blessed as compatibility.
- Source repository and source database remain untouched by benchmarks. Success metadata represents the applied canonical changes. Competing writes, duplicate requests and retries cannot skip records or create duplicate identities/bootstrap posts.
- Failures before canonical commit leave no accepted write. Failures after it explicitly report saved canonical data with pending derived state, with an observable, deduplicated recovery path. Acceptance includes recovery, not merely the happy path.
- Authorization and transaction correctness remain required. Retraction tests follow the currently supported behavior and the separately agreed simplification contract; this work does not expand the canceled sweep-diagnostics project or assume the old continuous-suppression contract is permanent.

Cost requirements:

| Check | Target |
| --- | --- |
| Independent new identity | No scan/rewrite of every profile or activity author; no load of every discussion body for unrelated approval checks. Work follows that identity's records and actual dependencies. |
| Activity attribution | Author-specific lookups use an index; statement count follows affected identities rather than total registered identities. |
| Historical linking | Work follows matching authorship/bootstrap rows and changed approval dependents. Required broad migrations/recovery are explicit maintenance work. |
| Redundant writes | An unchanged profile or activity row is not refreshed solely because a different independent identity registered. Track statements and matched rows separately from actual value changes. |
| Local QDB acceptance run | At least five independent runs of each case; target median service wall time and execution-lock hold below 1,000 ms, with every observed lock hold below 2,000 ms. Record all samples and setup/cache conditions. |
| Representative production-like host | Target p95 execution-lock hold below 2,000 ms over at least 30 registrations, and no lock hold reaching the default 5,000 ms timeout. This remains unverified until host measurements exist. |
| Concurrent reads | Test home and user-directory reads during controlled writes: at least two concurrent registration clients and eight read clients. Require no registration-induced busy/5xx responses, correct content/access behavior, and read latency below the configured lock timeout. Record workload rate and host limits. |
| Memory | Isolated identity-update peak memory below 128 MiB at the baseline scale; unrelated profile growth must not add a full-site post-body array to independent registration. Record the actual host memory limit before delivery. |

If a target is missed, retain the evidence and revise the implementation or explicitly narrow its supported workload. Do not close the item by increasing the timeout or substituting a fastest-run measurement. If a numeric target must change, record the reason and preserve this original target.

Controlled scaling checks must vary dimensions independently. Keep the same affected identity and content while increasing unrelated profiles, posts and activity to roughly 2× and 4× the baseline where practical. Prepare internally consistent canonical/database fixtures in disposable directories, with a manifest of intentional changes. Compare statement counts, affected rows, query plans, peak memory and repeated timings. The small parity fixture is for correctness and harness checks; it is not evidence of QDB-scale performance.

Migration and delivery require the current schema specification, a version bump for schema-dependent changes, prior-schema startup regression, real rebuild/promotion verification, and affected UI/API flows. Avoid surprising production with a request-time schema rebuild. No new dependency is proposed. Required public UI verification applies only if later items change public UI behavior.

Evidence ownership: REG-04–09 record the intermediate query/update improvement; REG-10–19 establish bounded work and recovery; REG-20–23 own the final semantic, concurrency, scaling and delivery matrix; REG-24 collects the completion evidence and unresolved limitations. Shared checks may be reused when the relevant code is unchanged, but a focused pass is not a full-suite or production claim.
