# Local Contacts

Contacts is a private address book for your own LinkedIn connections export.
Use PHP CLI 8.1 or later with mbstring and a browser with JavaScript enabled.

## Open and import

1. Run `./v3 contacts` from your v3 checkout. Open the full URL it prints in a
   browser on the same computer. The URL grants access to that launch, so keep
   it private. Press Ctrl+C in the terminal to stop Contacts.
2. Choose your LinkedIn ZIP or its `Connections.csv` and select **Preview
   import**. Dropping a file onto the drop area opens its preview automatically.
   Direct ZIP import requires PHP's ZIP extension. If ZIP
   import is unavailable or the archive has multiple connections files,
   extract the intended `Connections.csv` on your computer and import it directly.
3. Review additions, updates, unchanged rows, and skipped rows. Select
   **Show more rows** to see additional results, then select **Import contacts**
   to save the import. If the preview
   expires or the book changes before confirmation, preview the file again.
4. Search by name, company, title, or email. Choose a contact, edit the note or
   last-contacted date, and select **Save changes**.

Contacts reads only `Connections.csv` from a ZIP. On reimport, a recognized
LinkedIn profile URL identifies an existing contact. Blank or omitted source
fields preserve saved values, and notes and last-contacted dates carry over.
Exact repeats stay unchanged. The preview gives reasons for skipping uncertain
matches, including shared names when a profile URL is missing, and conflicting
rows for one profile URL.

The listener binds to `127.0.0.1`. Run it on the computer where you use the
browser. Contacts stores the book outside the checkout and forum data
directories, separate from forum publishing and downloads. A restart prints a
fresh access URL.

## Back up and restore

The default data directory is `$XDG_DATA_HOME/v3/contacts` when
`XDG_DATA_HOME` is set, or `$HOME/.local/share/v3/contacts` otherwise.
`--data-dir=/private/path` selects a dedicated directory. Contacts creates a
missing directory with mode `0700`. An existing directory must be writable and
owner-only. Choose a path outside the checkout and forum directories, with no
symlink components.

Stop every Contacts launcher using the book before copying it. The backup is
`contacts.txt`, which contains imported details, notes, and last-contacted
dates in plain text. Keep the original export and backups somewhere private.

```bash
contacts_dir="${XDG_DATA_HOME:-$HOME/.local/share}/v3/contacts"
backup_dir="$(mktemp -d "$HOME/v3-contacts-backup.XXXXXXXX")"
install -m 600 "$contacts_dir/contacts.txt" "$backup_dir/contacts.txt"
printf '%s\n' "$backup_dir/contacts.txt"
```

If you use `--data-dir`, set `contacts_dir` to that path. To verify or restore a
backup, copy it into a new owner-only directory and start Contacts there. Set
`backup_dir` to the directory you saved if you are in a new terminal.

```bash
restore_dir="$(mktemp -d "$HOME/v3-contacts-restored.XXXXXXXX")"
install -m 600 "$backup_dir/contacts.txt" "$restore_dir/contacts.txt"
./v3 contacts --data-dir="$restore_dir"
```

Open the new URL and check a known contact, note, and date. Keep the original
book until you verify the restored copy. If Contacts refuses to start, check
the directory mode `0700`, file mode `0600`, and ownership. A damaged snapshot
requires a valid backup or a fresh private directory for reimport.

[Command reference](../reference/v3_cli.md#start-local-contacts) ·
[Snapshot format](../specs/contacts_book_v1.md)
