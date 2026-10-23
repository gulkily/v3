# Report: username changes that keep your approvals

Based on reading the code and git history. The app and test suite were not run.

## Short answer

Approvals are tied to your key, not your username. A rename is stored as a separate signed record that replaces the displayed name. Nothing in the activity indexer reads that record, so the rename never gets an activity entry. Both behaviours were added deliberately on 2026-10-15, as part of the "zero-click targeted invitations" feature.

## How it works

1. **Approval follows the key's identity ID.** Approvals are keyed on `openpgp:<40-hex fingerprint>`. Approval state is computed per `identity_id` (`src/ForumRewrite/ReadModel/ReadModelBuilder.php:729`), and operator seeds use the same ID (`ApprovalPolicy::isOperator`). The username is only a label.
2. **A rename is a new signed record.** Account -> Choose username calls `POST /api/prepare_profile_name`. The server builds a `set-name` record with `Author-Identity-ID` and `Username` (`src/ForumRewrite/Write/LocalWriteService.php:1178`). Your existing private key signs it. `POST /api/finalize_profile_name` verifies the signature (`:1199`) and commits `records/profile-names/profile-name-<hex>.txt` plus a `.asc` signature. Your key, identity bootstrap record and approval are untouched. Only the key owner can rename.
3. **The new name is applied when the read model is built.** `ProfileNames::latest()` picks the latest verified record per identity, ordered by `(Created-At, Record-ID)`. `ReadModelBuilder.php:643-655` uses it instead of the bootstrap username when building `profiles`. Unsigned or malformed records are ignored.
4. **The activity log doesn't see renames.** `indexActivity()` (`ReadModelBuilder.php:763`) only writes entries for posts, thread labels, reactions, feature flags and Codex handoffs. Profile-name records are not a source. The spec says only that existing activity fields "carry the effective name" (`docs/specs/read_model_schema_v1.md`, schema version 20). This was a design choice, not a regression.
5. **Old activity entries show the new name.** `resolveActivityAuthor()` looks up the author's current profile at rebuild time, so earlier rows are relabelled. The rename itself leaves no trace. Signed content files are unchanged.

## When it was introduced

All on **2026-10-15**, by Ilya Gulko, on the `invite_zero_click` feature branch, merged to `main` in `bc036d5d` at 15:51 (-0400).

| Commit | Time | Change |
|---|---|---|
| `83df2361` | 15:17 | Plan for the zero-click targeted invitation flow |
| `65f40965` | 15:20 | Stage 2: prepare/finalize endpoints, record parser, signed write path |
| `2bb85309` | 15:21 | Stage 3: `ProfileNames` replay in the read model (schema version 20) |
| `449ea0e8` | 15:22 | Stage 4: Account "rename-profile" button, temporary names for invitees |
| `68d26341` | 15:47 | Docs: "Choosing a username, step by step" |

Before this, renaming wasn't possible. `docs/specs/profile_read_contract_v1.md:57` still says "V1 has no separate username-change or profile-update flow after bootstrap", which is now out of date.

Why it was added: invitees get an auto-generated `guest` identity, so they need to pick a real name later without losing the key or approval.

## Things to be aware of

- **The activity log omits renames.** Adding them would need a new activity source in `indexActivity()` that reads `records/profile-names/`. I did not check whether git-commit-based activity views mention the "Set profile name for ..." commits.
- **Duplicate names and approvals.** Names need not be unique. `ApprovalPolicy::evaluate` counts approved profiles by `username_token` (lowercased username) and has a same-username rule. A rename changes that token, which can change who falls under the rule. I have not traced the consequences.
- **Stale spec line.** `docs/specs/profile_read_contract_v1.md:57` probably needs updating.

## Example record

A real record from the local instance (`state/local_repository`, commit `e895cbe`, "Set profile name for openpgp:b027d55a…", 2026-10-16 08:34:12 -0400):

`records/profile-names/profile-name-535aa5a752f215011b7407ff76f54a10.txt`

