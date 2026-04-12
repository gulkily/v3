A spec is a text document — I'll write it directly here rather than as a file, since it's meant to be read.

---

# Starfield Encoding Specification (SFENC) v0.1

## Overview

SFENC encodes arbitrary binary data into a PNG image that visually resembles a starfield with a galactic arm structure. The image is aesthetically plausible as generative art while being fully decodable by any conforming implementation.

---

## Definitions

- **Payload**: the raw bytes to be encoded, after compression
- **Seed**: a 64-bit unsigned integer used to initialize the PRNG
- **Canvas**: a 2D grid of RGBA pixels, width W × height H
- **Star**: a pixel cluster centered at coordinates (x, y) whose visual properties encode one payload byte
- **Background**: all pixels not belonging to any star; carries no payload data

---

## Image Structure

The image is a **lossless PNG** (RGBA, 8 bits per channel). JPEG and any lossy format are explicitly forbidden — lossy compression corrupts pixel values and destroys the payload.

### Dimensions

W and H must each be multiples of 8. Minimum 256×256. Recommended minimum for real payloads: 1024×1024.

Maximum storable bytes = `floor(W * H / 4)` — one byte per 4 pixels on average, accounting for star size and spacing margins. In practice, reserve 10% headroom.

### Regions

| Region | Location | Purpose |
|---|---|---|
| Header | Top-left 16×1 pixels | Metadata (see below) |
| Body | All remaining pixels | Background + stars |

---

## Header (16 pixels, 64 bytes)

The header occupies pixels (0,0) through (15,0). It is written **after** the background and stars, overwriting those pixels.

All multi-byte integers are **little-endian**.

| Offset (bytes) | Size | Field |
|---|---|---|
| 0 | 4 | Magic: `0x53 0x46 0x45 0x4E` (`SFEN`) |
| 4 | 1 | Version: `0x01` |
| 5 | 3 | Reserved, set to zero |
| 8 | 8 | Seed (uint64) |
| 16 | 8 | Payload length in bytes before compression (uint64) |
| 24 | 8 | Compressed length in bytes (uint64) |
| 32 | 1 | Compression type: `0x00` = none, `0x01` = gzip, `0x02` = zstd |
| 33 | 3 | Reserved, set to zero |
| 36 | 4 | CRC32 of compressed payload bytes |
| 40 | 24 | Reserved, set to zero |

Header bytes are packed into pixels as: `R = byte[4n], G = byte[4n+1], B = byte[4n+2], A = 255`. Alpha is always 255 throughout the image.

---

## Pseudo-Random Number Generator

All implementations must use the **same PRNG** to produce identical star positions from the same seed.

Algorithm: **xoshiro256\*\*** (public domain, widely implemented)

Initial state: seed the xoshiro256** state by running the 64-bit seed through the SplitMix64 generator for 4 rounds to produce 4 × 64-bit state words.

The PRNG produces a sequence of `float64` values in `[0, 1)` by taking the raw uint64 output and dividing by `2^64`.

---

## Background Generation

The background is drawn first, before any stars, and provides the "galaxy" visual structure.

### Galactic arm

Define a central axis passing through `(W*0.5, H*0.48)` at angle `θ = 0.35` radians.

For each pixel `(x, y)`, compute rotated coordinates:

```
dx = x - cx,  dy = y - cy
rx = dx*cos(θ) + dy*sin(θ)
ry = -dx*sin(θ) + dy*cos(θ)
nebula = exp( -(rx²)/(W²·0.12) - (ry²)/(H²·0.006) )
```

### Per-pixel color

For each pixel, draw the next PRNG float `n`:

```
base  = 2 + floor(n * 6)          // 2–7, near-black noise
glow  = floor(nebula * 22)        // 0–22 nebula brightening
R = base + floor(glow * 0.6)
G = base + floor(glow * 0.7)
B = base + glow
A = 255
```

All values are clamped to `[0, 255]`.

The PRNG is consumed **exactly once per pixel**, in row-major order (x from 0 to W-1, y from 0 to H-1), totaling `W*H` draws for the background pass.

---

## Star Placement

After the background pass, the PRNG continues (state not reset) to place stars.

Star count `N` = `ceil(compressed_payload_length * 1.05) + 50` (5% surplus plus 50 dummy stars to pad visual density).

