# Music embed feasibility and acceptance boundaries

Planning evidence gathered October 15, 2026; no application implementation or audio-playback acceptance is claimed.

## Bandcamp evidence

The user supplied these public releases and official Share/Embed examples:

- `https://lyndseyscott.bandcamp.com/album/well-held`: album `1043068150`, large player, artwork and track list.
- `https://lyndseyscott.bandcamp.com/track/plz-plz-universal-soul`: album `1043068150`, selected track `3419983385`, compact light/dark players.

Chrome loaded both supplied player forms with the expected titles. The original album and track HTTP response bodies also contained `og:video` metadata, respectively:

- `https://bandcamp.com/EmbeddedPlayer/v=2/album=1043068150/size=large/tracklist=false/artwork=small/`
- `https://bandcamp.com/EmbeddedPlayer/v=2/track=3419983385/size=large/tracklist=false/artwork=small/`

This proves the track page exposes its own track ID; the supplied album-plus-track iframe's link text points to the album and must not be used to infer the selected track. Extract validated identifiers, then construct our own player URL rather than copying returned HTML, style, or arbitrary iframe attributes.

Python urllib requests returned a 3,036-byte HTTP 200 JavaScript challenge. PHP `file_get_contents`, using a three-second timeout, redirects disabled, a 1 MiB read cap, and `User-Agent: Mozilla/5.0 (compatible)`, returned full release HTML and the matching metadata on both URLs. A second PHP pass returned the same album and track metadata, with response sizes of 342,977 and 206,568 bytes. No cookies, credentials, or browser challenge-solving were supplied to PHP. This is evidence for the current environment, not guaranteed production access. Challenge pages, missing metadata, or changed IDs require fallback and bounded retry.

Official [Bandcamp guidance](https://get.bandcamp.help/en/articles/15263071-how-do-i-create-a-bandcamp-embedded-player) describes manual player generation, not a guaranteed metadata API. The discovery approach above is an observation of public release HTML.

## SoundCloud evidence

An unauthenticated request to the documented [oEmbed endpoint](https://developers.soundcloud.com/docs/oembed) for `https://soundcloud.com/forss/flickermood` returned a widget iframe. Chrome loaded a widget targeting the public permalink directly and displayed Forss/Flickermood, including a Play control under the repository's existing sandbox permissions. The [widget documentation](https://developers.soundcloud.com/docs/api/html5-widget) describes autoplay and collection controls. A public set still requires live selection and playback validation; no server discovery is planned for SoundCloud.

## Exact initial URL boundary

These are conservative application acceptance rules, not exhaustive provider URL specifications. Every path segment must match completely; one trailing slash is optional. HTTP/HTTPS only; reject credentials, explicit ports, encoded path segments, dot segments, whitespace, backslashes, or extra segments.

- SoundCloud hosts: exactly `soundcloud.com` or `www.soundcloud.com`; paths `/USER/TRACK` and `/USER/sets/PLAYLIST`. Each variable segment uses ASCII letters, digits, underscore, and hyphen, beginning and ending with a letter/digit. Reserve first segments (case-insensitively): `discover`, `search`, `stream`, `you`, `charts`, `stations`, `sets`, `tracks`, `playlists`, `tags`, `people`, `pages`, `jobs`, `terms-of-use`, `settings`, `upload`, `messages`, `notifications`, `connect`, `oauth`, `oembed`. Reject track-position profile tabs `sets`, `tracks`, `albums`, `reposts`, `likes`, `followers`, `following`, `popular-tracks`.
- Bandcamp: exactly one artist label before `.bandcamp.com`, using a DNS label of 1–63 ASCII letters/digits/hyphens, beginning/ending with a letter/digit. Reject labels `www`, `daily`, `get`, `help`, `support`, `api`, `blog`, `blogstuff`, `artists`, `labels`, `merch`, `discover`, `search`. Paths exactly `/album/SLUG` or `/track/SLUG`; slugs use lowercase ASCII letters/digits separated by single hyphens. Unicode or other slug formats are excluded initially and documented.
- Private/tokenized URLs: reject extra SoundCloud `/s-TOKEN` path segments and query keys `secret_token`, `secret-token`, `token`, `access_token`, or `private` (case-insensitive after decoding). Reject access-related fragments using those keys too. Truly private availability cannot be inferred from a normal public-shaped URL; failed discovery/playback retains source access.
- Source links preserve meaningful parameters/fragments and use existing tracking cleanup. Discovery/player targets use the validated HTTPS content path without source query/fragment. Do not resolve share links or custom domains.
- Accept: the supplied Bandcamp URLs, SoundCloud `/forss/flickermood`, and shape `/artist/sets/release`. Reject: `/discover/sets`, `/artist/tracks`, `/artist/song/s-SECRET`, `on.soundcloud.com/example`, `bandcamp.com/album/example`, `help.bandcamp.com/track/example`, and `artist.bandcamp.com/album/example/extra`.

## Bandcamp resolution contract for implementation planning

- Reuse canonical detection and player markup. Add a same-origin resolution endpoint invoked on expansion, gated by both effective embed flags. No external discovery during normal HTML rendering; no thread-title writes.
- Resolve only canonical HTTPS artist release URLs passing the boundary above. Validate public network destinations, disable redirects, enforce a three-second total deadline and 1 MiB response cap, and accept only successful HTML responses with valid metadata. Challenges and timeouts are failures even with HTTP 200.
- Parse only an exact HTTPS `bandcamp.com/EmbeddedPlayer/` metadata URL, extracting positive decimal album/track IDs matching the requested release kind; reconstruct an allowlisted player URL. Do not trust remote HTML or follow embedded destinations.
- Cache successes for 24 hours and failures for one hour by canonical release URL, with bounded retry and deduplication of concurrent lookups. Use a disposable provider-resolution cache, separate from thread titles and the canonical/read-model stores; no read-model schema change.
- Return a small success/unavailable response with the constructed player URL on success. Preserve source access while pending or unavailable; allow a later expansion/reload to use a successful cache entry without repeated iframe loads. Reuse the existing preview controller's infrastructure only where compatible; its title/thumbnail cache fields must not be repurposed for player IDs.
