# Forte dense phone layout

Request (maintainer, 2026-11-08): on iPhone, Forte should use as much of the screen as possible with text as small as is still barely readable, and the zoom level should be adjustable. Direct work; no matching feedback item.

## Decisions

- Every Forte size is in rem, so the phone media query (`max-width: 48rem`) in `public/assets/forte.css` sets the root size to `12px × --forte-zoom` instead of restyling each rule. Body text is about 10px at the default; desktop is unchanged.
- Zoom: Smaller/Larger toolbar buttons in the shared `templates/partials/paned_toolbar.php` (Board, Users, Activity), shown only at phone width, step `--forte-zoom` by 0.1 between 0.6 and 2.5 and save it as `forte-zoom` in localStorage. `templates/standalone_layout.php` (used only by Forte) restores it before first paint. Desktop browsers keep their own zoom. Pinch zoom is untouched.
- Space reclaimed on phones: decorative menubar hidden, window border and pane margins removed, single-row toolbar with icon-beside-label buttons, disabled Refresh placeholder hidden, tighter cell/folder/status/content padding and line height, smaller folder pane. Page height uses `100dvh` so iOS browser chrome does not push the status bar off-screen.
- Form fields stay at 16px on phones: iOS Safari zooms the page into any smaller field on focus, which would undo the dense scale while composing.

## Verification

- Headless Chrome, iPhone viewport (390×664, DPR 3): Board, Activity and Users with a row selected; no page overflow; Activity rows now fit without horizontal scrolling; New dialog fields at 16px; Larger ×3 survives reload (zoom 1.3, root 15.6px); Smaller ×6 gives 0.7; toolbar items aligned. Desktop 1280×800: zoom buttons hidden, root 16px.
- `./v3 test --smoke ForteBoardReaderTest NavIconSetsTest`: all pass except `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, which also fails on unmodified main. Full suite not run.
- Not verified on a real iPhone/Safari (WebKit text rendering, home-indicator area) or in dark scheme.
