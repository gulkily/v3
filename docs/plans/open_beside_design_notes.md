# Open Beside Design Notes

Design notes for a second pane: on a wide screen, a click opens the link beside
the current page. This is not a Railspike artifact and approves no
implementation. Step 1 was drafted on 2026-11-17 (it recommends one extra
pane first, with the strip as the next cycle) and awaits Approved Step 1;
staged plans are committed after Approved Step 3. "Open beside" is a working
label.

Requested by the maintainer on 2026-11-16 in
[thread-20261116070645-8b937ee9](https://zenmemes.com/threads/thread-20261116070645-8b937ee9)
("this would be an amazing place to add another column"), clarified in a Claude
session the same day: the empty space beside the content column should show any
other page. Feedback item:
[PF-20261117-F10](../feedback/items/PF-20261117-F10.txt).

## Decided by the maintainer

- It is a mode the reader switches on. With it on, a normal click opens the
  link in the second pane.
- The pane stays as the reader navigates in the first.
- Browsers without JavaScript are skipped for now. They get today's site,
  unchanged.
- Laying the Prompts page's cards out in several columns is a possible future
  direction, not this request.

Everything below is a recommendation from two design review rounds on
2026-11-17, held in the session as a role-played panel (interaction design,
information architecture, accessibility, visual and motion). None of it is
approved. The maintainer floated "scroll them over to the side to make more
space"; the strip below is one reading of that and is not confirmed.

## Recommendations

### Scope

- Phones and narrow screens: the mode does nothing.
- First slice: two panes. The strip is the same rules with the cap lifted.

### Layout

- A strip of panes, left to right, in the order they were opened.
- Pane width is the reading column set on the reading-margins ruler. No new
  width control.
- One site header for the whole strip. Pane pages render without it.
- Each pane has a slim bar: title, pin, close, "open alone".

### What a click does

- A link inside a page opens in a new pane right after the pane clicked in.
- Unpinned panes further right close. Closing is never losing: they go to a
  pane list, which has "Reopen closed" from the first slice. This keeps the
  v3 rule that a later click must not drop an earlier one.
- A pane with unsent text is kept automatically. Kept and pinned panes shift
  right.
- A main nav click behaves like a click in the first pane.
- Ctrl-click and external links behave as they do today.

### History and address

- Panes never navigate themselves. The strip catches every click and
  decides. If panes are iframes, a link followed inside one would add its own
  history entry and Back would become unpredictable.
- Each change to the strip is one history entry. Back undoes the last
  change.
- The address bar shows the active pane's URL. The strip is remembered per
  device.

### Focus and accessibility

- Opening a pane announces it and leaves focus on the link, so a reader can
  open several threads from a list in a row.
- One key jumps to the new pane and one key returns.
- The clicked link is marked as open beside.
- Each pane is a labelled region. Slide animation is skipped under reduced
  motion.

### Freshness

- A pane that has gone out of date shows a quiet "Updated, reload" hint on
  its bar. Panes never reload on their own.

### Finding the mode

- A toggle at the right end of the reading-margins ruler, plus a row in
  Preferences.
- Stored per browser and applied before paint.

### Later slices

- Lift the two-pane cap.
- Panes that slide left collapse to spines showing the title. At most three
  spines; older ones fold into the pane list.
- A folded pane unloads its page and remembers its address and scroll
  position.
- Themes may dress panes (see window controls below).

## Prior art: Pollyanna

Pollyanna has this as "Spawn dialogs in-page" (`draggable_spawn`), in
`default/template/js/dragging.js` of
[gulkily/pollyanna](https://github.com/gulkily/pollyanna), read at `7a5666d`.

- Every page has a twin at `/dialog/<name>.html` holding only its dialogs,
  with no header or footer (`pages.pl`, `PutHtmlFile('dialog/...')`).
- Links carry an `onclick` that checks the preference. On: `FetchDialogFromUrl`
  fetches the twin and `InsertFetchedDialog` adds it to `#maincontent`. Off, or
  without JavaScript: the link navigates as normal.
- `FetchDialog` focuses a dialog already on the page instead of fetching it
  again.
- Open dialogs are listed in the `opened_dialogs` preference, mirrored to a
  cookie. With "Reopen dialogs on new page" (`draggable_reopen`) each page
  load refetches them in one request (`/dialog/a,b,c.html`).
- `TileDialogs` packs dialogs into free space, `UpdateDialogList` keeps a
  list of them, and `CloseDialog` removes one and forgets it.

Where it struggled:

- Injected HTML arrives without its scripts. `InsertFetchedDialog` re-runs
  `ShowTimestamps` and `LoadCheckboxValues` by hand, and a todo says
  `write.js` still needs loading.
- Reopening is JavaScript-only; "php should also be able to inject new
  dialogs" stayed a todo.
- The settings panel says "Proper equipment required. These features work
  best with a mouse."

What to carry over: a chrome-less version of every page, a per-reader list of
what is open, and links that still work as plain links. What to change: show
the pane as an iframe of the chrome-less page instead of injecting HTML, so
likes, reply forms and page scripts work without re-initialising. The cost is
a second page load.

## Fit with existing v3 work

- [Reading margins](../../public/assets/reading_margins.js) create the empty
  space and set the pane width.
- [Window controls](../concepts/window_controls.md) (PR #308, Bubblegum,
  behind `FORUM_DRAGGABLE_WINDOWS_ENABLED`) are a layer inside a page: a
  window is a card, a pane holds a whole page. The strip should work in every
  theme, and Bubblegum's theme script can dress a pane with its title bar.
  - Reuse its patterns: the Preferences row stored per browser, the
    before-paint restore, and the feature flag.
  - Float works inside a pane, never across panes. The Float button is hidden
    for a pane below a set width.
  - The window list or taskbar deferred in
    [draggable windows Step 2](draggable_windows/draggable_windows_step2_feature_description.md)
    and the pane list are one list. Build it once, theme-neutral; Bubblegum
    may draw it as a taskbar.
- The Forte board (`templates/pages/forte_board.php`) has list and content
  panes built for that one page. They are not reused.
- The [browsing tree checklist](browsing_tree_checklist.md) plans tree
  history and a filmstrip of recent screens. The strip is a reader's path
  through the site, so the pane list and the filmstrip may be one control.

## Open questions

- Is it a strip of panes, or one extra pane that the first slides over for?
- Does a click close the panes to its right (as recommended), or keep
  everything until closed?
- Float inside each pane (as recommended), or hidden while more than one
  pane is open?
- Name for the mode.
- Whether the ruler toggle shows always or only when the margins leave a
  column free.
- Whether the pane list and the filmstrip are one control or two.

## Next

- Try a rough two-pane prototype on three tasks: read the board and a thread
  together, reply while reading another thread, and compare two threads.
- The maintainer confirms or changes the recommendations, and approves or
  revises Step 1.

## Related, not part of this

A local branch `feat/prompt_columns` (not pushed) lays the Prompts page's
cards out in columns when the reading column is wide. It came from a first
misreading of the request and waits on the maintainer.
