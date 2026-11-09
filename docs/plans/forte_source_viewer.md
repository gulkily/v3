# Forte source file viewer

Request (maintainer, 2026-11-08): when Forte links to a text file, show a readable viewer inside the content pane first, with a button to show the raw file. iPhone browsers render the raw `/source/*` text badly (long values wrapped mid-token, no structure). The maintainer wants the same treatment wherever the site links to text files, eventually. Direct work; no matching feedback item.

## Decisions

- `public/assets/source_viewer.js` has two parts. `ForumSourceViewer.render(container, text, href)` is page-independent, so other pages can reuse it. The Forte binding below it handles opening links in a pane.
- The binding takes over a plain click on any same-origin `/source/current/*`, `/source/blob/{commit}/*` or `/source/commits/{commit}` link inside a Forte `.paned-content-pane`. It fetches the existing text endpoint and shows the result in that pane. There is no new server route.
- The view depends on the text:
  - Leading `Key: value` lines become a field list, with the body after the blank line shown as readable text.
  - A commit's `Files:` list links each file, and those links open in the viewer too.
  - An armored PGP block shows its type, the fingerprint (taken from the key's file name) and the block itself.
  - Anything else shows as wrapped monospace text.
- Navigation:
  - Back returns through files opened from inside the viewer, then to the pane as it was.
  - Escape closes the viewer.
  - Show raw file opens the original URL.
- The pane's own items stay in the DOM and are only hidden while the viewer is open. The viewer closes when the owning reader hides or replaces the item the link came from, for example when another row is selected. The readers themselves are untouched.
- Loaded on Forte Board, Activity and Users.

## Browser history (2026-11-08)

The maintainer reported that the browser's Back from the viewer went back two pages: the viewer added no history entries, so Back left the view the viewer was opened from.

- Each opened file is now a history entry at the same URL, with the stack of open files in `history.state`. The key is `forteSourceViewer`, and it is merged into any existing state.
- The viewer's Back button calls `history.back()`. Escape goes back the full stack depth. Both browser Back and Forward re-sync the pane from `history.state`.
- The `popstate` sync runs after the pane's reader has restored its own selection, so a reopened viewer attaches to the restored item.
- A reload starts without the viewer and drops that entry's viewer state. Back after a reload therefore takes one extra step on the same view. This was accepted rather than adding a new server route or URL parameter.
- Verified in headless Chrome at iPhone size, after arriving from another page:
  - Open a file, then browser Back returns to the item. Forward reopens the file.
  - Opening a commit, then a key from it: Back steps key → commit → item.
  - The in-page Back stays in step with history, and Forward reopens the viewer.
  - Escape from depth 2 returns to the item.
  - Selecting another row while the viewer is open, then Back, returns to the viewer on the earlier item.
  - Leaving the page takes a single Back.
  - No console errors.
- Not replayed on a real iPhone (Safari swipe-back included).

## Follow-up

- Use `ForumSourceViewer.render` for text-file links elsewhere on the site, such as the main activity page, post pages and approval pages. That needs a host element on those pages, since they have no pane.

## Verification

- Headless Chrome, iPhone viewport, Activity page:
  - A label record opened from Relevant files.
  - A commit opened, then a public key opened from the commit's file list.
  - Back stepped commit ← key, then back to the item.
  - Selecting another row closed the viewer.
  - No console errors; the page URL was unchanged.
- `./v3 test --smoke SourceViewerTest ForteBoardReaderTest`: all pass except `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, which also fails on unmodified main. The full suite was not run.
- Not verified:
  - A real iPhone/Safari.
  - The dark scheme.
  - A detached `.asc` signature file: the local dev repository has no signed records, so only the public-key path of the armor view was exercised.
