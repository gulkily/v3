# Continuous Fast Post Risk Assessment MVP Plan V1

**Status:** Draft — decision worksheet. Do not implement until the unanswered
questions below are completed and the MVP scope is approved.

**Supersedes:** The earlier review-queue framing of this document. Fast scoring
is a near-real-time, low-cost assessment for every newly accepted thread and
comment, not primarily a manually curated review queue.

**Related work:**
- `fast_post_scoring_productization_remaining_work_plan.md` records the gaps
  in the existing manual batch scorer.
- `fast_llm_post_scoring_step2_feature_description.md` and
  `fast_post_scoring_batch_runner/step2_feature_description.md` describe the
  delivered scoring primitive and batch runner.

## Product Intent

When a new thread or comment reaches the server and is accepted into the read
model, assess it cheaply with a small-model classifier. Persist a probability
that it is malicious, trollish, spammy, bad-faith, or otherwise non-contentful
according to the approved rubric, versus well-intentioned and contentful.

The normal path is asynchronous: the web request never waits for an LLM. A
coalesced queue task is triggered after the accepted post is durable, and cron
workers score new posts with higher priority than backfill. The product target
is a persisted successful assessment or an explicit retryable/terminal outcome
within one to two minutes of acceptance, subject to the selected service level
and provider availability.

This MVP produces private operational assessment data. It does not by itself
hide, delete, down-rank, publish, or otherwise act on a post. Any score-driven
moderation, ranking, agent behavior, or reader-facing display is a separate
feature with its own policy and calibration plan.

## MVP Outcome and Boundary

### Included

- Automatic eligibility and coalesced enqueueing for every newly accepted,
  nonempty root post and reply, plus their qualifying edits.
- A compact, independently configured lower-cost model request using the
  current fast-scoring context boundary: target text; bounded parent/root
  context only where a reply needs it.
- Private persistent result state keyed by post content and rubric revision,
  including probability, source, timestamp, attempts, and safe failure data.
- New-post priority, separate bounded backfill behavior, cron/worker capacity
  controls, and an explicit one-to-two-minute service-level measurement.
- Terminal/retryable state semantics, bounded retry, safe recovery controls,
  cost limits, status, and a live-provider smoke check.
- An approved-operator-only health/status surface or command. It is for
  operating the classifier, not necessarily for reviewing individual posts.
- A calibration and rollout process before any later score-driven action.

### Excluded

- Waiting for model inference in the create/edit web request.
- Public display of a score, score-based public sort/ranking, content hiding,
  user reputation, or automated moderation.
- A promise that every model call succeeds within two minutes during provider
  outages; failures must instead become visible and retry according to policy.
- Broad thread history, unrelated profile data, or private data in the model
  request beyond the approved compact context.
- Historical backfill being allowed to consume capacity needed for fresh posts.

## Required Decisions — Complete in This Document

Answer every numbered item. Select a proposed answer or replace it. A decision
may be deferred only if its associated capability is removed from the MVP.

### A. Assessment Contract

1. **Feature name:** Is “Continuous Fast Post Risk Assessment” acceptable, or
   what operator-facing name should be used?

2. **Probability meaning:** Complete this exactly: “A probability of `1` means
   ___; a probability of `0` means ___.”
   - Proposed starting point: `1` means the post is likely malicious, trollish,
     spammy, bad-faith, or non-contentful under the rubric; `0` means it is
     likely well-intentioned and contentful under the rubric.

3. **Class boundary:** Which behaviors belong in the high-risk side: spam,
   scams, manipulation, harassment, trolling, off-topic low-effort content,
   duplicated content, adversarial prompt injection, or others? Which must be
   explicitly excluded from this classifier?

4. **Single score versus categories:** Is one risk probability sufficient for
   MVP, or must the classifier also retain distinct reasons/categories? If
   categories are required, list them and say whether they affect the service
   target or only provide diagnostics.
   - Proposed answer: one probability plus narrow non-score eligibility signals;
     defer category probabilities to a later feature.

5. **Eligible content:** Confirm that all nonempty newly accepted root posts
   and replies are eligible. List exclusions such as system-generated posts,
   deleted/withdrawn content, imports, known agent posts, specific boards, or
   a language/content type.

6. **Edits:** Which edits require a new assessment: every persisted text edit,
   only material body/subject edits, metadata/tag edits, moderation edits, or
   none? Define “material” if used.
   - Proposed answer: any subject/body change creates a new content identity;
     metadata-only edits do not.

