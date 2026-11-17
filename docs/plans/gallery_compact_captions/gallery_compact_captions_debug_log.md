# gallery_compact_captions debug log

## 2026-11-15T03:55Z closeout (one-shot)

- Planning commit ff48697 preceded implementation.
- Own regression caught by the smoke run: a head-script comment naming gallery_zoom.js leaked that string onto every page; reworded.
- Pre-existing failure fixed: media-embed thread-card test depended on a default-off flag.
- Browser check used a static harness rather than the app fixture (no gallery fixture page in tests/browser); real-device check pending.