For each star `i` from 0 to N-1:

1. Draw float `p` from PRNG.
2. If `p < 0.6`: place near the galactic arm (see below). Else: place uniformly.

**Near-arm placement:**
```
t  = (rng() - 0.5) * W * 0.9
spread = H * 0.04
x  = cx + t*cos(θ) + (rng() - 0.5)*spread*2
y  = cy - t*sin(θ) + (rng() - 0.5)*spread*2
```
This consumes 2 additional PRNG draws.

**Uniform placement:**
```
x = rng() * W
y = rng() * H
```
This consumes 2 additional PRNG draws.

Both cases: clamp x to `[1, W-2]`, y to `[1, H-2]`, then `floor` to integer.

Stars are stored in an ordered list. The first `compressed_payload_length` stars carry payload bytes. Remaining stars are drawn with a random byte (`floor(rng()*256)`) for visual filler — these PRNG draws must still occur to keep the sequence consistent.

---

## Byte-to-Star Encoding

Each payload byte `b` is encoded into the visual properties of one star centered at `(x, y)`.

### Brightness (bits 0–6)

```
brightness = 180 + (b & 0x7F)    // range 180–255
```

### Color (bits 0–1, reused)

```
hue = b & 0x03
```

| hue | R | G | B |
|---|---|---|---|
| 0 | brightness | brightness | brightness | (white) |
| 1 | floor(brightness·0.85) | floor(brightness·0.90) | brightness | (blue-white) |
| 2 | brightness | brightness | floor(brightness·0.70) | (yellow) |
| 3 | brightness | floor(brightness·0.70) | floor(brightness·0.60) | (red-orange) |

### Size (bits 6–7)

```
sz = (b >> 6) & 0x03
```

| sz | Pixel offsets from center |
|---|---|
| 0 | `(0,0)` |
| 1 | `(0,0),(±1,0),(0,±1)` — plus-shape, 5px |
| 2 | sz=1 plus `(±1,±1)` — 9px |
| 3 | `(0,0),(±2,0),(0,±2),(±1,0),(0,±1)` — 13px diffuse |

All offset pixels receive the same RGB. Any offset that falls outside the canvas bounds is silently skipped.

### Writing pixels

For each pixel in the star's pixel set, set `R, G, B` as computed above and `A = 255`. Stars are written in order; later stars overwrite earlier ones if they overlap. The header is written last and overwrites any star pixels in that region.

---

## Decoding

1. Read the 16 header pixels; extract metadata. Verify magic bytes. Reject if version is unsupported.
2. Re-initialize the same PRNG with the seed from the header.
3. Re-run background generation, consuming exactly `W*H` PRNG draws (output discarded).
4. Re-run star placement with `N` = same formula, consuming the same PRNG draws to reproduce the star coordinate list.
5. For each of the first `compressed_payload_length` stars at `(x, y)`:
   - Read `R` from the pixel at `(x, y)` (take the center pixel only).
   - Recover `b = R - 180`. This gives the original byte.
6. Assemble the byte array and decompress according to the compression type in the header.
7. Verify CRC32 of the compressed bytes against the header value.
8. Return the decompressed payload.

> Note: the color and size bits are redundant with brightness for error-detection purposes but are not used in decoding — brightness alone recovers the byte.

---

## Constraints and Edge Cases

- If a star center pixel has been overwritten by a later star, decoding reads the later star's value. Encoders must detect and resolve star center collisions by resampling (re-draw from PRNG with a fixed retry limit of 10; if unresolved, log a warning and accept the collision — at low star densities this is negligible).
- Minimum payload: 0 bytes is valid; produces a pure starfield with no data stars.
- The PRNG seed may be any uint64, including 0. Seed 0 is valid.
- Files produced by different implementations with the same seed and payload must be **bit-identical** in the body region (header reserved bytes may differ). This is the conformance requirement.

---

## Recommended Compression

Use **gzip** (type `0x01`) for maximum portability. zstd (type `0x02`) is preferred for large payloads (>1MB) where decode speed matters. Uncompressed (type `0x00`) is permitted for testing only.

---

## Versioning

This is version `0x01`. Future versions may change star encoding, PRNG, or background algorithms. Decoders must reject unknown version bytes rather than attempt to decode.