```
Record-ID: profile-name-535aa5a752f215011b7407ff76f54a10
Created-At: 2026-10-16T12:34:12Z
Operation: set-name
Author-Identity-ID: openpgp:b027d55ae2f5f6adc6fad79aa842dd0adbfa64b0
Username: new

```

Its companion `.txt.asc` file holds a detached OpenPGP signature over those exact bytes. Fields (`ProfileNameRecordParser`):

- `Record-ID`: `profile-name-<32 hex>`, must match the filename.
- `Created-At`: UTC timestamp. The latest verified record per identity wins; a new record must be strictly later than the previous one.
- `Operation`: always `set-name`.
- `Author-Identity-ID`: the key's identity. Approvals attach to this value, and it names the key that must have signed the file.
- `Username`: 1-80 characters from `a-z 0-9 . _ -`.
- Body: empty; the parser rejects any body text.

The activity feed has no entry for this record.

## Effect on composite users

"Composite identity" does not appear anywhere in the repo (a `todo.txt` task tracks defining it). The defined term is **composite user**: all approved profiles sharing a normalized username (`username_token`). It is defined in `docs/specs/profile_read_contract_v1.md` ("Username Route Resolution"), `docs/architecture/private_messaging.md` and `ApprovedUserKeyResolver`.

Expected effect of a rename record, from reading the code and specs (not run):

- **Approval:** unchanged. It follows `identity_id`.
- **Username and token:** the profile's `username` becomes the record's `Username` and its `username_token` becomes the lowercased value, so the profile leaves its old composite user and joins the one with the new token (or forms a new one).
- **Aggregate pages:** `/user/<name>` and the `/users/` directory group approved profiles by token, so counts, totals and the participant list change for both the old and new names.
- **Private messages:** `ApprovedUserKeyResolver` selects recipient keys by token, so new messages to the new name are encrypted to this key and messages to the old name no longer include it.
- **Unverified:** whether stored `private_messages` rows (`sender_username_token`, `recipient_username_token`) are updated on a rename. If they are not, existing conversations stay under the old name.
- **Unverified:** the approval same-username rule in `ApprovalPolicy::evaluate` (see above).

## Follow-up: gate, activity entry and caption (branch `feature/username-change-gate`)

Plans: [username_change_gate](../plans/username_change_gate/).

- **Gate:** new site-mutable flag `FORUM_USERNAME_CHANGES_ENABLED`, off by default. While off, Account hides "Choose username", `/api/prepare_profile_name` and `/api/finalize_profile_name` refuse (finalize re-checks the flag, so a change prepared earlier cannot complete), and rename activity is hidden. Saved names stay in effect and keys and approvals are never touched by the flag.
- **Activity:** read-model schema version 21 indexes one `profile_name_change` activity row per verified rename ("Changed username from X to Y", with the record as source). It is listed in the `all` and `identity` views, counts, detail pages, RSS, the Forte activity view and the pending-users latest activity only while the flag is on.
- **Caption:** while the flag is on, the key-replacing Account button reads "Start over with a new key" (it creates a new key and identity); while off it keeps "Re-setup / change name".
- **Consequence to note:** the zero-click invitation flow lets invitees pick a name after arrival; with the flag off they cannot, and stay `guest` until an operator enables it.
- **Still open:** the composite-user effect of a rename (grouping by username token, approval same-username rule, stored private-message tokens) is untraced; see `todo.txt`.

## Follow-up: composite user definition and join requests (branch `feat/username-rename-collision`)

Plans: [username_rename_collision](../plans/username_rename_collision/).

- **Traced:** stored private messages are not re-keyed on a rename, so earlier conversations stay under the old name; and a rename into an approved user's name previously joined that composite user without consent, gaining its new messages and "same username" approval authority.
- **Defined:** "composite user" in the [profile read contract](../specs/profile_read_contract_v1.md#composite-user); "composite identity" is not a separate term.
- **Changed:** renaming into a name another profile uses is now a join request that a holder of the name or an operator must accept; `guest` stays shared. See [joining a username](../reference/invitation_entry.md#joining-a-username-someone-else-uses).
- **Still open:** private-message history after a rename, and conversation metadata visible to whoever later takes a vacated name.