7. **Rubric:** Provide or name the production rubric prompt. Who owns it, who
   may change it, and how will reviewers know what changed between revisions?

8. **Rubric revision:** Is a prompt-text hash enough for freshness, or is a
   human-assigned name/version required too?
   - Proposed answer: retain the hash for freshness and require a displayed
     name/version plus a short change note.

9. **Model:** What provider/model may be used, what structured-output behavior
   is required, and who may change the model or model settings?

10. **Context and privacy:** Confirm the target-text and bounded parent/root
    reply context boundary. May author names, board/tag names, links, quoted
    text, or prior score data be sent to the provider? List any prohibited data.

### B. Service Level, Queue Priority, and Cost

11. **Service target:** Choose the measured target: e.g. 95% of eligible new
    posts have a persisted `scored` or explicit non-terminal result within 60,
    90, or 120 seconds of acceptance. State the percentile and deadline.
    - Proposed answer: 95% within 120 seconds under normal provider operation.

12. **Clock start:** Does the target begin when the web request is received,
    the canonical write commits, the read model finishes updating, or the queue
    task is enqueued?
    - Proposed answer: successful canonical write/read-model update, because
      only then is the post durably eligible for worker processing.

13. **Cron cadence:** How often will the worker run, and how much jitter is
    acceptable? A one-to-two-minute target normally requires a schedule of at
    least once per minute.

14. **Worker capacity:** State maximum concurrent workers, per-run post limit,
    provider timeout, and maximum time a worker may spend before yielding. How
    should an already-running worker affect the next cron invocation?

15. **Burst capacity:** What arrival rate must meet the target (posts/minute or
    posts per two minutes)? What happens when it is exceeded: temporary queue
    delay, extra worker capacity, or a hard admission/cost limit?

16. **Priority policy:** Confirm that fresh eligible posts always outrank
    historical/backfill work. Within fresh work, should the queue use oldest
    deadline first, oldest accepted first, newest first, or another ordering?
    - Proposed answer: earliest accepted unscored post first, so the oldest
      service deadline is protected; backfill is a separate lower-priority
      sweep.

17. **Backfill:** Is historical scoring in MVP? If yes, define when it may run,
    its capacity share, and whether it pauses whenever fresh work exists.
    - Proposed answer: optional low-priority backfill that never claims fresh
      capacity and pauses whenever any fresh eligible post is pending.

18. **Budget:** State maximum request count, input/output tokens, and monetary
    cost per post and per day. What happens at the daily limit, and who may
    override it?

19. **Enable/pause policy:** Who may enable scoring and pause/resume automatic
    enqueueing? Does pause stop new scoring only, retries too, backfill too, or
    all provider calls?
    - Proposed answer: pause stops all background provider calls and fresh
      enqueueing; an explicit bounded smoke check remains separately available
      to an authorized operator.

### C. Result State, Retry, and Lifecycle

20. **Terminal states:** Confirm which results are current for matching content
    and rubric: `scored`, deterministic `excluded`, and any others.
    - Proposed answer: only `scored` and a documented ineligible `excluded`
      result are terminal.

21. **Retryable states:** Confirm whether `disabled`, `config_missing`,
    `provider_error`, and `invalid_response` are retryable. List any additional
    state and its terminal/retryable classification.
    - Proposed answer: all four are non-terminal and visible to operations.

22. **Automatic retry:** Specify attempts, backoff schedule, retryable failure
    categories, and whether a new content/rubric/model/configuration revision
    resets the attempt count.
    - Proposed answer: capped exponential retry only for transient provider
      failures; configuration repair or a changed content/rubric identity makes
      the post eligible immediately; operators may explicitly retry a named
      failure state.

23. **Exhausted retry:** After retries are exhausted, should the result remain
    in a failure state, retry on the next configuration change, require an
    operator retry, or all of these? Who is notified?

24. **Lifecycle triggers:** Confirm which events coalescingly enqueue fresh
    work: new root/reply, qualifying text edit, import, read-model rebuild,
    rubric change, scorer enablement, model change, and provider/configuration
    repair. Identify any that must not trigger a sweep.

25. **Deduplication and ordering:** How should multiple quick edits of one post
    behave? Is it sufficient to score only the final current content, even if
    earlier revisions were enqueued?
    - Proposed answer: yes; deduplicate to the latest content identity and
      never spend a model call on a superseded revision when it can be avoided.

26. **Data retention:** How long retain current scores, stale score revisions,
    attempt/failure data, task history, and model-exchange records? What must
    be redacted or deleted?

