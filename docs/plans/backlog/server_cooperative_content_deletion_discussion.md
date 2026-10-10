# Server cooperative content deletion discussion

Discussion date: 2026-10-10. Status: exploration notes, not an approved implementation plan.

## Intended scope

The immediate goal is to let users retract their own posts with the server's
help. The user clarified that this was the intent behind "explore
server-cooperative content deletion" in [todo.txt](../../../todo.txt).

Propagating and honoring deletion requests across cooperating instances may be
useful in the future, but is outside the initial scope. Local deletion should
be useful independently of any cross-instance protocol.

## Current behavior

The existing [`delete-record` command](../../../scripts/delete_record.php)
removes a canonical file with `git rm`, commits the removal, and rebuilds the
read model. It also rebuilds static artifacts when an artifact root is
configured. See the [CLI reference](../../reference/v3_cli.md#delete-a-canonical-record).

This is an operator removal mechanism. It retains content in Git history and
does not by itself establish an author-requested deletion protocol or a promise
to purge every server-controlled copy.

## Deletion promises to distinguish

| Promise | Meaning |
| --- | --- |
| Stop displaying | Remove content from pages, search, feeds, APIs, and current exports. |
| Forget locally | Also purge retained content from server-controlled history, artifacts, and caches, with an explicit backup policy. |
| Cooperate across instances | Share authenticated deletion requests that other instances can independently verify and honor. Future scope. |

The initial feature still needs a decision about how thoroughly the local
server forgets content. Hiding a post while leaving its text accessible through
historical downloads would provide a weaker promise than users may expect.
Copies already downloaded by others cannot be guaranteed to disappear.

## Proposed direction

Explore an author-signed deletion record identifying the target post. The
server would verify the requester's authority, remove the content according to
its deletion policy, and retain a minimal tombstone. The tombstone would prevent
an old archive import or restore from silently resurrecting deleted content.
Its contents and retention need definition without preserving the deleted prose.

Keep author deletion distinguishable from moderator removal. Deleting a post
should generally preserve other people's replies, with a deleted-post
placeholder maintaining the conversation structure. These are proposed design
choices, not settled requirements.

## Open questions

- What proves deletion authority when an author has multiple keys, has lost a
  key, or posted anonymously without a signing identity?
- Does local deletion mean suppression, physical purging, or a staged process?
  How are Git history, old static releases, offline snapshots, caches, exports,
  and backups handled?
- What metadata remains in a tombstone, and how does it survive imports and
  restores?
- How should deleting a thread root affect its subject, navigation, and replies?
- What should the user see while deletion is pending, complete, or partially
  failed?

## Future possibility

A portable deletion record could later let cooperating instances honor author
requests under their own policies. Another instance might honor an author's
request automatically while applying its own rules to moderator actions.
Cross-instance delivery and trust rules can be explored later; preventing local
resurrection is relevant from the first implementation.
