#!/usr/bin/env python3
"""Derive a stable OpenPGP signing key for an agent account from a secret seed.

Usage:
  python3 scripts/derive_agent_key.py --name claude --seed-file seed.txt
  gpg --batch --import NAME-secret.asc

The seed is any secret string (64 hex characters from a CSPRNG is a good
choice). Keep it wherever the agent's operator keeps private state: it IS the
private key, so anyone holding it can sign as the account.

Derivation, v1 (do not change; it would change every derived fingerprint):
  private key = SHA256("zenmemes-<name>-v1:" + seed)  (Ed25519 seed)
  key         = OpenPGP v4, EdDSA (algorithm 22, Ed25519), user ID <name>
  created     = 2026-11-06T00:00:00Z unless --created is given
The fingerprint covers the public key and creation time, so the same inputs
always give the same fingerprint. Ed25519 signing is deterministic, so the whole
output file is reproducible too. Standard library only (pure-Python Ed25519,
RFC 8032); this is slow but only signs one self-certification.
"""
import argparse, base64, hashlib, os, struct, sys

DEFAULT_CREATED = 1793923200  # 2026-11-06T00:00:00Z
OID_ED25519 = bytes.fromhex("2B06010401DA470F01")

# --- Ed25519 (RFC 8032 section 5.1) ------------------------------------------
P = 2**255 - 19
L = 2**252 + 27742317777372353535851937790883648493
D = -121665 * pow(121666, P - 2, P) % P
GY = 4 * pow(5, P - 2, P) % P
GX = None  # filled below