### D. Operations and Access

27. **Operator access:** Which exact existing role may see health/status,
    per-post results, failure details, retry/invalidate controls, configuration
    metadata, and exchange links? Are these permissions different?
    - Proposed answer: approved operators see health and recovery controls;
      existing LLM-exchange access remains no broader than it is today.

28. **Visibility:** Confirm that no score, failure reason, rubric detail, or
    model output is visible to anonymous visitors, ordinary readers, or post
    authors. State any exception.

29. **Required health data:** Which are mandatory: fresh-pending count and age,
    percentage meeting the service target, counts by state/source/revision,
    last success, last failure, retry backlog, cron/worker health, active
    model/rubric, pause state, and estimated spend?

30. **Recovery controls:** Which are needed for MVP: status command/page,
    retry one post, retry all retryable failures, invalidate a post/revision,
    requeue fresh work, pause/resume, and a one-post live-provider smoke check?
    Who may run each?

31. **Alerting:** What breach generates an alert or operator action: no worker
    heartbeat, oldest fresh pending post beyond the target, provider error rate,
    exhausted failures, budget exhaustion, or another condition? Where is the
    alert delivered?

32. **Backup/recovery:** Are the private score database and required operational
    records backed up? What restore test and recovery point are needed?

### E. Validation, Rollout, and Future Use

33. **Calibration set:** Where will labeled examples come from, who labels
    them, and how many are needed before enabling production scoring?

34. **Quality measure:** What precision, recall, false-positive tolerance, or
    qualitative review standard makes the classifier acceptable as an
    assessment? This does not authorize automated action.

35. **Pilot:** Name the pilot owner, eligible environment/content, duration,
    success criteria, and rollback triggers.
    - Proposed answer: run a bounded live-provider smoke, then a private
      operator pilot with no score-driven actions, before broad enablement.

36. **Release controls:** Which independently switchable flags are required for
    scoring, fresh enqueueing, retrying, backfill, and operator status? What is
    the immediate action that stops cost during an incident?

37. **Initial consumer:** After persistence, who or what may read the score in
    MVP: operator status only, the existing approved-only API, an approved-only
    post diagnostic, or another explicitly named internal system? Confirm that
    no score changes user-facing behavior in MVP.
    - Proposed answer: retain the approved-only API and add operator health;
      no score-driven decision or public display.

38. **Release sign-off:** Who approves the rubric, privacy boundary, cost
    envelope, queue/service target, operations/alerts, calibration result, and
    MVP release?

## Delivery Plan After Decisions

The completed answers become the acceptance contract. Implement in small,
separately approved feature slices:

1. **Assessment contract and state semantics:** final rubric/configuration,
   named revision, compact context, private state fields, terminal/retryable
   transitions, and unit coverage.
2. **Fresh-post priority pipeline:** post-write/read-model lifecycle trigger,
   coalesced deduplication, latest-content handling, deadline-aware candidate
   selection, and no-web-request-wait coverage.
3. **Worker capacity and retries:** cron/lock behavior, provider timeout,
   backoff, fresh-versus-backfill capacity isolation, cost limits, and service
   target metrics.
4. **Operations:** protected status, recovery controls, safe error data,
   alerts, retention, backup/runbook updates, and bounded live-provider smoke
   verification.
5. **Calibration and rollout:** labeled sample evaluation, pilot, production
   service-level/cost verification, rollback exercise, and release sign-off.

Any later proposal to affect moderation, ranking, visibility, agent behavior,
or a post's lifecycle must be planned separately with explicit thresholds,
human override, audit, calibration, and rollback contracts.

## MVP Acceptance Checklist

- [ ] The chosen rubric defines both probability endpoints and eligible content
  unambiguously, with an owned named revision.
- [ ] Every newly accepted eligible root post and reply is automatically made
  available to the priority queue without the web request calling the provider.
- [ ] Under the selected normal-load conditions, the selected percentile meets
  the one-to-two-minute persisted-outcome target.
- [ ] Fresh posts retain capacity and priority over historical backfill.
- [ ] A temporary disabled/configuration/provider/response failure cannot make
  a current post permanently ineligible for a later assessment.
- [ ] Operators can discover a missed target, failed score, stopped worker, or
  budget breach and recover through supported controls without editing SQLite.
- [ ] The configured budget, concurrency, timeout, retention, access, and
  alerting rules are verified in a pilot.
- [ ] No MVP score changes public display, ranking, moderation, agent behavior,
  or the accepted post itself.
