> **Feature plan:** [Step 1](./music_media_embeds_step1_solution_assessment.md) · [Step 2](./music_media_embeds_step2_feature_description.md) · [Step 3](./music_media_embeds_step3_development_plan.md) · [Step 4](./music_media_embeds_step4_implementation_summary.md)

Debug mode: enabled

Branch: `feature/music-media-embeds`. Planning commit: `d909b0f2`.

Implementation in progress; production flags remain unchanged.

## Stage 1 - SoundCloud tracks

- Changes: strict public track detection, reserved/private URL rejection, shared deferred player with autoplay disabled, responsive track sizing, unchanged source/title behavior; registered focused music regression suite.
- Verification: `php tests/run.php MusicMediaEmbedTest MediaEmbedDetectorTest MediaEmbedRendererTest ThreadTitleTest` — 42 passed. Isolated browser fixture created a track thread/reply: two cards, visible source, keyboard expansion, correct live artist/title and Play-to-Pause control transition.
- Notes: live listening received the expected playing control state; final browser acceptance will check playback progress and all shared views. No server lookup added.

## Stage 2 - SoundCloud sets

- Changes: direct set recognition and collection sizing reuse the track player path; profile tabs and private/extra-path variants remain excluded.
- Verification: focused music/detector/renderer/title suite — 43 passed. Isolated thread/reply live smoke for `soundcloud.com/justinhasanhickman/sets/forss-512437271`: two cards, visible source, keyboard expansion, collection track list, and Play-to-Pause transition.
- Notes: no lookup/cache added; live provider restrictions remain subject to final acceptance.

## Stage 3 - Bandcamp resolution foundation

- Changes: one shared release-URL policy; metadata parser accepting only matching Bandcamp player IDs; HTTPS fetch pins a validated public IPv4 address while verifying the artist TLS hostname, rejects redirects/non-HTML/oversized responses, and applies a three-second fetch deadline. Separate disposable per-release cache uses 24-hour successes, one-hour failures, and nonblocking locks.
- Verification: resolver/music suites — 7 passed, covering URL boundaries, destination addresses, malformed/foreign/wrong-kind metadata, status/challenge/size failures, cache expiry/backoff/corruption and lock deduplication. Native production fetcher returned album `1043068150` and track `3419983385` for the supplied pages.
- Notes: no canonical or read-model schema changes. System DNS resolution uses the host resolver; an over-deadline result is rejected before connecting. This prerequisite is not a releasable Bandcamp UI by itself.