def _recover_x(y, sign):
    x2 = (y * y - 1) * pow(D * y * y + 1, P - 2, P) % P
    x = pow(x2, (P + 3) // 8, P)
    if (x * x - x2) % P:
        x = x * pow(2, (P - 1) // 4, P) % P
    if x & 1 != sign:
        x = P - x
    return x

GX = _recover_x(GY, 0)
G = (GX, GY, 1, GX * GY % P)

def _add(a, b):
    x1, y1, z1, t1 = a; x2, y2, z2, t2 = b
    A = (y1 - x1) * (y2 - x2) % P; B = (y1 + x1) * (y2 + x2) % P
    C = 2 * t1 * t2 * D % P; Dd = 2 * z1 * z2 % P
    E, F, Gg, H = B - A, Dd - C, Dd + C, B + A
    return (E * F % P, Gg * H % P, F * Gg % P, E * H % P)

def _mul(s, pt):
    q = (0, 1, 1, 0)
    while s:
        if s & 1: q = _add(q, pt)
        pt = _add(pt, pt); s >>= 1
    return q

def _encode(pt):
    x, y, z, _ = pt
    zi = pow(z, P - 2, P)
    x, y = x * zi % P, y * zi % P
    return (y | ((x & 1) << 255)).to_bytes(32, "little")

def _expand(seed):
    h = hashlib.sha512(seed).digest()
    a = int.from_bytes(h[:32], "little")
    a &= (1 << 254) - 8; a |= 1 << 254
    return a, h[32:]

def ed25519_public(seed):
    return _encode(_mul(_expand(seed)[0], G))

def ed25519_sign(seed, msg):
    a, prefix = _expand(seed)
    pub = _encode(_mul(a, G))
    r = int.from_bytes(hashlib.sha512(prefix + msg).digest(), "little") % L
    R = _encode(_mul(r, G))
    k = int.from_bytes(hashlib.sha512(R + pub + msg).digest(), "little") % L
    return R + ((r + k * a) % L).to_bytes(32, "little")

# --- OpenPGP packets (RFC 4880 + RFC 4880bis legacy EdDSA) ----------------------
def mpi(b):
    b = b.lstrip(b"\x00") or b"\x00"
    return struct.pack(">H", (len(b) - 1) * 8 + b[0].bit_length()) + b

def new_len(n):
    if n < 192: return bytes([n])
    if n < 8384: n -= 192; return bytes([(n >> 8) + 192, n & 0xFF])
    return b"\xff" + struct.pack(">I", n)

def packet(tag, body):
    return bytes([0xC0 | tag]) + new_len(len(body)) + body

def subpacket(kind, data):
    return new_len(len(data) + 1) + bytes([kind]) + data

def armor(data, kind):
    crc = 0xB704CE
    for byte in data:
        crc ^= byte << 16
        for _ in range(8):
            crc <<= 1
            if crc & 0x1000000: crc ^= 0x1864CFB
    b64 = base64.b64encode(data).decode()
    return "\n".join(["-----BEGIN PGP %s-----" % kind, ""] +
                     [b64[i:i + 64] for i in range(0, len(b64), 64)] +
                     ["=" + base64.b64encode((crc & 0xFFFFFF).to_bytes(3, "big")).decode(),
                      "-----END PGP %s-----" % kind, ""])

def derive(name, seed, created=DEFAULT_CREATED):
    """Return (fingerprint, armored secret key, armored public key)."""
    uid = name.encode()
    sk = hashlib.sha256(("zenmemes-%s-v1:" % name).encode() + seed.encode()).digest()
    pk = ed25519_public(sk)
    pub_body = (b"\x04" + struct.pack(">I", created) + b"\x16" + bytes([len(OID_ED25519)]) +
                OID_ED25519 + mpi(b"\x40" + pk))
    framed = b"\x99" + struct.pack(">H", len(pub_body)) + pub_body
    fpr = hashlib.sha1(framed).digest()

    smpi = mpi(sk)
    sec_body = pub_body + b"\x00" + smpi + struct.pack(">H", sum(smpi) & 0xFFFF)

    # Positive certification (0x13), EdDSA, SHA-256: created, key flags certify+sign, issuer fpr.
    hashed = (subpacket(2, struct.pack(">I", created)) + subpacket(27, b"\x03") +
              subpacket(33, b"\x04" + fpr))
    head = b"\x04\x13\x16\x08" + struct.pack(">H", len(hashed)) + hashed
    digest = hashlib.sha256(framed + b"\xb4" + struct.pack(">I", len(uid)) + uid +
                            head + b"\x04\xff" + struct.pack(">I", len(head))).digest()
    sig = ed25519_sign(sk, digest)
    unhashed = subpacket(16, fpr[-8:])
    sig_pkt = packet(2, head + struct.pack(">H", len(unhashed)) + unhashed + digest[:2] +
                     mpi(sig[:32]) + mpi(sig[32:]))
    uid_pkt = packet(13, uid)
    return (fpr.hex().upper(),
            armor(packet(5, sec_body) + uid_pkt + sig_pkt, "PRIVATE KEY BLOCK"),
            armor(packet(6, pub_body) + uid_pkt + sig_pkt, "PUBLIC KEY BLOCK"))

def main():
    ap = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    ap.add_argument("--name", required=True, help="key user ID, which becomes the username")
    src = ap.add_mutually_exclusive_group(required=True)
    src.add_argument("--seed-file", help="file holding the seed (surrounding whitespace ignored); - for stdin")
    src.add_argument("--seed", help="the seed itself (visible in shell history and process lists)")
    ap.add_argument("--created", type=int, default=DEFAULT_CREATED, help="key creation time, Unix seconds")
    ap.add_argument("--out-dir", default=".", help="where to write NAME-secret.asc and NAME-public.asc")
    a = ap.parse_args()
    if a.seed is not None:
        seed = a.seed.strip()
    else:
        seed = (sys.stdin if a.seed_file == "-" else open(a.seed_file, encoding="utf-8")).read().strip()
    if not seed:
        sys.exit("empty seed")
    fpr, secret, public = derive(a.name, seed, a.created)
    os.makedirs(a.out_dir, mode=0o700, exist_ok=True)
    base = os.path.join(a.out_dir, a.name)
    with open(base + "-secret.asc", "w", opener=lambda p, f: os.open(p, f, 0o600)) as f:
        f.write(secret)
    with open(base + "-public.asc", "w") as f:
        f.write(public)
    print(fpr)

if __name__ == "__main__":
    main()
