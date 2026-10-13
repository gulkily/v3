# Starfield Encoding Specification (SFENC) v0.2

SFENC stores bytes in a lossless starfield PNG. It is **not encryption**. Key backups must encrypt their payload before encoding. v0.1 was unimplemented and not recoverable as specified; reject its version byte.

## Limits and canonical containers

Input is 0–131072 bytes. Image width equals height and is the first of 256, 512, 1024, 2048 with capacity for all stars below. Reject files above 20 MiB, other dimensions, unsupported versions, lengths, compression, nonzero reserved bytes, or checksum failures before allocating from claimed lengths. All arithmetic below is integer arithmetic (division means floor), except no floating-point operations are needed at all.

The payload is canonical gzip: header hex `1f8b08000000000000ff`, DEFLATE stored blocks, then little-endian CRC32 and original length. Split input into maximal 65535-byte blocks, with one empty block for empty input. Each block starts with byte 0 (nonfinal) or 1 (final), little-endian LEN and its 16-bit one's complement, then LEN bytes. No additional blocks, members or trailing data are permitted. Compression field is 1; v0.2 does not support zstd or uncompressed payloads. Stored blocks deliberately trade file size for reproducibility and bounded decoding without browser compression dependencies.

PNG uses signature `89504e470d0a1a0a`, then exactly IHDR, IDAT, IEND. IHDR is width/height big-endian, depth 8, color 6 (RGBA), compression/filter/interlace 0. Each scanline has filter byte 0 then opaque RGBA bytes. One IDAT contains zlib header `7801`, the same stored-block algorithm over scanlines, then big-endian Adler32. Chunks use standard big-endian length and CRC32(type + data). IEND is empty. CRC32 is reflected IEEE polynomial `edb88320`, initial/final XOR `ffffffff`. Adler32 starts a=1,b=0, modulus 65521. These exact container rules are normative: reject re-encoded/noncanonical files and advise keeping the original download. No canvas/color conversion is used.

## Header

The first 22 pixels of row 0 contain 64 header bytes followed by two zero pad bytes, three sequential bytes per pixel in RGB; alpha is 255. Write the header last. All header integers are little-endian.

| Offset | Bytes | Value |
|---|---|---|
| 0 | 4 | `SFEN` |
| 4 | 1 | 2 |
| 5 | 3 | zero |
| 8 | 8 | uint64 seed |
| 16 | 8 | original payload length |
| 24 | 8 | gzip length |
| 32 | 1 | 1 (gzip) |
| 33 | 3 | zero |
| 36 | 4 | CRC32 of gzip bytes |
| 40 | 24 | zero |

## PRNG and placement

Every operation on PRNG words wraps modulo 2^64. SplitMix64 starts at seed; four times add `9e3779b97f4a7c15`, set z=state, set z=(z XOR (z>>30))*`bf58476d1ce4e5b9`, then z=(z XOR (z>>27))*`94d049bb133111eb`, and output z XOR (z>>31). Those outputs initialize s0..s3.

xoshiro256** outputs rotl(s1*5,7)*9. Then t=s1<<17; s2^=s0; s3^=s1; s1^=s2; s0^=s3; s2^=t; s3=rotl(s3,45). rotl is a 64-bit left rotation. Draws return uint64, never floats.

Let C=floor(W/5), R=floor((H-1)/5). Cells are row-major integers 0..C*R-1. Shuffle them with Fisher–Yates: for i=C*R-1 down to 1, j=nextUint64() mod (i+1), swap cells[i],cells[j]. Cell k has center (2+5*(k mod C), 3+5*floor(k/C)). Cells cannot overlap or reach the header.

For gzip length L, N=ceil(105*L/100)+50. Choose the smallest permitted dimensions with C*R >= N, else reject. The first L shuffled cells carry gzip bytes; the remaining N-L carry nextUint64() mod 256, one draw per dummy. Neither background nor payload stars consume random numbers. Seed 0 is valid.

## Pixels

Fill background in row-major order. Let q=seed mod 256, base=2+((13*x+7*y+q) mod 6), distance=abs(10*y-5*H-3*(x-W/2)), glow=max(0,24-floor(distance/10)). RGB=(base+floor(3*glow/5),base+floor(7*glow/10),base+glow), alpha=255. This integer galactic band replaces v0.1's platform-dependent transcendental math.

For byte b let v=192+(b mod 64), hue=floor(b/64). RGB by hue: 0=(v,v,v); 1=(floor(85*v/100),floor(90*v/100),v); 2=(v,v,floor(70*v/100)); 3=(v,floor(70*v/100),floor(60*v/100)). Hue is uniquely identifiable; v is max(R,G,B); decode b=64*hue+v-192 and require re-encoding b to reproduce RGB exactly.

Draw each star with that RGB, alpha 255. Hue 0 draws center only; hue 1 adds four axial neighbors at distance 1; hue 2 draws the 3x3 square; hue 3 draws that square plus four axial neighbors at distance 2 (13 pixels). Shapes fit within their own 5x5 cell.

## Decode and conformance

Validate bounded canonical PNG structure, chunk CRCs, zlib blocks/Adler32 and filter/alpha bytes. Extract header; validate fields, lengths and the canonical dimensions. Recreate shuffled cells. Decode the first L centers, verify gzip CRC against header **before** inflating, then verify bounded canonical gzip and its CRC/length. Re-encode with the extracted seed and require exact PNG equality, detecting altered background, header padding, dummy stars, shapes, or containers. Return original payload bytes only on success.

Given identical payload and seed, the **entire PNG**, including its body, is byte-identical. Encryption is randomized: this promise concerns already-encrypted payload bytes, not separate encryption operations. `tests/fixtures/starfield/vectors.json` contains SHA256 conformance vectors; `tests/Support/starfield_reference.py` is an independent Python reference codec. Verify full payload range, empty input, all byte values, capacity boundaries and malformed input using its `--test` entry point.
