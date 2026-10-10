# Ephemeral Testing: Task Descriptions and Agent Prompts

Companion to `ephemeral_testing_targets.md`. Each task gives a goal, the inputs the
agent may use, and a concise prompt to hand to a fresh coding agent.

## Ground rules (apply to every task)

- Work in a new, empty directory outside the repo. Nothing built is committed here.
- Allowed inputs: the docs and artifacts listed per task. Do not read `src/` unless the task says so.
- Keep a `FINDINGS.md` in the working directory. Log every ambiguity, question,
  workaround, surprising default and unhelpful error message, with a one-line
  note on what doc, error message or API change would have prevented it.
- Stop after the deliverable works or after being blocked for a meaningful time; record why.
- Triage findings afterwards as: doc fix, error-message fix, or API change.

### Common prompt preamble

> You are testing a codebase by building a throwaway tool on top of it. Use only
> the inputs listed below. Do not read implementation source unless told to.
> Maintain `FINDINGS.md`: log every point of confusion, workaround, surprising
> behavior or poor error message, and what would have prevented it. Findings
> about the project are the real deliverable; the tool is disposable.

---

## Task 1: Read-only interface from a database dump

**Goal:** Build a read-only web or CLI browser over a SQLite dump of the forum.

**Inputs:** a dump of the read model, `queries/sqlite` catalog,
`docs/specs/read_model_schema_v1.md`, `README.md`.

**Deliverable:** threads list, thread view and search, with no write paths.

**Prompt:**
> Build a read-only browser (small web app or CLI) over the attached SQLite dump.
> It must list threads, show a thread with its posts in order, and support basic
> search. Infer the schema from the dump and docs only. Open the DB read-only.
> Log in `FINDINGS.md` anything you had to guess about the schema, ordering,
> visibility or moderation fields.

## Task 2: Independent canonical-record parser and verifier

**Goal:** Reimplement record parsing and signature verification from the spec.

**Inputs:** the record specs in `docs/specs/` (`canonical_post_record_v1.md`,
`identity_bootstrap_record_v1.md`, `public_key_storage_v1.md`, and the other
`*_record_v1.md` files), `docs/architecture/public_architecture_and_trust.md`, and a
sample set of raw records with their detached signatures and public keys (no parser source).

**Deliverable:** a parser and verifier in Python or JS, plus a report comparing its
results with the repo's `scripts/audit_post_signatures.php` output on the samples.

**Prompt:**
> Implement a parser and signature verifier for the canonical records in the
> sample directory, in Python, using only the specs in `docs/specs/` and the trust
> architecture doc. Do not read PHP source. Expect that exactly what is signed and
> how verification works may be underspecified; log each such gap. Output one line per record: id, valid/invalid, reason. Log every
> place the spec was ambiguous or silent, and every disagreement with the
> reference results.

## Task 3: Third-party posting client

**Goal:** Create a key, sign a post and submit it to a local dev instance.

**Inputs:** public docs, a running dev instance (`scripts/dev_server.php`).

**Deliverable:** a script that registers or identifies a key, signs, posts, and
reads the post back.

**Prompt:**
> Write a minimal client that generates an OpenPGP key, signs a post, submits it
> to the dev instance at the given URL, and fetches it back. Use only public
> docs. Log every rejection message you hit and whether it told you what to fix.

## Task 4: Alternate static renderer

**Goal:** Render the archive with a different layout from the published data.

**Inputs:** a repository archive or `state/static_html` snapshot, docs.

**Deliverable:** a single-page archive viewer or feed generated from the data.

**Prompt:**
> Generate a self-contained static archive viewer (single HTML file or small
> folder) from the attached repository archive, with a different layout than the
> existing site. Use only the archive and docs. Log what you had to reverse-engineer
> about file layout, naming and cross-references.

## Task 5: Foreign-format importer

**Goal:** Import a small non-forum dataset (e.g. mailing-list mbox) as posts.

**Inputs:** the `qdb_archive_import_*` scripts as the documented precedent, docs.

**Deliverable:** an importer that emits records the system accepts.

**Prompt:**
> Using the qdb archive import as the model, write an importer that converts the
> attached mbox into valid post records. Validate the output with the repo's
> tooling. Log which parts of the qdb importer were reusable and which were
> hard-wired to qdb.

## Task 6: Agent-reply provider swap

**Goal:** Add an alternate reply provider behind the existing contract.

**Inputs:** provider interface docs, `scripts/test_agent_reply_provider.php`, tests.
Reading the interface file is allowed; reading the Anthropic provider is not.

**Deliverable:** a stub or alternate provider that passes the provider tests.

**Prompt:**
> Add a new agent-reply provider (a deterministic fake is fine) that satisfies the
> existing provider contract and passes the provider tests. Do not read the
> Anthropic provider implementation. Log every assumption the contract made
> implicitly.

## Task 7: Operator status report

**Goal:** Produce a health summary using only the operator scripts.

**Inputs:** `scripts/*.php` and their `--help` output, `docs/reference/v3_cli.md`,
`docs/runbooks/`, `README.md`.

**Deliverable:** a script or markdown report showing queue state, recent errors
and index freshness.

**Prompt:**
> Build an operator status report using only the CLI scripts in `scripts/` and
> their help output. Do not read script source. Log which questions you could not
> answer from the CLI and which flags or outputs were unclear.

## Task 8: Blind deploy

**Goal:** Stand up a fresh instance from the docs alone.

**Inputs:** `README.md`, `CONTRIBUTING.md`, a clean clone.

**Deliverable:** a running local instance with one signed post visible.

**Prompt:**
> From a clean clone, get a local instance running and create one signed post,
> following only `README.md` and `CONTRIBUTING.md`. Record each step, each
> command that failed, and each missing prerequisite.

---

## Suggested order

1. Tasks 1 and 2 first (data layer and spec).
2. Then 3 and 8 (external contract and onboarding).
3. Tasks 4 to 7 as time allows.

## Findings triage template

| # | Task | Finding | Type (doc / error / API) | Proposed fix | Status |
|---|------|---------|--------------------------|--------------|--------|
