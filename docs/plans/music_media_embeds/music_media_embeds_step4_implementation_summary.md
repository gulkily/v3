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
